<section class="page mall-page">
    <header class="mall-hero">
        <p class="eyebrow">SAMGYEONG REWARD MALL</p>
        <h1>삼경몰</h1>
        <p>모은 상점을 포상 혜택으로 교환하는 공간입니다. 구매 기록은 남고, 사용 가능 포인트에서 차감됩니다.</p>
    </header>

    <?php if ($saved === 'cart'): ?>
        <div class="notice success">장바구니가 업데이트되었습니다.</div>
    <?php elseif ($saved === 'checkout'): ?>
        <div class="notice success">구매가 완료되었습니다. 삼경원 확인 후 혜택이 적용됩니다.</div>
    <?php elseif ($error === 'points'): ?>
        <div class="notice error">사용 가능한 상점이 부족합니다.</div>
    <?php elseif ($error === 'empty'): ?>
        <div class="notice error">장바구니가 비어 있습니다.</div>
    <?php elseif ($error === 'checkout'): ?>
        <div class="notice error">결제 처리 중 오류가 발생했습니다.</div>
    <?php endif; ?>

    <section class="mall-status-grid">
        <article>
            <span>현재 상점</span>
            <strong><?= e((string) ($points['merit_total'] ?? 0)) ?>점</strong>
        </article>
        <article>
            <span>사용한 상점</span>
            <strong><?= e((string) ($points['spent_total'] ?? 0)) ?>점</strong>
        </article>
        <article>
            <span>구매 가능</span>
            <strong><?= e((string) ($points['available_total'] ?? 0)) ?>점</strong>
        </article>
    </section>

    <?php if (!$studentOpen && (current_user()['role'] ?? '') === 'admin'): ?>
        <p class="mall-admin-note">현재 학생 구매 기간은 닫혀 있습니다. 슈퍼관리자 미리보기 상태입니다.</p>
    <?php endif; ?>
    <?php if (!empty($points['reset_at'])): ?>
        <p class="muted small-note">현재 포인트는 <?= e((string) $points['reset_at']) ?> 이후 기록 기준입니다.</p>
    <?php endif; ?>

    <div class="mall-layout">
        <section class="mall-items">
            <?php foreach ($items as $item): ?>
                <article class="mall-card">
                    <div class="mall-card-head">
                        <span><?= e((string) $item['price']) ?>점</span>
                        <strong><?= e($item['name']) ?></strong>
                    </div>
                    <p><?= e($item['description']) ?></p>
                    <form method="post" action="/samgyeong-mall/cart/add">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                        <button type="submit">장바구니 담기</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </section>

        <aside class="mall-cart">
            <h2>장바구니</h2>
            <?php if (!$cart['items']): ?>
                <p class="empty-board">담긴 상품이 없습니다.</p>
            <?php endif; ?>
            <?php foreach ($cart['items'] as $item): ?>
                <div class="mall-cart-row">
                    <div>
                        <strong><?= e($item['name']) ?></strong>
                        <span><?= e((string) $item['price']) ?>점 x <?= e((string) $item['quantity']) ?></span>
                    </div>
                    <form method="post" action="/samgyeong-mall/cart/remove">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                        <button type="submit" aria-label="삭제">×</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <div class="mall-cart-total">
                <span>합계</span>
                <strong><?= e((string) $cart['total']) ?>점</strong>
            </div>
            <form method="post" action="/samgyeong-mall/checkout" onsubmit="return confirm('장바구니 상품을 구매할까요? 사용한 상점은 구매 가능 포인트에서 차감됩니다.');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button type="submit" <?= !$cart['items'] ? 'disabled' : '' ?>>결제하기</button>
            </form>
        </aside>
    </div>

    <section class="mall-orders">
        <h2>최근 구매 내역</h2>
        <table class="board-table points-table">
            <thead>
                <tr>
                    <th>일시</th>
                    <th>상품</th>
                    <th>수량</th>
                    <th>사용 상점</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="4" class="empty-board">구매 내역이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e($order['created_at']) ?></td>
                        <td class="title-cell"><?= e($order['item_name']) ?></td>
                        <td><?= e((string) $order['quantity']) ?></td>
                        <td><?= e((string) $order['total_price']) ?>점</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
