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
        foreach (Board::all() as $slug => $board) {
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
    '/council' => fn () => view('page', ['title' => '학생회 소개', 'body' => "학생회는 학생들의 의견을 모으고 학교 생활 개선을 함께 논의하는 자치기구입니다."]),
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
    $users = $db->query('SELECT id, username, role, created_at FROM users ORDER BY id ASC')->fetchAll();
    echo view('admin-users', ['title' => '계정 권한 관리', 'users' => $users]);
    exit;
}

if ($path === '/admin/users/role' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $role = $_POST['role'] ?? '';

    if ($userId > 1 && in_array($role, ['student', 'council', 'admin'], true)) {
        $stmt = $db->prepare('UPDATE users SET role = ? WHERE id = ?');
        $stmt->execute([$role, $userId]);
    }

    redirect('/admin/users');
}

if ($path === '/admin/halls') {
    $auth->requireRole(['admin']);
    $members = $db->query('SELECT * FROM hall_members ORDER BY hall_key, sort_order, id')->fetchAll();
    echo view('admin-halls', ['title' => '관별 명단 관리', 'members' => $members]);
    exit;
}

if ($path === '/admin/halls/save' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $halls = hall_definitions();
    $ids = $_POST['id'] ?? [];
    $stmt = $db->prepare('
        UPDATE hall_members
        SET student_name = ?, year = ?, role_label = ?, photo_path = ?, sort_order = ?
        WHERE id = ?
    ');

    foreach ($ids as $index => $id) {
        $id = (int) $id;
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

        $stmt->execute([$studentName, $year, $roleLabel, $photoPath, $sortOrder, $id]);
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

if ($path === '/admin/halls/delete' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('SELECT photo_path FROM hall_members WHERE id = ?');
        $stmt->execute([$id]);
        delete_upload($stmt->fetchColumn() ?: null);

        $stmt = $db->prepare('DELETE FROM hall_members WHERE id = ?');
        $stmt->execute([$id]);
    }

    redirect('/admin/halls');
}

function save_hall_photo(string $field): ?string
{
    if (empty($_FILES[$field]['tmp_name']) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    $mime = mime_content_type($_FILES[$field]['tmp_name']) ?: '';
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($extensions[$mime])) {
        return null;
    }

    $stored = 'hall_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
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
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->index($board);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/post/(\d+)$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->show($board, (int) $matches[2]);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/new$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->create($board);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/post/(\d+)/edit$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->edit($board, (int) $matches[2]);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/store$#', $path, $matches) && $method === 'POST') {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->store($board);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/post/(\d+)/update$#', $path, $matches) && $method === 'POST') {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->update($board, (int) $matches[2]);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/post/(\d+)/delete$#', $path, $matches) && $method === 'POST') {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->delete($board, (int) $matches[2]);
    exit;
}

http_response_code(404);
echo view('page', ['title' => '404', 'body' => '요청한 페이지를 찾을 수 없습니다.']);
