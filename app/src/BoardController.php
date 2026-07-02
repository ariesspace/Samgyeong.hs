<?php

declare(strict_types=1);

final class BoardController
{
    public function __construct(private PDO $db, private Auth $auth)
    {
    }

    public function index(array $board): string
    {
        if ($board['read_roles'] !== [] && !$this->auth->hasRole($board['read_roles'])) {
            return view('access-denied', [
                'title' => '권한 없음',
                'message' => $board['slug'] === 'council'
                    ? '삼경원(학생회) 인원 및 관리자만 접근이 가능한 메뉴입니다.'
                    : '재학생 이상 로그인 후 접근이 가능한 메뉴입니다.',
            ]);
        }

        $keyword = trim($_GET['q'] ?? '');
        $where = 'WHERE board = ?';
        $params = [$board['slug']];

        if ($keyword !== '') {
            $where .= ' AND (posts.title LIKE ? OR posts.body LIKE ? OR users.username LIKE ?)';
            $like = '%' . $keyword . '%';
            $params = [$board['slug'], $like, $like, $like];
        }

        $stmt = $this->db->prepare("
            SELECT posts.*, users.username
            FROM posts
            JOIN users ON users.id = posts.author_id
            {$where}
            ORDER BY posts.id DESC
        ");
        $stmt->execute($params);
        $posts = $stmt->fetchAll();
        $user = $this->auth->user();

        foreach ($posts as &$post) {
            $post['can_manage'] = $user && ($user['role'] === 'admin' || (int) $user['id'] === (int) $post['author_id']);
        }
        unset($post);

        return view('board', [
            'title' => $board['name'],
            'board' => $board,
            'posts' => $posts,
            'keyword' => $keyword,
            'canWrite' => $this->auth->hasRole($board['write_roles']),
            'hasManageColumn' => array_reduce($posts, fn (bool $carry, array $post): bool => $carry || (bool) $post['can_manage'], false),
        ]);
    }

    public function create(array $board): string
    {
        $this->auth->requireRole($board['write_roles']);
        return view('post-form', [
            'title' => $board['name'] . ' 글쓰기',
            'board' => $board,
            'post' => null,
            'action' => '/board/' . $board['slug'] . '/store',
            'submitLabel' => '등록',
        ]);
    }

    public function show(array $board, int $id): string
    {
        if ($board['read_roles'] !== [] && !$this->auth->hasRole($board['read_roles'])) {
            return view('access-denied', [
                'title' => '권한 없음',
                'message' => $board['slug'] === 'council'
                    ? '삼경원(학생회) 인원 및 관리자만 접근이 가능한 메뉴입니다.'
                    : '재학생 이상 로그인 후 접근이 가능한 메뉴입니다.',
            ]);
        }

        $this->db->prepare('UPDATE posts SET views = views + 1 WHERE board = ? AND id = ?')
            ->execute([$board['slug'], $id]);

        $stmt = $this->db->prepare('
            SELECT posts.*, users.username
            FROM posts
            JOIN users ON users.id = posts.author_id
            WHERE posts.board = ? AND posts.id = ?
        ');
        $stmt->execute([$board['slug'], $id]);
        $post = $stmt->fetch();

        if (!$post) {
            http_response_code(404);
            return view('page', ['title' => '404', 'body' => '게시글을 찾을 수 없습니다.']);
        }

        return view('post-detail', [
            'title' => $post['title'],
            'board' => $board,
            'post' => $post,
            'canManage' => $this->canManage($post),
        ]);
    }

    public function store(array $board): never
    {
        $this->auth->requireRole($board['write_roles']);
        $file = $this->saveUpload();

        $stmt = $this->db->prepare('
            INSERT INTO posts (board, tag, title, body, file_name, file_path, author_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $board['slug'],
            $this->selectedTag($board),
            trim($_POST['title'] ?? ''),
            sanitize_post_body($_POST['body'] ?? ''),
            $file['name'],
            $file['path'],
            $this->auth->user()['id'],
        ]);

        redirect('/board/' . $board['slug']);
    }

    public function edit(array $board, int $id): string
    {
        $post = $this->post($board, $id);
        $this->requireManage($post);

        return view('post-form', [
            'title' => $board['name'] . ' 수정',
            'board' => $board,
            'post' => $post,
            'action' => '/board/' . $board['slug'] . '/post/' . $id . '/update',
            'submitLabel' => '수정',
        ]);
    }

    public function update(array $board, int $id): never
    {
        $post = $this->post($board, $id);
        $this->requireManage($post);
        $file = $this->saveUpload();

        $fileName = $post['file_name'];
        $filePath = $post['file_path'];
        if ($file['path']) {
            $this->deleteUpload($filePath);
            $fileName = $file['name'];
            $filePath = $file['path'];
        }

        $stmt = $this->db->prepare('
            UPDATE posts
            SET tag = ?, title = ?, body = ?, file_name = ?, file_path = ?
            WHERE board = ? AND id = ?
        ');
        $stmt->execute([
            $this->selectedTag($board),
            trim($_POST['title'] ?? ''),
            sanitize_post_body($_POST['body'] ?? ''),
            $fileName,
            $filePath,
            $board['slug'],
            $id,
        ]);

        redirect('/board/' . $board['slug'] . '/post/' . $id);
    }

    public function delete(array $board, int $id): never
    {
        $post = $this->post($board, $id);
        $this->requireManage($post);

        $stmt = $this->db->prepare('DELETE FROM posts WHERE board = ? AND id = ?');
        $stmt->execute([$board['slug'], $id]);
        $this->deleteUpload($post['file_path']);

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

    private function post(array $board, int $id): array
    {
        $stmt = $this->db->prepare('
            SELECT posts.*, users.username
            FROM posts
            JOIN users ON users.id = posts.author_id
            WHERE posts.board = ? AND posts.id = ?
        ');
        $stmt->execute([$board['slug'], $id]);
        $post = $stmt->fetch();

        if (!$post) {
            http_response_code(404);
            echo view('page', ['title' => '404', 'body' => '게시글을 찾을 수 없습니다.']);
            exit;
        }

        return $post;
    }

    private function selectedTag(array $board): string
    {
        $tag = trim($_POST['tag'] ?? '');
        return in_array($tag, $board['tags'], true) ? $tag : $board['badge'];
    }

    private function canManage(array $post): bool
    {
        $user = $this->auth->user();
        if (!$user) {
            return false;
        }

        return $user['role'] === 'admin' || (int) $user['id'] === (int) $post['author_id'];
    }

    private function requireManage(array $post): void
    {
        if (!$this->canManage($post)) {
            http_response_code(403);
            echo view('page', ['title' => '권한 없음', 'body' => '작성자와 관리자만 수정하거나 삭제할 수 있습니다.']);
            exit;
        }
    }

    private function deleteUpload(?string $path): void
    {
        if (!$path) {
            return;
        }

        $target = __DIR__ . '/../storage/uploads/' . basename($path);
        if (is_file($target)) {
            unlink($target);
        }
    }
}
