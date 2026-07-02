<section class="page">
    <h1>관별 명단</h1>
    <p class="muted">각 관의 대표 학생 명단을 정리하는 공간입니다.</p>

    <div class="hall-grid">
        <?php
            $halls = [];
            foreach ($members as $member) {
                $key = $member['hall_key'];
                if (!isset($halls[$key])) {
                    $halls[$key] = [
                        'name' => $member['hall_name'],
                        'meaning' => $member['hall_meaning'],
                        'color' => $member['hall_color'],
                        'students' => [],
                    ];
                }
                $halls[$key]['students'][] = $member;
            }
        ?>
        <?php foreach ($halls as $hall): ?>
            <article class="hall-card <?= e($hall['color']) ?>">
                <h2><?= e($hall['name']) ?> <span><?= e($hall['meaning']) ?></span></h2>
                <ul>
                    <?php foreach ($hall['students'] as $student): ?>
                        <li>
                            <?php if (trim($student['role_label']) !== ''): ?>
                                <span><?= e($student['role_label']) ?></span>
                            <?php else: ?>
                                <span class="empty-role"></span>
                            <?php endif; ?>
                            <strong><?= e($student['student_name']) ?></strong>
                            <em><?= e((string) $student['year']) ?>학년</em>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>
</section>
