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
    '/about' => fn () => view('page', ['title' => '학교 소개', 'body' => '삼경고의 교육 목표, 연혁, 학교 현황을 정리하는 페이지입니다.']),
    '/rules' => fn () => view('page', ['title' => '규정집', 'body' => '학교 생활 규정과 학생회 운영 규정을 게시하는 공간입니다.']),
    '/login' => fn () => $auth->loginPage(),
    '/logout' => fn () => $auth->logout(),
];

if (isset($routes[$path])) {
    echo $routes[$path]();
    exit;
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
