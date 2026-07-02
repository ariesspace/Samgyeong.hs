<section class="page admin-users-page">
    <h1>계정 정보 수정</h1>
    <p class="muted">이름, 관, 학년은 관리자만 수정할 수 있습니다. 비밀번호 변경은 계정 리스트의 초기화 기능을 사용합니다.</p>

    <section class="admin-create-panel">
        <h2><?= e($account['username']) ?></h2>
        <form method="post" action="/admin/users/profile" class="admin-create-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="user_id" value="<?= e((string) $account['id']) ?>">
            <label>
                이름
                <input name="display_name" value="<?= e($account['display_name']) ?>" required>
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
            <div class="admin-create-actions">
                <a class="button secondary" href="/admin/users">목록으로</a>
                <button type="submit">수정 저장</button>
            </div>
        </form>
    </section>
</section>
