<article class="page post-detail">
    <header class="post-detail-head">
        <a class="back-link" href="/board/<?= e($board['slug']) ?>">목록으로</a>
        <h1><?= e($post['title']) ?></h1>
        <dl class="post-meta">
            <div>
                <dt>분류</dt>
                <dd><span class="board-badge"><?= e($board['badge']) ?></span></dd>
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
        <?= nl2br(e($post['body'])) ?>
    </div>

    <?php if ($post['file_path']): ?>
        <div class="post-file">
            <span>첨부파일</span>
            <a href="/uploads/<?= e($post['file_path']) ?>" download><?= e($post['file_name']) ?></a>
        </div>
    <?php endif; ?>

    <footer class="post-detail-foot">
        <a class="button secondary-button" href="/board/<?= e($board['slug']) ?>">목록</a>
    </footer>
</article>
