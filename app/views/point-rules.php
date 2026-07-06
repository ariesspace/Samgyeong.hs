<?php
$sectionMap = [];
foreach ($sections as $section) {
    $sectionMap[$section['key'] ?? ''] = $section;
}

$demeritSection = $sectionMap['demerit'] ?? ['items' => []];
$meritSection = $sectionMap['merit'] ?? ['items' => []];
$submitSection = $sectionMap['submit'] ?? ['items' => []];
?>

<section class="page point-rules-page point-policy-page">
    <header class="point-policy-hero">
        <div class="point-policy-mark" aria-hidden="true">衡</div>
        <p class="eyebrow">Samgyeong Humanities High School</p>
        <h1>상벌점 리스트</h1>
        <p>
            학교생활규정 중 상점, 벌점, 제출 절차만 따로 정리한 안내표입니다.
            아래 항목을 선택해 기준과 적용 원칙을 확인하세요.
        </p>
    </header>

    <nav class="point-policy-tabs" aria-label="상벌점 기준 바로가기">
        <a class="is-demerit" href="#demerit-rules"><span>!</span> 벌점 항목</a>
        <a class="is-merit" href="#merit-rules"><span>★</span> 상점 항목</a>
        <a class="is-submit" href="#submit-rules"><span>i</span> 제출 절차</a>
    </nav>

    <section class="point-policy-viewer" aria-label="상벌점 기준 문서">
        <div class="point-policy-viewer-head">
            <div>
                <span class="viewer-dot"></span>
                공식 규정지침 열람
            </div>
            <strong>三敬</strong>
        </div>

        <div class="point-policy-scroll">
            <section id="demerit-rules" class="point-policy-section point-policy-demerit">
                <div class="point-policy-section-head">
                    <div>
                        <span class="section-symbol">!</span>
                        <h2>벌점 항목</h2>
                    </div>
                    <em>Penalties</em>
                </div>

                <div class="point-policy-list">
                    <?php foreach ($demeritSection['items'] as $item): ?>
                        <article class="point-policy-row">
                            <strong><?= e($item['score']) ?></strong>
                            <p><?= e($item['text']) ?></p>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($demeritSection['items'])): ?>
                        <p class="empty-board">등록된 벌점 항목이 없습니다.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="merit-rules" class="point-policy-section point-policy-merit">
                <div class="point-policy-section-head">
                    <div>
                        <span class="section-symbol">★</span>
                        <h2>상점 항목</h2>
                    </div>
                    <em>Rewards</em>
                </div>

                <div class="point-policy-list">
                    <?php foreach ($meritSection['items'] as $item): ?>
                        <article class="point-policy-row">
                            <strong><?= e($item['score']) ?></strong>
                            <p><?= e($item['text']) ?></p>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($meritSection['items'])): ?>
                        <p class="empty-board">등록된 상점 항목이 없습니다.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section id="submit-rules" class="point-policy-section point-policy-submit">
                <div class="point-policy-section-head">
                    <div>
                        <span class="section-symbol">i</span>
                        <h2>상점 제출 절차</h2>
                    </div>
                    <em>Submission Rules</em>
                </div>

                <ol class="point-policy-steps">
                    <?php foreach ($submitSection['items'] as $index => $item): ?>
                        <li>
                            <strong><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></strong>
                            <span><?= e($item['text']) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php if (empty($submitSection['items'])): ?>
                        <li><span>등록된 제출 절차가 없습니다.</span></li>
                    <?php endif; ?>
                </ol>
            </section>
        </div>
    </section>
</section>
