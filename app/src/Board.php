<?php

declare(strict_types=1);

final class Board
{
    private const BOARDS = [
        'notice' => ['name' => '공지/게시판', 'write_roles' => ['council', 'admin']],
        'resources' => ['name' => '학생 자료실', 'write_roles' => ['council', 'admin']],
        'council' => ['name' => '학생회 게시판', 'write_roles' => ['council', 'admin']],
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
