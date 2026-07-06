<?php
    $presets = page_permission_presets();
?>

<section class="page admin-users-page">
    <h1>페이지 권한 설정</h1>
    <p class="muted">게시판이 아닌 일반 페이지의 접근 범위를 설정합니다. 학교규칙, 생활규정, 상벌점 페이지도 여기에서 관리합니다.</p>

    <?php if ($saved): ?>
        <div class="success">페이지 권한 설정이 저장되었습니다.</div>
    <?php endif; ?>

    <form method="post" action="/admin/pages/permissions/save" class="board-permission-form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <table class="board-table admin-board-permission-table">
            <thead>
                <tr>
                    <th>페이지</th>
                    <th>읽기 권한</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $key => $page): ?>
                    <?php
                        $roles = page_read_roles($db, $key);
                        $currentPreset = page_permission_preset_from_roles($roles);
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($page['label']) ?></strong>
                            <span><?= e($page['path']) ?></span>
                        </td>
                        <td>
                            <select class="permission-select" name="read_preset[<?= e($key) ?>]">
                                <?php foreach ($presets as $value => $option): ?>
                                    <option value="<?= e($value) ?>" <?= $currentPreset === $value ? 'selected' : '' ?>><?= e($option['label']) ?></option>
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
