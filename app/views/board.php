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

    <?php if ($posts): ?>
        <table class="board-table">
            <thead>
                <tr>
                    <th>번호</th>
                    <th>제목</th>
                    <th>작성자</th>
                    <th>작성일</th>
                    <th>첨부</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($posts as $post): ?>
                    <tr>
                        <td><?= e((string) $post['id']) ?></td>
                        <td class="title-cell">
                            <strong><?= e($post['title']) ?></strong>
                            <span><?= nl2br(e($post['body'])) ?></span>
                        </td>
                        <td><?= e($post['username']) ?></td>
                        <td><?= e(substr($post['created_at'], 0, 10)) ?></td>
                        <td>
                            <?php if ($post['file_path']): ?>
                                <a href="/uploads/<?= e($post['file_path']) ?>" download>다운로드</a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
