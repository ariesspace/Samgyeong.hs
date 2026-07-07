<section class="page board-page">
    <header class="board-head">
        <h1><?= e($board['name']) ?></h1>
    </header>

    <div class="board-tools">
        <p>총 <strong><?= count($posts) ?></strong>건</p>
        <div class="board-actions">
            <?php if (($canToggleHidden ?? false)): ?>
                <?php if ($showHidden ?? false): ?>
                    <a class="button secondary-button board-view-toggle" href="/board/<?= e($board['slug']) ?>">숨김 제외</a>
                <?php else: ?>
                    <a class="button secondary-button board-view-toggle" href="/board/<?= e($board['slug']) ?>?view=all">전체 보기</a>
                <?php endif; ?>
            <?php endif; ?>
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

    <div class="board-table-wrap">
        <table class="board-table public-board-table">
            <thead>
                <tr>
                    <th class="col-no">번호</th>
                    <th>제목</th>
                    <th class="col-file">첨부파일</th>
                    <th class="col-date">등록일</th>
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
                    <tr class="<?= !empty($post['is_hidden']) ? 'is-hidden-post' : '' ?>">
                        <td class="col-no">
                            <?= e((string) $post['id']) ?>
                        </td>
                        <td class="board-title-cell">
                            <?php $tag = $post['tag'] ?? $board['badge']; ?>
                            <a class="board-title-link <?= $tag === '공지' ? 'is-notice-title' : '' ?>" href="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>">
                                <?= e($post['title']) ?>
                            </a>
                            <?php if (!empty($post['is_hidden'])): ?>
                                <span class="hidden-post-badge">숨김</span>
                            <?php endif; ?>
                        </td>
                        <td class="board-file-cell">
                            <?php if (($post['attachment_count'] ?? 0) > 0): ?>
                                <a class="file-download-link" href="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>" title="첨부파일 <?= e((string) $post['attachment_count']) ?>개">
                                    <span aria-hidden="true">↓</span> <?= e((string) $post['attachment_count']) ?>개
                                </a>
                            <?php else: ?>
                                <span class="no-file">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="col-date"><?= e(str_replace('-', '.', substr($post['created_at'], 0, 10))) ?></td>
                        <td class="col-views"><?= e((string) ($post['views'] ?? 0)) ?></td>
                        <?php if ($hasManageColumn): ?>
                            <td class="col-manage">
                                <?php if (($canToggleHidden ?? false)): ?>
                                    <form class="board-row-hide" method="post" action="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>/hide">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="hidden" value="<?= !empty($post['is_hidden']) ? '0' : '1' ?>">
                                        <input type="hidden" name="view" value="<?= ($showHidden ?? false) ? 'all' : '' ?>">
                                        <button type="submit" aria-label="<?= !empty($post['is_hidden']) ? '게시글 다시 보이기' : '게시글 숨기기' ?>">
                                            <?= !empty($post['is_hidden']) ? '보이기' : '숨김' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($post['can_manage']): ?>
                                    <form class="board-row-delete" method="post" action="/board/<?= e($board['slug']) ?>/post/<?= e((string) $post['id']) ?>/delete" onsubmit="return confirm('삭제하시겠습니까?');">
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
    </div>
</section>
