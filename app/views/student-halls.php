<section class="page">
    <h1>관별 명단</h1>
    <p class="muted">각 관의 대표 학생 명단을 정리하는 공간입니다.</p>

    <div class="hall-grid">
        <?php
            $halls = [
                ['name' => '경천관', 'meaning' => '하늘', 'color' => 'blue', 'students' => [['이도윤', 3, '관장'], ['김서윤', 2, '부관장'], ['박지우', 1, '대표']]],
                ['name' => '경인관', 'meaning' => '사람', 'color' => 'gold', 'students' => [['최우진', 3, '관장'], ['정하은', 2, '부관장'], ['강하린', 1, '대표']]],
                ['name' => '경물관', 'meaning' => '만물', 'color' => 'green', 'students' => [['송준기', 3, '관장'], ['유승호', 2, '부관장'], ['오진우', 1, '대표']]],
            ];
        ?>
        <?php foreach ($halls as $hall): ?>
            <article class="hall-card <?= e($hall['color']) ?>">
                <h2><?= e($hall['name']) ?> <span><?= e($hall['meaning']) ?></span></h2>
                <ul>
                    <?php foreach ($hall['students'] as $student): ?>
                        <li>
                            <span><?= e($student[2]) ?></span>
                            <strong><?= e($student[0]) ?></strong>
                            <em><?= e((string) $student[1]) ?>학년</em>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </article>
        <?php endforeach; ?>
    </div>
</section>
