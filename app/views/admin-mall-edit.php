<?php
    $item = $item ?? [];
    $stockLimit = $item['stock_limit'] ?? null;
    $remaining = $item['remaining_stock'] ?? null;
    $isActive = (int) ($item['active'] ?? 0) === 1;
    $isForcedSoldOut = (int) ($item['force_sold_out'] ?? 0) === 1;
    $isSoldOut = !empty($item['sold_out']);
?>

<section class="page admin-users-page mall-admin-page mall-admin-edit-page">
    <div class="admin-history-head mall-admin-head">
        <div>
            <p class="eyebrow">SAMGYEONG MALL ITEM</p>
            <h1>상품 수정</h1>
            <p class="muted">상품명, 가격, 품절 상태와 재고를 수정합니다.</p>
        </div>
        <a class="button secondary" href="/admin/mall">목록으로</a>
    </div>

    <section class="admin-create-panel mall-item-edit-panel">
        <div class="mall-item-edit-summary">
            <div>
                <span>현재 상품</span>
                <strong><?= e($item['name']) ?></strong>
            </div>
            <div class="mall-admin-badges">
                <em class="<?= $isActive ? 'good' : 'neutral' ?>"><?= $isActive ? '판매중' : '숨김' ?></em>
                <?php if ($isSoldOut): ?><em class="danger">품절</em><?php endif; ?>
            </div>
        </div>

        <div class="mall-stock-summary edit-summary">
            <span>판매 <?= e((string) ($item['sold_quantity'] ?? 0)) ?>개</span>
            <span><?= $stockLimit === null || $stockLimit === '' ? '재고 제한 없음' : '총 재고 ' . e((string) $stockLimit) . '개' ?></span>
            <strong><?= $remaining === null ? '구매 가능' : '잔여 ' . e((string) $remaining) . '개' ?></strong>
        </div>

        <form method="post" action="/admin/mall/items/update" class="admin-create-form mall-item-edit-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">

            <label>
                상품명
                <input name="name" value="<?= e($item['name']) ?>" required>
            </label>

            <label>
                필요 상점
                <input type="number" name="price" min="1" max="999" value="<?= e((string) $item['price']) ?>" required>
            </label>

            <label>
                총 재고
                <input type="number" name="stock_limit" min="1" max="999" value="<?= e($stockLimit === null ? '' : (string) $stockLimit) ?>" placeholder="비우면 무제한">
            </label>

            <label class="wide-field">
                설명
                <textarea name="description" rows="5" required><?= e($item['description']) ?></textarea>
            </label>

            <div class="mall-item-switches">
                <label class="mall-switch-toggle">
                    <input type="checkbox" name="active" value="1" <?= $isActive ? 'checked' : '' ?>>
                    <span>판매 노출</span>
                </label>
                <label class="mall-switch-toggle soldout-toggle">
                    <input type="checkbox" name="force_sold_out" value="1" <?= $isForcedSoldOut ? 'checked' : '' ?>>
                    <span>수동 품절</span>
                </label>
            </div>

            <div class="admin-create-actions">
                <a class="button secondary" href="/admin/mall">취소</a>
                <button type="submit">저장</button>
            </div>
        </form>
    </section>
</section>
