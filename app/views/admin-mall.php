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
            <p class="muted">목록에서는 상품명만 간단히 확인하고, 수정과 추가는 팝업에서 처리합니다.</p>
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

    <div class="mall-admin-layout mall-admin-list-only">
        <section class="mall-admin-panel mall-items-editor">
            <div class="section-title-row">
                <div>
                    <h2>상품 목록</h2>
                    <p class="muted">수정 버튼을 누르면 상품명, 설명, 재고, 품절 상태를 변경할 수 있습니다.</p>
                </div>
            </div>

            <div class="mall-admin-simple-list mall-admin-table-list">
                <?php foreach ($items as $index => $item): ?>
                    <?php
                        $isActive = (int) ($item['active'] ?? 0) === 1;
                        $isForcedSoldOut = (int) ($item['force_sold_out'] ?? 0) === 1;
                        $isSoldOut = !empty($item['sold_out']);
                        $stockLimit = $item['stock_limit'] ?? null;
                        $remaining = $item['remaining_stock'] ?? null;
                    ?>
                    <article class="mall-admin-list-row <?= $isSoldOut ? 'is-sold-out' : '' ?>">
                        <div class="mall-admin-row-main">
                            <span class="mall-admin-row-no"><?= e((string) ($index + 1)) ?></span>
                            <div>
                                <strong><?= e($item['name']) ?></strong>
                            </div>
                        </div>

                        <div class="mall-admin-row-actions">
                            <div class="mall-admin-badges">
                                <em class="<?= $isActive ? 'good' : 'neutral' ?>"><?= $isActive ? '판매중' : '숨김' ?></em>
                                <?php if ($isSoldOut): ?><em class="danger">품절</em><?php endif; ?>
                            </div>
                            <button class="icon-button" type="button" data-mall-modal-open="mall-item-modal-<?= e((string) $item['id']) ?>" title="상품 수정" aria-label="상품 수정">✎</button>
                        </div>
                    </article>

                    <div class="mall-admin-modal-backdrop" id="mall-item-modal-<?= e((string) $item['id']) ?>" hidden>
                        <section class="mall-admin-modal" role="dialog" aria-modal="true" aria-labelledby="mall-item-title-<?= e((string) $item['id']) ?>">
                            <button type="button" class="mall-admin-modal-close" data-mall-modal-close aria-label="닫기">×</button>
                            <div class="mall-item-edit-summary">
                                <div>
                                    <span>상품 수정</span>
                                    <strong id="mall-item-title-<?= e((string) $item['id']) ?>"><?= e($item['name']) ?></strong>
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
                                    <button type="button" class="button secondary" data-mall-modal-close>취소</button>
                                    <button type="submit">저장</button>
                                </div>
                            </form>
                        </section>
                    </div>
                <?php endforeach; ?>

                <article class="mall-admin-list-row mall-admin-add-row">
                    <button class="mall-add-inline-button" type="button" data-mall-modal-open="mall-item-add-modal" aria-label="새 상품 추가">
                        <span>+</span>
                        <strong>새 상품 추가</strong>
                    </button>
                </article>
            </div>
        </section>
    </div>
</section>

<div class="mall-admin-modal-backdrop" id="mall-item-add-modal" hidden>
    <section class="mall-admin-modal" role="dialog" aria-modal="true" aria-labelledby="mall-item-add-title">
        <button type="button" class="mall-admin-modal-close" data-mall-modal-close aria-label="닫기">×</button>
        <div class="mall-item-edit-summary">
            <div>
                <span>상품 추가</span>
                <strong id="mall-item-add-title">새 상품</strong>
            </div>
        </div>

        <form method="post" action="/admin/mall/items/add" class="admin-create-form mall-item-edit-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

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

            <label class="wide-field">
                설명
                <textarea name="description" rows="5" placeholder="학생 화면에 표시할 설명을 입력해 주세요." required></textarea>
            </label>

            <div class="admin-create-actions">
                <button type="button" class="button secondary" data-mall-modal-close>취소</button>
                <button type="submit">추가</button>
            </div>
        </form>
    </section>
</div>

<script>
document.addEventListener('click', function (event) {
    const openButton = event.target.closest('[data-mall-modal-open]');
    if (openButton) {
        const modal = document.getElementById(openButton.getAttribute('data-mall-modal-open'));
        if (modal) {
            modal.hidden = false;
            document.body.classList.add('modal-open');
        }
        return;
    }

    const closeButton = event.target.closest('[data-mall-modal-close]');
    const backdrop = event.target.classList && event.target.classList.contains('mall-admin-modal-backdrop') ? event.target : null;
    if (closeButton || backdrop) {
        const modal = (closeButton || backdrop).closest('.mall-admin-modal-backdrop');
        if (modal) {
            modal.hidden = true;
            document.body.classList.remove('modal-open');
        }
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('.mall-admin-modal-backdrop:not([hidden])').forEach(function (modal) {
        modal.hidden = true;
    });
    document.body.classList.remove('modal-open');
});
</script>
