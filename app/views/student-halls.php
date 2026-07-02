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

    $selectedHall = $selectedHall ?? '';
?>

<section class="page hall-status-page">
    <header class="text-page-head">
        <h1><?= $selectedHall ? e($halls[$selectedHall]['name']) : '관별 현황' ?></h1>
    </header>

    <?php if ($selectedHall): ?>
        <?php $hall = $halls[$selectedHall]; ?>
        <section class="hall-album-section">
            <div class="section-title-row">
                <h2><?= e($hall['name']) ?> 앨범</h2>
                <span><?= e($hall['meaning']) ?>을 공경하는 관</span>
            </div>

            <div class="hall-album-grid">
                <?php if (!$hall['students']): ?>
                    <p class="empty-board">등록된 학생이 없습니다.</p>
                <?php endif; ?>

                <?php foreach ($hall['students'] as $student): ?>
                    <article class="hall-album-card <?= e($hall['color']) ?>">
                        <div class="hall-album-symbol" aria-hidden="true">
                            <img src="<?= !empty($student['photo_path']) ? '/uploads/' . e($student['photo_path']) : '/assets/samgyeong-emblem.png' ?>" alt="">
                        </div>
                        <strong><?= e($student['student_name']) ?></strong>
                        <span><?= e((string) $student['year']) ?>학년</span>
                        <em><?= trim($student['role_label']) !== '' ? e($student['role_label']) : '관원' ?></em>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php else: ?>

    <nav class="hall-tabs" aria-label="관별 현황 보기">
        <button class="active" type="button" data-hall-tab="hall-status">관별 인원현황</button>
        <button type="button" data-hall-tab="hall-members">관장단 및 자치기구 명단</button>
    </nav>

    <section id="hall-status" class="hall-status-section hall-tab-panel active">
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

    <section id="hall-members" class="hall-members-section hall-tab-panel" hidden>
        <div class="section-title-row">
            <h2>관장단 및 자치기구 조직도</h2>
        </div>

        <div class="hall-org-chart">
            <div class="org-root">
                <strong>삼경원 (三敬院)</strong>
                <span>최고 학생 자치기구</span>
            </div>

            <div class="org-trunk" aria-hidden="true"></div>
            <div class="org-split" aria-hidden="true">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <div class="org-branches">
                <?php foreach ($halls as $hall): ?>
                    <article class="org-branch <?= e($hall['color']) ?>">
                        <div class="org-hall-chief">
                            <strong><?= e($hall['name']) ?>장</strong>
                            <span>3학년 관장</span>
                        </div>

                        <div class="org-sub-unit">
                            <strong><?= e($hall['name']) ?> 자치부</strong>
                            <span>2학년 부관장</span>
                        </div>

                        <div class="org-members">
                            관원
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>
</section>

<?php if (!$selectedHall): ?>
    <script src="/hall-tabs.js"></script>
<?php endif; ?>
