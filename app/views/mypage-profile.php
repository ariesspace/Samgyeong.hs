<section class="page mypage-page">
    <h1>내 정보 수정</h1>
    <p class="muted">이름, 관, 학년은 관리자만 수정할 수 있습니다. 본인 계정에서는 비밀번호만 변경할 수 있습니다.</p>

    <?php if ($saved): ?>
        <div class="success">비밀번호가 변경되었습니다.</div>
    <?php endif; ?>
    <?php if ($error === 'password'): ?>
        <div class="error">새 비밀번호와 확인 값이 일치하지 않습니다.</div>
    <?php endif; ?>

    <section class="profile-card">
        <div class="profile-grid">
            <label>
                아이디
                <input value="<?= e($profile['username']) ?>" disabled>
            </label>
            <label>
                권한
                <input value="<?= e(role_label($profile['role'])) ?>" disabled>
            </label>
            <label>
                이름
                <input value="<?= e($profile['display_name'] ?: '-') ?>" disabled>
            </label>
            <label>
                관
                <input value="<?= e(hall_label($profile['hall_key'] ?? '')) ?>" disabled>
            </label>
            <label>
                학년
                <input value="<?= (int) ($profile['year'] ?? 0) > 0 ? e((string) $profile['year']) . '학년' : '-' ?>" disabled>
            </label>
        </div>
    </section>

    <section class="profile-card">
        <h2>비밀번호 변경</h2>
        <form method="post" action="/mypage/password" class="profile-password-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                새 비밀번호
                <input type="password" name="password" required autocomplete="new-password" placeholder="새 비밀번호 입력">
            </label>
            <label>
                새 비밀번호 확인
                <input type="password" name="password_confirm" required autocomplete="new-password" placeholder="새 비밀번호 다시 입력">
            </label>
            <button type="submit">비밀번호 변경</button>
        </form>
    </section>
</section>
