<section class="page">
    <h1>일정 캘린더</h1>
    <div class="calendar">
        <div class="calendar-head">2026년 7월 학생회 일정</div>
        <div class="calendar-week">
            <?php foreach (['일', '월', '화', '수', '목', '금', '토'] as $day): ?>
                <span><?= e($day) ?></span>
            <?php endforeach; ?>
        </div>
        <div class="calendar-days">
            <?php for ($i = 0; $i < 3; $i++): ?>
                <div></div>
            <?php endfor; ?>
            <?php for ($date = 1; $date <= 31; $date++): ?>
                <div>
                    <strong><?= $date ?></strong>
                    <?php if ($date === 1): ?><span>학생회 회의</span><?php endif; ?>
                    <?php if ($date === 15): ?><span class="red">관장단 간담회</span><?php endif; ?>
                    <?php if ($date === 25): ?><span class="green">시설 보수 점검</span><?php endif; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>
