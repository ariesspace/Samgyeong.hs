<section class="page">
    <h1>관별 명단 관리</h1>
    <p class="muted">학생 이름과 학년만 입력해도 저장됩니다. 직책은 필요한 학생에게만 적어 주세요.</p>

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

    <form method="post" action="/admin/halls/save" enctype="multipart/form-data" class="simple-hall-admin">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="hall-admin-grid">
            <?php foreach ($grouped as $key => $hall): ?>
                <article class="hall-admin-card <?= e($hall['color']) ?>">
                    <h2><?= e($hall['name']) ?> <span><?= e($hall['meaning']) ?></span></h2>

                    <div class="hall-admin-list">
                        <?php foreach ($hall['members'] as $member): ?>
                            <div class="hall-admin-row">
                                <input type="hidden" name="id[]" value="<?= e((string) $member['id']) ?>">
                                <input type="hidden" name="current_photo_path[]" value="<?= e($member['photo_path'] ?? '') ?>">
                                <input type="hidden" name="sort_order[]" value="<?= e((string) $member['sort_order']) ?>">

                                <div class="hall-photo-preview">
                                    <img src="<?= !empty($member['photo_path']) ? '/uploads/' . e($member['photo_path']) : '/assets/samgyeong-emblem.png' ?>" alt="" onerror="this.src='/assets/samgyeong-emblem.png'">
                                </div>
                                <label class="field-name">
                                    이름
                                    <input name="student_name[]" value="<?= e($member['student_name']) ?>" required>
                                </label>
                                <label class="field-year">
                                    학년
                                    <select name="year[]">
                                        <?php for ($year = 1; $year <= 3; $year++): ?>
                                            <option value="<?= $year ?>" <?= (int) $member['year'] === $year ? 'selected' : '' ?>><?= $year ?>학년</option>
                                        <?php endfor; ?>
                                    </select>
                                </label>
                                <label class="field-role">
                                    직책
                                    <input name="role_label[]" value="<?= e($member['role_label']) ?>" placeholder="없으면 비워둠">
                                </label>
                                <label class="field-photo">
                                    사진
                                    <input type="file" name="photo_<?= e((string) $member['id']) ?>" accept="image/*" data-hall-photo-input>
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
                    <input name="new_role_label" placeholder="없으면 비워둠">
                </label>
                <label>
                    사진
                    <input type="file" name="new_photo" accept="image/*" data-hall-photo-input>
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

<div class="photo-crop-modal" data-photo-modal hidden>
    <div class="photo-crop-dialog">
        <h2>앨범 사진 미리보기</h2>
        <p>앨범 카드에 보이는 4:5 비율로 중앙을 맞춰 저장합니다.</p>
        <div class="photo-crop-frame">
            <img alt="" data-photo-preview>
        </div>
        <div class="photo-crop-actions">
            <button type="button" class="ghost-button" data-photo-cancel>취소</button>
            <button type="button" data-photo-apply>이대로 사용</button>
        </div>
    </div>
</div>

<script src="/hall-photos.js"></script>
