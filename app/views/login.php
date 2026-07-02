<section class="page narrow">
    <h1>로그인</h1>
    <?php if ($error): ?>
        <p class="error"><?= e($error) ?></p>
    <?php endif; ?>
    <form method="post" action="/login" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
            아이디
            <input name="username" required autocomplete="username">
        </label>
        <label>
            비밀번호
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit">로그인</button>
    </form>
</section>
