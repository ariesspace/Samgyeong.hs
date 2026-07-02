<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '삼경고') ?></title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/">삼경고</a>
        <nav>
            <a href="/about">학교 소개</a>
            <a href="/board/notice">공지/게시판</a>
            <a href="/board/resources">학생 자료실</a>
            <a href="/board/council">학생회</a>
            <a href="/rules">규정집</a>
            <?php if (!empty($_SESSION['user'])): ?>
                <span><?= e($_SESSION['user']['username']) ?> (<?= e($_SESSION['user']['role']) ?>)</span>
                <a href="/logout">로그아웃</a>
            <?php else: ?>
                <a href="/login">로그인</a>
            <?php endif; ?>
        </nav>
    </header>
    <main>
        <?= $content ?>
    </main>
</body>
</html>
