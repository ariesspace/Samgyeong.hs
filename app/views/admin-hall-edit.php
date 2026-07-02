<section class="page hall-admin-page">
    <h1>관별 명단 수정</h1>
    <p class="muted">관, 이름, 학년, 직책, 사진을 수정합니다. 직책을 비우면 명단에는 관원으로 표시됩니다.</p>

    <?php $halls = hall_definitions(); ?>

    <form method="post" action="/admin/halls/update" enctype="multipart/form-data" class="hall-edit-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= e((string) $member['id']) ?>">

        <div class="hall-edit-photo">
            <div class="hall-photo-preview">
                <img src="<?= !empty($member['photo_path']) ? '/uploads/' . e($member['photo_path']) : '/assets/samgyeong-emblem.png' ?>" alt="" onerror="this.src='/assets/samgyeong-emblem.png'">
            </div>
            <label>
                사진 변경
                <input type="file" name="photo" accept="image/*" data-hall-photo-input>
            </label>
        </div>

        <div class="hall-edit-fields">
            <label>
                관
                <select name="hall_key">
                    <?php foreach ($halls as $key => $hall): ?>
                        <option value="<?= e($key) ?>" <?= $member['hall_key'] === $key ? 'selected' : '' ?>><?= e($hall['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                이름
                <input name="student_name" value="<?= e($member['student_name']) ?>" required>
            </label>
            <label>
                학년
                <select name="year">
                    <?php for ($year = 1; $year <= 3; $year++): ?>
                        <option value="<?= $year ?>" <?= (int) $member['year'] === $year ? 'selected' : '' ?>><?= $year ?>학년</option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>
                직책
                <input name="role_label" value="<?= e($member['role_label']) ?>" placeholder="없으면 비워둠">
            </label>
        </div>

        <div class="form-actions">
            <a class="button ghost-button" href="/admin/halls">목록으로</a>
            <button type="submit">수정 저장</button>
        </div>
    </form>
</section>

<div class="photo-crop-modal" data-photo-modal hidden>
    <div class="photo-crop-dialog">
        <h2>앨범 사진 미리보기</h2>
        <p>앨범 카드에 보이는 4:5 비율로 중앙에 맞춰 저장합니다.</p>
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
