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
        'council' => [
            'name' => '자유게시판(학생회)',
            'badge' => '의견',
            'tags' => ['의견', '공지', '회의', '일반'],
            'read_roles' => ['council', 'admin'],
            'write_roles' => ['council', 'admin'],
        ],
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
