<?php

declare(strict_types=1);

final class BoardController
{
    public function __construct(private PDO $db, private Auth $auth)
    {
    }

    public function index(array $board): string
    {
        $stmt = $this->db->prepare("
            SELECT posts.*, users.username
            FROM posts
            JOIN users ON users.id = posts.author_id
            WHERE board = ?
            ORDER BY posts.id DESC
        ");
        $stmt->execute([$board['slug']]);

        return view('board', [
            'title' => $board['name'],
            'board' => $board,
            'posts' => $stmt->fetchAll(),
            'canWrite' => $this->auth->hasRole($board['write_roles']),
        ]);
    }

    public function create(array $board): string
    {
        $this->auth->requireRole($board['write_roles']);
        return view('post-form', ['title' => $board['name'] . ' 글쓰기', 'board' => $board]);
    }

    public function store(array $board): never
    {
        $this->auth->requireRole($board['write_roles']);
        $file = $this->saveUpload();

        $stmt = $this->db->prepare('
            INSERT INTO posts (board, title, body, file_name, file_path, author_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $board['slug'],
            trim($_POST['title'] ?? ''),
            trim($_POST['body'] ?? ''),
            $file['name'],
            $file['path'],
            $this->auth->user()['id'],
        ]);

        redirect('/board/' . $board['slug']);
    }

    private function saveUpload(): array
    {
        if (empty($_FILES['file']['tmp_name']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['name' => null, 'path' => null];
        }

        $original = basename((string) $_FILES['file']['name']);
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?: 'file';
        $stored = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
        $target = __DIR__ . '/../storage/uploads/' . $stored;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
            return ['name' => null, 'path' => null];
        }

        return ['name' => $original, 'path' => $stored];
    }
}
