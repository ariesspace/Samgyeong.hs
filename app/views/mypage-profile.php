<section class="page mypage-page">
    <section class="profile-manage-card">
        <div class="profile-manage-head">
            <div>
                <h1><?= e(($profile['display_name'] ?: $profile['username'])) ?> 프로필 관리</h1>
                <p><?= e($profile['username']) ?></p>
            </div>
        </div>

        <?php if ($saved === 'profile'): ?>
            <div class="success">프로필 정보가 저장되었습니다. 사진을 변경했다면 관별 명단과 앨범에도 함께 반영됩니다.</div>
        <?php elseif ($saved === 'photo'): ?>
            <div class="success">프로필 사진이 저장되었습니다. 관별 명단과 앨범에도 함께 반영됩니다.</div>
        <?php elseif ($saved === 'password'): ?>
            <div class="success">비밀번호가 변경되었습니다.</div>
        <?php endif; ?>
        <?php if ($error === 'password'): ?>
            <div class="error">새 비밀번호와 확인 값이 일치하지 않습니다.</div>
        <?php elseif ($error === 'photo'): ?>
            <div class="error">사진을 저장하지 못했습니다. JPG, PNG, WEBP 파일로 다시 시도해 주세요.</div>
        <?php endif; ?>

        <p class="profile-note">이름, 관, 학년은 관리자만 변경할 수 있습니다. 사진은 본인이 직접 등록할 수 있고, 등록한 사진은 관별 명단과 인원 앨범에 같이 사용됩니다.</p>

        <form method="post" action="/mypage/profile" enctype="multipart/form-data" class="profile-manage-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="profile-photo-area">
                <div class="profile-photo-frame hall-photo-preview">
                    <img src="<?= !empty($profile['photo_path']) ? '/uploads/' . e($profile['photo_path']) : '/assets/samgyeong-emblem2.png?v=2026070637' ?>" alt="" onerror="this.src='/assets/samgyeong-emblem2.png?v=2026070637'">
                </div>
                <label class="profile-photo-button" title="사진 변경">
                    <span aria-hidden="true">▣</span>
                    <input type="file" name="photo" accept="image/*" data-hall-photo-input>
                </label>
            </div>

            <div class="profile-readonly-grid">
                <label>
                    아이디
                    <input value="<?= e($profile['username']) ?>" disabled>
                </label>
                <label>
                    현재 권한
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

            <div class="profile-form-actions">
                <button type="button" class="ghost-button" data-password-open>비밀번호 변경</button>
                <button type="submit">프로필 저장</button>
            </div>
        </form>
    </section>
</section>

<div class="profile-password-modal" data-password-modal hidden>
    <div class="profile-password-dialog">
        <div class="profile-password-head">
            <h2>비밀번호 변경</h2>
            <button type="button" class="icon-button" data-password-close aria-label="닫기">×</button>
        </div>
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
            <div class="profile-password-actions">
                <button type="button" class="ghost-button" data-password-close>취소</button>
                <button type="submit">변경</button>
            </div>
        </form>
    </div>
</div>

<div class="photo-crop-modal" data-photo-modal hidden>
    <div class="photo-crop-dialog">
        <h2>프로필 사진 미리보기</h2>
        <p>관별 앨범 카드에 보이는 4:5 비율로 중앙에 맞춰 저장합니다.</p>
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
<script>
document.querySelectorAll('[data-password-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.querySelector('[data-password-modal]');
        if (modal) {
            modal.hidden = false;
            const input = modal.querySelector('input[type="password"]');
            if (input) {
                input.focus();
            }
        }
    });
});
document.querySelectorAll('[data-password-close]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = button.closest('[data-password-modal]');
        if (modal) {
            modal.hidden = true;
        }
    });
});
</script>
