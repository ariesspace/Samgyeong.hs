<section class="page">
    <h1>관별 명단 관리</h1>
    <p class="muted">관 이름, 의미, 색상, 학생 이름, 학년, 직책을 수정한 뒤 저장합니다.</p>

    <form method="post" action="/admin/halls/save" class="admin-editor">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="admin-table-wrap">
            <table class="board-table admin-table">
                <thead>
                    <tr>
                        <th>정렬</th>
                        <th>관 코드</th>
                        <th>관 이름</th>
                        <th>의미</th>
                        <th>색상</th>
                        <th>학생 이름</th>
                        <th>학년</th>
                        <th>직책</th>
                        <th>삭제</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="id[]" value="<?= e((string) $member['id']) ?>">
                                <input name="sort_order[]" type="number" value="<?= e((string) $member['sort_order']) ?>">
                            </td>
                            <td><input name="hall_key[]" value="<?= e($member['hall_key']) ?>" required></td>
                            <td><input name="hall_name[]" value="<?= e($member['hall_name']) ?>" required></td>
                            <td><input name="hall_meaning[]" value="<?= e($member['hall_meaning']) ?>"></td>
                            <td>
                                <select name="hall_color[]">
                                    <?php foreach (['blue' => '파랑', 'gold' => '금색', 'green' => '초록'] as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $member['hall_color'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td><input name="student_name[]" value="<?= e($member['student_name']) ?>" required></td>
                            <td><input name="year[]" type="number" min="1" max="3" value="<?= e((string) $member['year']) ?>"></td>
                            <td><input name="role_label[]" value="<?= e($member['role_label']) ?>" required></td>
                            <td>
                                <button class="danger-button" type="submit" form="delete-hall-member-<?= e((string) $member['id']) ?>">삭제</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="new-row">
                        <td><input name="new_sort_order" type="number" value="99"></td>
                        <td><input name="new_hall_key" value="gyeongcheon"></td>
                        <td><input name="new_hall_name" value="경천관"></td>
                        <td><input name="new_hall_meaning" value="하늘"></td>
                        <td>
                            <select name="new_hall_color">
                                <option value="blue">파랑</option>
                                <option value="gold">금색</option>
                                <option value="green">초록</option>
                            </select>
                        </td>
                        <td><input name="new_student_name" placeholder="새 학생 이름"></td>
                        <td><input name="new_year" type="number" min="1" max="3" value="1"></td>
                        <td><input name="new_role_label" value="대표"></td>
                        <td><span class="muted">추가</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="form-actions">
            <a class="button ghost-button" href="/student-halls">명단 보기</a>
            <button type="submit">전체 저장</button>
        </div>
    </form>

    <?php foreach ($members as $member): ?>
        <form id="delete-hall-member-<?= e((string) $member['id']) ?>" method="post" action="/admin/halls/delete">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $member['id']) ?>">
        </form>
    <?php endforeach; ?>
</section>
