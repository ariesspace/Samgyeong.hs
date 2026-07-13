<article class="page post-detail">
    <header class="post-detail-head">
        <a class="back-link" href="/board/<?= e($board['slug']) ?>">목록으로</a>
        <h1><?= e($post['title']) ?></h1>
        <dl class="post-meta">
            <div>
                <dt>분류</dt>
                <?php $tag = $post['tag'] ?? $board['badge']; ?>
                <?php
                    $tagClass = match ($tag) {
                        '공지' => 'board-badge-notice',
                        '소양' => 'board-badge-literacy',
                        '교칙' => 'board-badge-rules',
                        default => '',
                    };
                ?>
                <dd><span class="board-badge <?= e($tagClass) ?>"><?= e($tag) ?></span></dd>
            </div>
            <div>
                <dt>작성자</dt>
                <dd><?= e(($post['author_name'] ?? '') ?: $post['username']) ?></dd>
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

    <section class="post-like-panel" aria-label="좋아요">
        <?php if ($canLike): ?>
            <form method="post" action="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>/like">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <button class="post-like-button <?= $likedByUser ? 'active' : '' ?>" type="submit" aria-pressed="<?= $likedByUser ? 'true' : 'false' ?>">
                    <span aria-hidden="true"><?= $likedByUser ? '♥' : '♡' ?></span>
                    <?= $likedByUser ? '좋아요 취소' : '좋아요' ?>
                </button>
            </form>
        <?php else: ?>
            <div class="post-like-readonly">
                <span aria-hidden="true">♡</span>
                좋아요
            </div>
        <?php endif; ?>
        <strong><?= e((string) ($likeCount ?? 0)) ?></strong>
    </section>

    <?php if (!empty($files)): ?>
        <div class="post-file">
            <span>첨부파일</span>
            <ul>
                <?php foreach ($files as $file): ?>
                    <li>
                        <a href="/uploads/<?= e($file['file_path']) ?>" download><?= e($file['file_name']) ?></a>
                    </li>
                <?php endforeach; ?>
            </ul>
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
