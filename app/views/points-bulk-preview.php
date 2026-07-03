<section class="page points-assign-page">
    <h1>일괄 입력 미리보기</h1>
    <p class="muted">분석된 항목을 확인한 뒤 저장해 주세요. 확인 필요 항목은 저장되지 않습니다.</p>

    <section class="points-history-panel">
        <h2>저장 가능 항목 <?= e((string) count($parsed)) ?>건</h2>

        <?php if ($parsed): ?>
            <form method="post" action="/points/assign/bulk-save" class="points-bulk-save-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <?php foreach ($parsed as $item): ?>
                    <input type="hidden" name="user_id[]" value="<?= e((string) $item['user_id']) ?>">
                    <input type="hidden" name="type[]" value="<?= e($item['type']) ?>">
                    <input type="hidden" name="points[]" value="<?= e((string) $item['points']) ?>">
                    <input type="hidden" name="reason[]" value="<?= e($item['reason']) ?>">
                    <input type="hidden" name="issued_at[]" value="<?= e($item['issued_at']) ?>">
                <?php endforeach; ?>
                <button type="submit">확인된 항목 저장</button>
                <a class="button ghost-button" href="/points/assign">돌아가기</a>
            </form>
        <?php endif; ?>

        <table class="board-table points-table">
            <thead>
                <tr>
                    <th>줄</th>
                    <th>학생</th>
                    <th>구분</th>
                    <th>점수</th>
                    <th>일자</th>
                    <th>사유</th>
                    <th>원문</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$parsed): ?>
                    <tr><td colspan="7" class="empty-board">저장 가능한 항목이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($parsed as $item): ?>
                    <tr>
                        <td><?= e((string) $item['line_no']) ?></td>
                        <td><?= e($item['student_name']) ?></td>
                        <td><span class="point-type <?= $item['type'] === 'merit' ? 'good' : 'bad' ?>"><?= $item['type'] === 'merit' ? '상점' : '벌점' ?></span></td>
                        <td><?= e(($item['type'] === 'merit' ? '+' : '-') . (string) $item['points']) ?></td>
                        <td><?= e($item['issued_at']) ?></td>
                        <td class="title-cell"><?= e($item['reason']) ?></td>
                        <td class="title-cell"><?= e($item['raw_line']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="points-history-panel">
        <h2>확인 필요 항목 <?= e((string) count($failed)) ?>건</h2>
        <table class="board-table points-table">
            <thead>
                <tr>
                    <th>줄</th>
                    <th>원문</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$failed): ?>
                    <tr><td colspan="3" class="empty-board">확인 필요 항목이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($failed as $item): ?>
                    <tr>
                        <td><?= e((string) $item['line_no']) ?></td>
                        <td class="title-cell"><?= e($item['raw_line']) ?></td>
                        <td><?= e($item['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
