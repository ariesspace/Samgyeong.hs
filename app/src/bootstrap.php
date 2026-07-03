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

function sanitize_post_body(?string $html): string
{
    $html = trim($html ?? '');
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
    $html = strip_tags($html, '<p><div><br><strong><b><em><i><u><span><ul><ol><li>');
    $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/\s+(href|src)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

    $html = preg_replace_callback('/\s+style\s*=\s*("|\')([^"\']*)\1/i', function (array $match): string {
        $allowed = [];
        foreach (explode(';', $match[2]) as $declaration) {
            [$property, $value] = array_pad(array_map('trim', explode(':', $declaration, 2)), 2, '');
            $property = strtolower($property);
            $value = trim($value, "\"' ");

            if ($property === 'font-size' && preg_match('/^([1-2][0-9]|30)px$/', $value)) {
                $allowed[] = 'font-size: ' . $value;
            }

            if ($property === 'font-family') {
                $family = strtolower(str_replace(['"', "'"], '', $value));
                $families = [
                    'noto sans kr' => 'Noto Sans KR, sans-serif',
                    'malgun gothic' => 'Malgun Gothic, sans-serif',
                    'serif' => 'serif',
                    'georgia' => 'Georgia, serif',
                    'monospace' => 'monospace',
                ];

                if (isset($families[$family])) {
                    $allowed[] = 'font-family: ' . $families[$family];
                }
            }
        }

        return $allowed === [] ? '' : ' style="' . e(implode('; ', $allowed)) . '"';
    }, $html) ?? '';

    $html = preg_replace('/<(?!span\b)([a-z0-9]+)\s+[^>]*>/i', '<$1>', $html) ?? '';
    $html = preg_replace('/<span(?![^>]*style=)[^>]*>/i', '<span>', $html) ?? '';

    return $html;
}

function render_post_body(?string $body): string
{
    $body = trim($body ?? '');
    if ($body === '') {
        return '';
    }

    if ($body !== strip_tags($body)) {
        return sanitize_post_body($body);
    }

    return nl2br(e($body));
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
        'council' => '삼경원',
        'student' => '재학생',
        'guest' => '게스트',
        default => '방문자',
    };
}

function hall_label(?string $hallKey): string
{
    $halls = hall_definitions();

    return $hallKey && isset($halls[$hallKey]) ? $halls[$hallKey]['name'] : '-';
}

function nav_groups(): array
{
    $groups = [
        '학교소개' => [
            ['label' => '학교소개 및 교훈', 'href' => '/about'],
            ['label' => '학교 상징', 'href' => '/symbols'],
            ['label' => '삼경인 선서문', 'href' => '/pledge'],
            ['label' => '학교 연혁', 'href' => '/history'],
            ['label' => '오시는 길', 'href' => '/location'],
        ],
        '입학안내' => [
            ['label' => '모집요강', 'href' => '/admissions'],
            ['label' => '입학 게시판', 'href' => '/board/notice'],
        ],
        '삼경마당' => [
            [
                'label' => '관별 현황',
                'href' => '/student-halls',
                'children' => [
                    ['label' => '경천관', 'href' => '/student-halls?hall=gyeongcheon'],
                    ['label' => '경인관', 'href' => '/student-halls?hall=gyeongin'],
                    ['label' => '경물관', 'href' => '/student-halls?hall=gyeongmul'],
                ],
            ],
            [
                'label' => '학교생활 규정',
                'href' => '/rules',
                'children' => [
                    ['label' => '생활 규정', 'href' => '/rules'],
                    ['label' => '상벌점 리스트', 'href' => '/rules/points'],
                    ['label' => '징계 및 포상', 'href' => '/rules/discipline'],
                ],
            ],
            ['label' => '자료실', 'href' => '/board/resources'],
            ['label' => '자유게시판', 'href' => '/board/free'],
        ],
        '학생 자치기구' => [
            ['label' => '학생회 소개', 'href' => '/council'],
            ['label' => '자유게시판', 'href' => '/board/council'],
            ['label' => '일정 캘린더', 'href' => '/calendar'],
            ['label' => '상벌점 부여', 'href' => '/points/assign'],
        ],
    ];

    if (current_user() && (current_user()['role'] ?? '') !== 'guest') {
        $groups['마이페이지'] = [
            ['label' => '내 정보 수정', 'href' => '/mypage'],
            ['label' => '상벌점 현황', 'href' => '/mypage/points'],
        ];
    }

    if ((current_user()['role'] ?? null) === 'admin') {
        $groups['시스템 관리'] = [
            [
                'label' => '계정 권한 관리',
                'href' => '/admin/users',
                'children' => [
                    ['label' => '계정 리스트', 'href' => '/admin/users'],
                    ['label' => '계정 생성', 'href' => '/admin/users/create'],
                ],
            ],
            ['label' => '게시판 권한 설정', 'href' => '/admin/boards/permissions'],
            ['label' => '상벌점 기준 관리', 'href' => '/admin/point-rules'],
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

function point_rule_categories(): array
{
    return [
        'personal' => [
            'title' => '개인에 대한 징계 기준',
            'tone' => 'red',
            'description' => '',
        ],
        'year' => [
            'title' => '학년에 대한 징계 기준',
            'tone' => 'orange',
            'description' => '',
        ],
        'hall' => [
            'title' => '관에 대한 징계 기준',
            'tone' => 'gold',
            'description' => '경천관, 경인관, 경물관 단위로 적용됩니다.',
        ],
        'school' => [
            'title' => '전체에 대한 징계 기준',
            'tone' => 'gray',
            'description' => '',
        ],
    ];
}

function build_point_rule_sections(array $rules): array
{
    $sections = point_rule_categories();
    foreach ($sections as $key => $section) {
        $sections[$key]['items'] = [];
    }

    foreach ($rules as $rule) {
        $category = $rule['category'] ?? '';
        if (!isset($sections[$category])) {
            continue;
        }

        $sections[$category]['items'][] = [
            'id' => (int) ($rule['id'] ?? 0),
            'score' => (string) ($rule['score_label'] ?? ''),
            'text' => (string) ($rule['rule_text'] ?? ''),
            'emphasis' => (int) ($rule['is_emphasis'] ?? 0) === 1,
            'sort_order' => (int) ($rule['sort_order'] ?? 0),
        ];
    }

    return array_values($sections);
}
