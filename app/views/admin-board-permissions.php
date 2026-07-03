<?php
    $readOptions = [
        'public' => '전체 공개',
        'student' => '재학생 이상',
        'council' => '삼경원 이상',
        'admin' => '관리자만',
    ];
    $writeOptions = [
        'none' => '글쓰기 불가',
        'student' => '재학생 이상',
        'council' => '삼경원 이상',
        'admin' => '관리자만',
    ];
    $presetOf = function (array $roles, bool $read): string {
        $allowed = $read ? ['guest', 'student', 'council', 'admin'] : ['student', 'council', 'admin'];
        $roles = array_values(array_intersect($allowed, $roles));
        sort($roles);
        $key = implode(',', $roles);

        return match ($key) {
            '' => $read ? 'public' : 'none',
            'admin' => 'admin',
            'admin,council' => 'council',
            'admin,council,guest,student' => 'student',
            'admin,council,student' => 'student',
            default => $read ? 'student' : 'none',
        };
    };
?>

<section class="page admin-users-page">
    <h1>게시판 권한 설정</h1>
    <p class="muted">게시판별 접근 범위를 선택합니다. 세부 역할을 하나씩 고르지 않고, 운영에 맞는 범위만 지정하면 됩니다.</p>

    <?php if ($saved): ?>
        <div class="success">게시판 권한 설정이 저장되었습니다.</div>
    <?php endif; ?>

    <form method="post" action="/admin/boards/permissions/save" class="board-permission-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <table class="board-table admin-board-permission-table">
            <thead>
                <tr>
                    <th>게시판</th>
                    <th>읽기 권한</th>
                    <th>쓰기 권한</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($boards as $slug => $board): ?>
                    <tr>
                        <td>
                            <strong><?= e($board['name']) ?></strong>
                            <span>/board/<?= e($slug) ?></span>
                        </td>
                        <td>
                            <select class="permission-select" name="read_preset[<?= e($slug) ?>]">
                                <?php $currentRead = $presetOf($board['read_roles'], true); ?>
                                <?php foreach ($readOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $currentRead === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="permission-select" name="write_preset[<?= e($slug) ?>]">
                                <?php $currentWrite = $presetOf($board['write_roles'], false); ?>
                                <?php foreach ($writeOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $currentWrite === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="form-actions">
            <a class="button ghost-button" href="/admin/users">돌아가기</a>
            <button type="submit">권한 저장</button>
        </div>
    </form>
</section>
