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
    '/' => fn () => view('home', ['title' => '삼경고']),
    '/about' => fn () => view('page', ['title' => '학교소개 및 교훈', 'body' => "삼경고는 전통과 자율을 바탕으로 서로를 존중하는 학교 문화를 세워가는 인문계 고등학교입니다.\n\n교훈: 바르게 생각하고, 따뜻하게 말하며, 책임 있게 행동한다."]),
    '/pledge' => fn () => view('page', ['title' => '삼경고 선서문', 'body' => "나는 삼경고 학생으로서 학교의 명예를 나의 명예로 여기고, 공동체의 약속을 지키며, 배움과 실천으로 더 나은 사람이 되겠습니다."]),
    '/history' => fn () => view('page', ['title' => '학교 연혁', 'body' => "학교 연혁을 정리하는 페이지입니다. 설립, 주요 행사, 교육과정 변화 등을 순서대로 게시할 수 있습니다."]),
    '/location' => fn () => view('page', ['title' => '오시는 길', 'body' => "주소, 교통편, 문의처를 정리하는 페이지입니다."]),
    '/admissions' => fn () => view('page', ['title' => '모집요강', 'body' => "입학 전형 일정, 지원 자격, 제출 서류, 문의처를 안내하는 페이지입니다."]),
    '/rules' => fn () => view('page', ['title' => '학교생활 규정', 'body' => '학교 생활 규정과 학생회 운영 규정을 게시하는 공간입니다.']),
    '/student-halls' => function () use ($db) {
        $rows = $db->query('SELECT * FROM hall_members ORDER BY hall_key, sort_order, id')->fetchAll();
        return view('student-halls', ['title' => '관별 명단', 'members' => $rows]);
    },
    '/council' => fn () => view('page', ['title' => '학생회 소개', 'body' => "학생회는 학생들의 의견을 모으고 학교 생활 개선을 함께 논의하는 자치기구입니다."]),
    '/calendar' => fn () => view('calendar', ['title' => '일정 캘린더']),
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
        SET student_name = ?, year = ?, role_label = ?, sort_order = ?
        WHERE id = ?
    ');

    foreach ($ids as $index => $id) {
        $studentName = trim($_POST['student_name'][$index] ?? '');
        $year = max(1, min(3, (int) ($_POST['year'][$index] ?? 1)));
        $roleLabel = trim($_POST['role_label'][$index] ?? '');
        $sortOrder = (int) ($_POST['sort_order'][$index] ?? 0);

        if ($studentName === '' || $roleLabel === '') {
            continue;
        }

        $stmt->execute([$studentName, $year, $roleLabel, $sortOrder, (int) $id]);
    }

    $newName = trim($_POST['new_student_name'] ?? '');
    if ($newName !== '') {
        $hallKey = $_POST['new_hall_key'] ?? 'gyeongcheon';
        $hall = $halls[$hallKey] ?? $halls['gyeongcheon'];
        $year = max(1, min(3, (int) ($_POST['new_year'] ?? 1)));
        $roleLabel = trim($_POST['new_role_label'] ?? '대표');
        $sortOrder = (int) ($_POST['new_sort_order'] ?? 99);

        $stmt = $db->prepare('
            INSERT INTO hall_members
            (hall_key, hall_name, hall_meaning, hall_color, student_name, year, role_label, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$hallKey, $hall['name'], $hall['meaning'], $hall['color'], $newName, $year, $roleLabel, $sortOrder]);
    }

    redirect('/admin/halls');
}

if ($path === '/admin/halls/delete' && $method === 'POST') {
    $auth->requireRole(['admin']);
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $db->prepare('DELETE FROM hall_members WHERE id = ?');
        $stmt->execute([$id]);
    }

    redirect('/admin/halls');
}

if (preg_match('#^/board/([a-z-]+)$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->index($board);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/new$#', $path, $matches)) {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->create($board);
    exit;
}

if (preg_match('#^/board/([a-z-]+)/store$#', $path, $matches) && $method === 'POST') {
    $board = Board::fromSlug($matches[1]);
    echo (new BoardController($db, $auth))->store($board);
    exit;
}

http_response_code(404);
echo view('page', ['title' => '404', 'body' => '요청한 페이지를 찾을 수 없습니다.']);
