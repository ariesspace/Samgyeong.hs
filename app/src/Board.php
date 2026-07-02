<?php

declare(strict_types=1);

final class Board
{
    private const BOARDS = [
        'notice' => ['name' => '공지사항', 'badge' => '공지', 'read_roles' => [], 'write_roles' => ['council', 'admin']],
        'resources' => ['name' => '자료실', 'badge' => '자료', 'read_roles' => ['student', 'council', 'admin'], 'write_roles' => ['council', 'admin']],
        'council' => ['name' => '자유게시판(학생회)', 'badge' => '의견', 'read_roles' => ['council', 'admin'], 'write_roles' => ['council', 'admin']],
    ];

    public static function fromSlug(string $slug): array
    {
        if (!isset(self::BOARDS[$slug])) {
            http_response_code(404);
            echo view('page', ['title' => '404', 'body' => '게시판을 찾을 수 없습니다.']);
            exit;
        }

        return ['slug' => $slug] + self::BOARDS[$slug];
    }

    public static function all(): array
    {
        return self::BOARDS;
    }
}
