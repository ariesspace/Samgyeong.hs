<?php
    $records = $records ?? [];
    $resetAt = $resetAt ?? null;
?>

<section class="page admin-users-page point-reset-admin-page point-history-admin-page">
    <div class="admin-history-head">
        <div>
            <h1>상벌점 전체 히스토리</h1>
            <p class="muted">현재 등록된 전체 인원의 상점, 벌점, 취소 기록을 최신순으로 확인합니다.</p>
            <?php if ($resetAt): ?>
                <span class="history-reset-note">현재 합계 기준: <?= e((string) $resetAt) ?> 이후</span>
            <?php endif; ?>
        </div>
        <form method="post" action="/admin/points/reset/store" class="history-reset-form" onsubmit="return confirm('상벌점 합계 기준만 0점으로 초기화할까요? 기존 히스토리는 삭제되지 않습니다.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="note" value="관리자 기준 초기화">
            <button type="submit" class="danger-button">합계 기준 초기화</button>
        </form>
    </div>

    <?php if ($saved): ?>
        <div class="notice success">상벌점 합계 기준이 0점으로 초기화되었습니다. 기존 히스토리는 그대로 보존됩니다.</div>
    <?php endif; ?>

    <section class="points-history-panel admin-point-history-panel">
        <div class="history-table-head">
            <h2>전체 기록</h2>
            <span><?= e((string) count($records)) ?>건</span>
        </div>
        <table class="board-table points-table admin-history-table">
            <thead>
                <tr>
                    <th>일자</th>
                    <th>학생</th>
                    <th>구분</th>
                    <th>점수</th>
                    <th>사유</th>
                    <th>담당</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$records): ?>
                    <tr>
                        <td colspan="7" class="empty-board">상벌점 기록이 없습니다.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($records as $record): ?>
                    <?php
                        $isCanceled = !empty($record['canceled_at']);
                        $isCancellation = !empty($record['cancellation_of_id']);
                        $studentName = student_label([
                            'display_name' => $record['target_name'] ?? '',
                            'username' => $record['target_username'] ?? '',
                            'hall_key' => $record['target_hall_key'] ?? '',
                            'year' => $record['target_year'] ?? 0,
                        ]);
                        $issuerName = trim((string) (($record['issuer_name'] ?? '') ?: ($record['issuer_username'] ?? '')));
                        $status = $isCanceled ? '취소됨' : ($isCancellation ? '취소 기록' : '유효');
                    ?>
                    <tr class="<?= $isCanceled || $isCancellation ? 'point-record-muted' : '' ?>">
                        <td data-label="일자"><?= e($record['issued_at']) ?></td>
                        <td data-label="학생"><?= e($studentName) ?></td>
                        <td data-label="구분"><span class="point-type <?= $record['type'] === 'merit' ? 'good' : 'bad' ?>"><?= $record['type'] === 'merit' ? '상점' : '벌점' ?></span></td>
                        <td data-label="점수" class="point-score-cell"><?= e(($record['type'] === 'merit' ? '+' : '-') . (string) $record['points']) ?></td>
                        <td data-label="사유" class="title-cell"><?= e($record['reason']) ?></td>
                        <td data-label="담당"><?= e($issuerName !== '' ? $issuerName : '삭제된 계정') ?></td>
                        <td data-label="상태"><span class="history-status <?= $isCanceled || $isCancellation ? 'muted' : 'active' ?>"><?= e($status) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="admin-history-mobile-list" aria-label="모바일 상벌점 전체 기록">
            <?php if (!$records): ?>
                <p class="empty-board">상벌점 기록이 없습니다.</p>
            <?php endif; ?>
            <?php foreach ($records as $record): ?>
                <?php
                    $isCanceled = !empty($record['canceled_at']);
                    $isCancellation = !empty($record['cancellation_of_id']);
                    $studentName = student_label([
                        'display_name' => $record['target_name'] ?? '',
                        'username' => $record['target_username'] ?? '',
                        'hall_key' => $record['target_hall_key'] ?? '',
                        'year' => $record['target_year'] ?? 0,
                    ]);
                    $pointSign = $record['type'] === 'merit' ? '+' : '-';
                    $shortDate = preg_replace('/^\d{4}-/', '', (string) $record['issued_at']);
                    $status = $isCanceled ? '취소' : ($isCancellation ? '취소기록' : '');
                ?>
                <article class="mobile-history-row <?= $isCanceled || $isCancellation ? 'is-muted' : '' ?>">
                    <span class="mobile-history-type <?= $record['type'] === 'merit' ? 'good' : 'bad' ?>"><?= $record['type'] === 'merit' ? '상' : '벌' ?></span>
                    <span class="mobile-history-main">
                        <strong><?= e($studentName) ?></strong>
                        <em><?= e($record['reason']) ?></em>
                    </span>
                    <b><?= e($pointSign . (string) $record['points']) ?></b>
                    <?php if ($status !== ''): ?>
                        <span class="mobile-history-status"><?= e($status) ?></span>
                    <?php else: ?>
                        <time><?= e($shortDate) ?></time>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
</section>
