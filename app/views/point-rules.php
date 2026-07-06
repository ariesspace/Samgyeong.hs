<section class="page mypage-page point-rules-page">
    <header class="point-rules-hero">
        <p class="eyebrow">Samgyeong Discipline & Rewards</p>
        <h1>상벌점 리스트</h1>
        <p>학교생활규정 중 상점, 벌점, 제출 절차를 표 형태로 정리했습니다. 기준은 기록과 확인이 쉽도록 간결하게 관리합니다.</p>
    </header>

    <div class="points-summary">
        <article>
            <span>벌점 기준</span>
            <strong>1~5점</strong>
        </article>
        <article>
            <span>상점 기준</span>
            <strong>1~5점</strong>
        </article>
        <article>
            <span>상점 증빙</span>
            <strong>필수</strong>
        </article>
    </div>

    <?php foreach ($sections as $section): ?>
        <?php if (($section['key'] ?? '') === 'submit'): ?>
            <section class="points-rule-section points-submit-rule">
                <h2><?= e($section['title']) ?></h2>
                <ol>
                    <?php foreach ($section['items'] as $item): ?>
                        <li><?= e($item['text']) ?></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php else: ?>
            <section class="points-rule-section">
                <h2><?= e($section['title']) ?></h2>
                <table class="board-table points-table my-points-table point-rules-table">
                    <thead>
                        <tr>
                            <th>구분</th>
                            <th>점수</th>
                            <th>항목</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($section['items'] as $item): ?>
                            <tr>
                                <td data-label="구분"><span class="point-type <?= e($section['type_class']) ?>"><?= e($section['type_label']) ?></span></td>
                                <td data-label="점수"><?= e($item['score']) ?></td>
                                <td data-label="항목" class="title-cell"><?= e($item['text']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
