<section class="page discipline-awards-page">
    <h1>징계 및 포상</h1>
    <p class="muted">상벌점 기록을 바탕으로 한 지도 절차와 포상 기준을 안내합니다.</p>

    <section class="discipline-awards-lead">
        <article>
            <span>지도</span>
            <h2>기록보다 먼저 회복을 봅니다</h2>
            <p>벌점은 처벌 자체보다 약속을 다시 세우기 위한 기준입니다. 누적 기준에 따라 개인, 학년, 관, 전체 단위의 지도가 적용됩니다.</p>
        </article>
        <article>
            <span>포상</span>
            <h2>좋은 태도는 분명히 남깁니다</h2>
            <p>공동체를 위해 기여한 행동, 예절 실천, 자발적인 봉사와 모범 사례는 상점과 추천 기록으로 남겨 포상 심의에 반영합니다.</p>
        </article>
    </section>

    <section class="award-flow">
        <h2>포상 절차</h2>
        <ol>
            <li><strong>추천</strong><span>삼경원, 교사, 관장단이 모범 사례를 추천합니다.</span></li>
            <li><strong>확인</strong><span>상점 기록과 활동 내용을 확인합니다.</span></li>
            <li><strong>심의</strong><span>포상 기준에 따라 대상자를 선정합니다.</span></li>
            <li><strong>기록</strong><span>포상 결과를 학생 기록과 안내 자료에 반영합니다.</span></li>
        </ol>
    </section>

    <details class="discipline-rule-details">
        <summary>징계 기준 보기</summary>
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
    </details>
</section>
