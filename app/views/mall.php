<?php
    $productCount = count($items);
    $cartCount = array_sum(array_map(fn ($item) => (int) $item['quantity'], $cart['items']));
    $availablePoints = (int) ($points['available_total'] ?? 0);
?>

<section class="page mall-page shop-page">
    <?php if ($saved === 'cart'): ?>
        <div class="success">장바구니가 업데이트되었습니다.</div>
    <?php elseif ($saved === 'checkout'): ?>
        <div class="success">구매가 완료되었습니다. 삼경원 확인 후 혜택이 적용됩니다.</div>
    <?php elseif ($error === 'points'): ?>
        <div class="error">사용 가능한 상점이 부족합니다.</div>
    <?php elseif ($error === 'empty'): ?>
        <div class="error">장바구니가 비어 있습니다.</div>
    <?php elseif ($error === 'checkout'): ?>
        <div class="error">결제 처리 중 오류가 발생했습니다.</div>
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
                    <article class="shop-product-card tone-<?= e((string) (($index % 5) + 1)) ?>">
                        <div class="shop-product-visual">
                            <span><?= e(mb_substr($item['name'], 0, 1)) ?></span>
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
                            <button type="submit">담기</button>
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
