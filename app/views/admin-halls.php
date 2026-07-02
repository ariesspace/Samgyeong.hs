<section class="page hall-admin-page">
    <h1>관별 명단 관리</h1>
    <p class="muted">관 변경, 이름, 학년, 직책, 사진을 한 줄에서 바로 수정할 수 있습니다. 직책을 비워두면 명단에는 관원으로 표시됩니다.</p>

    <?php $halls = hall_definitions(); ?>

    <form method="post" action="/admin/halls/save" enctype="multipart/form-data" class="simple-hall-admin">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <section class="add-member-panel hall-add-panel">
            <div>
                <h2>인원 추가</h2>
                <p class="muted">새 학생을 먼저 추가한 뒤 아래 리스트에서 순서와 사진을 계속 관리할 수 있습니다.</p>
            </div>
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

        <div class="hall-admin-toolbar">
            <a class="button ghost-button" href="/student-halls">명단 보기</a>
            <button type="submit">변경사항 저장</button>
        </div>

        <div class="hall-list-wrap">
            <table class="board-table hall-admin-table">
                <thead>
                    <tr>
                        <th>사진</th>
                        <th>관</th>
                        <th>이름</th>
                        <th>학년</th>
                        <th>직책</th>
                        <th>순서</th>
                        <th>사진 변경</th>
                        <th>관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr class="hall-list-row">
                            <td>
                                <div class="hall-photo-preview">
                                    <img src="<?= !empty($member['photo_path']) ? '/uploads/' . e($member['photo_path']) : '/assets/samgyeong-emblem.png' ?>" alt="" onerror="this.src='/assets/samgyeong-emblem.png'">
                                </div>
                            </td>
                            <td>
                                <input type="hidden" name="id[]" value="<?= e((string) $member['id']) ?>">
                                <input type="hidden" name="current_photo_path[]" value="<?= e($member['photo_path'] ?? '') ?>">
                                <select name="hall_key[]">
                                    <?php foreach ($halls as $key => $hall): ?>
                                        <option value="<?= e($key) ?>" <?= $member['hall_key'] === $key ? 'selected' : '' ?>><?= e($hall['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input name="student_name[]" value="<?= e($member['student_name']) ?>" required></td>
                            <td>
                                <select name="year[]">
                                    <?php for ($year = 1; $year <= 3; $year++): ?>
                                        <option value="<?= $year ?>" <?= (int) $member['year'] === $year ? 'selected' : '' ?>><?= $year ?>학년</option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                            <td><input name="role_label[]" value="<?= e($member['role_label']) ?>" placeholder="없으면 비워둠"></td>
                            <td><input type="number" name="sort_order[]" value="<?= e((string) $member['sort_order']) ?>" min="0"></td>
                            <td><input type="file" name="photo_<?= e((string) $member['id']) ?>" accept="image/*" data-hall-photo-input></td>
                            <td>
                                <button class="danger-button" type="submit" form="delete-hall-member-<?= e((string) $member['id']) ?>">삭제</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <a class="button ghost-button" href="/student-halls">명단 보기</a>
            <button type="submit">변경사항 저장</button>
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
