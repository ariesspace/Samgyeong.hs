<section class="page points-assign-page">
    <h1>상벌점 부여</h1>
    <p class="muted">삼경원 및 관리자가 학생에게 상점 또는 벌점을 부여하는 페이지입니다.</p>

    <?php if ($saved === '1'): ?>
        <div class="success">상벌점이 저장되었습니다.</div>
    <?php elseif ($saved === 'deleted'): ?>
        <div class="success">상벌점 기록이 삭제되었습니다.</div>
    <?php endif; ?>

    <section class="points-assign-panel">
        <h2>상벌점 입력</h2>
        <form method="post" action="/points/assign/store" class="points-assign-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                학생
                <select name="user_id" required>
                    <option value="">학생 선택</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= e((string) $student['id']) ?>">
                            <?= e(($student['display_name'] ?: $student['username'])) ?>
                            <?= e(hall_label($student['hall_key'] ?? '')) ?>
                            <?= (int) ($student['year'] ?? 0) > 0 ? e((string) $student['year']) . '학년' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                구분
                <select name="type" required>
                    <option value="merit">상점</option>
                    <option value="demerit">벌점</option>
                </select>
            </label>
            <label>
                점수
                <input type="number" name="points" min="1" max="100" value="1" required>
            </label>
            <label>
                일자
                <input type="date" name="issued_at" value="<?= e(date('Y-m-d')) ?>" required>
            </label>
            <label class="points-reason-field">
                사유
                <input name="reason" maxlength="160" required placeholder="예: 자습실 정리 우수, 생활 규정 미준수">
            </label>
            <button type="submit">부여하기</button>
        </form>
    </section>

    <section class="points-history-panel">
        <h2>최근 부여 기록</h2>
        <table class="board-table points-table">
            <thead>
                <tr>
                    <th>일자</th>
                    <th>학생</th>
                    <th>구분</th>
                    <th>점수</th>
                    <th>사유</th>
                    <th>부여자</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$records): ?>
                    <tr>
                        <td colspan="7" class="empty-board">부여 기록이 없습니다.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?= e($record['issued_at']) ?></td>
                        <td><?= e($record['target_name'] ?: $record['target_username']) ?></td>
                        <td><span class="point-type <?= $record['type'] === 'merit' ? 'good' : 'bad' ?>"><?= $record['type'] === 'merit' ? '상점' : '벌점' ?></span></td>
                        <td><?= e(($record['type'] === 'merit' ? '+' : '-') . (string) $record['points']) ?></td>
                        <td class="title-cell"><?= e($record['reason']) ?></td>
                        <td><?= e($record['issuer_name'] ?: $record['issuer_username']) ?></td>
                        <td>
                            <form class="board-row-delete" method="post" action="/points/assign/delete" onsubmit="return confirm('삭제하시겠습니까?');">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="id" value="<?= e((string) $record['id']) ?>">
                                <button type="submit">삭제</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
