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
                role TEXT NOT NULL CHECK(role IN ('student', 'council', 'admin')),
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

        $columns = $pdo->query("PRAGMA table_info(posts)")->fetchAll();
        $postColumns = array_column($columns, 'name');
        if (!in_array('views', $postColumns, true)) {
            $pdo->exec('ALTER TABLE posts ADD COLUMN views INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('tag', $postColumns, true)) {
            $pdo->exec("ALTER TABLE posts ADD COLUMN tag TEXT NOT NULL DEFAULT '공지'");
        }

        $columns = $pdo->query("PRAGMA table_info(hall_members)")->fetchAll();
        $hallColumns = array_column($columns, 'name');
        if (!in_array('photo_path', $hallColumns, true)) {
            $pdo->exec('ALTER TABLE hall_members ADD COLUMN photo_path TEXT');
        }
        if (!in_array('user_id', $hallColumns, true)) {
            $pdo->exec('ALTER TABLE hall_members ADD COLUMN user_id INTEGER');
        }

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
            ];

            foreach ($samples as $sample) {
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
    }
}
