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
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                board TEXT NOT NULL,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                file_name TEXT,
                file_path TEXT,
                author_id INTEGER NOT NULL,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(author_id) REFERENCES users(id)
            );

            CREATE TABLE IF NOT EXISTS hall_members (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                hall_key TEXT NOT NULL,
                hall_name TEXT NOT NULL,
                hall_meaning TEXT NOT NULL,
                hall_color TEXT NOT NULL CHECK(hall_color IN ('blue', 'gold', 'green')),
                student_name TEXT NOT NULL,
                year INTEGER NOT NULL CHECK(year BETWEEN 1 AND 3),
                role_label TEXT NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ");

        $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count === 0) {
            $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
            $stmt->execute(['admin', password_hash('admin1234', PASSWORD_DEFAULT), 'admin']);
        }

        $hallCount = (int) $pdo->query('SELECT COUNT(*) FROM hall_members')->fetchColumn();
        if ($hallCount === 0) {
            $members = [
                ['gyeongcheon', '경천관', '하늘', 'blue', '이도윤', 3, '관장', 10],
                ['gyeongcheon', '경천관', '하늘', 'blue', '김서윤', 2, '부관장', 20],
                ['gyeongcheon', '경천관', '하늘', 'blue', '박지우', 1, '대표', 30],
                ['gyeongin', '경인관', '사람', 'gold', '최우진', 3, '관장', 10],
                ['gyeongin', '경인관', '사람', 'gold', '정하은', 2, '부관장', 20],
                ['gyeongin', '경인관', '사람', 'gold', '강하린', 1, '대표', 30],
                ['gyeongmul', '경물관', '만물', 'green', '송준기', 3, '관장', 10],
                ['gyeongmul', '경물관', '만물', 'green', '유승호', 2, '부관장', 20],
                ['gyeongmul', '경물관', '만물', 'green', '오진우', 1, '대표', 30],
            ];

            $stmt = $pdo->prepare('
                INSERT INTO hall_members
                (hall_key, hall_name, hall_meaning, hall_color, student_name, year, role_label, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');

            foreach ($members as $member) {
                $stmt->execute($member);
            }
        }
    }
}
