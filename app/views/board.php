<section class="page board-page">
    <header class="board-head">
        <h1><?= e($board['name']) ?></h1>
    </header>

    <div class="board-tools">
        <p>총 <strong><?= count($posts) ?></strong>건</p>
        <div class="board-actions">
            <?php if ($canWrite): ?>
                <a class="button board-write-button" href="/board/<?= e($board['slug']) ?>/new">글쓰기</a>
            <?php endif; ?>
            <form method="get" action="/board/<?= e($board['slug']) ?>" class="board-search">
                <select name="field" aria-label="검색 범위">
                    <option value="all">제목</option>
                </select>
                <input name="q" value="<?= e($keyword ?? '') ?>" placeholder="검색어 입력">
                <button type="submit">검색</button>
            </form>
        </div>
    </div>

    <table class="board-table public-board-table">
        <thead>
            <tr>
                <th class="col-no">번호</th>
                <th>제목</th>
                <th class="col-author">작성자</th>
                <th class="col-date">작성일</th>
                <th class="col-views">조회수</th>
                <?php if ($hasManageColumn): ?>
                    <th class="col-manage">관리</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php if (!$posts): ?>
                <tr>
                    <td colspan="<?= $hasManageColumn ? 6 : 5 ?>" class="empty-board">게시글이 없습니다.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($posts as $post): ?>
                <tr>
                    <td>
                        <span class="board-badge"><?= e($post['tag'] ?? $board['badge']) ?></span>
                    </td>
                    <td class="board-title-cell">
                        <a class="board-title-link" href="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>">
                            <?= e($post['title']) ?>
                        </a>
                        <?php if ($post['file_path']): ?>
                            <a class="file-link" href="/uploads/<?= e($post['file_path']) ?>" download>첨부</a>
                        <?php endif; ?>
                    </td>
                    <td><?= e($post['username']) ?></td>
                    <td><?= e(substr($post['created_at'], 0, 10)) ?></td>
                    <td><?= e((string) ($post['views'] ?? 0)) ?></td>
                    <?php if ($hasManageColumn): ?>
                        <td>
                            <?php if ($post['can_manage']): ?>
                                <form class="board-row-delete" method="post" action="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>/delete" onsubmit="return confirm('이 게시글을 삭제할까요?');">
                                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                    <button type="submit" aria-label="게시글 삭제">삭제</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
