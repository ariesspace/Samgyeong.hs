<?php
    $total = array_reduce($orders, fn (int $sum, array $order): int => $sum + (int) $order['total_price'], 0);
    $quantity = array_reduce($orders, fn (int $sum, array $order): int => $sum + (int) $order['quantity'], 0);
    $receiptNo = $orders ? 'SGM-' . date('Ymd') . '-' . str_pad((string) $orders[0]['id'], 4, '0', STR_PAD_LEFT) : '';
    $orderedAt = $orders ? substr((string) $orders[0]['created_at'], 0, 16) : '';
?>

<section class="page mall-receipt-page">
    <?php if (!$orders): ?>
        <section class="receipt-empty">
            <p class="shop-brand">SAMGYEONG<span>.MALL</span></p>
            <h1>확인할 결제 내역이 없습니다.</h1>
            <p>결제 완료 직후에만 영수증이 표시됩니다. 구매 내역은 마이페이지에서 확인할 수 있습니다.</p>
            <div class="receipt-actions">
                <a class="button secondary" href="/samgyeong-mall">쇼핑 계속 하기</a>
                <a class="button" href="/mypage/points#mall-orders">구매 내역 확인하기</a>
            </div>
        </section>
    <?php else: ?>
        <header class="receipt-hero">
            <div>
                <p class="shop-brand">SAMGYEONG<span>.MALL</span></p>
                <h1>결제가 완료되었습니다.</h1>
                <p>구매한 포상 상품은 삼경원 확인 후 실제 혜택으로 적용됩니다.</p>
            </div>
            <div class="receipt-stamp" aria-hidden="true">
                <span>PAID</span>
                <strong>완료</strong>
            </div>
        </header>

        <section class="receipt-paper" aria-label="삼경몰 결제 영수증">
            <div class="receipt-paper-head">
                <div>
                    <span>RECEIPT</span>
                    <h2>삼경몰 영수증</h2>
                </div>
                <strong><?= e($receiptNo) ?></strong>
            </div>

            <dl class="receipt-meta">
                <div>
                    <dt>구매자</dt>
                    <dd><?= e(($user['display_name'] ?? '') ?: ($user['username'] ?? '')) ?></dd>
                </div>
                <div>
                    <dt>결제일시</dt>
                    <dd><?= e($orderedAt) ?></dd>
                </div>
                <div>
                    <dt>상품 수량</dt>
                    <dd><?= e((string) $quantity) ?>개</dd>
                </div>
            </dl>

            <table class="receipt-table">
                <thead>
                    <tr>
                        <th>상품명</th>
                        <th>수량</th>
                        <th>단가</th>
                        <th>합계</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td data-label="상품명"><?= e($order['item_name']) ?></td>
                            <td data-label="수량"><?= e((string) $order['quantity']) ?>개</td>
                            <td data-label="단가"><?= e((string) $order['price']) ?> P</td>
                            <td data-label="합계"><?= e((string) $order['total_price']) ?> P</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="receipt-total">
                <span>총 결제 포인트</span>
                <strong><?= e((string) $total) ?> P</strong>
            </div>

            <p class="receipt-note">이 영수증은 삼경몰 포상 상품 구매 기록이며, 실제 사용 완료 여부는 마이페이지의 삼경몰 상품 목록에서 확인할 수 있습니다.</p>
        </section>

        <div class="receipt-actions">
            <a class="button secondary" href="/samgyeong-mall">쇼핑 계속 하기</a>
            <a class="button" href="/mypage/points#mall-orders">결제 완료 내역 확인하기</a>
            <button type="button" class="button print-button" onclick="window.print()">영수증 출력</button>
        </div>
    <?php endif; ?>
</section>
