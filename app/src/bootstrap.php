<?php

declare(strict_types=1);

session_start();

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/' . $class . '.php';
    if (is_file($file)) {
        require $file;
    }
});

function view(string $template, array $data = []): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/../views/' . $template . '.php';
    $content = ob_get_clean();

    ob_start();
    require __DIR__ . '/../views/layout.php';
    return ob_get_clean();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        echo view('page', ['title' => '요청 만료', 'body' => '보안 토큰이 맞지 않습니다. 다시 시도해 주세요.']);
        exit;
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function role_label(?string $role): string
{
    return match ($role) {
        'admin' => '관리자',
        'council' => '학생회',
        'student' => '학생',
        default => '방문자',
    };
}

function nav_groups(): array
{
    $groups = [
        '학교소개' => [
            ['label' => '학교소개 및 교훈', 'href' => '/about'],
            ['label' => '삼경고 선서문', 'href' => '/pledge'],
            ['label' => '학교 연혁', 'href' => '/history'],
            ['label' => '오시는 길', 'href' => '/location'],
        ],
        '입학안내' => [
            ['label' => '모집요강', 'href' => '/admissions'],
            ['label' => '입학 게시판', 'href' => '/board/notice'],
        ],
        '삼경마당' => [
            ['label' => '관별 명단', 'href' => '/student-halls'],
            ['label' => '학교생활 규정', 'href' => '/rules'],
            ['label' => '자료실', 'href' => '/board/resources'],
        ],
        '학생 자치기구' => [
            ['label' => '학생회 소개', 'href' => '/council'],
            ['label' => '자유게시판', 'href' => '/board/council'],
            ['label' => '일정 캘린더', 'href' => '/calendar'],
        ],
    ];

    if ((current_user()['role'] ?? null) === 'admin') {
        $groups['시스템 관리'] = [
            ['label' => '계정 권한 관리', 'href' => '/admin/users'],
            ['label' => '관별 명단 관리', 'href' => '/admin/halls'],
        ];
    }

    return $groups;
}

function active_group(string $path): string
{
    foreach (nav_groups() as $group => $items) {
        foreach ($items as $item) {
            if ($path === $item['href'] || str_starts_with($path, $item['href'] . '/')) {
                return $group;
            }
        }
    }

    return '학교소개';
}

function hall_definitions(): array
{
    return [
        'gyeongcheon' => ['name' => '경천관', 'meaning' => '하늘', 'color' => 'blue'],
        'gyeongin' => ['name' => '경인관', 'meaning' => '사람', 'color' => 'gold'],
        'gyeongmul' => ['name' => '경물관', 'meaning' => '만물', 'color' => 'green'],
    ];
}
