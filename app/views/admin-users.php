<section class="page admin-users-page">
    <h1>계정 권한 관리</h1>
    <p class="muted">총 <strong><?= e((string) count($users)) ?></strong>명의 가입된 계정이 있습니다.</p>
    <?php
    $savedMessages = [
        'profile' => '계정 정보가 저장되었습니다.',
        'reset' => '비밀번호가 samgyeong1234로 초기화되었습니다.',
        'deleted' => '계정이 삭제되었습니다.',
    ];
    ?>
    <?php if (isset($savedMessages[$saved ?? ''])): ?>
        <div class="notice success"><?= e($savedMessages[$saved]) ?></div>
    <?php endif; ?>

    <table class="board-table admin-user-table compact-user-table">
        <thead>
            <tr>
                <th>No.</th>
                <th>이름</th>
                <th>아이디</th>
                <th>소속</th>
                <th>권한</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $index => $user): ?>
                <?php $isProtected = (int) $user['id'] === 1 || (int) $user['id'] === (int) (current_user()['id'] ?? 0); ?>
                <tr>
                    <td><?= e((string) ($index + 1)) ?></td>
                    <td class="admin-user-name"><?= e($user['display_name'] ?: '-') ?></td>
                    <td class="admin-user-id"><?= e($user['username']) ?></td>
                    <td>
                        <?= e(hall_label($user['hall_key'] ?? '')) ?>
                        <?= (int) ($user['year'] ?? 0) > 0 ? ' · ' . e((string) $user['year']) . '학년' : '' ?>
                    </td>
                    <td><span class="role-badge role-badge-<?= e($user['role']) ?>"><?= e(role_label($user['role'])) ?></span></td>
                    <td>
                        <?php if ($isProtected): ?>
                            <span class="muted">보호</span>
                        <?php else: ?>
                            <div class="compact-actions">
                                <a class="icon-button" href="/admin/users/edit?id=<?= e((string) $user['id']) ?>" title="계정 수정" aria-label="계정 수정">✎</a>
                                <button class="icon-button danger" type="submit" form="delete-user-<?= e((string) $user['id']) ?>" title="계정 삭제" aria-label="계정 삭제">🗑</button>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php foreach ($users as $user): ?>
        <?php if ((int) $user['id'] !== 1 && (int) $user['id'] !== (int) (current_user()['id'] ?? 0)): ?>
            <form id="delete-user-<?= e((string) $user['id']) ?>" method="post" action="/admin/users/delete" onsubmit="return confirm('삭제하시겠습니까?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="user_id" value="<?= e((string) $user['id']) ?>">
            </form>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
