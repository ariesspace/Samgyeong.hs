<section class="page admin-users-page">
    <h1>게시판 권한 설정</h1>
    <p class="muted">게시판별 읽기와 쓰기 권한을 조정합니다. 읽기 권한을 모두 비우면 방문자에게도 공개됩니다.</p>

    <?php if ($saved): ?>
        <div class="success">게시판 권한 설정이 저장되었습니다.</div>
    <?php endif; ?>

    <form method="post" action="/admin/boards/permissions/save" class="board-permission-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <table class="board-table admin-board-permission-table">
            <thead>
                <tr>
                    <th>게시판</th>
                    <th>읽기 권한</th>
                    <th>쓰기 권한</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($boards as $slug => $board): ?>
                    <tr>
                        <td>
                            <strong><?= e($board['name']) ?></strong>
                            <span>/board/<?= e($slug) ?></span>
                        </td>
                        <td>
                            <div class="permission-checks">
                                <label class="permission-public">
                                    <input type="checkbox" disabled <?= $board['read_roles'] === [] ? 'checked' : '' ?>>
                                    전체 공개
                                </label>
                                <?php foreach ($roles as $role => $label): ?>
                                    <label>
                                        <input type="checkbox" name="read_roles[<?= e($slug) ?>][]" value="<?= e($role) ?>" <?= in_array($role, $board['read_roles'], true) ? 'checked' : '' ?>>
                                        <?= e($label) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <div class="permission-checks">
                                <?php foreach ($roles as $role => $label): ?>
                                    <label>
                                        <input type="checkbox" name="write_roles[<?= e($slug) ?>][]" value="<?= e($role) ?>" <?= in_array($role, $board['write_roles'], true) ? 'checked' : '' ?>>
                                        <?= e($label) ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="form-actions">
            <a class="button ghost-button" href="/admin/users">돌아가기</a>
            <button type="submit">권한 저장</button>
        </div>
    </form>
</section>
