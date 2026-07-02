<?php
    $halls = [];
    foreach (hall_definitions() as $key => $hall) {
        $halls[$key] = $hall + [
            'students' => [],
            'years' => [1 => 0, 2 => 0, 3 => 0],
            'total' => 0,
        ];
    }

    foreach ($members as $member) {
        $key = $member['hall_key'];
        if (!isset($halls[$key])) {
            continue;
        }

        $year = (int) $member['year'];
        $halls[$key]['students'][] = $member;
        if (isset($halls[$key]['years'][$year])) {
            $halls[$key]['years'][$year]++;
        }
        $halls[$key]['total']++;
    }

    $yearTotals = [1 => 0, 2 => 0, 3 => 0];
    $grandTotal = 0;
    foreach ($halls as $hall) {
        foreach ($hall['years'] as $year => $count) {
            $yearTotals[$year] += $count;
        }
        $grandTotal += $hall['total'];
    }
?>

<section class="page hall-status-page">
    <header class="text-page-head">
        <h1>관별 현황</h1>
    </header>

    <nav class="hall-tabs" aria-label="관별 현황 보기">
        <a class="active" href="#hall-status">관별 인원현황</a>
        <a href="#hall-members">관장단 및 자치기구 명단</a>
    </nav>

    <section id="hall-status" class="hall-status-section">
        <div class="section-title-row">
            <h2>관별 인원현황</h2>
            <span><?= e(date('Y.m.d.')) ?> 기준</span>
        </div>

        <div class="hall-status-table-wrap">
            <table class="hall-status-table">
                <thead>
                    <tr>
                        <th>관별 구분</th>
                        <th>1학년</th>
                        <th>2학년</th>
                        <th>3학년</th>
                        <th>합계</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($halls as $hall): ?>
                        <tr>
                            <th class="hall-name <?= e($hall['color']) ?>"><?= e($hall['name']) ?> <span>(<?= e($hall['meaning']) ?>)</span></th>
                            <td><?= e((string) $hall['years'][1]) ?></td>
                            <td><?= e((string) $hall['years'][2]) ?></td>
                            <td><?= e((string) $hall['years'][3]) ?></td>
                            <td class="total"><?= e((string) $hall['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>총 합계</th>
                        <td><?= e((string) $yearTotals[1]) ?></td>
                        <td><?= e((string) $yearTotals[2]) ?></td>
                        <td><?= e((string) $yearTotals[3]) ?></td>
                        <td><?= e((string) $grandTotal) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    <section id="hall-members" class="hall-members-section">
        <div class="section-title-row">
            <h2>관장단 및 자치기구 명단</h2>
            <span>관리자 입력 명단 기준</span>
        </div>

        <div class="hall-grid compact">
            <?php foreach ($halls as $hall): ?>
                <article class="hall-card <?= e($hall['color']) ?>">
                    <h2><?= e($hall['name']) ?> <span><?= e($hall['meaning']) ?></span></h2>
                    <ul>
                        <?php if (!$hall['students']): ?>
                            <li class="empty-hall-member">등록된 학생이 없습니다.</li>
                        <?php endif; ?>

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
</section>
