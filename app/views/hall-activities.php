<?php
$halls = hall_definitions();
$activities = $activities ?? [];

$renderActivitySummary = static function (string $summary): string {
    $needle = '식단 게시판';
    $escaped = e($summary);

    if (!str_contains($summary, $needle)) {
        return $escaped;
    }

    return str_replace(
        e($needle),
        '<a class="hall-activity-inline-link" href="/meal-board">식단 게시판</a>',
        $escaped
    );
};
?>

<section class="page hall-activities-page" data-hall-activities>
    <header class="hall-activities-hero">
        <p class="eyebrow">Samgyeong Hall Activities</p>
        <h1>관별 자치활동</h1>
        <p>삼경의 정신을 각 관의 방식으로 실천하는 자치활동 앨범입니다.</p>
    </header>

    <section class="hall-activity-filter" aria-label="관별 활동 필터">
        <button type="button" class="all active" data-hall-filter="all">전체</button>
        <?php foreach ($halls as $key => $hall): ?>
            <button type="button" class="<?= e($hall['color']) ?>" data-hall-filter="<?= e($hall['color']) ?>">
                <?= e($hall['name']) ?> <?= e($hall['meaning']) ?>
            </button>
        <?php endforeach; ?>
    </section>

    <section class="hall-activity-grid">
        <?php foreach ($activities as $index => $activity): ?>
            <?php
                $hall = $halls[$activity['hall_key'] ?? ''] ?? null;
                if (!$hall) {
                    continue;
                }
            ?>
            <article class="hall-activity-card <?= e($hall['color']) ?>" data-hall-card="<?= e($hall['color']) ?>">
                <div class="hall-activity-cover">
                    <span><?= e($hall['name']) ?> · <?= e($hall['meaning']) ?></span>
                    <strong><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></strong>
                </div>
                <div class="hall-activity-body">
                    <h2><?= e($activity['title']) ?></h2>
                    <p><?= $renderActivitySummary((string) $activity['summary']) ?></p>
                    <dl>
                        <div>
                            <dt>방식</dt>
                            <dd><?= e($activity['method']) ?></dd>
                        </div>
                        <div>
                            <dt>의미</dt>
                            <dd><?= e($activity['value']) ?></dd>
                        </div>
                    </dl>
                </div>
            </article>
        <?php endforeach; ?>

        <?php if (empty($activities)): ?>
            <p class="empty-board">등록된 자치활동이 없습니다.</p>
        <?php endif; ?>
    </section>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-hall-activities]');
    if (!root) {
        return;
    }

    var filters = root.querySelectorAll('[data-hall-filter]');
    var cards = root.querySelectorAll('[data-hall-card]');

    filters.forEach(function (filterButton) {
        filterButton.addEventListener('click', function () {
            var selected = filterButton.dataset.hallFilter;

            filters.forEach(function (button) {
                button.classList.toggle('active', button === filterButton);
            });

            cards.forEach(function (card) {
                card.hidden = selected !== 'all' && card.dataset.hallCard !== selected;
            });
        });
    });
});
</script>
