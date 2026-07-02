<section class="page">
    <h1>관별 명단 관리</h1>
    <p class="muted">관을 선택하고 학생 이름, 학년, 직책만 간단히 수정합니다.</p>

    <?php
        $halls = hall_definitions();
        $grouped = [];
        foreach ($halls as $key => $hall) {
            $grouped[$key] = $hall + ['members' => []];
        }
        foreach ($members as $member) {
            if (isset($grouped[$member['hall_key']])) {
                $grouped[$member['hall_key']]['members'][] = $member;
            }
        }
    ?>

    <form method="post" action="/admin/halls/save" class="simple-hall-admin">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="hall-admin-grid">
            <?php foreach ($grouped as $key => $hall): ?>
                <article class="hall-admin-card <?= e($hall['color']) ?>">
                    <h2><?= e($hall['name']) ?> <span><?= e($hall['meaning']) ?></span></h2>

                    <div class="hall-admin-list">
                        <?php foreach ($hall['members'] as $member): ?>
                            <div class="hall-admin-row">
                                <input type="hidden" name="id[]" value="<?= e((string) $member['id']) ?>">
                                <input type="hidden" name="sort_order[]" value="<?= e((string) $member['sort_order']) ?>">

                                <label>
                                    이름
                                    <input name="student_name[]" value="<?= e($member['student_name']) ?>" required>
                                </label>
                                <label>
                                    학년
                                    <select name="year[]">
                                        <?php for ($year = 1; $year <= 3; $year++): ?>
                                            <option value="<?= $year ?>" <?= (int) $member['year'] === $year ? 'selected' : '' ?>><?= $year ?>학년</option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                                <label>
                                    직책
                                    <input name="role_label[]" value="<?= e($member['role_label']) ?>" required>
                                </label>
                                <button class="danger-button" type="submit" form="delete-hall-member-<?= e((string) $member['id']) ?>">삭제</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <section class="add-member-panel">
            <h2>학생 추가</h2>
            <div class="add-member-row">
                <label>
                    관
                    <select name="new_hall_key">
                        <?php foreach ($halls as $key => $hall): ?>
                            <option value="<?= e($key) ?>"><?= e($hall['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    이름
                    <input name="new_student_name" placeholder="학생 이름">
                </label>
                <label>
                    학년
                    <select name="new_year">
                        <option value="1">1학년</option>
                        <option value="2">2학년</option>
                        <option value="3">3학년</option>
                    </select>
                </label>
                <label>
                    직책
                    <input name="new_role_label" value="대표">
                </label>
                <input type="hidden" name="new_sort_order" value="99">
            </div>
        </section>

        <div class="form-actions">
            <a class="button ghost-button" href="/student-halls">명단 보기</a>
            <button type="submit">저장</button>
        </div>
    </form>

    <?php foreach ($members as $member): ?>
        <form id="delete-hall-member-<?= e((string) $member['id']) ?>" method="post" action="/admin/halls/delete">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $member['id']) ?>">
        </form>
    <?php endforeach; ?>
</section>
