<section class="page point-rules-page">
    <h1>상벌점 리스트</h1>
    <p class="muted">학교생활 규정에 따른 벌점 누적 기준과 징계 적용 범위를 정리했습니다.</p>

    <section class="point-rules-intro">
        <p class="eyebrow">Samgyeong Conduct Standard</p>
        <h2>상벌점 및 징계 기준표</h2>
        <p>개인, 학년, 관, 전체 단위별 벌점 누적 기준을 한눈에 확인할 수 있습니다.</p>
    </section>

    <div class="point-rule-sections">
        <?php foreach ($sections as $section): ?>
            <section class="point-rule-block point-rule-<?= e($section['tone']) ?>">
                <div class="point-rule-head">
                    <h2><?= e($section['title']) ?></h2>
                    <?php if (!empty($section['description'])): ?>
                        <p><?= e($section['description']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="point-rule-list">
                    <?php if (empty($section['items'])): ?>
                        <div class="point-rule-row">
                            <strong>-</strong>
                            <span>등록된 기준이 없습니다.</span>
                        </div>
                    <?php endif; ?>
                    <?php foreach ($section['items'] as $item): ?>
                        <div class="point-rule-row <?= !empty($item['emphasis']) ? 'is-emphasis' : '' ?>">
                            <strong><?= e($item['score']) ?></strong>
                            <span><?= e($item['text']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
