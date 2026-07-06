<?php
    $meritTotal = (int) ($points['merit_total'] ?? 0);
    $demeritTotal = (int) ($points['demerit_total'] ?? 0);
    $mallOrders = $mallOrders ?? [];
    $mallOrderCount = user_mall_order_quantity($mallOrders);
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
            <span>삼경몰 상품 목록</span>
            <strong><?= e((string) $mallOrderCount) ?>개</strong>
        </article>
    </div>

    <?php if (!empty($points['reset_at'])): ?>
        <p class="muted small-note">현재 합계는 <?= e((string) $points['reset_at']) ?> 이후 기록 기준입니다. 기존 히스토리는 삭제되지 않습니다.</p>
    <?php endif; ?>

    <h2 class="subsection-title">상벌점 히스토리</h2>
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
                <?php
                    $isCanceled = !empty($record['canceled_at']);
                    $isCancellation = !empty($record['cancellation_of_id']);
                ?>
                <tr class="<?= $isCanceled || $isCancellation ? 'point-record-muted' : '' ?>">
                    <td data-label="일자"><?= e($record['issued_at']) ?></td>
                    <td data-label="구분"><span class="point-type <?= $record['type'] === 'merit' ? 'good' : 'bad' ?>"><?= $record['type'] === 'merit' ? '상점' : '벌점' ?></span></td>
                    <td data-label="점수"><?= e(($record['type'] === 'merit' ? '+' : '-') . (string) $record['points']) ?></td>
                    <td data-label="사유" class="title-cell">
                        <?= e($record['reason']) ?>
                        <?php if ($isCanceled): ?>
                            <span class="point-status">취소됨</span>
                        <?php elseif ($isCancellation): ?>
                            <span class="point-status">취소 기록</span>
                        <?php endif; ?>
                    </td>
                    <td data-label="담당"><?= e($record['issuer_name'] ?: $record['issuer_username']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2 id="mall-orders" class="subsection-title">삼경몰 상품 목록</h2>
    <table class="board-table points-table my-points-table">
        <thead>
            <tr>
                <th>구매일</th>
                <th>상품</th>
                <th>수량</th>
                <th>사용 포인트</th>
                <th>상태</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$mallOrders): ?>
                <tr>
                    <td colspan="5" class="empty-board">구매한 삼경몰 상품이 없습니다.</td>
                </tr>
            <?php endif; ?>
            <?php foreach ($mallOrders as $order): ?>
                <tr>
                    <td data-label="구매일"><?= e(substr($order['created_at'], 0, 10)) ?></td>
                    <td data-label="상품" class="title-cell"><?= e($order['item_name']) ?></td>
                    <td data-label="수량"><?= e((string) $order['quantity']) ?>개</td>
                    <td data-label="사용 포인트"><?= e((string) $order['total_price']) ?>점</td>
                    <td data-label="상태">
                        <?php if (!empty($order['used_at'])): ?>
                            <span class="mall-order-status used">사용 완료</span>
                            <small><?= e(substr($order['used_at'], 0, 10)) ?></small>
                        <?php else: ?>
                            <span class="mall-order-status pending">사용 대기</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
