<article class="page post-detail">
    <header class="post-detail-head">
        <a class="back-link" href="/board/<?= e($board['slug']) ?>">목록으로</a>
        <h1><?= e($post['title']) ?></h1>
        <dl class="post-meta">
            <div>
                <dt>분류</dt>
                <dd><span class="board-badge"><?= e($post['tag'] ?? $board['badge']) ?></span></dd>
            </div>
            <div>
                <dt>작성자</dt>
                <dd><?= e($post['username']) ?></dd>
            </div>
            <div>
                <dt>작성일</dt>
                <dd><?= e(substr($post['created_at'], 0, 10)) ?></dd>
            </div>
            <div>
                <dt>조회수</dt>
                <dd><?= e((string) ($post['views'] ?? 0)) ?></dd>
            </div>
        </dl>
    </header>

    <div class="post-body">
        <?= render_post_body($post['body']) ?>
    </div>

    <?php if ($post['file_path']): ?>
        <div class="post-file">
            <span>첨부파일</span>
            <a href="/uploads/<?= e($post['file_path']) ?>" download><?= e($post['file_name']) ?></a>
        </div>
    <?php endif; ?>

    <footer class="post-detail-foot">
        <a class="button secondary-button" href="/board/<?= e($board['slug']) ?>">목록</a>
        <?php if ($canManage): ?>
            <div class="post-manage-actions">
                <a class="button ghost-button" href="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>/edit">수정</a>
                <form method="post" action="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>/delete" onsubmit="return confirm('삭제하시겠습니까?');">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <button class="danger-button" type="submit">삭제</button>
                </form>
            </div>
        <?php endif; ?>
    </footer>
</article>
