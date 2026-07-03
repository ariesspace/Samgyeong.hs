<main class="main-home">
    <section class="main-visual">
        <div class="main-visual-image"></div>
        <div class="main-visual-overlay"></div>

        <div class="main-ribbon">
            <strong>삼경인문고등학교 우리 예절 캠페인</strong>
            <span>존중, 배려, 책임으로 함께 만드는 학교</span>
        </div>

        <div class="main-copy">
            <p>Samgyeong Humanities High School</p>
            <h1>배움이 깊어지고<br>마음이 자라는 학교</h1>
            <a href="/about">학교 소개 보기</a>
        </div>

        <div class="main-popups">
            <a class="popup-card light" href="/admissions">
                <span>입학 안내</span>
                <strong>2027학년도 신입생 모집요강</strong>
                <em>전형 일정과 제출 서류를 확인하세요.</em>
            </a>
            <a class="popup-card dark" href="/calendar">
                <span>삼경원 일정</span>
                <strong>7월 자치활동 캘린더</strong>
                <em>회의, 간담회, 시설 점검 일정을 안내합니다.</em>
            </a>
        </div>
    </section>

    <section class="home-boards">
        <?php foreach ($boards as $board): ?>
            <article class="home-board">
                <div class="home-board-head">
                    <h2><?= e($board['name']) ?></h2>
                    <a href="/board/<?= e($board['slug']) ?>">모두보기</a>
                </div>
                <ul>
                    <?php if (!$board['items']): ?>
                        <li class="home-board-empty">등록된 게시글이 없습니다.</li>
                    <?php endif; ?>

                    <?php foreach ($board['items'] as $item): ?>
                        <?php
                            $date = substr($item['created_at'], 0, 10);
                            $isNew = strtotime($item['created_at']) >= strtotime('-7 days');
                        ?>
                        <li>
                            <a href="/board/<?= e($board['slug']) ?>/post/<?= e((string) $item['id']) ?>">
                                <span><?= e($item['title']) ?></span>
                                <?php if ($isNew): ?><strong>NEW</strong><?php endif; ?>
                            </a>
                            <time><?= e($date) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </section>
</main>
