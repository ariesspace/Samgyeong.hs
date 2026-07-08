<?php

declare(strict_types=1);

final class Auth
{
    public function __construct(private PDO $db)
    {
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function hasRole(array $roles): bool
    {
        $user = $this->user();
        return $user !== null && in_array($user['role'], $roles, true);
    }

    public function requireRole(array $roles): void
    {
        if (!$this->hasRole($roles)) {
            http_response_code(403);
            echo view('page', ['title' => '권한 없음', 'body' => '이 페이지에 접근할 권한이 없습니다.']);
            exit;
        }
    }

    public function loginPage(string $error = ''): string
    {
        return view('login', ['title' => '로그인', 'error' => $error]);
    }

    public function login(string $username, string $password): string
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([trim($username)]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return $this->loginPage('아이디 또는 비밀번호가 올바르지 않습니다.');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'username' => $user['username'],
            'role' => $user['role'],
            'display_name' => $user['display_name'] ?? '',
            'hall_key' => $user['hall_key'] ?? '',
            'year' => $user['year'] ?? 0,
            'photo_path' => $user['photo_path'] ?? '',
            'must_change_password' => (int) ($user['must_change_password'] ?? 0),
        ];

        redirect('/');
    }

    public function logout(): never
    {
        $_SESSION = [];
        session_destroy();
        redirect('/');
    }
}
