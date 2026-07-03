<?php

declare(strict_types=1);

final class Board
{
    private const BOARDS = [
        'notice' => [
            'name' => '입학 게시판',
            'badge' => '공지',
            'tags' => ['공지', '모집', '안내', '일반'],
            'read_roles' => [],
            'write_roles' => ['council', 'admin'],
        ],
        'resources' => [
            'name' => '자료실',
            'badge' => '자료',
            'tags' => ['자료', '규정', '안내', '일반'],
            'read_roles' => ['student', 'council', 'admin'],
            'write_roles' => ['council', 'admin'],
        ],
        'free' => [
            'name' => '자유게시판',
            'badge' => '일반',
            'tags' => ['일반', '질문', '정보', '의견'],
            'read_roles' => ['student', 'council', 'admin'],
            'write_roles' => ['student', 'council', 'admin'],
        ],
        'council' => [
            'name' => '자유게시판(학생회)',
            'badge' => '의견',
            'tags' => ['의견', '공지', '회의', '일반'],
            'read_roles' => ['council', 'admin'],
            'write_roles' => ['council', 'admin'],
        ],
    ];

    public static function fromSlug(string $slug, ?PDO $db = null): array
    {
        if (!isset(self::BOARDS[$slug])) {
            http_response_code(404);
            echo view('page', ['title' => '404', 'body' => '게시판을 찾을 수 없습니다.']);
            exit;
        }

        return ['slug' => $slug] + self::withPermissions($slug, self::BOARDS[$slug], $db);
    }

    public static function all(?PDO $db = null): array
    {
        $boards = [];
        foreach (self::BOARDS as $slug => $board) {
            $boards[$slug] = self::withPermissions($slug, $board, $db);
        }

        return $boards;
    }

    public static function roleOptions(): array
    {
        return [
            'student' => '재학생',
            'council' => '삼경원',
            'admin' => '관리자',
        ];
    }

    private static function withPermissions(string $slug, array $board, ?PDO $db): array
    {
        if (!$db) {
            return $board;
        }

        $stmt = $db->prepare('SELECT read_roles, write_roles FROM board_permissions WHERE board_slug = ?');
        $stmt->execute([$slug]);
        $permissions = $stmt->fetch();
        if (!$permissions) {
            return $board;
        }

        $readRoles = json_decode((string) $permissions['read_roles'], true);
        $writeRoles = json_decode((string) $permissions['write_roles'], true);
        $allowedRoles = array_keys(self::roleOptions());

        if (is_array($readRoles)) {
            $board['read_roles'] = array_values(array_intersect($readRoles, $allowedRoles));
        }
        if (is_array($writeRoles)) {
            $board['write_roles'] = array_values(array_intersect($writeRoles, $allowedRoles));
        }

        return $board;
    }
}
