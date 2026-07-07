<section class="page admin-users-page admin-point-rules-page" data-admin-point-rules>
    <div class="admin-tool-head">
        <div>
            <p>POINT DISCIPLINE MANAGER</p>
            <h1>상벌점 기준 관리</h1>
            <span>징계 및 포상 메뉴에 표시되는 단위별 징계 기준의 점수와 문구를 관리합니다.</span>
        </div>
    </div>

    <?php if ($saved): ?>
        <div class="notice success">상벌점 기준이 저장되었습니다.</div>
    <?php endif; ?>
    <?php if ($deleted): ?>
        <div class="notice success">상벌점 기준을 삭제했습니다.</div>
    <?php endif; ?>

    <?php foreach ($sections as $section): ?>
        <form id="add-rule-<?= e($section['key'] ?? '') ?>" method="post" action="/admin/point-rules/add"></form>
    <?php endforeach; ?>

    <form method="post" action="/admin/point-rules/save" class="admin-point-rules-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="admin-point-section-grid">
            <?php foreach ($sections as $section): ?>
                <?php
                    $sectionKey = $section['key'] ?? '';
                    $addModalId = 'add-point-rule-' . $sectionKey;
                    $tone = $section['tone'] ?? 'gray';
                ?>
                <section class="admin-point-section-card tone-<?= e($tone) ?>">
                    <div class="admin-point-section-head">
                        <div>
                            <span><?= e($section['description'] ?: '단위별 적용 기준') ?></span>
                            <h2><?= e($section['title']) ?></h2>
                        </div>
                        <button type="button" class="ghost-button" data-modal-open="<?= e($addModalId) ?>">기준 추가</button>
                    </div>

                    <div class="admin-point-rule-list">
                        <?php foreach ($section['items'] as $item): ?>
                            <?php $modalId = 'edit-point-rule-' . (string) $item['id']; ?>
                            <article class="admin-point-rule-card <?= !empty($item['emphasis']) ? 'is-emphasis' : '' ?>">
                                <strong><?= e($item['score']) ?></strong>
                                <p title="<?= e($item['text']) ?>"><?= e($item['text']) ?></p>
                                <div>
                                    <?php if (!empty($item['emphasis'])): ?><span>강조</span><?php endif; ?>
                                    <button type="button" class="ghost-button" data-modal-open="<?= e($modalId) ?>">수정</button>
                                    <button class="icon-button danger" type="submit" form="delete-rule-<?= e((string) $item['id']) ?>" title="삭제" aria-label="삭제">×</button>
                                </div>
                            </article>

                            <div class="admin-edit-modal" id="<?= e($modalId) ?>" data-admin-modal hidden>
                                <div class="admin-edit-dialog">
                                    <header>
                                        <div>
                                            <span><?= e($section['title']) ?></span>
                                            <h2><?= e($item['score']) ?> 기준 수정</h2>
                                        </div>
                                        <button type="button" class="icon-button" data-modal-close aria-label="닫기">×</button>
                                    </header>
                                    <div class="admin-edit-dialog-body point-rule-dialog-body">
                                        <input type="hidden" name="id[]" value="<?= e((string) $item['id']) ?>">
                                        <input type="hidden" name="category[]" value="<?= e($item['category'] ?? '') ?>">
                                        <label>
                                            점수
                                            <input name="score_label[]" value="<?= e($item['score']) ?>" required>
                                        </label>
                                        <label class="wide">
                                            내용
                                            <textarea name="rule_text[]" rows="5" required><?= e($item['text']) ?></textarea>
                                        </label>
                                        <label class="checkbox-field wide">
                                            <input type="checkbox" name="is_emphasis[]" value="<?= e((string) $item['id']) ?>" <?= !empty($item['emphasis']) ? 'checked' : '' ?>>
                                            강조 항목으로 표시
                                        </label>
                                    </div>
                                    <footer>
                                        <button type="button" class="ghost-button" data-modal-close>취소</button>
                                        <button type="submit">변경 저장</button>
                                    </footer>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php if (empty($section['items'])): ?>
                            <p class="empty-board">등록된 기준이 없습니다.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <div class="admin-edit-modal" id="<?= e($addModalId) ?>" data-admin-modal hidden>
                    <div class="admin-edit-dialog">
                        <header>
                            <div>
                                <span><?= e($section['title']) ?></span>
                                <h2>새 기준 추가</h2>
                            </div>
                            <button type="button" class="icon-button" data-modal-close aria-label="닫기">×</button>
                        </header>
                        <div class="admin-edit-dialog-body point-rule-dialog-body">
                            <input form="<?= e('add-rule-' . $sectionKey) ?>" type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input form="<?= e('add-rule-' . $sectionKey) ?>" type="hidden" name="category" value="<?= e($sectionKey) ?>">
                            <label>
                                점수
                                <input form="<?= e('add-rule-' . $sectionKey) ?>" name="score_label" placeholder="예: -10점" required>
                            </label>
                            <label class="wide">
                                내용
                                <textarea form="<?= e('add-rule-' . $sectionKey) ?>" name="rule_text" rows="5" placeholder="<?= e($section['title']) ?>에 추가할 내용을 입력하세요." required></textarea>
                            </label>
                            <label class="checkbox-field wide">
                                <input form="<?= e('add-rule-' . $sectionKey) ?>" type="checkbox" name="is_emphasis" value="1">
                                강조 항목으로 표시
                            </label>
                        </div>
                        <footer>
                            <button type="button" class="ghost-button" data-modal-close>취소</button>
                            <button type="submit" form="<?= e('add-rule-' . $sectionKey) ?>">기준 추가</button>
                        </footer>
                    </div>
                </div>
            <?php endforeach; ?>
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
</section>

<script>
document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.modalOpen);
        if (modal) {
            modal.hidden = false;
            const firstInput = modal.querySelector('input, select, textarea, button');
            if (firstInput) {
                firstInput.focus();
            }
        }
    });
});

document.querySelectorAll('[data-admin-modal]').forEach((modal) => {
    modal.querySelectorAll('[data-modal-close]').forEach((button) => {
        button.addEventListener('click', () => {
            modal.hidden = true;
        });
    });
    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            modal.hidden = true;
        }
    });
});
</script>
