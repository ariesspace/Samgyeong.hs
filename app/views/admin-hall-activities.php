<section class="page admin-users-page admin-hall-activities-page" data-admin-hall-activities>
    <div class="admin-tool-head">
        <div>
            <p>HALL ACTIVITY MANAGER</p>
            <h1>관별 자치활동 관리</h1>
            <span>삼경마당에 표시되는 관별 자치활동 카드뉴스의 문구를 관리합니다.</span>
        </div>
        <button type="button" class="button admin-tool-primary" data-modal-open="add-hall-activity-modal">새 활동 추가</button>
    </div>

    <?php if ($saved): ?>
        <div class="notice success">관별 자치활동이 저장되었습니다.</div>
    <?php endif; ?>
    <?php if ($deleted): ?>
        <div class="notice success">관별 자치활동을 삭제했습니다.</div>
    <?php endif; ?>

    <form method="post" action="/admin/hall-activities/save" class="admin-hall-activity-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="admin-hall-activity-list">
            <?php foreach ($activities as $activity): ?>
                <?php
                    $hall = $halls[$activity['hall_key'] ?? ''] ?? ['name' => '미지정', 'meaning' => '', 'color' => 'gray'];
                    $modalId = 'edit-hall-activity-' . (string) $activity['id'];
                ?>
                <article class="admin-hall-activity-card <?= e($hall['color'] ?? 'gray') ?>">
                    <div class="admin-hall-activity-card-head">
                        <span><?= e($hall['name']) ?> <?= e($hall['meaning']) ?></span>
                        <div>
                            <button type="button" class="ghost-button" data-modal-open="<?= e($modalId) ?>">수정</button>
                            <button class="icon-button danger" type="submit" form="delete-activity-<?= e((string) $activity['id']) ?>" title="삭제" aria-label="삭제">×</button>
                        </div>
                    </div>
                    <h2><?= e($activity['title']) ?></h2>
                    <p><?= e($activity['summary']) ?></p>
                    <dl>
                        <div>
                            <dt>방식</dt>
                            <dd><?= e($activity['method']) ?></dd>
                        </div>
                        <div>
                            <dt>의미</dt>
                            <dd><?= e($activity['value']) ?></dd>
                        </div>
                    </dl>
                </article>

                <div class="admin-edit-modal" id="<?= e($modalId) ?>" data-admin-modal hidden>
                    <div class="admin-edit-dialog">
                        <header>
                            <div>
                                <span><?= e($hall['name']) ?> 활동 수정</span>
                                <h2><?= e($activity['title']) ?></h2>
                            </div>
                            <button type="button" class="icon-button" data-modal-close aria-label="닫기">×</button>
                        </header>
                        <div class="admin-edit-dialog-body">
                            <input type="hidden" name="id[]" value="<?= e((string) $activity['id']) ?>">
                            <label>
                                관
                                <select name="hall_key[]" required>
                                    <?php foreach ($halls as $key => $hallOption): ?>
                                        <option value="<?= e($key) ?>" <?= ($activity['hall_key'] ?? '') === $key ? 'selected' : '' ?>>
                                            <?= e($hallOption['name']) ?> <?= e($hallOption['meaning']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                제목
                                <input name="title[]" value="<?= e($activity['title']) ?>" required>
                            </label>
                            <label class="wide">
                                요약
                                <textarea name="summary[]" rows="3" required><?= e($activity['summary']) ?></textarea>
                            </label>
                            <label>
                                방식
                                <textarea name="method[]" rows="4" required><?= e($activity['method']) ?></textarea>
                            </label>
                            <label>
                                의미
                                <textarea name="value[]" rows="4" required><?= e($activity['value']) ?></textarea>
                            </label>
                        </div>
                        <footer>
                            <button type="button" class="ghost-button" data-modal-close>취소</button>
                            <button type="submit">변경 저장</button>
                        </footer>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($activities)): ?>
                <p class="empty-board">등록된 자치활동이 없습니다.</p>
            <?php endif; ?>
        </div>
    </form>

    <form id="add-hall-activity" method="post" action="/admin/hall-activities/add">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    </form>

    <div class="admin-edit-modal" id="add-hall-activity-modal" data-admin-modal hidden>
        <div class="admin-edit-dialog">
            <header>
                <div>
                    <span>새 카드뉴스</span>
                    <h2>자치활동 추가</h2>
                </div>
                <button type="button" class="icon-button" data-modal-close aria-label="닫기">×</button>
            </header>
            <div class="admin-edit-dialog-body">
                <label>
                    관
                    <select form="add-hall-activity" name="hall_key" required>
                        <?php foreach ($halls as $key => $hall): ?>
                            <option value="<?= e($key) ?>"><?= e($hall['name']) ?> <?= e($hall['meaning']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    제목
                    <input form="add-hall-activity" name="title" placeholder="예: 경천 독서 노트" required>
                </label>
                <label class="wide">
                    요약
                    <textarea form="add-hall-activity" name="summary" rows="3" placeholder="활동을 한 문장으로 설명해 주세요." required></textarea>
                </label>
                <label>
                    방식
                    <textarea form="add-hall-activity" name="method" rows="4" required></textarea>
                </label>
                <label>
                    의미
                    <textarea form="add-hall-activity" name="value" rows="4" required></textarea>
                </label>
            </div>
            <footer>
                <button type="button" class="ghost-button" data-modal-close>취소</button>
                <button type="submit" form="add-hall-activity">활동 추가</button>
            </footer>
        </div>
    </div>

    <?php foreach ($activities as $activity): ?>
        <form id="delete-activity-<?= e((string) $activity['id']) ?>" method="post" action="/admin/hall-activities/delete" onsubmit="return confirm('삭제하시겠습니까?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $activity['id']) ?>">
        </form>
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
