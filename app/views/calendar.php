<?php
    $firstDay = strtotime($month . '-01');
    $daysInMonth = (int) date('t', $firstDay);
    $startOffset = (int) date('w', $firstDay);
    $prevMonth = date('Y-m', strtotime('-1 month', $firstDay));
    $nextMonth = date('Y-m', strtotime('+1 month', $firstDay));
    $currentMonth = date('Y-m');
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
        <div class="calendar-head">
            <a href="/calendar?month=<?= e($prevMonth) ?>" aria-label="이전달">‹</a>
            <a class="calendar-head-title" href="/calendar?month=<?= e($currentMonth) ?>" title="이번달로 이동">
                <?= e(date('Y년 n월', $firstDay)) ?> 학생회 일정
            </a>
            <a href="/calendar?month=<?= e($nextMonth) ?>" aria-label="다음달">›</a>
        </div>
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
                <?php $dayEvents = $eventsByDay[$date] ?? []; ?>
                <?php
                    $dayEventData = array_map(
                        fn ($event) => [
                            'title' => $event['title'],
                            'category' => $event['category'],
                        ],
                        $dayEvents
                    );
                    $fullDate = $month . '-' . str_pad((string) $date, 2, '0', STR_PAD_LEFT);
                ?>
                <div
                    class="calendar-day-cell <?= $dayEvents ? 'has-events' : '' ?>"
                    role="button"
                    tabindex="0"
                    data-calendar-day
                    data-date="<?= e(str_replace('-', '.', $fullDate)) ?>"
                    data-events="<?= e(json_encode($dayEventData, JSON_UNESCAPED_UNICODE)) ?>"
                >
                    <strong><?= e((string) $date) ?></strong>
                    <?php if ($dayEvents): ?>
                        <em class="calendar-event-count"><?= e((string) count($dayEvents)) ?></em>
                    <?php endif; ?>
                    <?php foreach ($dayEvents as $event): ?>
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

    <section class="calendar-mobile-list">
        <h2><?= e(date('n월', $firstDay)) ?> 일정</h2>
        <?php if (!$events): ?>
            <p>등록된 일정이 없습니다.</p>
        <?php endif; ?>
        <?php foreach ($events as $event): ?>
            <?php
                $eventDay = (int) substr($event['event_date'], 8, 2);
                $listDayEvents = $eventsByDay[$eventDay] ?? [];
                $listEventData = array_map(
                    fn ($item) => [
                        'title' => $item['title'],
                        'category' => $item['category'],
                    ],
                    $listDayEvents
                );
            ?>
            <article
                class="calendar-list-item <?= e($event['category']) ?>"
                role="button"
                tabindex="0"
                data-calendar-day
                data-date="<?= e(str_replace('-', '.', $event['event_date'])) ?>"
                data-events="<?= e(json_encode($listEventData, JSON_UNESCAPED_UNICODE)) ?>"
            >
                <time><?= e(str_replace('-', '.', $event['event_date'])) ?></time>
                <span><?= e($event['title']) ?></span>
                <form method="post" action="/calendar/events/delete" onsubmit="return confirm('삭제하시겠습니까?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $event['id']) ?>">
                    <input type="hidden" name="month" value="<?= e($month) ?>">
                    <button type="submit" aria-label="일정 삭제">삭제</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
</section>

<div class="calendar-day-modal" data-calendar-modal hidden>
    <div class="calendar-day-dialog">
        <div class="calendar-day-dialog-head">
            <h2 data-calendar-modal-title>일정</h2>
            <button type="button" class="icon-button" data-calendar-modal-close aria-label="닫기">×</button>
        </div>
        <div class="calendar-day-dialog-body" data-calendar-modal-body></div>
    </div>
</div>

<script>
(() => {
    const modal = document.querySelector('[data-calendar-modal]');
    const title = document.querySelector('[data-calendar-modal-title]');
    const body = document.querySelector('[data-calendar-modal-body]');
    if (!modal || !title || !body) {
        return;
    }

    const openDay = (cell) => {
        let events = [];
        try {
            events = JSON.parse(cell.dataset.events || '[]');
        } catch (_) {
            events = [];
        }

        title.textContent = `${cell.dataset.date || ''} 일정`;
        body.innerHTML = '';

        if (events.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'calendar-day-empty';
            empty.textContent = '등록된 일정이 없습니다.';
            body.appendChild(empty);
        } else {
            events.forEach((event) => {
                const item = document.createElement('p');
                item.className = `calendar-day-popup-item ${event.category || 'general'}`;
                item.textContent = event.title || '';
                body.appendChild(item);
            });
        }

        modal.hidden = false;
    };

    document.querySelectorAll('[data-calendar-day]').forEach((cell) => {
        cell.addEventListener('click', (event) => {
            if (event.target.closest('form, button, a')) {
                return;
            }
            openDay(cell);
        });
        cell.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openDay(cell);
            }
        });
    });

    document.querySelectorAll('[data-calendar-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            modal.hidden = true;
        });
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.hidden = true;
        }
    });
})();
</script>
