<?php
    $productCount = count($items);
    $cartCount = array_sum(array_map(fn ($item) => (int) $item['quantity'], $cart['items']));
    $availablePoints = (int) ($points['available_total'] ?? 0);
    $receiptOrders = $receiptOrders ?? [];
    $receiptTotal = array_reduce($receiptOrders, fn (int $sum, array $order): int => $sum + (int) $order['total_price'], 0);
    $receiptQuantity = array_reduce($receiptOrders, fn (int $sum, array $order): int => $sum + (int) $order['quantity'], 0);
    $receiptNo = $receiptOrders ? 'SGM-' . date('Ymd') . '-' . str_pad((string) $receiptOrders[0]['id'], 4, '0', STR_PAD_LEFT) : '';
    $orderedAt = $receiptOrders ? substr((string) $receiptOrders[0]['created_at'], 0, 16) : '';
    $buyerName = ($user['display_name'] ?? '') ?: ($user['username'] ?? '');
?>

<section class="page mall-page shop-page">
    <?php if ($saved === 'cart'): ?>
        <div class="success">장바구니가 업데이트되었습니다.</div>
    <?php elseif ($error === 'points'): ?>
        <div class="error">사용 가능한 상점이 부족합니다.</div>
    <?php elseif ($error === 'empty'): ?>
        <div class="error">장바구니가 비어 있습니다.</div>
    <?php elseif ($error === 'checkout'): ?>
        <div class="error">결제 처리 중 오류가 발생했습니다.</div>
    <?php elseif ($error === 'soldout'): ?>
        <div class="error">품절된 상품이 포함되어 있습니다. 장바구니를 다시 확인해 주세요.</div>
    <?php endif; ?>

    <?php if ($receiptOrders): ?>
        <div class="mall-receipt-modal-backdrop" data-receipt-modal>
            <section class="mall-receipt-modal" role="dialog" aria-modal="true" aria-labelledby="mall-receipt-title">
                <button type="button" class="mall-receipt-close" data-receipt-close aria-label="영수증 닫기">×</button>
                <div class="mall-receipt-done">
                    <span>결제 완료</span>
                    <h2 id="mall-receipt-title">구매가 정상적으로 처리되었습니다.</h2>
                    <p>구매한 포상 상품은 삼경원 확인 후 실제 혜택으로 적용됩니다. 아래 영수증을 확인해 주세요.</p>
                </div>

                <div class="mall-receipt-ticket" id="mall-receipt-copy">
                    <div class="mall-receipt-ticket-head">
                        <div>
                            <span>RECEIPT</span>
                            <strong>삼경몰 영수증</strong>
                        </div>
                        <em><?= e($receiptNo) ?></em>
                    </div>
                    <dl class="mall-receipt-summary">
                        <div>
                            <dt>구매자</dt>
                            <dd><?= e($buyerName) ?></dd>
                        </div>
                        <div>
                            <dt>결제일시</dt>
                            <dd><?= e($orderedAt) ?></dd>
                        </div>
                        <div>
                            <dt>상품 수량</dt>
                            <dd><?= e((string) $receiptQuantity) ?>개</dd>
                        </div>
                    </dl>
                    <div class="mall-receipt-lines">
                        <?php foreach ($receiptOrders as $order): ?>
                            <div>
                                <span><?= e($order['item_name']) ?></span>
                                <b><?= e((string) $order['quantity']) ?>개</b>
                                <strong><?= e((string) $order['total_price']) ?> P</strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mall-receipt-ticket-total">
                        <span>총 결제 포인트</span>
                        <strong><?= e((string) $receiptTotal) ?> P</strong>
                    </div>
                </div>

                <div class="mall-receipt-modal-actions">
                    <button type="button" class="button secondary" data-copy-receipt>영수증 복사</button>
                    <a class="button secondary" href="/mypage/points#mall-orders">구매 내역 보기</a>
                    <a class="button" href="/samgyeong-mall">쇼핑 계속하기</a>
                </div>
            </section>
        </div>
        <script>
            (() => {
                const modal = document.querySelector('[data-receipt-modal]');
                const closeButton = document.querySelector('[data-receipt-close]');
                const copyButton = document.querySelector('[data-copy-receipt]');
                const close = () => {
                    if (modal) modal.hidden = true;
                    if (window.history.replaceState) {
                        window.history.replaceState({}, '', '/samgyeong-mall');
                    }
                };
                closeButton?.addEventListener('click', close);
                modal?.addEventListener('click', (event) => {
                    if (event.target === modal) close();
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') close();
                });
                copyButton?.addEventListener('click', async () => {
                    const receipt = document.getElementById('mall-receipt-copy');
                    const text = receipt?.innerText.trim() || '';
                    try {
                        await navigator.clipboard.writeText(text);
                        copyButton.textContent = '복사 완료';
                    } catch (error) {
                        copyButton.textContent = '복사 불가';
                    }
                });
            })();
        </script>
    <?php endif; ?>

    <header class="shop-topbar">
        <div>
            <p class="shop-brand">SAMGYEONG<span>.MALL</span></p>
            <h1>포인트 교환소</h1>
            <p>학교생활로 모은 상점을 필요한 포상 혜택으로 교환하세요.</p>
        </div>
        <div class="shop-wallet">
            <span>구매 가능 포인트</span>
            <strong><?= e((string) $availablePoints) ?> P</strong>
        </div>
    </header>

    <div class="shop-layout">
        <section class="shop-products">
            <div class="shop-products-head">
                <div>
                    <h2>상품 목록</h2>
                    <p>총 <?= e((string) $productCount) ?>개의 포상 상품</p>
                </div>
                <span>낮은 포인트순</span>
            </div>

            <div class="shop-product-grid">
                <?php foreach ($items as $index => $item): ?>
                    <?php $soldOut = !empty($item['sold_out']); ?>
                    <article class="shop-product-card tone-<?= e((string) (($index % 5) + 1)) ?> <?= $soldOut ? 'is-sold-out' : '' ?>">
                        <div class="shop-product-visual">
                            <span><?= e(mb_substr($item['name'], 0, 1)) ?></span>
                            <?php if ($soldOut): ?>
                                <strong>품절</strong>
                            <?php elseif (($item['remaining_stock'] ?? null) !== null): ?>
                                <strong>잔여 <?= e((string) $item['remaining_stock']) ?>개</strong>
                            <?php endif; ?>
                        </div>
                        <div class="shop-product-body">
                            <div class="shop-product-title-row">
                                <h3><?= e($item['name']) ?></h3>
                                <strong><?= e((string) $item['price']) ?> P</strong>
                            </div>
                            <p><?= e($item['description']) ?></p>
                        </div>
                        <form method="post" action="/samgyeong-mall/cart/add">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                            <button type="submit" <?= $soldOut ? 'disabled' : '' ?>><?= $soldOut ? '품절' : '담기' ?></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <aside class="shop-cart">
            <div class="shop-cart-head">
                <div>
                    <h2>주문서</h2>
                    <p><?= e((string) $cartCount) ?>개 담김</p>
                </div>
                <span><?= e((string) $cart['total']) ?> P</span>
            </div>

            <div class="shop-cart-list">
                <?php if (!$cart['items']): ?>
                    <div class="shop-empty-cart">
                        <strong>장바구니가 비어 있습니다.</strong>
                        <p>원하는 포상 상품을 담아보세요.</p>
                    </div>
                <?php endif; ?>

                <?php foreach ($cart['items'] as $index => $item): ?>
                    <article class="shop-cart-item">
                        <div class="shop-cart-thumb tone-<?= e((string) (($index % 5) + 1)) ?>">
                            <?= e(mb_substr($item['name'], 0, 1)) ?>
                        </div>
                        <div class="shop-cart-info">
                            <strong><?= e($item['name']) ?></strong>
                            <span><?= e((string) $item['price']) ?> P</span>
                            <div class="shop-quantity">
                                <form method="post" action="/samgyeong-mall/cart/update">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                                    <input type="hidden" name="delta" value="-1">
                                    <button type="submit" aria-label="수량 줄이기">-</button>
                                </form>
                                <b><?= e((string) $item['quantity']) ?></b>
                                <form method="post" action="/samgyeong-mall/cart/update">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                                    <input type="hidden" name="delta" value="1">
                                    <button type="submit" aria-label="수량 늘리기">+</button>
                                </form>
                            </div>
                        </div>
                        <div class="shop-cart-price">
                            <strong><?= e((string) $item['line_total']) ?> P</strong>
                            <form method="post" action="/samgyeong-mall/cart/remove">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="item_id" value="<?= e((string) $item['id']) ?>">
                                <button type="submit" aria-label="삭제">×</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div class="shop-checkout">
                <div>
                    <span>상품 합계</span>
                    <strong><?= e((string) $cart['total']) ?> P</strong>
                </div>
                <div>
                    <span>결제 후 잔여</span>
                    <strong><?= e((string) max(0, $availablePoints - (int) $cart['total'])) ?> P</strong>
                </div>
                <form method="post" action="/samgyeong-mall/checkout" onsubmit="return confirm('장바구니 상품을 구매할까요? 사용한 상점은 구매 가능 포인트에서 차감됩니다.');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button type="submit" <?= !$cart['items'] ? 'disabled' : '' ?>>결제하기</button>
                </form>
            </div>
        </aside>
    </div>
</section>
