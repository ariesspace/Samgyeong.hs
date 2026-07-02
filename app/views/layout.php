<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '삼경고') ?></title>
    <link rel="stylesheet" href="/styles.css">
</head>
<body>
    <?php
        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $groups = nav_groups();
        $activeGroup = active_group($requestPath);
    ?>
    <div class="utility-bar">
        <span>삼경인문고등학교 공식 사이트</span>
        <div class="utility-actions">
            <?php if (!empty($_SESSION['user'])): ?>
                <span><?= e($_SESSION['user']['username']) ?> · <?= e(role_label($_SESSION['user']['role'])) ?></span>
                <a href="/logout">로그아웃</a>
            <?php else: ?>
                <a href="/login">로그인</a>
            <?php endif; ?>
        </div>
    </div>

    <header class="site-header">
        <a class="brand-block" href="/">
            <strong>삼경인문고등학교</strong>
            <span>SAMGYEONG HUMANITIES HIGH SCHOOL</span>
        </a>
        <nav class="primary-nav">
            <?php foreach ($groups as $group => $items): ?>
                <a class="<?= $activeGroup === $group ? 'active' : '' ?>" href="<?= e($items[0]['href']) ?>"><?= e($group) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <section class="site-hero">
        <div>
            <h1>삼경인문고등학교</h1>
            <p>SAMGYEONG HUMANITIES HIGH SCHOOL</p>
        </div>
    </section>

    <main class="shell">
        <aside class="sidebar">
            <h2><?= e($activeGroup) ?></h2>
            <ul>
                <?php foreach ($groups[$activeGroup] as $item): ?>
                    <li>
                        <a class="<?= $requestPath === $item['href'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                            <?= e($item['label']) ?>
                            <span><?= $requestPath === $item['href'] ? '-' : '+' ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </aside>
        <section class="content-panel">
            <div class="breadcrumb">홈 &gt; <?= e($activeGroup) ?> &gt; <strong><?= e($title ?? '') ?></strong></div>
            <?= $content ?>
        </section>
    </main>

    <footer class="site-footer">
        <strong>삼경인문고등학교</strong>
        <span>SAMGYEONG HUMANITIES HIGH SCHOOL</span>
        <p>서울특별시 삼경구 삼경로 1 · 교무실 02-123-4567 · 행정실 02-123-4568</p>
        <p>Copyright Samgyeong Humanities High School. All Rights Reserved.</p>
    </footer>
</body>
</html>
