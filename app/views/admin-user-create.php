<section class="page admin-users-page">
    <h1>계정 생성</h1>
    <p class="muted">학생, 삼경원, 관리자 계정을 새로 발급합니다. 생성한 뒤 권한 변경이나 비밀번호 초기화는 계정 리스트에서 처리할 수 있습니다.</p>

    <section class="admin-create-panel">
        <h2>새 계정 정보</h2>
        <form method="post" action="/admin/users/create" class="admin-create-form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                아이디
                <input name="username" required autocomplete="off" placeholder="예: student_01">
            </label>
            <label>
                이름
                <input name="display_name" required autocomplete="off" placeholder="학생 이름">
            </label>
            <label>
                초기 비밀번호
                <input type="password" name="password" required autocomplete="new-password" placeholder="초기 비밀번호 입력">
            </label>
            <label>
                권한
                <select name="role">
                    <option value="student">재학생 (일반)</option>
                    <option value="council">삼경원 (학생회)</option>
                    <option value="admin">관리자</option>
                </select>
            </label>
            <label>
                관
                <select name="hall_key">
                    <option value="">선택 안 함</option>
                    <?php foreach (hall_definitions() as $key => $hall): ?>
                        <option value="<?= e($key) ?>"><?= e($hall['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                학년
                <select name="year">
                    <option value="0">선택 안 함</option>
                    <option value="1">1학년</option>
                    <option value="2">2학년</option>
                    <option value="3">3학년</option>
                </select>
            </label>
            <div class="admin-create-actions">
                <a class="button secondary" href="/admin/users">목록으로</a>
                <button type="submit">계정 생성</button>
            </div>
        </form>
    </section>
</section>
