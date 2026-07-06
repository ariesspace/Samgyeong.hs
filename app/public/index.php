<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$db = Database::connect();
$auth = new Auth($db);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function require_mypage_access(Auth $auth): array
{
    $user = $auth->user();
    if (!$user) {
        redirect('/login');
    }

    if (($user['role'] ?? '') === 'guest') {
        http_response_code(403);
        echo view('access-denied', [
            'title' => '권한 없음',
            'message' => '게스트 계정은 마이페이지 기능을 사용할 수 없습니다.',
        ]);
        exit;
    }

    return $user;
}

function require_page_read_access(PDO $db, string $pageKey): ?string
{
    $roles = page_read_roles($db, $pageKey);
    if (can_read_page($db, $pageKey)) {
        return null;
    }

    return view('access-denied', [
        'title' => '권한 없음',
        'message' => page_access_denied_message($roles),
    ]);
}

function mall_cart(): array
{
    $cart = $_SESSION['mall_cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

function save_mall_cart(array $cart): void
{
    $_SESSION['mall_cart'] = array_filter(
        array_map('intval', $cart),
        fn (int $quantity): bool => $quantity > 0
    );
}

function mall_cart_details(PDO $db): array
{
    $cart = mall_cart();
    if ($cart === []) {
        return ['items' => [], 'total' => 0];
    }

    $ids = array_values(array_filter(array_map('intval', array_keys($cart))));
    if ($ids === []) {
        return ['items' => [], 'total' => 0];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM mall_items WHERE active = 1 AND id IN ($placeholders) ORDER BY sort_order, id");
    $stmt->execute($ids);
    $items = [];
    $total = 0;

    foreach ($stmt->fetchAll() as $item) {
        $quantity = max(1, (int) ($cart[(string) $item['id']] ?? 0));
        $lineTotal = (int) $item['price'] * $quantity;
        $items[] = $item + ['quantity' => $quantity, 'line_total' => $lineTotal];
        $total += $lineTotal;
    }

    return ['items' => $items, 'total' => $total];
}

if (str_starts_with($path, '/api/')) {
    require_once __DIR__ . '/../src/Api.php';
    samgyeong_api_handle_request($db, $path, $method);
}


if ($method === 'POST') {
    verify_csrf();
}

if ($path === '/login' && $method === 'POST') {
    echo $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '');
    exit;
}

if ($path === '/editor/image-upload' && $method === 'POST') {
    if (!$auth->user()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => '로그인이 필요합니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploaded = save_editor_image('image');
    header('Content-Type: application/json; charset=utf-8');
    if (!$uploaded) {
        http_response_code(400);
        echo json_encode(['error' => 'jpg, png, webp, gif 이미지만 업로드할 수 있습니다.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['url' => '/uploads/' . $uploaded], JSON_UNESCAPED_UNICODE);
    exit;
}

$routes = [
    '/' => function () use ($db) {
        $homeBoards = [];
        foreach (Board::all($db) as $slug => $board) {
            if ($slug === 'notice') {
                continue;
            }

            $stmt = $db->prepare("
                SELECT id, title, tag, created_at
                FROM posts
                WHERE board = ?
                ORDER BY CASE WHEN tag = '공지' THEN 0 ELSE 1 END, id DESC
                LIMIT 4
            ");
            $stmt->execute([$slug]);
            $homeBoards[] = ['slug' => $slug] + $board + ['items' => $stmt->fetchAll()];
        }

        return view('home', ['title' => '삼경고', 'boards' => $homeBoards]);
    },
    '/about' => fn () => view('about', ['title' => '학교소개 및 교훈']),
    '/symbols' => fn () => view('symbols', ['title' => '학교 상징']),
    '/pledge' => fn () => view('pledge', ['title' => '삼경인 선서문']),
    '/history' => fn () => view('history', ['title' => '학교 연혁']),
    '/location' => fn () => view('location', ['title' => '오시는 길']),
    '/admissions' => fn () => view('admissions', ['title' => '모집요강']),
    '/rules' => function () use ($db) {
        if ($denied = require_page_read_access($db, 'school-rules')) {
            return $denied;
        }
        return view('school-rules', ['title' => '학교규칙']);
    },
    '/rules/life' => function () use ($db) {
        if ($denied = require_page_read_access($db, 'student-life-rules')) {
            return $denied;
        }
        return view('student-life-rules', ['title' => '학교생활규정']);
    },
    '/rules/points' => function () use ($db) {
        if ($denied = require_page_read_access($db, 'point-rules')) {
            return $denied;
        }
        $rules = $db->query('SELECT * FROM point_list_rules ORDER BY category, sort_order, id')->fetchAll();
        return view('point-rules', [
            'title' => '상벌점 리스트',
            'sections' => build_point_list_sections($rules),
        ]);
    },
    '/rules/discipline' => function () use ($db) {
        if ($denied = require_page_read_access($db, 'discipline-awards')) {
            return $denied;
        }
        $rules = $db->query('SELECT * FROM point_rules ORDER BY category, sort_order, id')->fetchAll();
        $activeTab = $_GET['tab'] ?? 'penalty';
        if (!in_array($activeTab, ['penalty', 'reward', 'rule'], true)) {
            $activeTab = 'penalty';
        }
        return view('discipline-awards', [
            'title' => '징계 및 포상',
            'sections' => build_point_rule_sections($rules),
            'activeTab' => $activeTab,
        ]);
    },
    '/student-halls' => function () use ($db) {
        if ($denied = require_page_read_access($db, 'student-halls')) {
            return $denied;
        }
        $rows = $db->query('SELECT * FROM hall_members ORDER BY hall_key, sort_order, id')->fetchAll();
        $halls = hall_definitions();
        $selectedHall = $_GET['hall'] ?? '';
        return view('student-halls', [
            'title' => '관별 현황',
            'members' => $rows,
            'selectedHall' => isset($halls[$selectedHall]) ? $selectedHall : '',
        ]);
    },
    '/hall-activities' => function () use ($db) {
        if ($denied = require_page_read_access($db, 'hall-activities')) {
            return $denied;
        }
        $activities = $db->query('SELECT * FROM hall_activities ORDER BY sort_order, id')->fetchAll();
        return view('hall-activities', [
            'title' => '관별 자치활동',
            'activities' => $activities,
        ]);
    },
    '/samgyeong-mall' => function () use ($auth, $db) {
        $user = $auth->user();
        if (!can_access_mall($db, $user)) {
            return view('access-denied', [
                'title' => '삼경몰',
                'message' => page_access_denied_message(page_read_roles($db, 'samgyeong-mall')),
            ]);
        }

        $items = $db->query('SELECT * FROM mall_items WHERE active = 1 ORDER BY sort_order, id')->fetchAll();

        return view('mall', [
            'title' => '삼경몰',
            'items' => $items,
            'cart' => mall_cart_details($db),
            'points' => $user ? user_mall_available_points($db, (int) $user['id']) : [],
            'saved' => $_GET['saved'] ?? '',
            'error' => $_GET['error'] ?? '',
        ]);
    },
    '/council' => function () use ($db) {
        if ($denied = require_page_read_access($db, 'council-intro')) {
            return $denied;
        }
        return view('council', ['title' => '삼경원 소개']);
    },
    '/calendar' => function () use ($db) {
        if ($denied = require_page_read_access($db, 'calendar')) {
            return $denied;
        }
        $month = preg_match('/^\d{4}-\d{2}$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
        $stmt = $db->prepare('
            SELECT calendar_events.*, users.username
            FROM calendar_events
            JOIN users ON users.id = calendar_events.author_id
            WHERE event_date BETWEEN ? AND ?
            ORDER BY event_date ASC, id ASC
        ');
        $stmt->execute([$month . '-01', date('Y-m-t', strtotime($month . '-01'))]);

        return view('calendar', [
            'title' => '일정 캘린더',
            'month' => $month,
            'events' => $stmt->fetchAll(),
        ]);
    },
    '/login' => fn () => $auth->loginPage(),
    '/logout' => fn () => $auth->logout(),
];

if (isset($routes[$path])) {
    echo $routes[$path]();
    exit;
}

if ($path === '/samgyeong-mall/cart/add' && $method === 'POST') {
    $user = $auth->user();
    if (!can_access_mall($db, $user)) {
        redirect('/samgyeong-mall');
    }

    $itemId = (int) ($_POST['item_id'] ?? 0);
    $stmt = $db->prepare('SELECT id FROM mall_items WHERE id = ? AND active = 1');
    $stmt->execute([$itemId]);
    if ($stmt->fetchColumn()) {
        $cart = mall_cart();
        $cart[(string) $itemId] = min(9, (int) ($cart[(string) $itemId] ?? 0) + 1);
        save_mall_cart($cart);
    }

    redirect('/samgyeong-mall?saved=cart');
}

if ($path === '/samgyeong-mall/cart/remove' && $method === 'POST') {
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $cart = mall_cart();
    unset($cart[(string) $itemId]);
    save_mall_cart($cart);

    redirect('/samgyeong-mall?saved=cart');
}

if ($path === '/samgyeong-mall/cart/update' && $method === 'POST') {
    $user = $auth->user();
    if (!can_access_mall($db, $user)) {
        redirect('/samgyeong-mall');
    }

    $itemId = (int) ($_POST['item_id'] ?? 0);
    $delta = (int) ($_POST['delta'] ?? 0);
    $cart = mall_cart();
    $current = (int) ($cart[(string) $itemId] ?? 0);
    $next = max(0, min(9, $current + $delta));
    if ($next === 0) {
        unset($cart[(string) $itemId]);
    } else {
        $cart[(string) $itemId] = $next;
    }
    save_mall_cart($cart);

    redirect('/samgyeong-mall?saved=cart');
}

if ($path === '/samgyeong-mall/checkout' && $method === 'POST') {
    $user = $auth->user();
    if (!can_access_mall($db, $user)) {
        redirect('/samgyeong-mall');
    }

    $cart = mall_cart_details($db);
    if (!$user || $cart['items'] === []) {
        redirect('/samgyeong-mall?error=empty');
    }

    $points = user_mall_available_points($db, (int) $user['id']);
    if ((int) $cart['total'] > (int) $points['available_total']) {
        redirect('/samgyeong-mall?error=points');
    }

    $db->beginTransaction();
    try {
        $stmt = $db->prepare('
            INSERT INTO mall_orders (user_id, item_id, item_name, price, quantity, total_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        foreach ($cart['items'] as $item) {
            $stmt->execute([
                (int) $user['id'],
                (int) $item['id'],
                (string) $item['name'],
                (int) $item['price'],
                (int) $item['quantity'],
                (int) $item['line_total'],
            ]);
        }
        $db->commit();
        save_mall_cart([]);
        redirect('/samgyeong-mall?saved=checkout');
    } catch (Throwable $exception) {
        $db->rollBack();
        redirect('/samgyeong-mall?error=checkout');
    }
}

if ($path === '/admin/points/reset') {
    $auth->requireRole(['admin']);
    $resetAt = current_point_reset_at($db);
    $activeUsers = $db->query("SELECT COUNT(*) FROM users WHERE role IN ('student', 'council')")->fetchColumn();
    $recordCount = $db->query('SELECT COUNT(*) FROM point_records')->fetchColumn();
    echo view('admin-points-reset', [
        'title' => '상벌점 초기화',
        'resetAt' => $resetAt,
        'activeUsers' => (int) $activeUsers,
        'recordCount' => (int) $recordCount,
        'saved' => ($_GET['saved'] ?? '') === '1',
    ]);
    exit;
}

if ($path === '/admin/points/reset/store' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $note = trim((string) ($_POST['note'] ?? ''));
    $stmt = $db->prepare('INSERT INTO point_resets (reset_by, note) VALUES (?, ?)');
    $stmt->execute([(int) $auth->user()['id'], $note]);

    redirect('/admin/points/reset?saved=1');
}

if ($path === '/admin/mall') {
    $auth->requireRole(['admin']);
    echo view('admin-mall', [
        'title' => '삼경몰 관리',
        'items' => $db->query('SELECT * FROM mall_items ORDER BY sort_order, id')->fetchAll(),
        'saved' => ($_GET['saved'] ?? ''),
    ]);
    exit;
}

if ($path === '/admin/mall/items' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $ids = $_POST['id'] ?? [];
    $names = $_POST['name'] ?? [];
    $descriptions = $_POST['description'] ?? [];
    $prices = $_POST['price'] ?? [];
    $activeIds = array_map('intval', $_POST['active'] ?? []);

    if (is_array($ids) && is_array($names) && is_array($descriptions) && is_array($prices)) {
        $stmt = $db->prepare('
            UPDATE mall_items
            SET name = ?, description = ?, price = ?, active = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');
        $count = min(count($ids), count($names), count($descriptions), count($prices));
        for ($i = 0; $i < $count; $i++) {
            $id = (int) $ids[$i];
            $name = trim((string) $names[$i]);
            $description = trim((string) $descriptions[$i]);
            $price = max(1, (int) $prices[$i]);
            if ($id > 0 && $name !== '' && $description !== '') {
                $stmt->execute([$name, $description, $price, in_array($id, $activeIds, true) ? 1 : 0, ($i + 1) * 10, $id]);
            }
        }
    }

    redirect('/admin/mall?saved=items');
}

if ($path === '/admin/mall/items/add' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = max(1, (int) ($_POST['price'] ?? 0));
    if ($name !== '' && $description !== '') {
        $sortOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM mall_items')->fetchColumn();
        $stmt = $db->prepare('INSERT INTO mall_items (name, description, price, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $description, $price, $sortOrder]);
    }

    redirect('/admin/mall?saved=items');
}

if ($path === '/admin/users') {
    $auth->requireRole(['admin']);
    $users = $db->query('SELECT id, username, role, display_name, hall_key, year, photo_path, created_at FROM users ORDER BY id ASC')->fetchAll();
    echo view('admin-users', [
        'title' => '계정 권한 관리',
        'users' => $users,
        'saved' => $_GET['saved'] ?? '',
    ]);
    exit;
}

if ($path === '/admin/users/create' && $method === 'GET') {
    $auth->requireRole(['admin']);
    echo view('admin-user-create', ['title' => '계정 생성']);
    exit;
}

if ($path === '/admin/users/edit' && $method === 'GET') {
    $auth->requireRole(['admin']);
    $userId = (int) ($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT id, username, role, display_name, hall_key, year, photo_path, created_at FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user || (int) $user['id'] === 1 || (int) $user['id'] === (int) ($auth->user()['id'] ?? 0)) {
        redirect('/admin/users');
    }
    $stmt = $db->prepare('SELECT role_label FROM hall_members WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user['role_label'] = $stmt->fetchColumn() ?: '';

    echo view('admin-user-edit', [
        'title' => '계정 정보 수정',
        'account' => $user,
        'saved' => $_GET['saved'] ?? '',
        'error' => $_GET['error'] ?? '',
    ]);
    exit;
}

if ($path === '/admin/users/create' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';
    $displayName = trim($_POST['display_name'] ?? '');
    $hallKey = $_POST['hall_key'] ?? '';
    $year = max(0, min(3, (int) ($_POST['year'] ?? 0)));

    if ($username !== '' && $password !== '' && in_array($role, ['guest', 'student', 'council', 'admin'], true)) {
        if ($role === 'admin' || $role === 'guest') {
            $hallKey = '';
            $year = 0;
        }
        $stmt = $db->prepare('INSERT OR IGNORE INTO users (username, password_hash, role, display_name, hall_key, year) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $role, $displayName !== '' ? $displayName : $username, $hallKey, $year]);
        if ($stmt->rowCount() > 0) {
            sync_user_hall_member($db, (int) $db->lastInsertId());
        }
    }

    redirect('/admin/users');
}

if ($path === '/admin/users/update' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $role = $_POST['role'] ?? '';

    if ($userId > 1 && $userId !== (int) ($auth->user()['id'] ?? 0) && in_array($role, ['guest', 'student', 'council', 'admin'], true)) {
        $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $userId]);
        sync_user_hall_member($db, $userId);
    }

    redirect('/admin/users');
}

if ($path === '/admin/users/profile' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $displayName = trim($_POST['display_name'] ?? '');
    $role = $_POST['role'] ?? '';
    $hallKey = $_POST['hall_key'] ?? '';
    $year = max(0, min(3, (int) ($_POST['year'] ?? 0)));
    $roleLabel = trim($_POST['role_label'] ?? '');

    if ($userId > 1 && $userId !== (int) ($auth->user()['id'] ?? 0) && $displayName !== '' && in_array($role, ['guest', 'student', 'council', 'admin'], true)) {
        $stmt = $db->prepare('SELECT id, photo_path FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $account = $stmt->fetch();
        if ($role === 'admin' || $role === 'guest') {
            $hallKey = '';
            $year = 0;
        }
        if ($account) {
            $photoPath = $account['photo_path'] ?: null;
            $hasPhotoUpload = isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $uploadedPhoto = save_hall_photo('photo');
            if ($hasPhotoUpload && !$uploadedPhoto) {
                redirect('/admin/users/edit?id=' . $userId . '&error=' . urlencode('사진 업로드에 실패했습니다. jpg, png, webp, gif 파일인지 확인해 주세요.'));
            }
            if ($uploadedPhoto) {
                delete_upload($photoPath);
                $photoPath = $uploadedPhoto;
            }

            $stmt = $db->prepare('UPDATE users SET display_name = ?, hall_key = ?, year = ?, role = ?, photo_path = ? WHERE id = ?');
            $stmt->execute([$displayName, $hallKey, $year, $role, $photoPath, $userId]);
            sync_user_hall_member($db, $userId);
            if ($role !== 'admin' && $role !== 'guest') {
                $stmt = $db->prepare('UPDATE hall_members SET role_label = ? WHERE user_id = ?');
                $stmt->execute([$roleLabel, $userId]);
            }
        }
    }

    redirect('/admin/users/edit?id=' . $userId . '&saved=profile');
}

if ($path === '/admin/users/reset-password' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $redirectTo = $_POST['redirect_to'] ?? '/admin/users';
    if (!is_string($redirectTo) || !str_starts_with($redirectTo, '/admin/users')) {
        $redirectTo = '/admin/users';
    }

    if ($userId > 1 && $userId !== (int) ($auth->user()['id'] ?? 0)) {
        $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash('samgyeong1234', PASSWORD_DEFAULT), $userId]);
    }

    redirect($redirectTo);
}

if ($path === '/admin/users/delete' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $userId = (int) ($_POST['user_id'] ?? 0);

    if ($userId > 1 && $userId !== (int) ($auth->user()['id'] ?? 0)) {
        $stmt = $db->prepare('SELECT username, display_name, hall_key, year, photo_path FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user || ($user['username'] ?? '') === 'guest') {
            redirect('/admin/users');
        }

        delete_upload($user['photo_path'] ?? null);

        $stmt = $db->prepare('DELETE FROM hall_members WHERE user_id = ?');
        $stmt->execute([$userId]);

        if ($user && trim((string) $user['display_name']) !== '' && ($user['hall_key'] ?? '') !== '' && (int) ($user['year'] ?? 0) > 0) {
            $stmt = $db->prepare('
                DELETE FROM hall_members
                WHERE (user_id IS NULL OR user_id = 0)
                  AND student_name = ?
                  AND hall_key = ?
                  AND year = ?
            ');
            $stmt->execute([
                trim((string) $user['display_name']),
                $user['hall_key'],
                (int) $user['year'],
            ]);
        }

        $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
    }

    redirect('/admin/users?saved=deleted');
}

if ($path === '/admin/pages/permissions') {
    $auth->requireRole(['admin']);
    echo view('admin-page-permissions', [
        'title' => '페이지 권한 설정',
        'pages' => page_permission_definitions(),
        'db' => $db,
        'saved' => ($_GET['saved'] ?? '') === '1',
    ]);
    exit;
}

if ($path === '/admin/pages/permissions/save' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $presets = page_permission_presets();
    $pages = page_permission_definitions();
    $readPresets = $_POST['read_preset'] ?? [];

    $stmt = $db->prepare('
        INSERT INTO page_permissions (page_key, read_roles, updated_at)
        VALUES (?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(page_key) DO UPDATE SET
            read_roles = excluded.read_roles,
            updated_at = CURRENT_TIMESTAMP
    ');

    foreach ($pages as $key => $page) {
        $preset = is_array($readPresets) ? (string) ($readPresets[$key] ?? 'student') : 'student';
        $roles = $presets[$preset]['roles'] ?? $page['default_read_roles'];
        $stmt->execute([
            $key,
            json_encode($roles, JSON_UNESCAPED_UNICODE),
        ]);
    }

    redirect('/admin/pages/permissions?saved=1');
}

if ($path === '/admin/boards/permissions') {
    $auth->requireRole(['admin']);
    echo view('admin-board-permissions', [
        'title' => '게시판 권한 설정',
        'boards' => Board::all($db),
        'roles' => Board::roleOptions(),
        'saved' => ($_GET['saved'] ?? '') === '1',
    ]);
    exit;
}

if ($path === '/admin/boards/permissions/save' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $readPresets = [
        'public' => [],
        'student' => ['student', 'council', 'admin'],
        'council' => ['council', 'admin'],
        'admin' => ['admin'],
    ];
    $writePresets = [
        'none' => [],
        'student' => ['student', 'council', 'admin'],
        'council' => ['council', 'admin'],
        'admin' => ['admin'],
    ];
    $boards = Board::all($db);
    $stmt = $db->prepare('
        INSERT INTO board_permissions (board_slug, read_roles, write_roles, updated_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT(board_slug) DO UPDATE SET
            read_roles = excluded.read_roles,
            write_roles = excluded.write_roles,
            updated_at = CURRENT_TIMESTAMP
    ');

    foreach ($boards as $slug => $board) {
        $readPreset = $_POST['read_preset'][$slug] ?? 'public';
        $writePreset = $_POST['write_preset'][$slug] ?? 'none';
        $readRoles = $readPresets[$readPreset] ?? $readPresets['public'];
        $writeRoles = $writePresets[$writePreset] ?? $writePresets['none'];

        $stmt->execute([
            $slug,
            json_encode($readRoles, JSON_UNESCAPED_UNICODE),
            json_encode($writeRoles, JSON_UNESCAPED_UNICODE),
        ]);
    }

    redirect('/admin/boards/permissions?saved=1');
}

if ($path === '/admin/point-rules') {
    $auth->requireRole(['admin']);
    $rules = $db->query('SELECT * FROM point_rules ORDER BY category, sort_order, id')->fetchAll();
    echo view('admin-point-rules', [
        'title' => '상벌점 기준 관리',
        'sections' => build_point_rule_sections($rules),
        'saved' => ($_GET['saved'] ?? '') === '1',
        'deleted' => ($_GET['deleted'] ?? '') === '1',
    ]);
    exit;
}

if ($path === '/admin/point-rules/save' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $ids = $_POST['id'] ?? [];
    $categories = $_POST['category'] ?? [];
    $scoreLabels = $_POST['score_label'] ?? [];
    $ruleTexts = $_POST['rule_text'] ?? [];
    $emphasisIds = $_POST['is_emphasis'] ?? [];

    if (is_array($ids) && is_array($categories) && is_array($scoreLabels) && is_array($ruleTexts)) {
        $checkedIds = is_array($emphasisIds) ? array_map('intval', $emphasisIds) : [];
        $sortCounters = [];
        $stmt = $db->prepare('
            UPDATE point_rules
            SET score_label = ?, rule_text = ?, is_emphasis = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        $count = min(count($ids), count($categories), count($scoreLabels), count($ruleTexts));
        for ($i = 0; $i < $count; $i++) {
            $id = (int) $ids[$i];
            $category = (string) $categories[$i];
            $scoreLabel = trim((string) $scoreLabels[$i]);
            $ruleText = trim((string) $ruleTexts[$i]);
            if (!isset(point_rule_categories()[$category])) {
                continue;
            }

            $sortCounters[$category] = ($sortCounters[$category] ?? 0) + 10;
            $sortOrder = $sortCounters[$category];
            if ($id > 0 && $scoreLabel !== '' && $ruleText !== '') {
                $stmt->execute([$scoreLabel, $ruleText, in_array($id, $checkedIds, true) ? 1 : 0, $sortOrder, $id]);
            }
        }
    }

    redirect('/admin/point-rules?saved=1');
}

if ($path === '/admin/point-rules/add' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $category = $_POST['category'] ?? '';
    $scoreLabel = trim((string) ($_POST['score_label'] ?? ''));
    $ruleText = trim((string) ($_POST['rule_text'] ?? ''));
    $isEmphasis = isset($_POST['is_emphasis']) ? 1 : 0;

    if (isset(point_rule_categories()[$category]) && $scoreLabel !== '' && $ruleText !== '') {
        $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM point_rules WHERE category = ?');
        $stmt->execute([$category]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('
            INSERT INTO point_rules (category, score_label, rule_text, is_emphasis, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$category, $scoreLabel, $ruleText, $isEmphasis, $sortOrder]);
    }

    redirect('/admin/point-rules?saved=1');
}

if ($path === '/admin/point-rules/delete' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM point_rules WHERE id = ?');
        $stmt->execute([$id]);
    }

    redirect('/admin/point-rules?deleted=1');
}

if ($path === '/admin/point-list-rules') {
    require_superadmin($auth);
    $rules = $db->query('SELECT * FROM point_list_rules ORDER BY category, sort_order, id')->fetchAll();
    echo view('admin-point-list-rules', [
        'title' => '상벌점 리스트 관리',
        'sections' => build_point_list_sections($rules),
        'saved' => ($_GET['saved'] ?? '') === '1',
        'deleted' => ($_GET['deleted'] ?? '') === '1',
    ]);
    exit;
}

if ($path === '/admin/point-list-rules/save' && $method === 'POST') {
    require_superadmin($auth);
    $ids = $_POST['id'] ?? [];
    $categories = $_POST['category'] ?? [];
    $scoreLabels = $_POST['score_label'] ?? [];
    $ruleTexts = $_POST['rule_text'] ?? [];

    if (is_array($ids) && is_array($categories) && is_array($scoreLabels) && is_array($ruleTexts)) {
        $sortCounters = [];
        $stmt = $db->prepare('
            UPDATE point_list_rules
            SET score_label = ?, rule_text = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');

        $count = min(count($ids), count($categories), count($scoreLabels), count($ruleTexts));
        for ($i = 0; $i < $count; $i++) {
            $id = (int) $ids[$i];
            $category = (string) $categories[$i];
            $scoreLabel = trim((string) $scoreLabels[$i]);
            $ruleText = trim((string) $ruleTexts[$i]);
            if (!isset(point_list_rule_categories()[$category])) {
                continue;
            }
            if ($category !== 'submit' && $scoreLabel === '') {
                continue;
            }

            $sortCounters[$category] = ($sortCounters[$category] ?? 0) + 10;
            $sortOrder = $sortCounters[$category];
            if ($id > 0 && $ruleText !== '') {
                $stmt->execute([$scoreLabel, $ruleText, $sortOrder, $id]);
            }
        }
    }

    redirect('/admin/point-list-rules?saved=1');
}

if ($path === '/admin/point-list-rules/add' && $method === 'POST') {
    require_superadmin($auth);
    $category = $_POST['category'] ?? '';
    $scoreLabel = trim((string) ($_POST['score_label'] ?? ''));
    $ruleText = trim((string) ($_POST['rule_text'] ?? ''));

    if (isset(point_list_rule_categories()[$category]) && $ruleText !== '' && ($category === 'submit' || $scoreLabel !== '')) {
        $stmt = $db->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM point_list_rules WHERE category = ?');
        $stmt->execute([$category]);
        $sortOrder = (int) $stmt->fetchColumn();

        $stmt = $db->prepare('
            INSERT INTO point_list_rules (category, score_label, rule_text, sort_order)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([$category, $scoreLabel, $ruleText, $sortOrder]);
    }

    redirect('/admin/point-list-rules?saved=1');
}

if ($path === '/admin/point-list-rules/delete' && $method === 'POST') {
    require_superadmin($auth);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM point_list_rules WHERE id = ?');
        $stmt->execute([$id]);
    }

    redirect('/admin/point-list-rules?deleted=1');
}

if ($path === '/admin/hall-activities') {
    $auth->requireRole(['admin']);
    $activities = $db->query('SELECT * FROM hall_activities ORDER BY sort_order, id')->fetchAll();
    echo view('admin-hall-activities', [
        'title' => '관별 자치활동 관리',
        'activities' => $activities,
        'halls' => hall_definitions(),
        'saved' => ($_GET['saved'] ?? '') === '1',
        'deleted' => ($_GET['deleted'] ?? '') === '1',
    ]);
    exit;
}

if ($path === '/admin/hall-activities/save' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $ids = $_POST['id'] ?? [];
    $hallKeys = $_POST['hall_key'] ?? [];
    $titles = $_POST['title'] ?? [];
    $summaries = $_POST['summary'] ?? [];
    $methods = $_POST['method'] ?? [];
    $values = $_POST['value'] ?? [];
    $halls = hall_definitions();

    if (is_array($ids) && is_array($hallKeys) && is_array($titles) && is_array($summaries) && is_array($methods) && is_array($values)) {
        $stmt = $db->prepare('
            UPDATE hall_activities
            SET hall_key = ?, title = ?, summary = ?, method = ?, value = ?, sort_order = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ');
        $count = min(count($ids), count($hallKeys), count($titles), count($summaries), count($methods), count($values));
        for ($i = 0; $i < $count; $i++) {
            $id = (int) $ids[$i];
            $hallKey = (string) $hallKeys[$i];
            $title = trim((string) $titles[$i]);
            $summary = trim((string) $summaries[$i]);
            $methodText = trim((string) $methods[$i]);
            $valueText = trim((string) $values[$i]);

            if ($id <= 0 || !isset($halls[$hallKey]) || $title === '' || $summary === '' || $methodText === '' || $valueText === '') {
                continue;
            }

            $stmt->execute([$hallKey, $title, $summary, $methodText, $valueText, ($i + 1) * 10, $id]);
        }
    }

    redirect('/admin/hall-activities?saved=1');
}

if ($path === '/admin/hall-activities/add' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $hallKey = $_POST['hall_key'] ?? '';
    $title = trim((string) ($_POST['title'] ?? ''));
    $summary = trim((string) ($_POST['summary'] ?? ''));
    $methodText = trim((string) ($_POST['method'] ?? ''));
    $valueText = trim((string) ($_POST['value'] ?? ''));
    $halls = hall_definitions();

    if (isset($halls[$hallKey]) && $title !== '' && $summary !== '' && $methodText !== '' && $valueText !== '') {
        $sortOrder = (int) $db->query('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM hall_activities')->fetchColumn();
        $stmt = $db->prepare('
            INSERT INTO hall_activities (hall_key, title, summary, method, value, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$hallKey, $title, $summary, $methodText, $valueText, $sortOrder]);
    }

    redirect('/admin/hall-activities?saved=1');
}

if ($path === '/admin/hall-activities/delete' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM hall_activities WHERE id = ?');
        $stmt->execute([$id]);
    }

    redirect('/admin/hall-activities?deleted=1');
}

if ($path === '/mypage') {
    $mypageUser = require_mypage_access($auth);
    $stmt = $db->prepare('SELECT id, username, role, display_name, hall_key, year, photo_path FROM users WHERE id = ?');
    $stmt->execute([$mypageUser['id']]);
    echo view('mypage-profile', [
        'title' => '내 정보 수정',
        'profile' => $stmt->fetch(),
        'saved' => $_GET['saved'] ?? '',
        'error' => $_GET['error'] ?? '',
    ]);
    exit;
}

if ($path === '/mypage/profile' && $method === 'POST') {
    require_mypage_access($auth);

    $userId = (int) $auth->user()['id'];
    $hasPhotoUpload = !empty($_FILES['photo']['tmp_name']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

    if ($hasPhotoUpload) {
        $stmt = $db->prepare('SELECT photo_path FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $currentPhoto = $stmt->fetchColumn() ?: null;
        $uploadedPhoto = save_hall_photo('photo');

        if (!$uploadedPhoto) {
            redirect('/mypage?error=photo');
        }

        delete_upload($currentPhoto);
        $stmt = $db->prepare('UPDATE users SET photo_path = ? WHERE id = ?');
        $stmt->execute([$uploadedPhoto, $userId]);
        sync_user_hall_member($db, $userId);
        $_SESSION['user']['photo_path'] = $uploadedPhoto;
    }

    redirect('/mypage?saved=profile');
}

if ($path === '/mypage/photo' && $method === 'POST') {
    require_mypage_access($auth);

    $userId = (int) $auth->user()['id'];
    $stmt = $db->prepare('SELECT photo_path FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $currentPhoto = $stmt->fetchColumn() ?: null;
    $uploadedPhoto = save_hall_photo('photo');

    if ($uploadedPhoto) {
        delete_upload($currentPhoto);
        $stmt = $db->prepare('UPDATE users SET photo_path = ? WHERE id = ?');
        $stmt->execute([$uploadedPhoto, $userId]);
        sync_user_hall_member($db, $userId);

        $_SESSION['user']['photo_path'] = $uploadedPhoto;
        redirect('/mypage?saved=photo');
    }

    redirect('/mypage?error=photo');
}

if ($path === '/mypage/password' && $method === 'POST') {
    require_mypage_access($auth);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if ($password === '' || $password !== $confirm) {
        redirect('/mypage?error=password');
    }

    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $auth->user()['id']]);
    redirect('/mypage?saved=password');
}

if ($path === '/mypage/points') {
    require_mypage_access($auth);
    $userId = (int) $auth->user()['id'];
    $stmt = $db->prepare('
        SELECT point_records.*, issuer.display_name AS issuer_name, issuer.username AS issuer_username
        FROM point_records
        JOIN users AS issuer ON issuer.id = point_records.issuer_id
        WHERE point_records.user_id = ?
        ORDER BY point_records.issued_at DESC, point_records.id DESC
    ');
    $stmt->execute([$userId]);
    $records = $stmt->fetchAll();
    echo view('mypage-points', [
        'title' => '상벌점 현황',
        'records' => $records,
        'points' => user_mall_available_points($db, $userId),
    ]);
    exit;
}

if ($path === '/points/assign') {
    if ($denied = require_page_read_access($db, 'points-assign')) {
        echo $denied;
        exit;
    }
    $students = $db->query("
        SELECT id, username, display_name, hall_key, year, role
        FROM users
        WHERE role IN ('student', 'council')
        ORDER BY hall_key, year DESC, display_name, username
    ")->fetchAll();
    $records = $db->query('
        SELECT point_records.*, target.display_name AS target_name, target.username AS target_username,
               target.hall_key AS target_hall_key, target.year AS target_year,
               issuer.display_name AS issuer_name, issuer.username AS issuer_username
        FROM point_records
        JOIN users AS target ON target.id = point_records.user_id
        JOIN users AS issuer ON issuer.id = point_records.issuer_id
        ORDER BY point_records.issued_at DESC, point_records.id DESC
        LIMIT 30
    ')->fetchAll();
    echo view('points-assign', [
        'title' => '상벌점 부여',
        'students' => $students,
        'records' => $records,
        'saved' => $_GET['saved'] ?? '',
    ]);
    exit;
}

if ($path === '/points/assign/store' && $method === 'POST') {
    $auth->requireRole(['council', 'admin']);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $type = $_POST['type'] ?? '';
    $points = max(0, min(100, (int) ($_POST['points'] ?? 0)));
    $reason = trim($_POST['reason'] ?? '');
    $issuedAt = $_POST['issued_at'] ?? date('Y-m-d');

    if (
        $userId > 0
        && in_array($type, ['merit', 'demerit'], true)
        && $points > 0
        && $reason !== ''
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $issuedAt)
    ) {
        $stmt = $db->prepare("SELECT id FROM users WHERE id = ? AND role IN ('student', 'council')");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn()) {
            $stmt = $db->prepare('
                INSERT INTO point_records (user_id, type, points, reason, issuer_id, issued_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$userId, $type, $points, $reason, $auth->user()['id'], $issuedAt]);
        }
    }

    redirect('/points/assign?saved=1');
}

if ($path === '/points/assign/preview' && $method === 'POST') {
    $auth->requireRole(['council', 'admin']);
    $rawText = (string) ($_POST['raw_text'] ?? '');
    $defaultDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_POST['default_date'] ?? '') ? $_POST['default_date'] : date('Y-m-d');
    $students = $db->query("
        SELECT id, username, display_name, hall_key, year, role
        FROM users
        WHERE role IN ('student', 'council')
        ORDER BY hall_key, year DESC, display_name, username
    ")->fetchAll();
    [$parsed, $failed] = parse_point_bulk_text($rawText, $defaultDate, $students);

    echo view('points-bulk-preview', [
        'title' => '일괄 입력 미리보기',
        'parsed' => $parsed,
        'failed' => $failed,
    ]);
    exit;
}

if ($path === '/points/assign/bulk-save' && $method === 'POST') {
    $auth->requireRole(['council', 'admin']);
    $userIds = $_POST['user_id'] ?? [];
    $types = $_POST['type'] ?? [];
    $pointsList = $_POST['points'] ?? [];
    $reasons = $_POST['reason'] ?? [];
    $issuedDates = $_POST['issued_at'] ?? [];

    if (is_array($userIds) && is_array($types) && is_array($pointsList) && is_array($reasons) && is_array($issuedDates)) {
        $stmt = $db->prepare('
            INSERT INTO point_records (user_id, type, points, reason, issuer_id, issued_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $count = min(count($userIds), count($types), count($pointsList), count($reasons), count($issuedDates));
        for ($i = 0; $i < $count; $i++) {
            $userId = (int) $userIds[$i];
            $type = (string) $types[$i];
            $points = max(0, min(100, (int) $pointsList[$i]));
            $reason = trim((string) $reasons[$i]);
            $issuedAt = (string) $issuedDates[$i];
            if ($userId > 0 && in_array($type, ['merit', 'demerit'], true) && $points > 0 && $reason !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $issuedAt)) {
                $stmt->execute([$userId, $type, $points, $reason, $auth->user()['id'], $issuedAt]);
            }
        }
    }

    redirect('/points/assign?saved=1');
}

if ($path === '/points/assign/delete' && $method === 'POST') {
    $auth->requireRole(['council', 'admin']);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('SELECT * FROM point_records WHERE id = ?');
        $stmt->execute([$id]);
        $record = $stmt->fetch();

        if ($record && empty($record['canceled_at']) && empty($record['cancellation_of_id'])) {
            $cancelType = $record['type'] === 'merit' ? 'demerit' : 'merit';
            $cancelReason = ($record['type'] === 'merit' ? '상점 취소: ' : '벌점 취소: ') . (string) $record['reason'];

            $db->beginTransaction();
            try {
                $insert = $db->prepare('
                    INSERT INTO point_records (user_id, type, points, reason, issuer_id, issued_at, cancellation_of_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ');
                $insert->execute([
                    (int) $record['user_id'],
                    $cancelType,
                    (int) $record['points'],
                    $cancelReason,
                    (int) $auth->user()['id'],
                    date('Y-m-d'),
                    $id,
                ]);

                $update = $db->prepare('UPDATE point_records SET canceled_at = CURRENT_TIMESTAMP, canceled_by = ? WHERE id = ?');
                $update->execute([(int) $auth->user()['id'], $id]);

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }
        }
    }
    redirect('/points/assign?saved=canceled');
}

if ($path === '/admin/halls') {
    $auth->requireRole(['admin']);
    $members = $db->query('SELECT * FROM hall_members ORDER BY hall_key, sort_order, id')->fetchAll();
    echo view('admin-halls', ['title' => '관별 명단 관리', 'members' => $members]);
    exit;
}

if ($path === '/admin/halls/edit' && $method === 'GET') {
    $auth->requireRole(['admin']);
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM hall_members WHERE id = ?');
    $stmt->execute([$id]);
    $member = $stmt->fetch();
    if (!$member) {
        redirect('/admin/halls');
    }

    echo view('admin-hall-edit', ['title' => '관별 명단 수정', 'member' => $member]);
    exit;
}

if ($path === '/admin/halls/save' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $halls = hall_definitions();
    $ids = $_POST['id'] ?? [];
    $stmt = $db->prepare('
        UPDATE hall_members
        SET hall_key = ?, hall_name = ?, hall_meaning = ?, hall_color = ?, student_name = ?, year = ?, role_label = ?, photo_path = ?, sort_order = ?
        WHERE id = ?
    ');

    foreach ($ids as $index => $id) {
        $id = (int) $id;
        $hallKey = $_POST['hall_key'][$index] ?? 'gyeongcheon';
        $hall = $halls[$hallKey] ?? $halls['gyeongcheon'];
        $studentName = trim($_POST['student_name'][$index] ?? '');
        $year = max(1, min(3, (int) ($_POST['year'][$index] ?? 1)));
        $roleLabel = trim($_POST['role_label'][$index] ?? '');
        $photoPath = $_POST['current_photo_path'][$index] ?? null;
        $sortOrder = (int) ($_POST['sort_order'][$index] ?? 0);
        $uploadedPhoto = save_hall_photo('photo_' . $id);
        if ($uploadedPhoto) {
            delete_upload($photoPath);
            $photoPath = $uploadedPhoto;
        }

        if ($studentName === '') {
            continue;
        }

        $stmt->execute([$hallKey, $hall['name'], $hall['meaning'], $hall['color'], $studentName, $year, $roleLabel, $photoPath, $sortOrder, $id]);
    }

    $newName = trim($_POST['new_student_name'] ?? '');
    if ($newName !== '') {
        $hallKey = $_POST['new_hall_key'] ?? 'gyeongcheon';
        $hall = $halls[$hallKey] ?? $halls['gyeongcheon'];
        $year = max(1, min(3, (int) ($_POST['new_year'] ?? 1)));
        $roleLabel = trim($_POST['new_role_label'] ?? '');
        $sortOrder = (int) ($_POST['new_sort_order'] ?? 99);
        $photoPath = save_hall_photo('new_photo');

        $stmt = $db->prepare('
            INSERT INTO hall_members
            (hall_key, hall_name, hall_meaning, hall_color, student_name, year, role_label, photo_path, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$hallKey, $hall['name'], $hall['meaning'], $hall['color'], $newName, $year, $roleLabel, $photoPath, $sortOrder]);
    }

    redirect('/admin/halls');
}

if ($path === '/admin/halls/update' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $halls = hall_definitions();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $db->prepare('SELECT user_id, photo_path, sort_order FROM hall_members WHERE id = ?');
    $stmt->execute([$id]);
    $current = $stmt->fetch();

    if ($id > 0 && $current) {
        $hallKey = $_POST['hall_key'] ?? 'gyeongcheon';
        $hall = $halls[$hallKey] ?? $halls['gyeongcheon'];
        $studentName = trim($_POST['student_name'] ?? '');
        $year = max(1, min(3, (int) ($_POST['year'] ?? 1)));
        $roleLabel = trim($_POST['role_label'] ?? '');
        $sortOrder = (int) ($current['sort_order'] ?? 0);
        $photoPath = $current['photo_path'] ?: null;
        $uploadedPhoto = save_hall_photo('photo');
        if ($uploadedPhoto) {
            delete_upload($photoPath);
            $photoPath = $uploadedPhoto;
        }

        if ($studentName !== '') {
            $stmt = $db->prepare('
                UPDATE hall_members
                SET hall_key = ?, hall_name = ?, hall_meaning = ?, hall_color = ?, student_name = ?, year = ?, role_label = ?, photo_path = ?, sort_order = ?
                WHERE id = ?
            ');
            $stmt->execute([$hallKey, $hall['name'], $hall['meaning'], $hall['color'], $studentName, $year, $roleLabel, $photoPath, $sortOrder, $id]);

            if (!empty($current['user_id'])) {
                $stmt = $db->prepare('UPDATE users SET display_name = ?, hall_key = ?, year = ?, photo_path = ? WHERE id = ?');
                $stmt->execute([$studentName, $hallKey, $year, $photoPath, (int) $current['user_id']]);
            }
        }
    }

    redirect('/admin/halls');
}

if ($path === '/admin/halls/delete' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('SELECT user_id, photo_path FROM hall_members WHERE id = ?');
        $stmt->execute([$id]);
        $member = $stmt->fetch();
        if ($member && empty($member['user_id'])) {
            delete_upload($member['photo_path'] ?: null);
        }

        $stmt = $db->prepare('DELETE FROM hall_members WHERE id = ?');
        $stmt->execute([$id]);
    }

    redirect('/admin/halls');
}

function sync_user_hall_member(PDO $db, int $userId): void
{
    $stmt = $db->prepare('SELECT id, role, display_name, hall_key, year, photo_path FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return;
    }

    $halls = hall_definitions();
    $hallKey = (string) ($user['hall_key'] ?? '');
    $year = (int) ($user['year'] ?? 0);
    $displayName = trim((string) ($user['display_name'] ?? ''));
    $shouldAppear = $user['role'] !== 'admin'
        && $displayName !== ''
        && isset($halls[$hallKey])
        && $year >= 1
        && $year <= 3;

    if (!$shouldAppear) {
        $stmt = $db->prepare('DELETE FROM hall_members WHERE user_id = ?');
        $stmt->execute([$userId]);
        return;
    }

    $hall = $halls[$hallKey];
    $stmt = $db->prepare('SELECT id FROM hall_members WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $memberId = $stmt->fetchColumn();

    if ($memberId) {
        $stmt = $db->prepare('
            UPDATE hall_members
            SET hall_key = ?, hall_name = ?, hall_meaning = ?, hall_color = ?, student_name = ?, year = ?, photo_path = ?
            WHERE id = ?
        ');
        $stmt->execute([
            $hallKey,
            $hall['name'],
            $hall['meaning'],
            $hall['color'],
            $displayName,
            $year,
            $user['photo_path'] ?: null,
            (int) $memberId,
        ]);
        return;
    }

    $stmt = $db->prepare('
        INSERT INTO hall_members
        (user_id, hall_key, hall_name, hall_meaning, hall_color, student_name, year, role_label, photo_path, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userId,
        $hallKey,
        $hall['name'],
        $hall['meaning'],
        $hall['color'],
        $displayName,
        $year,
        '',
        $user['photo_path'] ?: null,
        99,
    ]);
}

function save_hall_photo(string $field): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    $extension = null;

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $_FILES[$field]['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
        $extension = $extensions[$mime] ?? null;
    }

    if ($extension === null) {
        $originalExtension = strtolower(pathinfo((string) $_FILES[$field]['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (in_array($originalExtension, $allowedExtensions, true)) {
            $extension = $originalExtension === 'jpeg' ? 'jpg' : $originalExtension;
        }
    }

    if ($extension === null) {
        return null;
    }

    $uploadDir = __DIR__ . '/../storage/uploads';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $stored = 'profile_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = $uploadDir . '/' . $stored;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return null;
    }

    return $stored;
}

function point_normalize(string $text): string
{
    return trim((string) preg_replace('/\s+/u', ' ', $text));
}

function extract_point_score(string $line): ?array
{
    $line = point_normalize($line);
    if (preg_match('/([+-])\s*(\d+)\s*점?/u', $line, $matches)) {
        return [
            'type' => $matches[1] === '+' ? 'merit' : 'demerit',
            'points' => (int) $matches[2],
            'token' => $matches[0],
        ];
    }

    $patterns = [
        'merit' => [
            '/(?:상점|상)\s*(\d+)\s*점?/u',
            '/(\d+)\s*점?\s*(?:상점|상)/u',
        ],
        'demerit' => [
            '/(?:벌점|벌)\s*(\d+)\s*점?/u',
            '/(\d+)\s*점?\s*(?:벌점|벌)/u',
        ],
    ];

    foreach ($patterns as $type => $items) {
        foreach ($items as $pattern) {
            if (preg_match($pattern, $line, $matches)) {
                return [
                    'type' => $type,
                    'points' => (int) $matches[1],
                    'token' => $matches[0],
                ];
            }
        }
    }

    return null;
}

function point_reason_from_line(string $line, string $studentName, ?string $scoreToken): string
{
    $reason = str_replace($studentName, ' ', $line);
    if ($scoreToken) {
        $reason = str_replace($scoreToken, ' ', $reason);
    }
    $reason = str_replace(['상점', '벌점', '상', '벌', '점', '사유', ':', '-', '=', '/', '(', ')'], ' ', $reason);
    $reason = point_normalize($reason);

    return $reason !== '' ? $reason : '사유 미입력';
}

function point_date_from_line(string $line, string $defaultDate): string
{
    if (!preg_match('/(\d{1,2})\s*\/\s*(\d{1,2})/u', $line, $matches)) {
        return $defaultDate;
    }

    $year = (int) date('Y');
    $month = max(1, min(12, (int) $matches[1]));
    $day = max(1, min(31, (int) $matches[2]));

    return sprintf('%04d-%02d-%02d', $year, $month, $day);
}

function parse_point_batch_header(string $line, string $defaultDate): ?array
{
    if (!str_contains($line, '일괄')) {
        return null;
    }

    $score = extract_point_score($line);
    if (!$score) {
        return null;
    }

    $reason = $line;
    $reason = preg_replace('/\d{1,2}\s*\/\s*\d{1,2}/u', ' ', $reason) ?? $reason;
    $reason = str_replace(['일괄', $score['token']], ' ', $reason);
    $reason = point_normalize($reason);

    return [
        'type' => $score['type'],
        'points' => $score['points'],
        'reason' => $reason !== '' ? $reason : '일괄 지급',
        'issued_at' => point_date_from_line($line, $defaultDate),
    ];
}

function find_point_student_candidates(string $line, array $students): array
{
    $candidates = [];
    foreach ($students as $student) {
        $name = trim((string) ($student['display_name'] ?: $student['username']));
        if ($name !== '' && str_contains($line, $name)) {
            $candidates[] = $student;
        }
    }

    usort($candidates, fn ($a, $b) => strlen((string) ($b['display_name'] ?: $b['username'])) <=> strlen((string) ($a['display_name'] ?: $a['username'])));

    return $candidates;
}

function parse_point_bulk_text(string $rawText, string $defaultDate, array $students): array
{
    $parsed = [];
    $failed = [];
    $activeBatch = null;
    $lines = preg_split('/\R/u', $rawText) ?: [];

    foreach ($lines as $index => $rawLine) {
        $line = point_normalize($rawLine);
        if ($line === '') {
            continue;
        }

        $batchHeader = parse_point_batch_header($line, $defaultDate);
        if ($batchHeader) {
            $activeBatch = $batchHeader;
            continue;
        }

        $candidates = find_point_student_candidates($line, $students);
        $lineNo = $index + 1;
        if (count($candidates) === 0) {
            $failed[] = ['line_no' => $lineNo, 'raw_line' => $line, 'status' => '학생 이름을 찾을 수 없음'];
            continue;
        }
        if (count($candidates) > 1) {
            $names = array_map(fn ($student) => (string) ($student['display_name'] ?: $student['username']), $candidates);
            $failed[] = ['line_no' => $lineNo, 'raw_line' => $line, 'status' => '학생 이름 중복 또는 여러 명 매칭: ' . implode(', ', $names)];
            continue;
        }

        $student = $candidates[0];
        $studentName = (string) ($student['display_name'] ?: $student['username']);
        $score = extract_point_score($line);
        if ($score) {
            $parsed[] = [
                'line_no' => $lineNo,
                'raw_line' => $line,
                'user_id' => (int) $student['id'],
                'student_name' => $studentName,
                'type' => $score['type'],
                'points' => $score['points'],
                'reason' => point_reason_from_line($line, $studentName, $score['token']),
                'issued_at' => $defaultDate,
            ];
            continue;
        }

        if ($activeBatch) {
            $parsed[] = [
                'line_no' => $lineNo,
                'raw_line' => $line,
                'user_id' => (int) $student['id'],
                'student_name' => $studentName,
                'type' => $activeBatch['type'],
                'points' => $activeBatch['points'],
                'reason' => $activeBatch['reason'],
                'issued_at' => $activeBatch['issued_at'],
            ];
            continue;
        }

        $failed[] = ['line_no' => $lineNo, 'raw_line' => $line, 'status' => '상점/벌점 또는 점수를 찾을 수 없음'];
    }

    return [$parsed, $failed];
}

function delete_upload(?string $path): void
{
    if (!$path) {
        return;
    }

    $target = __DIR__ . '/../storage/uploads/' . basename($path);
    if (is_file($target)) {
        unlink($target);
    }
}

function save_editor_image(string $field): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $_FILES[$field]['tmp_name']) : '';
        if ($finfo) {
            finfo_close($finfo);
        }
    }

    $extension = $extensions[$mime] ?? null;
    if ($extension === null) {
        return null;
    }

    $stored = 'post_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = __DIR__ . '/../storage/uploads/' . $stored;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return null;
    }

    return $stored;
}

if ($path === '/calendar/events/store' && $method === 'POST') {
    $auth->requireRole(['council', 'admin']);
    $date = $_POST['event_date'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'general';
    $categories = ['general', 'important', 'check'];

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $title !== '') {
        $stmt = $db->prepare('
            INSERT INTO calendar_events (event_date, title, category, author_id)
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $date,
            $title,
            in_array($category, $categories, true) ? $category : 'general',
            $auth->user()['id'],
        ]);
    }

    redirect('/calendar?month=' . substr($date ?: date('Y-m-d'), 0, 7));
}

if ($path === '/calendar/events/delete' && $method === 'POST') {
    $auth->requireRole(['council', 'admin']);
    $id = (int) ($_POST['id'] ?? 0);
    $month = preg_match('/^\d{4}-\d{2}$/', $_POST['month'] ?? '') ? $_POST['month'] : date('Y-m');

    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM calendar_events WHERE id = ?');
        $stmt->execute([$id]);
    }

    redirect('/calendar?month=' . $month);
}

if (preg_match('#^/board/([a-z-]+)$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1], $db);
    echo (new BoardController($db, $auth))->index($board);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/post/(\d+)$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1], $db);
    echo (new BoardController($db, $auth))->show($board, (int) $matches[2]);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/new$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1], $db);
    echo (new BoardController($db, $auth))->create($board);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/post/(\d+)/edit$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1], $db);
    echo (new BoardController($db, $auth))->edit($board, (int) $matches[2]);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/store$#', $path, $matches) && $method === 'POST') {
    $board = Board::fromSlug($matches[1], $db);
    echo (new BoardController($db, $auth))->store($board);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/post/(\d+)/update$#', $path, $matches) && $method === 'POST') {
    $board = Board::fromSlug($matches[1], $db);
    echo (new BoardController($db, $auth))->update($board, (int) $matches[2]);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/post/(\d+)/delete$#', $path, $matches) && $method === 'POST') {
    $board = Board::fromSlug($matches[1], $db);
    echo (new BoardController($db, $auth))->delete($board, (int) $matches[2]);
    exit;
}

http_response_code(404);
echo view('page', ['title' => '404', 'body' => '요청한 페이지를 찾을 수 없습니다.']);
