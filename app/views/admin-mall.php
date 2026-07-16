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
            <p class="muted">목록에서는 상품 상태만 확인하고, 세부 설정은 수정 화면에서 관리합니다.</p>
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
                    <p class="muted">품절 상품은 총 재고를 늘리거나 수동 품절을 해제하면 다시 구매 가능해집니다.</p>
                </div>
            </div>

            <div class="mall-admin-simple-list">
                <?php foreach ($items as $index => $item): ?>
                    <?php
                        $isActive = (int) ($item['active'] ?? 0) === 1;
                        $isSoldOut = !empty($item['sold_out']);
                        $stockLimit = $item['stock_limit'] ?? null;
                        $remaining = $item['remaining_stock'] ?? null;
                    ?>
                    <article class="mall-admin-list-row <?= $isSoldOut ? 'is-sold-out' : '' ?>">
                        <div class="mall-admin-row-main">
                            <span class="mall-admin-row-no"><?= e((string) ($index + 1)) ?></span>
                            <div>
                                <strong><?= e($item['name']) ?></strong>
                                <p><?= e($item['description']) ?></p>
                            </div>
                        </div>

                        <div class="mall-admin-row-meta">
                            <span><?= e((string) $item['price']) ?>점</span>
                            <span>판매 <?= e((string) ($item['sold_quantity'] ?? 0)) ?>개</span>
                            <span><?= $stockLimit === null || $stockLimit === '' ? '재고 제한 없음' : '총 ' . e((string) $stockLimit) . '개' ?></span>
                            <strong><?= $remaining === null ? '구매 가능' : '잔여 ' . e((string) $remaining) . '개' ?></strong>
                        </div>

                        <div class="mall-admin-row-actions">
                            <div class="mall-admin-badges">
                                <em class="<?= $isActive ? 'good' : 'neutral' ?>"><?= $isActive ? '판매중' : '숨김' ?></em>
                                <?php if ($isSoldOut): ?><em class="danger">품절</em><?php endif; ?>
                            </div>
                            <a class="icon-button" href="/admin/mall/items/edit?id=<?= e((string) $item['id']) ?>" title="상품 수정" aria-label="상품 수정">✎</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
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
        </aside>
    </div>
</section>
