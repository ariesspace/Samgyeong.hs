<section class="page">
    <h1>계정 권한 관리</h1>
    <p class="muted">가입된 계정의 권한을 확인하고 변경합니다. 첫 관리자 계정은 보호됩니다.</p>

    <table class="board-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>아이디</th>
                <th>현재 권한</th>
                <th>가입일</th>
                <th>권한 변경</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= e((string) $user['id']) ?></td>
                    <td><?= e($user['username']) ?></td>
                    <td><span class="role-badge"><?= e(role_label($user['role'])) ?></span></td>
                    <td><?= e(substr($user['created_at'], 0, 10)) ?></td>
                    <td>
                        <?php if ((int) $user['id'] === 1): ?>
                            <span class="muted">수정 불가</span>
                        <?php else: ?>
                            <form method="post" action="/admin/users/role" class="inline-form">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                <select name="role">
                                    <?php foreach (['student' => '학생', 'council' => '학생회', 'admin' => '관리자'] as $role => $label): ?>
                                        <option value="<?= e($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">저장</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
