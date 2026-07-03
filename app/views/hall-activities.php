<?php
$activities = [
    [
        'hall' => '경천관',
        'meaning' => '하늘',
        'tone' => 'blue',
        'title' => '명언 필사',
        'summary' => '고전 문학, 사자성어, 오늘의 명언을 자필로 필사하고 인증합니다.',
        'method' => '필사 사진과 짧은 소감을 삼경원에 제출합니다.',
        'value' => '하늘의 이치를 배우고 바른 생각을 세우는 경천의 기상을 기릅니다.',
    ],
    [
        'hall' => '경천관',
        'meaning' => '하늘',
        'tone' => 'blue',
        'title' => '하늘 관찰 일지',
        'summary' => '날씨, 구름, 계절의 변화를 기록하며 하루를 성찰합니다.',
        'method' => '관찰 사진 1장과 3줄 일지를 함께 제출합니다.',
        'value' => '작은 변화 속에서 질서와 이치를 발견하는 태도를 기릅니다.',
    ],
    [
        'hall' => '경인관',
        'meaning' => '사람',
        'tone' => 'gold',
        'title' => '경인 우체통',
        'summary' => '감사하거나 칭찬하고 싶은 선후배, 동료에게 예의를 갖춘 편지를 씁니다.',
        'method' => '삼경원이 편지를 모아 점호 또는 공지 시간에 전달합니다.',
        'value' => '타인을 존중하고 공동체의 온도를 높이는 경인 정신을 실천합니다.',
    ],
    [
        'hall' => '경인관',
        'meaning' => '사람',
        'tone' => 'gold',
        'title' => '인사 실천 챌린지',
        'summary' => '하루 동안 먼저 인사하고 상대를 배려한 사례를 기록합니다.',
        'method' => '실천 사례와 느낀 점을 간단한 카드 형식으로 제출합니다.',
        'value' => '말과 태도로 사람을 공경하는 습관을 만듭니다.',
    ],
    [
        'hall' => '경물관',
        'meaning' => '만물',
        'tone' => 'green',
        'title' => '사물 감사 그림일기',
        'summary' => '하루 동안 도움을 준 사물을 그리고 감사한 이유를 적습니다.',
        'method' => '그림 또는 사진과 감사 문장을 함께 제출합니다.',
        'value' => '주변의 사물과 환경을 아끼는 경물 정신을 표현합니다.',
    ],
    [
        'hall' => '경물관',
        'meaning' => '만물',
        'tone' => 'green',
        'title' => '공간 돌봄 캠페인',
        'summary' => '자습실, 복도, 공용 공간을 정리하고 개선점을 제안합니다.',
        'method' => '정리 전후 사진 또는 개선 제안서를 제출합니다.',
        'value' => '함께 쓰는 공간을 존중하고 책임 있게 관리하는 태도를 기릅니다.',
    ],
];
?>

<section class="page hall-activities-page" data-hall-activities>
    <header class="hall-activities-hero">
        <p class="eyebrow">Samgyeong Hall Activities</p>
        <h1>관별 자치활동</h1>
        <p>삼경의 정신을 각 관의 방식으로 실천하는 자치활동 앨범입니다. 활동은 고정된 세 가지에 머무르지 않고, 관별 특성과 필요에 따라 계속 확장됩니다.</p>
    </header>

    <section class="hall-activity-filter" aria-label="관별 활동 필터">
        <button type="button" class="all active" data-hall-filter="all">전체</button>
        <button type="button" class="blue" data-hall-filter="blue">경천관 하늘</button>
        <button type="button" class="gold" data-hall-filter="gold">경인관 사람</button>
        <button type="button" class="green" data-hall-filter="green">경물관 만물</button>
    </section>

    <section class="hall-activity-grid">
        <?php foreach ($activities as $index => $activity): ?>
            <article class="hall-activity-card <?= e($activity['tone']) ?>" data-hall-card="<?= e($activity['tone']) ?>">
                <div class="hall-activity-cover">
                    <span><?= e($activity['hall']) ?> · <?= e($activity['meaning']) ?></span>
                    <strong><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></strong>
                </div>
                <div class="hall-activity-body">
                    <h2><?= e($activity['title']) ?></h2>
                    <p><?= e($activity['summary']) ?></p>
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
