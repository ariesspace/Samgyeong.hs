<section class="page">
    <div class="page-title">
        <h1><?= e($board['name']) ?></h1>
        <?php if ($canWrite): ?>
            <a class="button" href="/board/<?= e($board['slug']) ?>/new">글쓰기</a>
        <?php endif; ?>
    </div>

    <?php if (!$posts): ?>
        <p class="muted">아직 게시글이 없습니다.</p>
    <?php endif; ?>

    <div class="post-list">
        <?php foreach ($posts as $post): ?>
            <article class="post">
                <h2><?= e($post['title']) ?></h2>
                <p class="meta"><?= e($post['username']) ?> · <?= e($post['created_at']) ?></p>
                <p><?= nl2br(e($post['body'])) ?></p>
                <?php if ($post['file_path']): ?>
                    <a href="/uploads/<?= e($post['file_path']) ?>" download><?= e($post['file_name']) ?></a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
