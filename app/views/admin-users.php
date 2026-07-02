<section class="page admin-users-page">
    <h1>계정 관리</h1>
    <p class="muted">계정을 생성하고 아이디, 비밀번호, 권한을 수정합니다. 첫 관리자 계정과 현재 로그인 계정은 보호됩니다.</p>

    <section class="admin-create-panel">
        <h2>새 계정 생성</h2>
        <form method="post" action="/admin/users/create" class="admin-user-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                아이디
                <input name="username" required autocomplete="off">
            </label>
            <label>
                비밀번호
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label>
                권한
                <select name="role">
                    <?php foreach (['student' => '학생', 'council' => '학생회', 'admin' => '관리자'] as $role => $label): ?>
                        <option value="<?= e($role) ?>"><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button type="submit">생성</button>
        </form>
    </section>

    <div class="admin-user-list">
        <?php foreach ($users as $user): ?>
            <?php $isProtected = (int) $user['id'] === 1 || (int) $user['id'] === (int) (current_user()['id'] ?? 0); ?>
            <article class="admin-user-card">
                <form method="post" action="/admin/users/update" class="admin-user-form">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
                    <label>
                        아이디
                        <input name="username" value="<?= e($user['username']) ?>" <?= $isProtected ? 'readonly' : '' ?> required>
                    </label>
                    <label>
                        새 비밀번호
                        <input type="password" name="password" placeholder="변경할 때만 입력" <?= $isProtected ? 'disabled' : '' ?> autocomplete="new-password">
                    </label>
                    <label>
                        권한
                        <select name="role" <?= $isProtected ? 'disabled' : '' ?>>
                            <?php foreach (['student' => '학생', 'council' => '학생회', 'admin' => '관리자'] as $role => $label): ?>
                                <option value="<?= e($role) ?>" <?= $user['role'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="admin-user-meta">
                        <span class="role-badge"><?= e(role_label($user['role'])) ?></span>
                        <small><?= e(substr($user['created_at'], 0, 10)) ?></small>
                    </div>
                    <div class="admin-user-actions">
                        <?php if ($isProtected): ?>
                            <span class="muted">보호 계정</span>
                        <?php else: ?>
                            <button type="submit">수정</button>
                            <button class="danger-button" type="submit" form="delete-user-<?= e((string) $user['id']) ?>">삭제</button>
                        <?php endif; ?>
                    </div>
                </form>
            </article>
        <?php endforeach; ?>
    </div>

    <?php foreach ($users as $user): ?>
        <?php if ((int) $user['id'] !== 1 && (int) $user['id'] !== (int) (current_user()['id'] ?? 0)): ?>
            <form id="delete-user-<?= e((string) $user['id']) ?>" method="post" action="/admin/users/delete" onsubmit="return confirm('이 계정을 삭제할까요?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
