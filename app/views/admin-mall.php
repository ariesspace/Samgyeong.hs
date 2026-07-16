<?php
    $items = $items ?? [];
    $activeCount = count(array_filter($items, fn ($item) => (int) ($item['active'] ?? 0) === 1));
    $soldOutCount = count(array_filter($items, fn ($item) => !empty($item['sold_out'])));
?>

<section class="page admin-users-page mall-admin-page mall-admin-refined-page">
    <div class="admin-history-head mall-admin-head">
        <div>
            <p class="eyebrow">SAMGYEONG MALL ADMIN</p>
            <h1>삼경몰 관리</h1>
            <p class="muted">상품의 판매 여부, 품절 상태, 재고와 가격을 관리합니다.</p>
        </div>
        <div class="mall-admin-counts" aria-label="상품 현황">
            <span>전체 <?= e((string) count($items)) ?>개</span>
            <strong>판매 <?= e((string) $activeCount) ?>개</strong>
            <b>품절 <?= e((string) $soldOutCount) ?>개</b>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="notice success">삼경몰 설정이 저장되었습니다.</div>
    <?php endif; ?>

    <div class="mall-admin-layout">
        <section class="mall-admin-panel mall-items-editor">
            <div class="section-title-row">
                <div>
                    <h2>상품 목록</h2>
                    <p class="muted">판매를 끄면 몰에서 숨겨지고, 품절 처리를 켜면 상품은 보이지만 구매할 수 없습니다.</p>
                </div>
            </div>

            <form method="post" action="/admin/mall/items" class="mall-items-form mall-items-card-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mall-admin-item-list">
                    <?php foreach ($items as $index => $item): ?>
                        <?php
                            $isActive = (int) ($item['active'] ?? 0) === 1;
                            $isForcedSoldOut = (int) ($item['force_sold_out'] ?? 0) === 1;
                            $isSoldOut = !empty($item['sold_out']);
                            $stockLimit = $item['stock_limit'] ?? null;
                            $remaining = $item['remaining_stock'] ?? null;
                        ?>
                        <article class="mall-admin-item-card <?= $isActive ? 'is-active' : 'is-paused' ?> <?= $isSoldOut ? 'is-sold-out' : '' ?>">
                            <header>
                                <div>
                                    <span>상품 <?= e((string) ($index + 1)) ?></span>
                                    <strong><?= e($item['name']) ?></strong>
                                </div>
                                <div class="mall-admin-badges">
                                    <em class="<?= $isActive ? 'good' : 'neutral' ?>"><?= $isActive ? '판매중' : '숨김' ?></em>
                                    <?php if ($isSoldOut): ?><em class="danger">품절</em><?php endif; ?>
                                </div>
                            </header>

                            <input type="hidden" name="id[]" value="<?= e((string) $item['id']) ?>">

                            <div class="mall-admin-status-row">
                                <label class="mall-switch-toggle">
                                    <input type="checkbox" name="active[]" value="<?= e((string) $item['id']) ?>" <?= $isActive ? 'checked' : '' ?>>
                                    <span>판매 노출</span>
                                </label>
                                <label class="mall-switch-toggle soldout-toggle">
                                    <input type="checkbox" name="force_sold_out[]" value="<?= e((string) $item['id']) ?>" <?= $isForcedSoldOut ? 'checked' : '' ?>>
                                    <span>수동 품절</span>
                                </label>
                            </div>

                            <div class="mall-stock-summary">
                                <span>판매 <?= e((string) ($item['sold_quantity'] ?? 0)) ?>개</span>
                                <span>
                                    <?= $stockLimit === null || $stockLimit === '' ? '재고 제한 없음' : '총 재고 ' . e((string) $stockLimit) . '개' ?>
                                </span>
                                <strong>
                                    <?= $remaining === null ? '구매 가능' : '잔여 ' . e((string) $remaining) . '개' ?>
                                </strong>
                            </div>

                            <div class="mall-item-field-grid">
                                <label>
                                    상품명
                                    <input name="name[]" value="<?= e($item['name']) ?>" required>
                                </label>
                                <label>
                                    필요 상점
                                    <input type="number" name="price[]" min="1" max="999" value="<?= e((string) $item['price']) ?>" required>
                                </label>
                                <label>
                                    총 재고
                                    <input type="number" name="stock_limit[]" min="1" max="999" value="<?= e($stockLimit === null ? '' : (string) $stockLimit) ?>" placeholder="무제한">
                                </label>
                                <label class="wide-field">
                                    설명
                                    <textarea name="description[]" rows="3" required><?= e($item['description']) ?></textarea>
                                </label>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="form-actions mall-save-actions">
                    <button type="submit">상품 설정 저장</button>
                </div>
            </form>
        </section>

        <aside class="mall-admin-side">
            <form method="post" action="/admin/mall/items/add" class="mall-add-form mall-add-card">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <h2>새 상품 추가</h2>
                <label>
                    상품명
                    <input name="name" placeholder="예: 특별 면제권" required>
                </label>
                <label>
                    필요 상점
                    <input type="number" name="price" min="1" max="999" placeholder="10" required>
                </label>
                <label>
                    총 재고
                    <input type="number" name="stock_limit" min="1" max="999" placeholder="비우면 무제한">
                </label>
                <label>
                    설명
                    <textarea name="description" rows="4" placeholder="학생 화면에 표시할 설명을 입력해 주세요." required></textarea>
                </label>
                <button type="submit">상품 추가</button>
            </form>

            <div class="mall-admin-help">
                <strong>품절 관리</strong>
                <p>치킨과 피자는 기본 총 재고가 1개입니다. 구매 후 품절된 상품은 총 재고를 늘리거나 수동 품절을 끄면 다시 구매 가능하게 만들 수 있습니다.</p>
            </div>
        </aside>
    </div>
</section>
