<?php

declare(strict_types=1);

final class Database
{
    public static function connect(): PDO
    {
        $dir = __DIR__ . '/../storage/data';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $pdo = new PDO('sqlite:' . $dir . '/samgyeong.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        self::migrate($pdo);

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL CHECK(role IN ('guest', 'student', 'council', 'admin')),
                display_name TEXT NOT NULL DEFAULT '',
                hall_key TEXT NOT NULL DEFAULT '',
                year INTEGER NOT NULL DEFAULT 0,
                photo_path TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                board TEXT NOT NULL,
                title TEXT NOT NULL,
                tag TEXT NOT NULL DEFAULT '공지',
                body TEXT NOT NULL,
                file_name TEXT,
                file_path TEXT,
                author_id INTEGER NOT NULL,
                views INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(author_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS hall_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                hall_key TEXT NOT NULL,
                hall_name TEXT NOT NULL,
                hall_meaning TEXT NOT NULL,
                hall_color TEXT NOT NULL CHECK(hall_color IN ('blue', 'gold', 'green')),
                student_name TEXT NOT NULL,
                year INTEGER NOT NULL CHECK(year BETWEEN 1 AND 3),
                role_label TEXT NOT NULL,
                photo_path TEXT,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS calendar_events (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_date TEXT NOT NULL,
                title TEXT NOT NULL,
                category TEXT NOT NULL DEFAULT 'general',
                author_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(author_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS board_permissions (
                board_slug TEXT PRIMARY KEY,
                read_roles TEXT NOT NULL DEFAULT '[]',
                write_roles TEXT NOT NULL DEFAULT '[]',
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS point_records (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                type TEXT NOT NULL CHECK(type IN ('merit', 'demerit')),
                points INTEGER NOT NULL CHECK(points > 0),
                reason TEXT NOT NULL,
                issuer_id INTEGER NOT NULL,
                issued_at TEXT NOT NULL,
                canceled_at TEXT,
                canceled_by INTEGER,
                cancellation_of_id INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id),
                FOREIGN KEY(issuer_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS point_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL CHECK(category IN ('personal', 'year', 'hall', 'school')),
                score_label TEXT NOT NULL,
                rule_text TEXT NOT NULL,
                is_emphasis INTEGER NOT NULL DEFAULT 0,
                sort_order INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, display_name, hall_key, year) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute(['admin', password_hash('admin1234', PASSWORD_DEFAULT), 'admin', '최고관리자', '', 0]);
        }

        $columns = $pdo->query("PRAGMA table_info(users)")->fetchAll();
        $userColumns = array_column($columns, 'name');
        if (!in_array('display_name', $userColumns, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN display_name TEXT NOT NULL DEFAULT ''");
            $pdo->exec("UPDATE users SET display_name = username WHERE display_name = ''");
        }
        if (!in_array('hall_key', $userColumns, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN hall_key TEXT NOT NULL DEFAULT ''");
        }
        if (!in_array('year', $userColumns, true)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN year INTEGER NOT NULL DEFAULT 0");
        }
        if (!in_array('photo_path', $userColumns, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN photo_path TEXT');
        }
        self::ensureGuestRole($pdo);
        self::ensureGuestAccount($pdo);

        $columns = $pdo->query("PRAGMA table_info(posts)")->fetchAll();
        $postColumns = array_column($columns, 'name');
        if (!in_array('views', $postColumns, true)) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN views INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('tag', $postColumns, true)) {
            $pdo->exec("ALTER TABLE posts ADD COLUMN tag TEXT NOT NULL DEFAULT '공지'");
        }

        $columns = $pdo->query("PRAGMA table_info(point_records)")->fetchAll();
        $pointColumns = array_column($columns, 'name');
        if (!in_array('canceled_at', $pointColumns, true)) {
            $pdo->exec('ALTER TABLE point_records ADD COLUMN canceled_at TEXT');
        }
        if (!in_array('canceled_by', $pointColumns, true)) {
            $pdo->exec('ALTER TABLE point_records ADD COLUMN canceled_by INTEGER');
        }
        if (!in_array('cancellation_of_id', $pointColumns, true)) {
            $pdo->exec('ALTER TABLE point_records ADD COLUMN cancellation_of_id INTEGER');
        }

        $columns = $pdo->query("PRAGMA table_info(hall_members)")->fetchAll();
        $hallColumns = array_column($columns, 'name');
        if (!in_array('photo_path', $hallColumns, true)) {
            $pdo->exec('ALTER TABLE hall_members ADD COLUMN photo_path TEXT');
        }
        if (!in_array('user_id', $hallColumns, true)) {
            $pdo->exec('ALTER TABLE hall_members ADD COLUMN user_id INTEGER');
        }

        self::ensureGuestBoardReadPermissions($pdo);

        $postCount = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
        if ($postCount === 0) {
            $adminId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
            $stmt = $pdo->prepare('
                INSERT INTO posts (board, tag, title, body, author_id, views, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $samples = [
                ['notice', '모집', '2027학년도 삼경인문고등학교 신입생 모집 요강', '신입생 모집 일정과 제출 서류를 안내합니다.', 152, '2026-07-02 09:00:00'],
                ['notice', '안내', '1학기 기말고사 시행 및 경천/경인/경물관 자습실 운영 안내', '기말고사 기간 중 자습실 운영 시간을 확인해 주세요.', 89, '2026-06-25 09:00:00'],
                ['notice', '공지', '전통 예절 교육 주간 명사 특강 안내', '전통 예절 교육 주간 특강 일정을 안내합니다.', 45, '2026-06-10 09:00:00'],
                ['resources', '규정', '학교생활 규정 개정본', '2026학년도 학교생활 규정 개정본입니다.', 320, '2026-03-02 09:00:00'],
                ['resources', '안내', '관별 자습실 이용 안내', '경천관, 경인관, 경물관 자습실 이용 수칙입니다.', 215, '2026-03-02 09:00:00'],
                ['council', '회의', '1학년 신입생 교육 진행 상황 공유', '학생회 신입생 교육 진행 상황을 공유합니다.', 12, '2026-07-01 09:00:00'],
                ['council', '의견', '경물관 시설 보수 의견 처리 방안', '접수된 시설 보수 의견의 처리 방안을 논의합니다.', 8, '2026-06-28 09:00:00'],
                ['minutes', '공지', '삼경원 회의록 열람 안내', '삼경원 회의록은 재학생 이상 로그인 후 열람할 수 있습니다.', 18, '2026-07-02 09:00:00'],
                ['minutes', '회의', '7월 정기 회의록', '7월 정기 회의 주요 안건과 결정 사항을 공유합니다.', 14, '2026-07-01 09:00:00'],
            ];

            foreach ($samples as $sample) {
                $stmt->execute([$sample[0], $sample[1], $sample[2], $sample[3], $adminId, $sample[4], $sample[5]]);
            }
        }

        $minutesCount = (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE board = 'minutes'")->fetchColumn();
        if ($minutesCount === 0) {
            $adminId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
            $stmt = $pdo->prepare('
                INSERT INTO posts (board, tag, title, body, author_id, views, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $minutesSamples = [
                ['minutes', '공지', '삼경원 회의록 열람 안내', '삼경원 회의록은 재학생 이상 로그인 후 열람할 수 있습니다.', 18, '2026-07-02 09:00:00'],
                ['minutes', '회의', '7월 정기 회의록', '7월 정기 회의 주요 안건과 결정 사항을 공유합니다.', 14, '2026-07-01 09:00:00'],
            ];

            foreach ($minutesSamples as $sample) {
                $stmt->execute([$sample[0], $sample[1], $sample[2], $sample[3], $adminId, $sample[4], $sample[5]]);
            }
        }

        $eventCount = (int) $pdo->query('SELECT COUNT(*) FROM calendar_events')->fetchColumn();
        if ($eventCount === 0) {
            $adminId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
            $stmt = $pdo->prepare('
                INSERT INTO calendar_events (event_date, title, category, author_id)
                VALUES (?, ?, ?, ?)
            ');
            $events = [
                ['2026-07-01', '학생회 회의', 'general'],
                ['2026-07-15', '관장단 간담회', 'important'],
                ['2026-07-25', '시설 보수 점검', 'check'],
            ];

            foreach ($events as $event) {
                $stmt->execute([$event[0], $event[1], $event[2], $adminId]);
            }
        }

        self::seedPointRules($pdo);
    }

    private static function seedPointRules(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM point_rules')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $rules = [
            ['personal', '-3점', '참회록(반성문) 작성', 0, 10],
            ['personal', '-5점', '버피 또는 토끼뜀 20회, 참회록(반성문) 작성', 0, 20],
            ['personal', '-8점', '버피 또는 토끼뜀 30회, 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착', 0, 30],
            ['personal', '-10점', '버피 또는 토끼뜀 40회, 예절 교육기간 1일', 0, 40],
            ['personal', '-13점', '버피 또는 토끼뜀 50회, 예절 교육기간 2일, 직속 3학년 선배(관장) 연대 참회록 작성', 0, 50],
            ['personal', '-15점', '퇴학 처리 (재입학 불가)', 1, 60],
            ['year', '-10점', '학년 전체 꼬리표 3일 부착, 학년 전체 참회록(반성문) 작성', 0, 10],
            ['year', '-15점', '학년 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착', 0, 20],
            ['year', '-20점', '학년 릴레이 버피 또는 토끼뜀 30회, 학년 예절 교육기간 1일', 0, 30],
            ['year', '-25점', '학년 릴레이 버피 또는 토끼뜀 40회, 학년 예절 교육기간 2일', 0, 40],
            ['year', '-30점', '학년 전체 집합', 1, 50],
            ['hall', '-10점', '관 전체 꼬리표 3일 부착, 관 전체 참회록(반성문) 작성', 0, 10],
            ['hall', '-15점', '관 소속 인원 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착', 0, 20],
            ['hall', '-20점', '관 소속 인원 릴레이 버피 또는 토끼뜀 30회, 관 예절 교육기간 1일', 0, 30],
            ['hall', '-25점', '관 소속 인원 릴레이 버피 또는 토끼뜀 40회, 관 예절 교육기간 2일', 0, 40],
            ['hall', '-30점', '관 전체 집합', 1, 50],
            ['school', '-25점', '전체 점호 실시 (삼경원 및 3학년 주도)', 1, 10],
        ];

        $stmt = $pdo->prepare('
            INSERT INTO point_rules (category, score_label, rule_text, is_emphasis, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ');

        foreach ($rules as $rule) {
            $stmt->execute($rule);
        }
    }

    private static function ensureGuestRole(PDO $pdo): void
    {
        $schema = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'users'")->fetchColumn();
        if (str_contains($schema, "'guest'")) {
            return;
        }

        $pdo->exec('PRAGMA foreign_keys = OFF');
        $pdo->beginTransaction();
        try {
            $pdo->exec("
                CREATE TABLE users_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    role TEXT NOT NULL CHECK(role IN ('guest', 'student', 'council', 'admin')),
                    display_name TEXT NOT NULL DEFAULT '',
                    hall_key TEXT NOT NULL DEFAULT '',
                    year INTEGER NOT NULL DEFAULT 0,
                    photo_path TEXT,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            ");
            $pdo->exec("
                INSERT INTO users_new (id, username, password_hash, role, display_name, hall_key, year, photo_path, created_at)
                SELECT id, username, password_hash, role, display_name, hall_key, year, photo_path, created_at
                FROM users
            ");
            $pdo->exec('DROP TABLE users');
            $pdo->exec('ALTER TABLE users_new RENAME TO users');
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    private static function ensureGuestAccount(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute(['guest']);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("UPDATE users SET role = 'guest', display_name = '게스트', hall_key = '', year = 0 WHERE username = 'guest'");
            $stmt->execute();
            return;
        }

        $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role, display_name, hall_key, year) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute(['guest', password_hash('guest1234', PASSWORD_DEFAULT), 'guest', '게스트', '', 0]);
    }

    private static function ensureGuestBoardReadPermissions(PDO $pdo): void
    {
        $rows = $pdo->query('SELECT board_slug, read_roles FROM board_permissions')->fetchAll();
        $stmt = $pdo->prepare('UPDATE board_permissions SET read_roles = ?, updated_at = CURRENT_TIMESTAMP WHERE board_slug = ?');

        foreach ($rows as $row) {
            $roles = json_decode((string) $row['read_roles'], true);
            if (!is_array($roles) || !in_array('student', $roles, true) || in_array('guest', $roles, true)) {
                continue;
            }

            $roles[] = 'guest';
            $stmt->execute([json_encode(array_values(array_unique($roles)), JSON_UNESCAPED_UNICODE), $row['board_slug']]);
        }
    }
}
