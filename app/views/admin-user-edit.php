<section class="page admin-users-page">
    <h1>계정 정보 수정</h1>
    <p class="muted">목록에서는 식별 정보만 확인하고, 권한과 세부 정보는 이 화면에서 관리합니다.</p>

    <?php if (($saved ?? '') === 'profile'): ?>
        <div class="notice success">계정 정보가 저장되었습니다.</div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="notice error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="admin-create-panel admin-account-detail">
        <div class="account-detail-head">
            <div>
                <h2><?= e($account['display_name'] ?: $account['username']) ?></h2>
                <p><?= e($account['username']) ?> · 가입일 <?= e(substr($account['created_at'], 0, 10)) ?></p>
            </div>
            <span class="role-badge role-badge-<?= e($account['role']) ?>"><?= e(role_label($account['role'])) ?></span>
        </div>

        <form method="post" action="/admin/users/profile" enctype="multipart/form-data" class="admin-create-form account-detail-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="user_id" value="<?= e((string) $account['id']) ?>">

            <div class="account-photo-edit">
                <img src="<?= !empty($account['photo_path']) ? '/uploads/' . e($account['photo_path']) : '/assets/samgyeong-emblem.png' ?>" alt="" onerror="this.src='/assets/samgyeong-emblem.png'">
                <label>
                    사진 변경
                    <input type="file" name="photo" accept="image/*">
                </label>
            </div>

            <label>
                이름
                <input name="display_name" value="<?= e($account['display_name']) ?>" required>
            </label>
            <label>
                권한
                <select name="role">
                    <?php foreach (['guest' => '게스트 (읽기 전용)', 'student' => '재학생 (일반)', 'council' => '삼경원 (학생회)', 'admin' => '관리자'] as $role => $label): ?>
                        <option value="<?= e($role) ?>" <?= $account['role'] === $role ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                관
                <select name="hall_key">
                    <option value="">선택 안 함</option>
                    <?php foreach (hall_definitions() as $key => $hall): ?>
                        <option value="<?= e($key) ?>" <?= ($account['hall_key'] ?? '') === $key ? 'selected' : '' ?>><?= e($hall['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                학년
                <select name="year">
                    <option value="0">선택 안 함</option>
                    <?php for ($year = 1; $year <= 3; $year++): ?>
                        <option value="<?= $year ?>" <?= (int) ($account['year'] ?? 0) === $year ? 'selected' : '' ?>><?= $year ?>학년</option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>
                관별 명단 태그
                <input name="role_label" value="<?= e($account['role_label'] ?? '') ?>" placeholder="예: 관장, 부관장 / 비우면 관원">
            </label>

            <div class="admin-create-actions">
                <a class="button secondary" href="/admin/users">목록으로</a>
                <button type="submit">저장</button>
            </div>
        </form>
    </section>

    <section class="admin-create-panel account-danger-panel">
        <h2>계정 관리</h2>
        <p class="muted">비밀번호 초기화 시 임시 비밀번호는 <strong>samgyeong1234</strong>로 변경됩니다.</p>

        <div class="account-danger-actions">
            <form method="post" action="/admin/users/reset-password" onsubmit="return confirm('이 계정의 비밀번호를 초기화할까요?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="user_id" value="<?= e((string) $account['id']) ?>">
                <input type="hidden" name="redirect_to" value="/admin/users?saved=reset">
                <button type="submit" class="ghost-button">비밀번호 초기화</button>
            </form>
            <?php if (($account['username'] ?? '') === 'guest'): ?>
                <span class="muted">guest 계정은 삭제할 수 없는 보호 계정입니다.</span>
            <?php else: ?>
                <form method="post" action="/admin/users/delete" onsubmit="return confirm('삭제하시겠습니까?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="user_id" value="<?= e((string) $account['id']) ?>">
                    <button type="submit" class="danger-button">계정 삭제</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</section>
