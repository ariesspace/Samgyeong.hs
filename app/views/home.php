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
            <h1>배움이 깊어지고 마음이 자라는 학교</h1>
            <a href="/about">학교 소개 보기</a>
        </div>

        <div class="main-popups">
            <a class="popup-card light" href="/admissions">
                <span>입학 안내</span>
                <strong>2027학년도 신입생 모집요강</strong>
                <em>전형 일정과 제출 서류를 확인하세요.</em>
            </a>
            <a class="popup-card dark" href="/calendar">
                <span>학생회 일정</span>
                <strong>7월 자치활동 캘린더</strong>
                <em>회의, 간담회, 시설 점검 일정을 안내합니다.</em>
            </a>
        </div>
    </section>

    <section class="home-boards">
        <?php
            $boards = [
                [
                    'title' => '공지사항',
                    'href' => '/board/notice',
                    'items' => [
                        ['title' => '2027학년도 대학수학능력시험 시행 계획 안내', 'date' => '2026-07-02', 'new' => true],
                        ['title' => '2026학년도 삼경고 이동 수업 신청 공고', 'date' => '2026-06-29', 'new' => true],
                        ['title' => '1학기 기말고사 시행 및 자습실 운영 안내', 'date' => '2026-06-15', 'new' => false],
                        ['title' => '학교생활 기본 계획 안내', 'date' => '2026-02-23', 'new' => false],
                    ],
                ],
                [
                    'title' => '가정통신문',
                    'href' => '/board/resources',
                    'items' => [
                        ['title' => '1학기 기말고사 실시 및 일과 운영 안내', 'date' => '2026-06-29', 'new' => true],
                        ['title' => '생명존중 및 자살예방 교육 안내', 'date' => '2026-06-29', 'new' => true],
                        ['title' => '2학년 방과후학교 신청 안내', 'date' => '2026-06-16', 'new' => false],
                        ['title' => '선택과목 수요조사 안내', 'date' => '2026-06-12', 'new' => false],
                    ],
                ],
                [
                    'title' => '입학 게시판',
                    'href' => '/admissions',
                    'items' => [
                        ['title' => '삼경인문고 진학 체험 캠프 안내', 'date' => '2026-06-05', 'new' => false],
                        ['title' => '신입생 합격자 발표 및 등록 안내', 'date' => '2026-02-02', 'new' => false],
                        ['title' => '추가 모집 합격자 안내', 'date' => '2026-01-19', 'new' => false],
                        ['title' => '입학 설명회 자료집 배포', 'date' => '2026-01-06', 'new' => false],
                    ],
                ],
            ];
        ?>

        <?php foreach ($boards as $board): ?>
            <article class="home-board">
                <div class="home-board-head">
                    <h2><?= e($board['title']) ?></h2>
                    <a href="<?= e($board['href']) ?>">모두보기</a>
                </div>
                <ul>
                    <?php foreach ($board['items'] as $item): ?>
                        <li>
                            <a href="<?= e($board['href']) ?>">
                                <span><?= e($item['title']) ?></span>
                                <?php if ($item['new']): ?><strong>NEW</strong><?php endif; ?>
                            </a>
                            <time><?= e($item['date']) ?></time>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="home-services">
        <a href="/student-halls">
            <strong>관별 명단</strong>
            <span>경천관, 경인관, 경물관 학생 대표</span>
        </a>
        <a href="/rules">
            <strong>학교생활 규정</strong>
            <span>생활 규정과 학생 자치 약속</span>
        </a>
        <a href="/council">
            <strong>학생회 소개</strong>
            <span>학생 자치기구 안내</span>
        </a>
        <a href="/board/council">
            <strong>학생회 게시판</strong>
            <span>의견 공유와 회의 기록</span>
        </a>
    </section>
</main>
