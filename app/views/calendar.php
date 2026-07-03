<?php
    $firstDay = strtotime($month . '-01');
    $daysInMonth = (int) date('t', $firstDay);
    $startOffset = (int) date('w', $firstDay);
    $eventsByDay = [];
    foreach ($events as $event) {
        $day = (int) substr($event['event_date'], 8, 2);
        $eventsByDay[$day][] = $event;
    }
?>

<section class="page calendar-page">
    <header class="page-title">
        <h1>일정 캘린더</h1>
    </header>

    <form method="post" action="/calendar/events/store" class="calendar-event-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
            날짜
            <input type="date" name="event_date" value="<?= e($month) ?>-01" required>
        </label>
        <label>
            일정명
            <input name="title" maxlength="80" required placeholder="일정명을 입력하세요">
        </label>
        <label>
            분류
            <select name="category">
                <option value="general">일반</option>
                <option value="important">중요</option>
                <option value="check">점검</option>
            </select>
        </label>
        <button type="submit">일정 추가</button>
    </form>

    <div class="calendar">
        <div class="calendar-head"><?= e(date('Y년 n월', $firstDay)) ?> 학생회 일정</div>
        <div class="calendar-week">
            <?php foreach (['일', '월', '화', '수', '목', '금', '토'] as $day): ?>
                <span><?= e($day) ?></span>
            <?php endforeach; ?>
        </div>
        <div class="calendar-days">
            <?php for ($i = 0; $i < $startOffset; $i++): ?>
                <div></div>
            <?php endfor; ?>
            <?php for ($date = 1; $date <= $daysInMonth; $date++): ?>
                <div>
                    <strong><?= e((string) $date) ?></strong>
                    <?php foreach ($eventsByDay[$date] ?? [] as $event): ?>
                        <div class="calendar-event <?= e($event['category']) ?>">
                            <span><?= e($event['title']) ?></span>
                            <form method="post" action="/calendar/events/delete" onsubmit="return confirm('삭제하시겠습니까?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                                <input type="hidden" name="month" value="<?= e($month) ?>">
                                <button type="submit" aria-label="일정 삭제">×</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</section>
