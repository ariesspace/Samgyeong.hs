<section class="page admin-users-page">
    <h1>계정 권한 관리</h1>
    <p class="muted">총 <strong><?= e((string) count($users)) ?></strong>명의 가입된 계정이 있습니다.</p>
    <p class="muted small-note">비밀번호 초기화 시 임시 비밀번호는 <strong>samgyeong1234</strong>로 변경됩니다.</p>

    <table class="board-table admin-user-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>아이디</th>
                <th>현재 권한</th>
                <th>가입일</th>
                <th>권한 부여/수정</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $index => $user): ?>
                <?php $isProtected = (int) $user['id'] === 1 || (int) $user['id'] === (int) (current_user()['id'] ?? 0); ?>
                <tr>
                    <td><?= e((string) ($index + 1)) ?></td>
                    <td class="admin-user-id"><?= e($user['username']) ?></td>
                    <td><span class="role-badge role-badge-<?= e($user['role']) ?>"><?= e(role_label($user['role'])) ?></span></td>
                    <td><?= e(substr($user['created_at'], 0, 10)) ?></td>
                    <td>
                        <?php if ($isProtected): ?>
                            <span class="muted">수정 불가</span>
                        <?php else: ?>
                            <form method="post" action="/admin/users/update" class="inline-form admin-role-form">
                                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                                <select name="role">
                                    <?php foreach (['student' => '재학생 (일반)', 'council' => '삼경원 (학생회)', 'admin' => '관리자'] as $role => $label): ?>
                                        <option value="<?= e($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit">저장</button>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($isProtected): ?>
                            <span class="muted">보호 계정</span>
                        <?php else: ?>
                            <div class="admin-row-actions">
                                <button type="submit" form="reset-user-<?= e((string) $user['id']) ?>">비밀번호 초기화</button>
                                <button class="danger-button" type="submit" form="delete-user-<?= e((string) $user['id']) ?>">삭제</button>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php foreach ($users as $user): ?>
        <?php if ((int) $user['id'] !== 1 && (int) $user['id'] !== (int) (current_user()['id'] ?? 0)): ?>
            <form id="reset-user-<?= e((string) $user['id']) ?>" method="post" action="/admin/users/reset-password" onsubmit="return confirm('이 계정의 비밀번호를 초기화할까요?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
            </form>
            <form id="delete-user-<?= e((string) $user['id']) ?>" method="post" action="/admin/users/delete" onsubmit="return confirm('이 계정을 삭제할까요?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
