<section class="page board-page">
    <header class="board-head">
        <h1><?= e($board['name']) ?></h1>
        <?php if ($canWrite): ?>
            <a class="button" href="/board/<?= e($board['slug']) ?>/new">글쓰기</a>
        <?php endif; ?>
    </header>

    <div class="board-tools">
        <p>총 <strong><?= count($posts) ?></strong>건</p>
        <form method="get" action="/board/<?= e($board['slug']) ?>" class="board-search">
            <select name="field" aria-label="검색 범위">
                <option value="all">제목</option>
            </select>
            <input name="q" value="<?= e($keyword ?? '') ?>" placeholder="검색어 입력">
            <button type="submit">검색</button>
        </form>
    </div>

    <table class="board-table public-board-table">
        <thead>
            <tr>
                <th class="col-no">번호</th>
                <th>제목</th>
                <th class="col-author">작성자</th>
                <th class="col-date">작성일</th>
                <th class="col-views">조회수</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$posts): ?>
                <tr>
                    <td colspan="5" class="empty-board">게시글이 없습니다.</td>
                </tr>
            <?php endif; ?>

            <?php foreach ($posts as $post): ?>
                <tr>
                    <td>
                        <span class="board-badge"><?= e($board['badge']) ?></span>
                    </td>
                    <td class="board-title-cell">
                        <strong><?= e($post['title']) ?></strong>
                        <?php if ($post['file_path']): ?>
                            <a class="file-link" href="/uploads/<?= e($post['file_path']) ?>" download>첨부</a>
                        <?php endif; ?>
                    </td>
                    <td><?= e($post['username']) ?></td>
                    <td><?= e(substr($post['created_at'], 0, 10)) ?></td>
                    <td><?= e((string) ($post['views'] ?? 0)) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
