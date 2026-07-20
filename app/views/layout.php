<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? '삼경고') ?></title>
    <link rel="stylesheet" href="/styles.css?v=2026072002">
    <link rel="stylesheet" href="/meal-compact.css?v=2026070632">
    <link rel="stylesheet" href="/rules-document.css?v=2026070633">
    <link rel="stylesheet" href="/post-files.css?v=2026070538">
    <link rel="stylesheet" href="/point-rules.css?v=2026070615">
    <link rel="stylesheet" href="/discipline-awards.css?v=2026071101">
    <link rel="stylesheet" href="/board-tags.css?v=2026071301">
    <link rel="stylesheet" href="/admin-point-rules.css?v=2026070642">
    <link rel="stylesheet" href="/admin-hall-activities.css?v=2026070634">
</head>
<body>
    <?php
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $groups = nav_groups();
        $isHome = $requestPath === '/';
        $isStandaloneShop = str_starts_with($requestPath, '/samgyeong-mall');
        $activeGroup = $isHome ? '' : active_group($requestPath);
        $watermarkUser = $_SESSION['user'] ?? null;
    ?>
    <?php if (!empty($watermarkUser)): ?>
        <?php
            $watermarkId = (string) (($watermarkUser['username'] ?? '') ?: ($watermarkUser['id'] ?? 'user'));
            $watermarkText = 'SAMGYEONG · ' . $watermarkId;
        ?>
        <div class="user-watermark" aria-hidden="true">
            <?php for ($i = 0; $i < 24; $i++): ?>
                <span><?= e($watermarkText) ?></span>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <div class="utility-bar">
        <span>삼경인문고등학교 공식 사이트</span>
        <div class="utility-actions">
            <?php if (!empty($_SESSION['user'])): ?>
                <span><?= e(($_SESSION['user']['display_name'] ?? '') ?: $_SESSION['user']['username']) ?> · <?= e(role_label($_SESSION['user']['role'])) ?></span>
                <a class="utility-button primary" href="/mypage"><span aria-hidden="true">⚙</span> 내 정보</a>
                <?php if (($_SESSION['user']['role'] ?? '') === 'admin'): ?>
                    <a class="utility-button system" href="/admin/users"><span aria-hidden="true">▦</span> 시스템</a>
                <?php endif; ?>
                <a class="utility-button secondary" href="/logout"><span aria-hidden="true">↪</span> 로그아웃</a>
            <?php else: ?>
                <a class="utility-button primary" href="/login"><span aria-hidden="true">↪</span> 로그인</a>
            <?php endif; ?>
        </div>
    </div>

    <header class="site-header">
        <a class="brand-block" href="/">
            <img src="/assets/samgyeong-emblem2.png?v=2026070637" alt="삼경인문고등학교 교표">
            <span>
                <strong>삼경인문고등학교</strong>
                <em>SAMGYEONG HUMANITIES HIGH SCHOOL</em>
            </span>
        </a>
        <nav class="primary-nav">
            <?php foreach ($groups as $group => $items): ?>
                <?php
                    $firstHref = $items[0]['href'] ?? '';
                    if (str_starts_with($firstHref, '/mypage') || str_starts_with($firstHref, '/admin')) {
                        continue;
                    }
                ?>
                <a class="<?= !$isHome && $activeGroup === $group ? 'active' : '' ?>" href="<?= e($items[0]['href']) ?>"><?= e($group) ?></a>
            <?php endforeach; ?>
        </nav>
    </header>

    <?php if ($isHome): ?>
        <?= $content ?>
    <?php elseif ($isStandaloneShop): ?>
        <main class="shop-standalone-shell">
            <?= $content ?>
        </main>
    <?php else: ?>
        <section class="site-hero sub-hero">
            <div>
                <span><?= e($activeGroup) ?></span>
                <h1><?= e($title ?? $activeGroup) ?></h1>
                <p>SAMGYEONG HUMANITIES HIGH SCHOOL</p>
            </div>
        </section>

        <main class="shell">
            <aside class="sidebar">
                <h2><?= e($activeGroup) ?></h2>
                <ul>
                    <?php foreach ($groups[$activeGroup] as $item): ?>
                        <?php
                            $itemPath = parse_url($item['href'], PHP_URL_PATH) ?: $item['href'];
                            $isActiveItem = $requestPath === $itemPath || str_starts_with($requestPath, $itemPath . '/');
                            foreach ($item['children'] ?? [] as $child) {
                                $childPath = parse_url($child['href'], PHP_URL_PATH) ?: $child['href'];
                                if ($requestUri === $child['href'] || $requestPath === $childPath) {
                                    $isActiveItem = true;
                                }
                            }
                        ?>
                        <li>
                            <a class="<?= $isActiveItem ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
                                <?= e($item['label']) ?>
                                <span><?= $isActiveItem ? '-' : '+' ?></span>
                            </a>
                            <?php if ($isActiveItem && !empty($item['children'])): ?>
                                <ul class="sidebar-submenu">
                                    <?php foreach ($item['children'] as $child): ?>
                                        <?php $isActiveChild = $requestUri === $child['href']; ?>
                                        <li>
                                            <a class="<?= $isActiveChild ? 'active' : '' ?>" href="<?= e($child['href']) ?>">
                                                <?= e($child['label']) ?>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
            <section class="content-panel">
                <div class="breadcrumb">홈 &gt; <?= e($activeGroup) ?> &gt; <strong><?= e($title ?? '') ?></strong></div>
                <?= $content ?>
            </section>
        </main>
    <?php endif; ?>

    <footer class="site-footer">
        <img src="/assets/samgyeong-emblem2.png?v=2026070637" alt="">
        <strong>삼경인문고등학교</strong>
        <span>SAMGYEONG HUMANITIES HIGH SCHOOL</span>
        <p>서울특별시 삼경구 삼경로 1 · 교무실 02-123-4567 · 행정실 02-123-4568</p>
        <p>Copyright Samgyeong Humanities High School. All Rights Reserved.</p>
    </footer>

    <?php
        $passwordNoticeUser = $_SESSION['user'] ?? null;
        $showPasswordNotice = $passwordNoticeUser
            && (int) ($passwordNoticeUser['must_change_password'] ?? 0) === 1
            && ($passwordNoticeUser['role'] ?? '') !== 'guest'
            && !str_starts_with($requestPath, '/mypage');
    ?>
    <?php if ($showPasswordNotice): ?>
        <div class="first-password-modal" data-first-password-modal data-user-id="<?= e((string) $passwordNoticeUser['id']) ?>">
            <div class="first-password-dialog" role="dialog" aria-modal="true" aria-labelledby="first-password-title">
                <span class="first-password-mark" aria-hidden="true">!</span>
                <h2 id="first-password-title">새 비밀번호로 계정을 지켜 주세요</h2>
                <p>
                    현재 계정은 발급 또는 초기화된 임시 비밀번호 상태입니다.
                    안전한 이용을 위해 마이페이지에서 본인만 아는 비밀번호로 변경해 주세요.
                </p>
                <div class="first-password-actions">
                    <a class="button" href="/mypage" data-first-password-go>마이페이지로 이동</a>
                    <button type="button" class="ghost-button" data-first-password-close>닫기</button>
                </div>
            </div>
        </div>
        <script>
        (() => {
            const modal = document.querySelector('[data-first-password-modal]');
            if (!modal) return;
            const key = `samgyeong:first-password-notice:${modal.dataset.userId || 'user'}`;
            if (sessionStorage.getItem(key) === 'closed') {
                modal.hidden = true;
                return;
            }
            modal.querySelector('[data-first-password-close]')?.addEventListener('click', () => {
                sessionStorage.setItem(key, 'closed');
                modal.hidden = true;
            });
            modal.querySelector('[data-first-password-go]')?.addEventListener('click', () => {
                sessionStorage.setItem(key, 'closed');
                modal.hidden = true;
            });
        })();
        </script>
    <?php endif; ?>
    <script>
    (() => {
        document.body.classList.add('copy-protected');

        const isEditableTarget = (target) => Boolean(target?.closest?.(
            'input, textarea, select, button, dialog, [contenteditable="true"], .rich-editor, .editor-toolbar'
        ));

        const preventOutsideEditable = (event) => {
            if (!isEditableTarget(event.target)) {
                event.preventDefault();
            }
        };

        ['contextmenu', 'copy', 'cut', 'dragstart', 'selectstart'].forEach((eventName) => {
            document.addEventListener(eventName, preventOutsideEditable);
        });

        document.addEventListener('keydown', (event) => {
            if (isEditableTarget(event.target)) return;

            const key = event.key.toLowerCase();
            const isBlockedShortcut = (event.ctrlKey || event.metaKey) && ['c', 'x', 's', 'u', 'p'].includes(key);

            if (isBlockedShortcut) {
                event.preventDefault();
            }
        });
    })();
    </script>
</body>
</html>
