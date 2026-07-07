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

            CREATE TABLE IF NOT EXISTS post_files (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                file_name TEXT NOT NULL,
                file_path TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(post_id) REFERENCES posts(id)
            );

            CREATE TABLE IF NOT EXISTS post_likes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(post_id, user_id),
                FOREIGN KEY(post_id) REFERENCES posts(id),
                FOREIGN KEY(user_id) REFERENCES users(id)
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

            CREATE TABLE IF NOT EXISTS page_permissions (
                page_key TEXT PRIMARY KEY,
                read_roles TEXT NOT NULL DEFAULT '[]',
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

            CREATE TABLE IF NOT EXISTS point_list_rules (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL CHECK(category IN ('demerit', 'merit', 'submit')),
                score_label TEXT NOT NULL DEFAULT '',
                rule_text TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS hall_activities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hall_key TEXT NOT NULL CHECK(hall_key IN ('gyeongcheon', 'gyeongin', 'gyeongmul')),
                title TEXT NOT NULL,
                summary TEXT NOT NULL,
                method TEXT NOT NULL,
                value TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS point_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reset_by INTEGER NOT NULL,
                note TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(reset_by) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS mall_settings (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS mall_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT NOT NULL,
                price INTEGER NOT NULL CHECK(price > 0),
                active INTEGER NOT NULL DEFAULT 1,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS mall_orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                item_id INTEGER NOT NULL,
                item_name TEXT NOT NULL,
                price INTEGER NOT NULL CHECK(price > 0),
                quantity INTEGER NOT NULL CHECK(quantity > 0),
                total_price INTEGER NOT NULL CHECK(total_price > 0),
                used_at TEXT,
                used_by INTEGER,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id),
                FOREIGN KEY(item_id) REFERENCES mall_items(id),
                FOREIGN KEY(used_by) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS meal_entries (
                meal_date TEXT PRIMARY KEY,
                lunch_text TEXT NOT NULL DEFAULT '',
                dinner_text TEXT NOT NULL DEFAULT '',
                updated_by INTEGER,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(updated_by) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS tally_webhook_events (
                event_id TEXT PRIMARY KEY,
                board TEXT NOT NULL,
                post_id INTEGER,
                payload TEXT NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(post_id) REFERENCES posts(id)
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

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_files_post_id ON post_files(post_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_likes_post_id ON post_likes(post_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_post_likes_user_id ON post_likes(user_id)');
        $pdo->exec("
            INSERT INTO post_files (post_id, file_name, file_path)
            SELECT posts.id, posts.file_name, posts.file_path
            FROM posts
            WHERE posts.file_path IS NOT NULL
              AND posts.file_path != ''
              AND NOT EXISTS (
                  SELECT 1
                  FROM post_files
                  WHERE post_files.post_id = posts.id
                    AND post_files.file_path = posts.file_path
              )
        ");

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

        $columns = $pdo->query("PRAGMA table_info(mall_orders)")->fetchAll();
        $mallOrderColumns = array_column($columns, 'name');
        if (!in_array('used_at', $mallOrderColumns, true)) {
            $pdo->exec('ALTER TABLE mall_orders ADD COLUMN used_at TEXT');
        }
        if (!in_array('used_by', $mallOrderColumns, true)) {
            $pdo->exec('ALTER TABLE mall_orders ADD COLUMN used_by INTEGER');
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
        self::ensureCouncilMinutesReadPermissions($pdo);
        self::ensureBasicLiteracyBoardPermissions($pdo);
        self::ensureMallDefaults($pdo);
        self::ensureMallItemsSplit($pdo);
        self::ensureMallGifticonItems($pdo);
        self::ensureMallGradeChangeItem($pdo);
        self::ensureMallAttendanceItem($pdo);
        self::ensureHallActivityTimeText($pdo);

        $postCount = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
        if ($postCount === 0) {
            $adminId = (int) $pdo->query("SELECT id FROM users WHERE username = 'admin'")->fetchColumn();
            $stmt = $pdo->prepare('
                INSERT INTO posts (board, tag, title, body, author_id, views, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $samples = [
                ['resources', '규정', '학교생활 규정 개정본', '2026학년도 학교생활 규정 개정본입니다.', 320, '2026-03-02 09:00:00'],
                ['resources', '안내', '관별 자습실 이용 안내', '경천관, 경인관, 경물관 자습실 이용 수칙입니다.', 215, '2026-03-02 09:00:00'],
                ['council', '회의', '1학년 신입생 교육 진행 상황 공유', '학생회 신입생 교육 진행 상황을 공유합니다.', 12, '2026-07-01 09:00:00'],
                ['council', '의견', '경물관 시설 보수 의견 처리 방안', '접수된 시설 보수 의견의 처리 방안을 논의합니다.', 8, '2026-06-28 09:00:00'],
            ];

            foreach ($samples as $sample) {
                $stmt->execute([$sample[0], $sample[1], $sample[2], $sample[3], $adminId, $sample[4], $sample[5]]);
            }
        }

        $activityCount = (int) $pdo->query('SELECT COUNT(*) FROM hall_activities')->fetchColumn();
        if ($activityCount === 0) {
            $activities = [
                ['gyeongcheon', '명언 필사', '고전 문학, 사자성어, 오늘의 명언을 자필로 필사하고 인증합니다.', '필사 사진과 짧은 소감을 삼경원에 제출합니다.', '하늘의 이치를 배우고 바른 생각을 세우는 경천의 기상을 기릅니다.', 10],
                ['gyeongcheon', '하늘 관찰 일지', '날씨, 구름, 계절의 변화를 기록하며 하루를 성찰합니다.', '관찰 사진 1장과 3줄 일지를 함께 제출합니다.', '작은 변화 속에서 질서와 이치를 발견하는 태도를 기릅니다.', 20],
                ['gyeongin', '경인 우체통', '감사하거나 칭찬하고 싶은 선후배, 동료에게 예의를 갖춘 편지를 씁니다.', '삼경원이 편지를 모아 점호 또는 공지 시간에 전달합니다.', '타인을 존중하고 공동체의 온도를 높이는 경인 정신을 실천합니다.', 30],
                ['gyeongin', '인사 실천 챌린지', '하루 동안 먼저 인사하고 상대를 배려한 사례를 기록합니다.', '실천 사례와 느낀 점을 간단한 카드 형식으로 제출합니다.', '말과 태도로 사람을 공경하는 습관을 만듭니다.', 40],
                ['gyeongmul', '사물 감사 그림일기', '하루 동안 도움을 준 사물을 그리고 감사한 이유를 적습니다.', '그림 또는 사진과 감사 문장을 함께 제출합니다.', '주변의 사물과 환경을 아끼는 경물 정신을 표현합니다.', 50],
                ['gyeongmul', '공간 돌봄 캠페인', '자습실, 복도, 공용 공간을 정리하고 개선점을 제안합니다.', '정리 전후 사진 또는 개선 제안서를 제출합니다.', '함께 쓰는 공간을 존중하고 책임 있게 관리하는 태도를 기릅니다.', 60],
                ['gyeongcheon', '당일 시사 요약', '당일 주요 시사 이슈와 지식을 공유하며 정세 파악 능력을 기릅니다.', '학업에 도움이 되거나 보도 가치가 있는 뉴스의 핵심 내용을 골라 2줄 이상 요약해 단대에 보고합니다.', '매일 00:00부터 선착순 3인만 인정하며, 비방·미확인 루머·정치적 편향이 있으면 제외합니다.', 70],
                ['gyeongin', '언어 트렌드 분석', '최신 문화 트렌드와 유행어를 분석해 선후배 간 소통 역량을 높입니다.', '최근 커뮤니티나 SNS 유행어 또는 의미 있는 사자성어 1개와 유래, 경어체 예문 3개를 단대에 공유합니다.', '상시 제출 가능하지만 일일 선착순 3인만 상점을 부여하며, 중복 단어는 인정하지 않습니다.', 80],
                ['gyeongin', '난제 발제', '재학생 간 논리적 토론을 유도하고 단대의 상호 작용을 활성화합니다.', '선후배와 동기가 한마디씩 거들 수밖에 없는 난제를 제시해 대화를 이끕니다.', '본인 제외 재학생 3명 이상의 유효 답변이 필요하며, 상시 제출 가능하되 일일 1회만 인정합니다.', 90],
                ['gyeongmul', '심야 학업 간식 추천', '야간 자율학습 중 메뉴 선정 문제를 데이터와 논리로 해결해 학우를 돕습니다.', '편의점 메뉴 레시피 또는 기온·습도·학업량을 바탕으로 최적의 야식 메뉴 2개를 추천합니다.', '메뉴명만 쓰지 않고 선택 근거를 함께 제시해야 하며, 19:00 이후 심야 학업 시간대에 1회만 인정합니다.', 100],
                ['gyeongcheon', '지식 나눔 카드뉴스 제작', '학교 구성원에게 도움이 되는 정보와 지식을 카드뉴스 또는 요약 노트로 공유합니다.', '학업 팁, 교칙 해설, 학교생활 안내 등을 제목이 있는 표지 1장 이상과 본문 1장 이상으로 구성해 제출합니다.', '핵심 내용과 활용 포인트가 분명해야 하며, 유사 주제 반복이나 정보성이 부족한 경우 삼경원 심사로 기각될 수 있습니다.', 110],
            ];

            $stmt = $pdo->prepare('
                INSERT INTO hall_activities (hall_key, title, summary, method, value, sort_order)
                VALUES (?, ?, ?, ?, ?, ?)
            ');

            foreach ($activities as $activity) {
                $stmt->execute($activity);
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

        $ruleCount = (int) $pdo->query('SELECT COUNT(*) FROM point_rules')->fetchColumn();
        if ($ruleCount === 0) {
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

        $listRuleCount = (int) $pdo->query('SELECT COUNT(*) FROM point_list_rules')->fetchColumn();
        if ($listRuleCount === 0) {
            $rules = [
                ['demerit', '1점', '메시지 삭제(혼란을 줄 만한 공지사항의 오류 및 개인정보 관련 사항은 제외), 인튀, 읽씹, 무단결석', 10],
                ['demerit', '2점', '인사 미흡, 관등 미흡, 대답 미흡, 현활 중 5분 내 미대답 (예절 기간)', 20],
                ['demerit', '3점', '호칭/경어 미흡, 일지 미작성, 무단불참', 30],
                ['demerit', '4점', '비속어 사용, 지시 불이행, 단체 대화방 내 싸움, 보안 규정 위반, 잠수 중 읽음', 40],
                ['demerit', '1~5점', '선배 재량', 50],
                ['merit', '1점', '일지 작성, 회의록 작성, 자유 게시판 작성, 갈매기 홍보 상점, 출석 4회 이상, 근무 상점(삼경원)', 10],
                ['merit', '2점', '교내 자치 활동 (하루 1회 제한), 한자 깜지 200회(한글 포함), 삼경인 선서문 3회 작성 (하루 1회 제한), 예절 테스트, 문장 깜지', 20],
                ['merit', '3점', '후배 교육 자료 작성 (하루 1회 제한)', 30],
                ['merit', '4점', '추가 규정 작성 예정', 40],
                ['merit', '1~5점', '선배 재량', 50],
                ['submit', '', '모든 상점 제출물은 성의 있게 작성되어야 하며, 삼경원 과반수 동의 시 기각될 수 있다.', 10],
                ['submit', '', '상점 제출은 삼경원 개인 채팅방으로 하며, 사진 첨부를 통해 얻는 모든 상점은 상단 또는 하단에 "2026.OO.OO (관명) O학년 OOO"을 작성하고 그 위에 형광펜으로 표시해야 인정된다.', 20],
                ['submit', '', '상점 제출 시, [(관명) O학년 OOO, (상점 사유) + O점]의 양식을 갖춰야 한다.', 30],
            ];

            $stmt = $pdo->prepare('
                INSERT INTO point_list_rules (category, score_label, rule_text, sort_order)
                VALUES (?, ?, ?, ?)
            ');

            foreach ($rules as $rule) {
                $stmt->execute($rule);
            }
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

    private static function ensureCouncilMinutesReadPermissions(PDO $pdo): void
    {
        $stmt = $pdo->prepare('SELECT read_roles FROM board_permissions WHERE board_slug = ?');
        $stmt->execute(['council-minutes']);
        $rawRoles = $stmt->fetchColumn();
        if ($rawRoles === false) {
            return;
        }

        $roles = json_decode((string) $rawRoles, true);
        if (!is_array($roles)) {
            $roles = [];
        }

        foreach (['student', 'council', 'admin'] as $role) {
            if (!in_array($role, $roles, true)) {
                $roles[] = $role;
            }
        }

        $roles = array_values(array_diff(array_unique($roles), ['guest']));
        $stmt = $pdo->prepare('UPDATE board_permissions SET read_roles = ?, updated_at = CURRENT_TIMESTAMP WHERE board_slug = ?');
        $stmt->execute([json_encode($roles, JSON_UNESCAPED_UNICODE), 'council-minutes']);
    }

    private static function ensureBasicLiteracyBoardPermissions(PDO $pdo): void
    {
        $stmt = $pdo->prepare('
            INSERT INTO board_permissions (board_slug, read_roles, write_roles, updated_at)
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(board_slug) DO NOTHING
        ');
        $stmt->execute([
            'basic-literacy',
            json_encode(['student', 'council', 'admin'], JSON_UNESCAPED_UNICODE),
            json_encode(['council', 'admin'], JSON_UNESCAPED_UNICODE),
        ]);

        $stmt = $pdo->prepare('SELECT read_roles FROM board_permissions WHERE board_slug = ?');
        $stmt->execute(['basic-literacy']);
        $roles = json_decode((string) $stmt->fetchColumn(), true);
        if (!is_array($roles)) {
            $roles = [];
        }

        $roles = array_values(array_diff(array_unique(array_merge($roles, ['student', 'council', 'admin'])), ['guest']));
        $stmt = $pdo->prepare('UPDATE board_permissions SET read_roles = ?, updated_at = CURRENT_TIMESTAMP WHERE board_slug = ?');
        $stmt->execute([json_encode($roles, JSON_UNESCAPED_UNICODE), 'basic-literacy']);
    }

    private static function ensureMallDefaults(PDO $pdo): void
    {
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO mall_settings (key, value) VALUES (?, ?)');
        $stmt->execute(['student_open', '0']);

        $count = (int) $pdo->query('SELECT COUNT(*) FROM mall_items')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $items = self::defaultMallItems();

        $stmt = $pdo->prepare('
            INSERT INTO mall_items (name, description, price, sort_order)
            VALUES (?, ?, ?, ?)
        ');

        foreach ($items as $item) {
            $stmt->execute($item);
        }
    }

    private static function ensureMallItemsSplit(PDO $pdo): void
    {
        $version = (int) ($pdo->query("SELECT value FROM mall_settings WHERE key = 'mall_items_version'")->fetchColumn() ?: 0);
        if ($version >= 2) {
            return;
        }

        $legacyUpdates = [
            '기본 면제권' => ['인사 면제권', '인사 예절 수행을 1회 면제받을 수 있는 권한', 10, 10],
            '외식 및 소속 변경권' => ['외식권', '정해진 기준에 따라 외식 1회를 신청할 수 있는 권한', 15, 50],
        ];

        foreach ($legacyUpdates as $legacyName => $item) {
            $stmt = $pdo->prepare('
                UPDATE mall_items
                SET name = ?, description = ?, price = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
                WHERE name = ?
            ');
            $stmt->execute([$item[0], $item[1], $item[2], $item[3], $legacyName]);
        }

        $update = $pdo->prepare('
            UPDATE mall_items
            SET description = ?, price = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM mall_items WHERE name = ?');
        $insert = $pdo->prepare('
            INSERT INTO mall_items (name, description, price, sort_order)
            VALUES (?, ?, ?, ?)
        ');

        foreach (self::defaultMallItems() as $item) {
            $stmt->execute([$item[0]]);
            if ((int) $stmt->fetchColumn() === 0) {
                $insert->execute($item);
            } else {
                $update->execute([$item[1], $item[2], $item[3], $item[0]]);
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO mall_settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute(['mall_items_version', '2']);
    }

    private static function ensureHallActivityTimeText(PDO $pdo): void
    {
        $version = (int) ($pdo->query("SELECT value FROM mall_settings WHERE key = 'hall_activity_time_text_version'")->fetchColumn() ?: 0);
        if ($version >= 1) {
            return;
        }

        $updates = [
            '당일 시사 요약' => '매일 00:00부터 선착순 3인만 인정하며, 비방·미확인 루머·정치적 편향이 있으면 제외합니다.',
            '심야 학업 간식 추천' => '메뉴명만 쓰지 않고 선택 근거를 함께 제시해야 하며, 19:00 이후 심야 학업 시간대에 1회만 인정합니다.',
        ];

        $stmt = $pdo->prepare('UPDATE hall_activities SET value = ? WHERE title = ?');
        foreach ($updates as $title => $value) {
            $stmt->execute([$value, $title]);
        }

        $stmt = $pdo->prepare('
            INSERT INTO mall_settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute(['hall_activity_time_text_version', '1']);
    }

    private static function ensureMallGifticonItems(PDO $pdo): void
    {
        $version = (int) ($pdo->query("SELECT value FROM mall_settings WHERE key = 'mall_items_version'")->fetchColumn() ?: 0);
        if ($version >= 3) {
            return;
        }

        $stmt = $pdo->prepare('
            UPDATE mall_items
            SET name = ?, description = ?, price = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ');
        $stmt->execute(['치킨 기프티콘 변경권', '치킨 기프티콘으로 교환을 신청할 수 있는 권한', 15, 50, '외식권']);

        $update = $pdo->prepare('
            UPDATE mall_items
            SET description = ?, price = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ');
        $exists = $pdo->prepare('SELECT COUNT(*) FROM mall_items WHERE name = ?');
        $insert = $pdo->prepare('
            INSERT INTO mall_items (name, description, price, sort_order)
            VALUES (?, ?, ?, ?)
        ');

        foreach (self::defaultMallItems() as $item) {
            $exists->execute([$item[0]]);
            if ((int) $exists->fetchColumn() === 0) {
                $insert->execute($item);
            } else {
                $update->execute([$item[1], $item[2], $item[3], $item[0]]);
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO mall_settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute(['mall_items_version', '3']);
    }

    private static function ensureMallGradeChangeItem(PDO $pdo): void
    {
        $version = (int) ($pdo->query("SELECT value FROM mall_settings WHERE key = 'mall_items_version'")->fetchColumn() ?: 0);
        if ($version >= 4) {
            return;
        }

        $stmt = $pdo->prepare('
            UPDATE mall_items
            SET name = ?, description = ?, price = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ');
        $stmt->execute(['학년 변경권', '타 인원의 학년 변경 또는 본인 학년 체험을 24시간 신청할 수 있는 권한', 30, 100, '동년 교류권']);

        $update = $pdo->prepare('
            UPDATE mall_items
            SET description = ?, price = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ');
        $exists = $pdo->prepare('SELECT COUNT(*) FROM mall_items WHERE name = ?');
        $insert = $pdo->prepare('
            INSERT INTO mall_items (name, description, price, sort_order)
            VALUES (?, ?, ?, ?)
        ');

        foreach (self::defaultMallItems() as $item) {
            $exists->execute([$item[0]]);
            if ((int) $exists->fetchColumn() === 0) {
                $insert->execute($item);
            } else {
                $update->execute([$item[1], $item[2], $item[3], $item[0]]);
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO mall_settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute(['mall_items_version', '4']);
    }

    private static function ensureMallAttendanceItem(PDO $pdo): void
    {
        $version = (int) ($pdo->query("SELECT value FROM mall_settings WHERE key = 'mall_items_version'")->fetchColumn() ?: 0);
        if ($version >= 5) {
            return;
        }

        $stmt = $pdo->prepare('
            UPDATE mall_items
            SET name = ?, description = ?, price = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ');
        $stmt->execute(['출석 면제권', '정해진 기준에 따라 출석 관련 예외 1회를 신청할 수 있는 권한', 10, 30, '반차 면제권']);

        $stmt = $pdo->prepare('
            UPDATE mall_orders
            SET item_name = ?
            WHERE item_name = ?
        ');
        $stmt->execute(['출석 면제권', '반차 면제권']);

        $stmt = $pdo->prepare('
            UPDATE mall_items
            SET active = 0, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ');
        $stmt->execute(['동아리 출석 인정권']);

        $update = $pdo->prepare('
            UPDATE mall_items
            SET description = ?, price = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE name = ?
        ');
        $exists = $pdo->prepare('SELECT COUNT(*) FROM mall_items WHERE name = ?');
        $insert = $pdo->prepare('
            INSERT INTO mall_items (name, description, price, sort_order)
            VALUES (?, ?, ?, ?)
        ');

        foreach (self::defaultMallItems() as $item) {
            $exists->execute([$item[0]]);
            if ((int) $exists->fetchColumn() === 0) {
                $insert->execute($item);
            } else {
                $update->execute([$item[1], $item[2], $item[3], $item[0]]);
            }
        }

        $stmt = $pdo->prepare('
            INSERT INTO mall_settings (key, value, updated_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = CURRENT_TIMESTAMP
        ');
        $stmt->execute(['mall_items_version', '5']);
    }

    private static function defaultMallItems(): array
    {
        return [
            ['인사 면제권', '인사 예절 수행을 1회 면제받을 수 있는 권한', 10, 10],
            ['관등 면제권', '관등 예절 수행을 1회 면제받을 수 있는 권한', 10, 20],
            ['출석 면제권', '정해진 기준에 따라 출석 관련 예외 1회를 신청할 수 있는 권한', 10, 30],
            ['치킨 기프티콘 변경권', '치킨 기프티콘으로 교환을 신청할 수 있는 권한', 15, 50],
            ['피자 기프티콘 변경권', '피자 기프티콘으로 교환을 신청할 수 있는 권한', 15, 60],
            ['커피 기프티콘 변경권', '커피 기프티콘으로 교환을 신청할 수 있는 권한', 15, 70],
            ['소속 변경권', '타 인원의 소속 관 변경을 24시간 신청할 수 있는 권한', 15, 80],
            ['직속 교류권', '타 직속 1일 체험을 24시간 신청할 수 있는 권한', 20, 90],
            ['학년 변경권', '타 인원의 학년 변경 또는 본인 학년 체험을 24시간 신청할 수 있는 권한', 30, 100],
            ['징계 사면권', '개인 징계 1회를 삼경원 심사를 거쳐 무효 처리하는 권한', 40, 110],
        ];
    }
}
