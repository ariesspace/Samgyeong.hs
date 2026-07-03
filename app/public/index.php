<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$db = Database::connect();
$auth = new Auth($db);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    verify_csrf();
}

if ($path === '/login' && $method === 'POST') {
    echo $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '');
    exit;
}

$routes = [
    '/' => function () use ($db) {
        $homeBoards = [];
        foreach (Board::all($db) as $slug => $board) {
            $stmt = $db->prepare('
                SELECT id, title, tag, created_at
                FROM posts
                WHERE board = ?
                ORDER BY id DESC
                LIMIT 4
            ');
            $stmt->execute([$slug]);
            $homeBoards[] = ['slug' => $slug] + $board + ['items' => $stmt->fetchAll()];
        }

        return view('home', ['title' => '삼경고', 'boards' => $homeBoards]);
    },
    '/about' => fn () => view('about', ['title' => '학교소개 및 교훈']),
    '/symbols' => fn () => view('symbols', ['title' => '학교 상징']),
    '/pledge' => fn () => view('pledge', ['title' => '삼경인 선서문']),
    '/history' => fn () => view('page', ['title' => '학교 연혁', 'body' => "학교 연혁을 정리하는 페이지입니다. 설립, 주요 행사, 교육과정 변화 등을 순서대로 게시할 수 있습니다."]),
    '/location' => fn () => view('page', ['title' => '오시는 길', 'body' => "주소, 교통편, 문의처를 정리하는 페이지입니다."]),
    '/admissions' => fn () => view('page', ['title' => '모집요강', 'body' => "입학 전형 일정, 지원 자격, 제출 서류, 문의처를 안내하는 페이지입니다."]),
    '/rules' => function () use ($auth) {
        if (!$auth->user()) {
            return view('access-denied', ['title' => '권한 없음', 'message' => '재학생 이상 로그인 후 접근이 가능한 메뉴입니다.']);
        }
        return view('page', ['title' => '학교생활 규정', 'body' => '학교 생활 규정과 학생회 운영 규정을 게시하는 공간입니다.']);
    },
    '/student-halls' => function () use ($db) {
        $rows = $db->query('SELECT * FROM hall_members ORDER BY hall_key, sort_order, id')->fetchAll();
        $halls = hall_definitions();
        $selectedHall = $_GET['hall'] ?? '';
        return view('student-halls', [
            'title' => '관별 현황',
            'members' => $rows,
            'selectedHall' => isset($halls[$selectedHall]) ? $selectedHall : '',
        ]);
    },
    '/council' => fn () => view('council', ['title' => '삼경원 소개']),
    '/calendar' => function () use ($auth, $db) {
        if (!$auth->hasRole(['council', 'admin'])) {
            return view('access-denied', ['title' => '권한 없음', 'message' => '삼경원(학생회) 인원 및 관리자만 접근이 가능한 메뉴입니다.']);
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

    echo view('admin-user-edit', ['title' => '계정 정보 수정', 'account' => $user]);
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

    if ($username !== '' && $password !== '' && in_array($role, ['student', 'council', 'admin'], true)) {
        if ($role === 'admin') {
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

    if ($userId > 1 && $userId !== (int) ($auth->user()['id'] ?? 0) && in_array($role, ['student', 'council', 'admin'], true)) {
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

    if ($userId > 1 && $userId !== (int) ($auth->user()['id'] ?? 0) && $displayName !== '' && in_array($role, ['student', 'council', 'admin'], true)) {
        $stmt = $db->prepare('SELECT id FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $exists = $stmt->fetchColumn();
        if ($role === 'admin') {
            $hallKey = '';
            $year = 0;
        }
        if ($exists !== false) {
            $stmt = $db->prepare('UPDATE users SET display_name = ?, hall_key = ?, year = ?, role = ? WHERE id = ?');
            $stmt->execute([$displayName, $hallKey, $year, $role, $userId]);
            sync_user_hall_member($db, $userId);
            if ($role !== 'admin') {
                $stmt = $db->prepare('UPDATE hall_members SET role_label = ? WHERE user_id = ?');
                $stmt->execute([$roleLabel, $userId]);
            }
        }
    }

    redirect('/admin/users?saved=profile');
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
        $stmt = $db->prepare('SELECT display_name, hall_key, year, photo_path FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
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

if ($path === '/mypage') {
    if (!$auth->user()) {
        redirect('/login');
    }
    $stmt = $db->prepare('SELECT id, username, role, display_name, hall_key, year, photo_path FROM users WHERE id = ?');
    $stmt->execute([$auth->user()['id']]);
    echo view('mypage-profile', [
        'title' => '내 정보 수정',
        'profile' => $stmt->fetch(),
        'saved' => $_GET['saved'] ?? '',
        'error' => $_GET['error'] ?? '',
    ]);
    exit;
}

if ($path === '/mypage/photo' && $method === 'POST') {
    if (!$auth->user()) {
        redirect('/login');
    }

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
    if (!$auth->user()) {
        redirect('/login');
    }
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if ($password === '' || $password !== $confirm) {
        redirect('/mypage?error=password');
    }

    $stmt = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $auth->user()['id']]);
    redirect('/mypage?saved=1');
}

if ($path === '/mypage/points') {
    if (!$auth->user()) {
        redirect('/login');
    }
    echo view('mypage-points', ['title' => '상벌점 현황']);
    exit;
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

    $stored = 'hall_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target = __DIR__ . '/../storage/uploads/' . $stored;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return null;
    }

    return $stored;
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
