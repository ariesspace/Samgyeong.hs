<section class="page admin-users-page admin-point-rules-page">
    <h1>상벌점 기준 관리</h1>
    <p class="muted">상벌점 리스트의 화면 틀은 고정하고, 각 기준의 점수와 문구만 관리합니다.</p>

    <?php if ($saved): ?>
        <div class="notice success">상벌점 기준이 저장되었습니다.</div>
    <?php endif; ?>
    <?php if ($deleted): ?>
        <div class="notice success">상벌점 기준이 삭제되었습니다.</div>
    <?php endif; ?>

    <form method="post" action="/admin/point-rules/save" class="admin-point-rules-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="point-rule-admin-sections">
            <?php foreach ($sections as $section): ?>
                <section class="admin-create-panel point-rule-admin-block">
                    <div class="point-rule-admin-head">
                        <h2><?= e($section['title']) ?></h2>
                        <?php if (!empty($section['description'])): ?>
                            <p><?= e($section['description']) ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="point-rule-admin-list">
                        <?php foreach ($section['items'] as $item): ?>
                            <div class="point-rule-admin-row">
                                <input type="hidden" name="id[]" value="<?= e((string) $item['id']) ?>">
                                <label>
                                    점수
                                    <input name="score_label[]" value="<?= e($item['score']) ?>" required>
                                </label>
                                <label class="rule-text-field">
                                    내용
                                    <textarea name="rule_text[]" rows="2" required><?= e($item['text']) ?></textarea>
                                </label>
                                <label>
                                    순서
                                    <input type="number" name="sort_order[]" value="<?= e((string) $item['sort_order']) ?>">
                                </label>
                                <label class="checkbox-field">
                                    <input type="checkbox" name="is_emphasis[]" value="<?= e((string) $item['id']) ?>" <?= !empty($item['emphasis']) ? 'checked' : '' ?>>
                                    강조
                                </label>
                                <button class="icon-button danger" type="submit" form="delete-rule-<?= e((string) $item['id']) ?>" title="삭제" aria-label="삭제">🗑</button>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($section['items'])): ?>
                            <p class="empty-board">등록된 기준이 없습니다.</p>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <a class="button ghost-button" href="/admin/users">돌아가기</a>
            <button type="submit">기준 저장</button>
        </div>
    </form>

    <?php foreach ($sections as $section): ?>
        <?php foreach ($section['items'] as $item): ?>
            <form id="delete-rule-<?= e((string) $item['id']) ?>" method="post" action="/admin/point-rules/delete" onsubmit="return confirm('삭제하시겠습니까?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
            </form>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <section class="admin-create-panel point-rule-add-panel">
        <h2>새 기준 추가</h2>
        <form method="post" action="/admin/point-rules/add" class="admin-create-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                분류
                <select name="category">
                    <?php foreach (point_rule_categories() as $key => $category): ?>
                        <option value="<?= e($key) ?>"><?= e($category['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                점수
                <input name="score_label" placeholder="예: -10점" required>
            </label>
            <label>
                순서
                <input type="number" name="sort_order" value="99">
            </label>
            <label class="rule-text-field">
                내용
                <textarea name="rule_text" rows="3" placeholder="기준 내용을 입력하세요" required></textarea>
            </label>
            <label class="checkbox-field">
                <input type="checkbox" name="is_emphasis" value="1">
                강조 항목으로 표시
            </label>
            <div class="admin-create-actions">
                <button type="submit">기준 추가</button>
            </div>
        </form>
    </section>
</section>
