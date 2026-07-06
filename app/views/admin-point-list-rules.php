<section class="page admin-users-page admin-point-rules-page">
    <h1>상벌점 리스트 관리</h1>
    <p class="muted">상벌점 리스트 화면에 표시되는 벌점, 상점, 제출 절차 문구를 관리합니다.</p>

    <?php if ($saved): ?>
        <div class="notice success">상벌점 리스트가 저장되었습니다.</div>
    <?php endif; ?>
    <?php if ($deleted): ?>
        <div class="notice success">항목을 삭제했습니다.</div>
    <?php endif; ?>

    <?php foreach ($sections as $section): ?>
        <form id="add-list-rule-<?= e($section['key'] ?? '') ?>" method="post" action="/admin/point-list-rules/add"></form>
    <?php endforeach; ?>

    <form method="post" action="/admin/point-list-rules/save" class="admin-point-rules-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="point-rule-admin-sections">
            <?php foreach ($sections as $section): ?>
                <?php $addFormId = 'add-list-rule-' . ($section['key'] ?? ''); ?>
                <section class="point-rule-admin-block">
                    <div class="point-rule-admin-head">
                        <div>
                            <h2><?= e($section['title']) ?></h2>
                            <?php if (!empty($section['description'])): ?>
                                <p><?= e($section['description']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="point-rule-admin-list">
                        <?php foreach ($section['items'] as $item): ?>
                            <div class="point-rule-admin-row <?= ($section['key'] ?? '') === 'submit' ? 'submit-row' : '' ?>">
                                <input type="hidden" name="id[]" value="<?= e((string) $item['id']) ?>">
                                <input type="hidden" name="category[]" value="<?= e($item['category'] ?? '') ?>">
                                <label>
                                    점수
                                    <input name="score_label[]" value="<?= e($item['score']) ?>" <?= ($section['key'] ?? '') === 'submit' ? 'placeholder="비워둠"' : 'required' ?>>
                                </label>
                                <label class="rule-text-field">
                                    내용
                                    <textarea name="rule_text[]" rows="2" required><?= e($item['text']) ?></textarea>
                                </label>
                                <span></span>
                                <button class="icon-button danger" type="submit" form="delete-list-rule-<?= e((string) $item['id']) ?>" title="삭제" aria-label="삭제">⌫</button>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($section['items'])): ?>
                            <p class="empty-board">등록된 항목이 없습니다.</p>
                        <?php endif; ?>
                    </div>

                    <div class="point-rule-inline-add">
                        <div class="point-rule-inline-add-title">새 항목 추가</div>
                        <div class="point-rule-inline-add-form <?= ($section['key'] ?? '') === 'submit' ? 'submit-row' : '' ?>">
                            <input form="<?= e($addFormId) ?>" type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input form="<?= e($addFormId) ?>" type="hidden" name="category" value="<?= e($section['key'] ?? '') ?>">
                            <label>
                                점수
                                <input form="<?= e($addFormId) ?>" name="score_label" placeholder="<?= ($section['key'] ?? '') === 'submit' ? '비워둠' : '예: 1점' ?>" <?= ($section['key'] ?? '') === 'submit' ? '' : 'required' ?>>
                            </label>
                            <label class="rule-text-field">
                                내용
                                <textarea form="<?= e($addFormId) ?>" name="rule_text" rows="2" placeholder="<?= e($section['title']) ?>에 추가할 내용을 입력하세요." required></textarea>
                            </label>
                            <span></span>
                            <button form="<?= e($addFormId) ?>" type="submit" class="ghost-button">추가</button>
                        </div>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>

        <div class="form-actions">
            <a class="button ghost-button" href="/rules/points">목록 보기</a>
            <button type="submit">리스트 저장</button>
        </div>
    </form>

    <?php foreach ($sections as $section): ?>
        <?php foreach ($section['items'] as $item): ?>
            <form id="delete-list-rule-<?= e((string) $item['id']) ?>" method="post" action="/admin/point-list-rules/delete" onsubmit="return confirm('삭제하시겠습니까?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
            </form>
        <?php endforeach; ?>
    <?php endforeach; ?>
</section>
