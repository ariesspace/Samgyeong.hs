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
            $where .= ' AND (posts.title LIKE ? OR posts.body LIKE ? OR users.username LIKE ? OR users.display_name LIKE ?)';
            $like = '%' . $keyword . '%';
            $params = [$board['slug'], $like, $like, $like, $like];
        }

        $stmt = $this->db->prepare("
            SELECT posts.*, users.username, users.display_name AS author_name
            FROM posts
            JOIN users ON users.id = posts.author_id
            {$where}
            ORDER BY CASE WHEN posts.tag = '공지' THEN 0 ELSE 1 END, posts.id DESC
        ");
        $stmt->execute($params);
        $posts = $stmt->fetchAll();
        $user = $this->auth->user();

        foreach ($posts as &$post) {
            $post['can_manage'] = $user && ($user['role'] === 'admin' || (int) $user['id'] === (int) $post['author_id']);
        }
        unset($post);
        $this->attachFileCounts($posts);

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
            SELECT posts.*, users.username, users.display_name AS author_name
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
            'files' => $this->postFiles((int) $post['id'], $post),
            'canManage' => $this->canManage($post),
        ]);
    }

    public function store(array $board): never
    {
        $this->auth->requireRole($board['write_roles']);
        $files = $this->saveUploads();
        $firstFile = $files[0] ?? ['name' => null, 'path' => null];

        $stmt = $this->db->prepare('
            INSERT INTO posts (board, tag, title, body, file_name, file_path, author_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $board['slug'],
            $this->selectedTag($board),
            trim($_POST['title'] ?? ''),
            sanitize_post_body($_POST['body'] ?? ''),
            $firstFile['name'],
            $firstFile['path'],
            $this->auth->user()['id'],
        ]);
        $this->insertPostFiles((int) $this->db->lastInsertId(), $files);

        redirect('/board/' . $board['slug']);
    }

    public function edit(array $board, int $id): string
    {
        $post = $this->post($board, $id);
        $this->requireManage($post);
        $post['files'] = $this->postFiles((int) $post['id'], $post);

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
        $files = $this->saveUploads();

        $this->ensureLegacyPostFile($id, $post);
        $this->deleteSelectedFiles($id, $_POST['delete_files'] ?? [], $post);
        $this->insertPostFiles($id, $files);
        $currentFiles = $this->storedPostFiles($id);
        $firstFile = $currentFiles[0] ?? ['file_name' => null, 'file_path' => null];

        $stmt = $this->db->prepare('
            UPDATE posts
            SET tag = ?, title = ?, body = ?, file_name = ?, file_path = ?
            WHERE board = ? AND id = ?
        ');
        $stmt->execute([
            $this->selectedTag($board),
            trim($_POST['title'] ?? ''),
            sanitize_post_body($_POST['body'] ?? ''),
            $firstFile['file_name'],
            $firstFile['file_path'],
            $board['slug'],
            $id,
        ]);

        redirect('/board/' . $board['slug'] . '/post/' . $id);
    }

    public function delete(array $board, int $id): never
    {
        $post = $this->post($board, $id);
        $this->requireManage($post);

        foreach ($this->postFiles($id, $post) as $file) {
            $this->deleteUpload($file['file_path']);
        }

        $this->db->prepare('DELETE FROM post_files WHERE post_id = ?')->execute([$id]);
        $stmt = $this->db->prepare('DELETE FROM posts WHERE board = ? AND id = ?');
        $stmt->execute([$board['slug'], $id]);

        redirect('/board/' . $board['slug']);
    }

    private function saveUploads(): array
    {
        $uploads = [];

        if (isset($_FILES['files'])) {
            $count = is_array($_FILES['files']['name']) ? count($_FILES['files']['name']) : 0;
            for ($i = 0; $i < $count; $i++) {
                $uploads[] = [
                    'name' => $_FILES['files']['name'][$i] ?? '',
                    'tmp_name' => $_FILES['files']['tmp_name'][$i] ?? '',
                    'error' => $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                ];
            }
        }

        if (isset($_FILES['file'])) {
            $uploads[] = [
                'name' => $_FILES['file']['name'] ?? '',
                'tmp_name' => $_FILES['file']['tmp_name'] ?? '',
                'error' => $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE,
            ];
        }

        $files = [];
        $uploadDir = __DIR__ . '/../storage/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        foreach ($uploads as $upload) {
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($upload['tmp_name'])) {
                continue;
            }

            $original = basename((string) $upload['name']);
            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $original) ?: 'file';
            $stored = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
            $target = $uploadDir . '/' . $stored;

            if (!move_uploaded_file($upload['tmp_name'], $target)) {
                continue;
            }

            $files[] = ['name' => $original, 'path' => $stored];
        }

        return $files;
    }

    private function post(array $board, int $id): array
    {
        $stmt = $this->db->prepare('
            SELECT posts.*, users.username, users.display_name AS author_name
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

    private function attachFileCounts(array &$posts): void
    {
        if ($posts === []) {
            return;
        }

        $ids = array_map(fn (array $post): int => (int) $post['id'], $posts);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("
            SELECT post_id, COUNT(*) AS file_count
            FROM post_files
            WHERE post_id IN ({$placeholders})
            GROUP BY post_id
        ");
        $stmt->execute($ids);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['post_id']] = (int) $row['file_count'];
        }

        foreach ($posts as &$post) {
            $count = $counts[(int) $post['id']] ?? 0;
            if ($count === 0 && !empty($post['file_path'])) {
                $count = 1;
            }
            $post['attachment_count'] = $count;
        }
        unset($post);
    }

    private function postFiles(int $postId, array $post): array
    {
        $stmt = $this->db->prepare('
            SELECT id, file_name, file_path
            FROM post_files
            WHERE post_id = ?
            ORDER BY id ASC
        ');
        $stmt->execute([$postId]);
        $files = $stmt->fetchAll();

        if (!empty($post['file_path'])) {
            $hasLegacyFile = array_filter(
                $files,
                fn (array $file): bool => $file['file_path'] === $post['file_path']
            );
            if (!$hasLegacyFile) {
                array_unshift($files, [
                    'id' => null,
                    'file_name' => $post['file_name'] ?: '첨부파일',
                    'file_path' => $post['file_path'],
                ]);
            }
        }

        return $files;
    }

    private function storedPostFiles(int $postId): array
    {
        $stmt = $this->db->prepare('
            SELECT id, file_name, file_path
            FROM post_files
            WHERE post_id = ?
            ORDER BY id ASC
        ');
        $stmt->execute([$postId]);

        return $stmt->fetchAll();
    }

    private function ensureLegacyPostFile(int $postId, array $post): void
    {
        if (empty($post['file_path'])) {
            return;
        }

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM post_files WHERE post_id = ? AND file_path = ?');
        $stmt->execute([$postId, $post['file_path']]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $this->db->prepare('INSERT INTO post_files (post_id, file_name, file_path) VALUES (?, ?, ?)')
            ->execute([$postId, $post['file_name'] ?: '첨부파일', $post['file_path']]);
    }

    private function deleteSelectedFiles(int $postId, array|string $selected, array $post): void
    {
        $selected = is_array($selected) ? $selected : [$selected];
        if ($selected === []) {
            return;
        }

        $select = $this->db->prepare('SELECT id, file_name, file_path FROM post_files WHERE id = ? AND post_id = ?');
        $delete = $this->db->prepare('DELETE FROM post_files WHERE id = ? AND post_id = ?');

        foreach ($selected as $value) {
            $value = (string) $value;
            if (ctype_digit($value)) {
                $select->execute([(int) $value, $postId]);
                $file = $select->fetch();
                if (!$file) {
                    continue;
                }

                $this->deleteUpload($file['file_path']);
                $delete->execute([(int) $value, $postId]);
                continue;
            }

            if (str_starts_with($value, 'legacy:') && !empty($post['file_path'])) {
                $path = basename(substr($value, strlen('legacy:')));
                if ($path !== basename($post['file_path'])) {
                    continue;
                }

                $this->deleteUpload($post['file_path']);
                $this->db->prepare('DELETE FROM post_files WHERE post_id = ? AND file_path = ?')
                    ->execute([$postId, $post['file_path']]);
            }
        }
    }

    private function insertPostFiles(int $postId, array $files): void
    {
        if ($files === []) {
            return;
        }

        $stmt = $this->db->prepare('INSERT INTO post_files (post_id, file_name, file_path) VALUES (?, ?, ?)');
        foreach ($files as $file) {
            if (empty($file['path'])) {
                continue;
            }
            $stmt->execute([$postId, $file['name'], $file['path']]);
        }
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
