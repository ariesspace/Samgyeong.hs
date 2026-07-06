<section class="page admin-users-page mall-admin-page">
    <h1>삼경몰 관리</h1>
    <p class="muted">삼경몰 운영 여부와 포상 상품을 관리합니다. 학생은 운영 기간에만 삼경몰을 이용할 수 있습니다.</p>

    <?php if ($saved): ?>
        <div class="notice success">삼경몰 설정이 저장되었습니다.</div>
    <?php endif; ?>

    <section class="mall-admin-panel">
        <div>
            <h2>운영 설정</h2>
            <p class="muted">닫힘 상태에서는 슈퍼관리자만 삼경몰에 접근할 수 있습니다.</p>
        </div>
        <form method="post" action="/admin/mall/settings" class="mall-open-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label class="toggle-line">
                <input type="checkbox" name="student_open" value="1" <?= $studentOpen ? 'checked' : '' ?>>
                학생 구매 기간 열기
            </label>
            <button type="submit">운영 설정 저장</button>
        </form>
    </section>

    <section class="mall-admin-panel">
        <div class="section-title-row">
            <div>
                <h2>상품 관리</h2>
                <p class="muted">포상 항목의 이름, 설명, 필요 상점을 수정할 수 있습니다.</p>
            </div>
        </div>

        <form method="post" action="/admin/mall/items" class="mall-items-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="mall-admin-items">
                <?php foreach ($items as $item): ?>
                    <article class="mall-admin-item">
                        <input type="hidden" name="id[]" value="<?= e((string) $item['id']) ?>">
                        <label>
                            상품명
                            <input name="name[]" value="<?= e($item['name']) ?>" required>
                        </label>
                        <label class="wide-field">
                            설명
                            <textarea name="description[]" rows="3" required><?= e($item['description']) ?></textarea>
                        </label>
                        <label>
                            필요 상점
                            <input type="number" name="price[]" min="1" max="999" value="<?= e((string) $item['price']) ?>" required>
                        </label>
                        <label class="toggle-line compact">
                            <input type="checkbox" name="active[]" value="<?= e((string) $item['id']) ?>" <?= (int) $item['active'] === 1 ? 'checked' : '' ?>>
                            판매
                        </label>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="form-actions">
                <button type="submit">상품 저장</button>
            </div>
        </form>

        <form method="post" action="/admin/mall/items/add" class="mall-add-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <h3>새 상품 추가</h3>
            <input name="name" placeholder="상품명" required>
            <input type="number" name="price" min="1" max="999" placeholder="필요 상점" required>
            <textarea name="description" rows="2" placeholder="상품 설명" required></textarea>
            <button type="submit">추가</button>
        </form>
    </section>

    <section class="mall-admin-panel">
        <h2>최근 구매 내역</h2>
        <table class="board-table points-table">
            <thead>
                <tr>
                    <th>일시</th>
                    <th>학생</th>
                    <th>상품</th>
                    <th>수량</th>
                    <th>사용 상점</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$orders): ?>
                    <tr><td colspan="5" class="empty-board">구매 내역이 없습니다.</td></tr>
                <?php endif; ?>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td><?= e($order['created_at']) ?></td>
                        <td><?= e(($order['display_name'] ?? '') ?: ($order['username'] ?? '')) ?></td>
                        <td class="title-cell"><?= e($order['item_name']) ?></td>
                        <td><?= e((string) $order['quantity']) ?></td>
                        <td><?= e((string) $order['total_price']) ?>점</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
