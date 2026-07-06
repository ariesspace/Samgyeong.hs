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
    $html = strip_tags($html, '<p><div><br><strong><b><em><i><u><span><ul><ol><li><img>');
    $html = preg_replace('/\s+on\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';
    $html = preg_replace('/\s+href\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? '';

    $html = preg_replace_callback('/<img\b[^>]*>/i', function (array $match): string {
        if (!preg_match('/\s+src\s*=\s*("|\')([^"\']+)\1/i', $match[0], $srcMatch)) {
            return '';
        }

        $src = html_entity_decode($srcMatch[2], ENT_QUOTES, 'UTF-8');
        $path = parse_url($src, PHP_URL_PATH) ?: '';
        if (!preg_match('#^/uploads/([A-Za-z0-9._-]+\.(?:jpg|jpeg|png|webp|gif))$#i', $path, $pathMatch)) {
            return '';
        }

        $alt = '';
        if (preg_match('/\s+alt\s*=\s*("|\')([^"\']*)\1/i', $match[0], $altMatch)) {
            $alt = mb_substr(strip_tags(html_entity_decode($altMatch[2], ENT_QUOTES, 'UTF-8')), 0, 80);
        }

        return '<img src="/uploads/' . e($pathMatch[1]) . '" alt="' . e($alt) . '" loading="lazy">';
    }, $html) ?? '';

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

    $html = preg_replace('/<(?!(span|img)\b)([a-z0-9]+)\s+[^>]*>/i', '<$2>', $html) ?? '';
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

function is_superadmin_account(?array $user = null): bool
{
    $user ??= current_user();

    return ($user['role'] ?? null) === 'admin' && ($user['username'] ?? '') === 'superadmin';
}

function require_superadmin(Auth $auth): void
{
    if (!is_superadmin_account($auth->user())) {
        http_response_code(403);
        echo view('page', ['title' => '권한 없음', 'body' => 'superadmin 계정만 접근할 수 있습니다.']);
        exit;
    }
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

function student_label(array $student): string
{
    $name = trim((string) (($student['display_name'] ?? '') ?: ($student['username'] ?? '')));
    $hall = hall_label($student['hall_key'] ?? null);
    $year = (int) ($student['year'] ?? 0);
    $parts = [];

    if ($hall !== '-') {
        $parts[] = $hall;
    }
    if ($year > 0) {
        $parts[] = $year . '학년';
    }
    if ($name !== '') {
        $parts[] = $name;
    }

    return $parts ? implode(' ', $parts) : '-';
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
                'label' => '학교규칙·학교생활규정',
                'href' => '/rules',
                'children' => [
                    ['label' => '학교규칙', 'href' => '/rules'],
                    ['label' => '학교생활규정', 'href' => '/rules/life'],
                    ['label' => '상벌점 리스트', 'href' => '/rules/points'],
                    ['label' => '징계 및 포상', 'href' => '/rules/discipline'],
                ],
            ],
            ['label' => '관별 자치활동', 'href' => '/hall-activities'],
            ['label' => '식단표', 'href' => '/meal'],
            ['label' => '삼경몰', 'href' => '/samgyeong-mall'],
            ['label' => '자료실', 'href' => '/board/resources'],
            ['label' => '자유게시판', 'href' => '/board/free'],
        ],
        '학생 자치기구' => [
            ['label' => '학생회 소개', 'href' => '/council'],
            ['label' => '자유게시판', 'href' => '/board/council'],
            ['label' => '회의록 게시판', 'href' => '/board/council-minutes'],
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
            [
                'label' => '권한 설정',
                'href' => '/admin/pages/permissions',
                'children' => [
                    ['label' => '페이지 권한 설정', 'href' => '/admin/pages/permissions'],
                    ['label' => '게시판 권한 설정', 'href' => '/admin/boards/permissions'],
                ],
            ],
            ['label' => '상벌점 기준 관리', 'href' => '/admin/point-rules'],
            ['label' => '관별 자치활동 관리', 'href' => '/admin/hall-activities'],
            ['label' => '상벌점 초기화', 'href' => '/admin/points/reset'],
            ['label' => '삼경몰 관리', 'href' => '/admin/mall'],
        ];
        if (is_superadmin_account()) {
            $groups['시스템 관리'][] = ['label' => '상벌점 리스트 관리', 'href' => '/admin/point-list-rules'];
        }
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
            foreach ($item['children'] ?? [] as $child) {
                $childPath = parse_url($child['href'], PHP_URL_PATH) ?: $child['href'];
                if ($path === $childPath || str_starts_with($path, $childPath . '/')) {
                    return $group;
                }
            }
        }
    }

    return '학교소개';
}

function page_permission_definitions(): array
{
    return [
        'school-rules' => [
            'label' => '학교규칙',
            'path' => '/rules',
            'default_read_roles' => ['student', 'council', 'admin'],
        ],
        'student-life-rules' => [
            'label' => '학교생활규정',
            'path' => '/rules/life',
            'default_read_roles' => ['student', 'council', 'admin'],
        ],
        'point-rules' => [
            'label' => '상벌점 리스트',
            'path' => '/rules/points',
            'default_read_roles' => ['student', 'council', 'admin'],
        ],
        'discipline-awards' => [
            'label' => '징계 및 포상',
            'path' => '/rules/discipline',
            'default_read_roles' => ['student', 'council', 'admin'],
        ],
        'student-halls' => [
            'label' => '관별 현황',
            'path' => '/student-halls',
            'default_read_roles' => [],
        ],
        'hall-activities' => [
            'label' => '관별 자치활동',
            'path' => '/hall-activities',
            'default_read_roles' => [],
        ],
        'samgyeong-mall' => [
            'label' => '삼경몰',
            'path' => '/samgyeong-mall',
            'default_read_roles' => ['admin'],
        ],
        'council-intro' => [
            'label' => '삼경원 소개',
            'path' => '/council',
            'default_read_roles' => [],
        ],
        'calendar' => [
            'label' => '삼경원 일정',
            'path' => '/calendar',
            'default_read_roles' => ['council', 'admin'],
        ],
        'points-assign' => [
            'label' => '상벌점 부여',
            'path' => '/points/assign',
            'default_read_roles' => ['council', 'admin'],
        ],
    ];
}

function page_permission_presets(): array
{
    return [
        'public' => ['label' => '전체 공개', 'roles' => []],
        'login' => ['label' => '로그인 사용자', 'roles' => ['guest', 'student', 'council', 'admin']],
        'student' => ['label' => '재학생 이상', 'roles' => ['student', 'council', 'admin']],
        'council' => ['label' => '삼경원 이상', 'roles' => ['council', 'admin']],
        'admin' => ['label' => '관리자만', 'roles' => ['admin']],
    ];
}

function page_permission_preset_from_roles(array $roles): string
{
    $allowed = ['guest', 'student', 'council', 'admin'];
    $roles = array_values(array_intersect($allowed, $roles));
    sort($roles);
    $key = implode(',', $roles);

    return match ($key) {
        '' => 'public',
        'admin' => 'admin',
        'admin,council' => 'council',
        'admin,council,student' => 'student',
        'admin,council,guest,student' => 'login',
        default => 'student',
    };
}

function page_read_roles(PDO $db, string $pageKey): array
{
    $definitions = page_permission_definitions();
    if (!isset($definitions[$pageKey])) {
        return ['admin'];
    }

    $roles = $definitions[$pageKey]['default_read_roles'];
    $stmt = $db->prepare('SELECT read_roles FROM page_permissions WHERE page_key = ?');
    $stmt->execute([$pageKey]);
    $rawRoles = $stmt->fetchColumn();
    if ($rawRoles !== false) {
        $decoded = json_decode((string) $rawRoles, true);
        if (is_array($decoded)) {
            $roles = array_values(array_intersect($decoded, ['guest', 'student', 'council', 'admin']));
        }
    }

    return $roles;
}

function can_read_page(PDO $db, string $pageKey, ?array $user = null): bool
{
    $roles = page_read_roles($db, $pageKey);
    if ($roles === []) {
        return true;
    }

    $user ??= current_user();
    return $user !== null && in_array($user['role'] ?? '', $roles, true);
}

function page_access_denied_message(array $roles): string
{
    return match (page_permission_preset_from_roles($roles)) {
        'login' => '로그인 후 접근이 가능한 메뉴입니다.',
        'student' => '재학생 이상 로그인 후 접근이 가능한 메뉴입니다.',
        'council' => '삼경원 및 관리자만 접근이 가능한 메뉴입니다.',
        'admin' => '관리자만 접근이 가능한 메뉴입니다.',
        default => '접근 권한이 없습니다.',
    };
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
        $sections[$key]['key'] = $key;
        $sections[$key]['items'] = [];
    }

    foreach ($rules as $rule) {
        $category = $rule['category'] ?? '';
        if (!isset($sections[$category])) {
            continue;
        }

        $sections[$category]['items'][] = [
            'id' => (int) ($rule['id'] ?? 0),
            'category' => $category,
            'score' => (string) ($rule['score_label'] ?? ''),
            'text' => (string) ($rule['rule_text'] ?? ''),
            'emphasis' => (int) ($rule['is_emphasis'] ?? 0) === 1,
            'sort_order' => (int) ($rule['sort_order'] ?? 0),
        ];
    }

    return array_values($sections);
}

function point_list_rule_categories(): array
{
    return [
        'demerit' => [
            'title' => '벌점 항목',
            'type_label' => '벌점',
            'type_class' => 'bad',
            'description' => '상벌점 리스트의 벌점 표에 표시됩니다.',
        ],
        'merit' => [
            'title' => '상점 항목',
            'type_label' => '상점',
            'type_class' => 'good',
            'description' => '상벌점 리스트의 상점 표에 표시됩니다.',
        ],
        'submit' => [
            'title' => '상점 제출 절차',
            'type_label' => '',
            'type_class' => '',
            'description' => '상점 제출 절차의 번호 목록에 표시됩니다.',
        ],
    ];
}

function build_point_list_sections(array $rules): array
{
    $sections = point_list_rule_categories();
    foreach ($sections as $key => $section) {
        $sections[$key]['key'] = $key;
        $sections[$key]['items'] = [];
    }

    foreach ($rules as $rule) {
        $category = $rule['category'] ?? '';
        if (!isset($sections[$category])) {
            continue;
        }

        $sections[$category]['items'][] = [
            'id' => (int) ($rule['id'] ?? 0),
            'category' => $category,
            'score' => (string) ($rule['score_label'] ?? ''),
            'text' => (string) ($rule['rule_text'] ?? ''),
            'sort_order' => (int) ($rule['sort_order'] ?? 0),
        ];
    }

    return array_values($sections);
}

function current_point_reset_at(PDO $db): ?string
{
    $value = $db->query('SELECT created_at FROM point_resets ORDER BY id DESC LIMIT 1')->fetchColumn();
    return $value !== false ? (string) $value : null;
}

function point_reset_condition(?string $resetAt, string $tableAlias = 'point_records'): string
{
    return $resetAt ? " AND {$tableAlias}.created_at > :reset_at" : '';
}

function user_point_totals(PDO $db, int $userId): array
{
    $resetAt = current_point_reset_at($db);
    $sql = "
        SELECT
            COALESCE(SUM(CASE WHEN type = 'merit' THEN points ELSE 0 END), 0) AS merit_total,
            COALESCE(SUM(CASE WHEN type = 'demerit' THEN points ELSE 0 END), 0) AS demerit_total
        FROM point_records
        WHERE user_id = :user_id
          AND canceled_at IS NULL
          AND cancellation_of_id IS NULL
    " . point_reset_condition($resetAt);

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($resetAt) {
        $stmt->bindValue(':reset_at', $resetAt);
    }
    $stmt->execute();
    $row = $stmt->fetch() ?: [];

    return [
        'merit_total' => (int) ($row['merit_total'] ?? 0),
        'demerit_total' => (int) ($row['demerit_total'] ?? 0),
        'reset_at' => $resetAt,
    ];
}

function user_mall_spent(PDO $db, int $userId): int
{
    $resetAt = current_point_reset_at($db);
    $sql = 'SELECT COALESCE(SUM(total_price), 0) FROM mall_orders WHERE user_id = :user_id';
    if ($resetAt) {
        $sql .= ' AND created_at > :reset_at';
    }

    $stmt = $db->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($resetAt) {
        $stmt->bindValue(':reset_at', $resetAt);
    }
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function user_mall_available_points(PDO $db, int $userId): array
{
    $totals = user_point_totals($db, $userId);
    $spent = user_mall_spent($db, $userId);
    $available = max(0, $totals['merit_total'] - $spent);

    return $totals + [
        'spent_total' => $spent,
        'available_total' => $available,
    ];
}

function user_mall_orders(PDO $db, int $userId): array
{
    $stmt = $db->prepare('
        SELECT mall_orders.*, manager.display_name AS used_by_name, manager.username AS used_by_username
        FROM mall_orders
        LEFT JOIN users AS manager ON manager.id = mall_orders.used_by
        WHERE mall_orders.user_id = ?
        ORDER BY mall_orders.created_at DESC, mall_orders.id DESC
    ');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function user_mall_order_quantity(array $orders): int
{
    return array_reduce($orders, fn (int $total, array $order): int => $total + (int) ($order['quantity'] ?? 0), 0);
}

function mall_student_open(PDO $db): bool
{
    $stmt = $db->prepare('SELECT value FROM mall_settings WHERE key = ?');
    $stmt->execute(['student_open']);
    return (string) ($stmt->fetchColumn() ?: '0') === '1';
}

function can_access_mall(PDO $db, ?array $user = null): bool
{
    return can_read_page($db, 'samgyeong-mall', $user);
}
