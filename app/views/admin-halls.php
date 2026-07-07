<section class="page hall-admin-page">
    <h1>관별 명단 관리</h1>
    <p class="muted">목록은 확인하기 쉽게 정리하고, 세부 정보는 수정 화면에서 관리합니다.</p>

    <?php $halls = hall_definitions(); ?>

    <form method="post" action="/admin/halls/save" enctype="multipart/form-data" class="simple-hall-admin">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <section class="add-member-panel hall-add-panel">
            <div>
                <h2>인원 추가</h2>
                <p class="muted">새 학생을 추가한 뒤 필요하면 수정 화면에서 사진과 세부 정보를 더 자세히 관리할 수 있습니다.</p>
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
                <input type="hidden" name="new_sort_order" value="99">
                <button type="submit">추가</button>
            </div>
        </section>
    </form>

    <div class="hall-admin-toolbar">
        <a class="button ghost-button" href="/student-halls">명단 보기</a>
    </div>

    <div class="hall-list-wrap">
        <table class="board-table hall-admin-table hall-summary-table">
            <thead>
                <tr>
                    <th>사진</th>
                    <th>관</th>
                    <th>이름</th>
                    <th>학년</th>
                    <th>직책</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td>
                            <div class="hall-photo-preview">
                                <img src="<?= !empty($member['photo_path']) ? '/uploads/' . e($member['photo_path']) : '/assets/samgyeong-emblem2.png?v=2026070637' ?>" alt="" onerror="this.src='/assets/samgyeong-emblem2.png?v=2026070637'">
                            </div>
                        </td>
                        <td><?= e($member['hall_name']) ?></td>
                        <td class="hall-member-name"><?= e($member['student_name']) ?></td>
                        <td><?= e((string) $member['year']) ?>학년</td>
                        <td><?= e($member['role_label'] !== '' ? $member['role_label'] : '관원') ?></td>
                        <td>
                            <div class="hall-admin-actions">
                                <a class="button ghost-button" href="/admin/halls/edit?id=<?= e((string) $member['id']) ?>">수정</a>
                                <button class="danger-button" type="submit" form="delete-hall-member-<?= e((string) $member['id']) ?>">삭제</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php foreach ($members as $member): ?>
        <form id="delete-hall-member-<?= e((string) $member['id']) ?>" method="post" action="/admin/halls/delete" onsubmit="return confirm('삭제하시겠습니까?');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= e((string) $member['id']) ?>">
        </form>
    <?php endforeach; ?>
</section>
