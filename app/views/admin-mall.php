<?php
    $items = $items ?? [];
    $activeCount = count(array_filter($items, fn ($item) => (int) ($item['active'] ?? 0) === 1));
?>

<section class="page admin-users-page mall-admin-page mall-admin-refined-page">
    <div class="admin-history-head">
        <div>
            <h1>삼경몰 관리</h1>
            <p class="muted">삼경몰에 노출되는 포상 상품의 이름, 설명, 필요 상점, 판매 여부를 관리합니다.</p>
        </div>
        <div class="mall-admin-counts" aria-label="상품 현황">
            <span>전체 <?= e((string) count($items)) ?>개</span>
            <strong>판매중 <?= e((string) $activeCount) ?>개</strong>
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
                    <p class="muted">판매 체크를 끄면 학생 화면의 삼경몰에서 숨겨집니다.</p>
                </div>
            </div>

            <form method="post" action="/admin/mall/items" class="mall-items-form mall-items-card-form">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="mall-admin-item-list">
                    <?php foreach ($items as $index => $item): ?>
                        <article class="mall-admin-item-card <?= (int) $item['active'] === 1 ? 'is-active' : 'is-paused' ?>">
                            <header>
                                <div>
                                    <span>상품 <?= e((string) ($index + 1)) ?></span>
                                    <strong><?= e($item['name']) ?></strong>
                                </div>
                                <label class="mall-sale-toggle">
                                    <input type="checkbox" name="active[]" value="<?= e((string) $item['id']) ?>" <?= (int) $item['active'] === 1 ? 'checked' : '' ?>>
                                    <span><?= (int) $item['active'] === 1 ? '판매중' : '숨김' ?></span>
                                </label>
                            </header>
                            <input type="hidden" name="id[]" value="<?= e((string) $item['id']) ?>">
                            <div class="mall-item-field-grid">
                                <label>
                                    상품명
                                    <input name="name[]" value="<?= e($item['name']) ?>" required>
                                </label>
                                <label>
                                    필요 상점
                                    <input type="number" name="price[]" min="1" max="999" value="<?= e((string) $item['price']) ?>" required>
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
                    <button type="submit">상품 목록 저장</button>
                </div>
            </form>
        </section>

        <aside class="mall-admin-side">
            <form method="post" action="/admin/mall/items/add" class="mall-add-form mall-add-card">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <h2>새 상품 추가</h2>
                <label>
                    상품명
                    <input name="name" placeholder="예: 출석 면제권" required>
                </label>
                <label>
                    필요 상점
                    <input type="number" name="price" min="1" max="999" placeholder="10" required>
                </label>
                <label>
                    설명
                    <textarea name="description" rows="4" placeholder="학생 화면에 표시될 설명을 입력해 주세요." required></textarea>
                </label>
                <button type="submit">상품 추가</button>
            </form>

            <div class="mall-admin-help">
                <strong>관리 기준</strong>
                <p>목록의 순서는 현재 보이는 상품 순서로 저장됩니다. 사용하지 않는 상품은 삭제 대신 판매 체크를 꺼서 숨길 수 있습니다.</p>
            </div>
        </aside>
    </div>
</section>
