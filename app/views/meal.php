<?php
    $weekdays = ['일', '월', '화', '수', '목', '금', '토'];
    $selectedTime = strtotime($selectedDate);
    $selectedLabel = $selectedTime ? date('n월 j일', $selectedTime) : $selectedDate;
?>

<section class="page meal-page">
    <header class="page-main-head">
        <p>SAMGYEONG MEAL TABLE</p>
        <h1>식단표 <span>食單表</span></h1>
        <p>월별 급식 일정과 선택한 날짜의 중식·석식을 확인할 수 있습니다.</p>
    </header>

    <nav class="meal-tabs" aria-label="급식 메뉴">
        <span>급식사진</span>
        <strong>식단표</strong>
        <span>급식 게시판</span>
        <span>영양상담</span>
    </nav>

    <div class="meal-layout">
        <section class="meal-calendar" aria-label="<?= e(date('Y년 n월', strtotime($month . '-01'))) ?> 식단 달력">
            <div class="meal-calendar-head">
                <a href="/meal?month=<?= e($prevMonth) ?>" aria-label="이전 달">‹</a>
                <strong><?= e(date('Y년 n월', strtotime($month . '-01'))) ?></strong>
                <a href="/meal?month=<?= e($nextMonth) ?>" aria-label="다음 달">›</a>
            </div>

            <div class="meal-week">
                <?php foreach ($weekdays as $weekday): ?>
                    <span><?= e($weekday) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="meal-days">
                <?php foreach ($calendarCells as $cell): ?>
                    <?php if ($cell === null): ?>
                        <span class="meal-day empty"></span>
                    <?php else: ?>
                        <?php
                            $dayNumber = (int) substr($cell, -2);
                            $weekday = (int) date('w', strtotime($cell));
                            $hasMeal = isset($meals[$cell]);
                            $classes = ['meal-day'];
                            if ($cell === $selectedDate) {
                                $classes[] = 'selected';
                            }
                            if ($cell === $today) {
                                $classes[] = 'today';
                            }
                            if ($hasMeal) {
                                $classes[] = 'has-meal';
                            }
                            if ($weekday === 0) {
                                $classes[] = 'sun';
                            } elseif ($weekday === 6) {
                                $classes[] = 'sat';
                            }
                        ?>
                        <a class="<?= e(implode(' ', $classes)) ?>" href="/meal?month=<?= e($month) ?>&date=<?= e($cell) ?>">
                            <?= e((string) $dayNumber) ?>
                            <?php if ($hasMeal): ?><i></i><?php endif; ?>
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="meal-detail">
            <header>
                <h2><?= e($selectedLabel) ?></h2>
                <?php if ($selectedDate === $today): ?><span>TODAY</span><?php endif; ?>
                <?php if (!empty($canManageMeal)): ?>
                    <a class="meal-edit-button" href="/meal?month=<?= e($month) ?>&date=<?= e($selectedDate) ?>&edit=1" aria-label="식단 수정">✎</a>
                <?php endif; ?>
            </header>

            <?php if (!empty($canManageMeal) && !empty($editMeal)): ?>
                <form class="meal-edit-form" method="post" action="/meal/save">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="meal_date" value="<?= e($selectedDate) ?>">
                    <label>
                        중식
                        <textarea name="lunch_text" rows="4" placeholder="한 줄에 메뉴 하나씩 입력"><?= e($selectedMeal['lunch_text'] ?? '') ?></textarea>
                    </label>
                    <label>
                        석식
                        <textarea name="dinner_text" rows="4" placeholder="한 줄에 메뉴 하나씩 입력"><?= e($selectedMeal['dinner_text'] ?? '') ?></textarea>
                    </label>
                    <div class="meal-edit-actions">
                        <a class="ghost-button" href="/meal?month=<?= e($month) ?>&date=<?= e($selectedDate) ?>">취소</a>
                        <button type="submit">저장</button>
                    </div>
                </form>
            <?php endif; ?>

            <div class="meal-block">
                <strong>중식</strong>
                <?php if ($selectedMeal['lunch']): ?>
                    <ul>
                        <?php foreach ($selectedMeal['lunch'] as $menu): ?>
                            <li><?= e($menu) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>등록된 중식 식단이 없습니다.</p>
                <?php endif; ?>
            </div>

            <div class="meal-block dinner">
                <strong>석식</strong>
                <?php if ($selectedMeal['dinner']): ?>
                    <ul>
                        <?php foreach ($selectedMeal['dinner'] as $menu): ?>
                            <li><?= e($menu) ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>등록된 석식 식단이 없습니다.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</section>
