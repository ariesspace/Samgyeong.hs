<section class="page admin-users-page admin-hall-activities-page">
    <h1>관별 자치활동 관리</h1>
    <p class="muted">삼경마당의 관별 자치활동 카드뉴스를 수정하거나 새 활동을 추가합니다.</p>

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
                <section class="admin-hall-activity-row">
                    <input type="hidden" name="id[]" value="<?= e((string) $activity['id']) ?>">
                    <div class="admin-hall-activity-main">
                        <label>
                            관
                            <select name="hall_key[]" required>
                                <?php foreach ($halls as $key => $hall): ?>
                                    <option value="<?= e($key) ?>" <?= ($activity['hall_key'] ?? '') === $key ? 'selected' : '' ?>>
                                        <?= e($hall['name']) ?> <?= e($hall['meaning']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            제목
                            <input name="title[]" value="<?= e($activity['title']) ?>" required>
                        </label>
                        <button class="icon-button danger" type="submit" form="delete-activity-<?= e((string) $activity['id']) ?>" title="삭제" aria-label="삭제">⌫</button>
                    </div>
                    <label>
                        요약
                        <textarea name="summary[]" rows="2" required><?= e($activity['summary']) ?></textarea>
                    </label>
                    <div class="admin-hall-activity-detail">
                        <label>
                            방식
                            <textarea name="method[]" rows="2" required><?= e($activity['method']) ?></textarea>
                        </label>
                        <label>
                            의미
                            <textarea name="value[]" rows="2" required><?= e($activity['value']) ?></textarea>
                        </label>
                    </div>
                </section>
            <?php endforeach; ?>

            <?php if (empty($activities)): ?>
                <p class="empty-board">등록된 자치활동이 없습니다.</p>
            <?php endif; ?>
        </div>

        <section class="admin-hall-activity-add">
            <h2>새 활동 추가</h2>
            <div class="admin-hall-activity-main">
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
            </div>
            <label>
                요약
                <textarea form="add-hall-activity" name="summary" rows="2" placeholder="활동을 한 문장으로 설명해 주세요." required></textarea>
            </label>
            <div class="admin-hall-activity-detail">
                <label>
                    방식
                    <textarea form="add-hall-activity" name="method" rows="2" required></textarea>
                </label>
                <label>
                    의미
                    <textarea form="add-hall-activity" name="value" rows="2" required></textarea>
                </label>
            </div>
        </section>

        <div class="form-actions">
            <a class="button ghost-button" href="/admin/users">돌아가기</a>
            <button type="submit">활동 저장</button>
            <button type="submit" form="add-hall-activity" class="button">새 활동 추가</button>
        </div>
    </form>

    <form id="add-hall-activity" method="post" action="/admin/hall-activities/add">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    </form>

    <?php foreach ($activities as $activity): ?>
        <form id="delete-activity-<?= e((string) $activity['id']) ?>" method="post" action="/admin/hall-activities/delete" onsubmit="return confirm('삭제하시겠습니까?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $activity['id']) ?>">
        </form>
    <?php endforeach; ?>
</section>
