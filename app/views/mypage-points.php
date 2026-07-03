<?php
    $meritTotal = 0;
    $demeritTotal = 0;
    foreach ($records as $record) {
        if ($record['type'] === 'merit') {
            $meritTotal += (int) $record['points'];
        } else {
            $demeritTotal += (int) $record['points'];
        }
    }
    $wishCoupons = intdiv($meritTotal, 10);
?>

<section class="page mypage-page">
    <h1>상벌점 현황</h1>
    <p class="muted">본인에게 부여된 상점과 벌점 기록을 확인할 수 있습니다.</p>

    <div class="points-summary">
        <article>
            <span>상점</span>
            <strong><?= e((string) $meritTotal) ?>점</strong>
        </article>
        <article>
            <span>벌점</span>
            <strong><?= e((string) $demeritTotal) ?>점</strong>
        </article>
        <article>
            <span>소원권</span>
            <strong><?= e((string) $wishCoupons) ?>개</strong>
        </article>
    </div>

    <table class="board-table points-table my-points-table">
        <thead>
            <tr>
                <th>일자</th>
                <th>구분</th>
                <th>점수</th>
                <th>사유</th>
                <th>담당</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$records): ?>
                <tr>
                    <td colspan="5" class="empty-board">상벌점 기록이 없습니다.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td data-label="일자"><?= e($record['issued_at']) ?></td>
                    <td data-label="구분"><span class="point-type <?= $record['type'] === 'merit' ? 'good' : 'bad' ?>"><?= $record['type'] === 'merit' ? '상점' : '벌점' ?></span></td>
                    <td data-label="점수"><?= e(($record['type'] === 'merit' ? '+' : '-') . (string) $record['points']) ?></td>
                    <td data-label="사유" class="title-cell"><?= e($record['reason']) ?></td>
                    <td data-label="담당"><?= e($record['issuer_name'] ?: $record['issuer_username']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
