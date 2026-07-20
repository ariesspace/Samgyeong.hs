<?php
    $author = trim((string) (($item['author_name'] ?? '') ?: ($item['author_username'] ?? '익명의 학우')));
    $topic = trim((string) ($item['topic'] ?? ''));
    $lunchText = (string) ($item['lunch_text'] ?? '');
    $dinnerText = (string) ($item['dinner_text'] ?? '');
    $noteText = (string) ($item['note'] ?? '');
    $combinedText = trim($topic . ' ' . $lunchText . ' ' . $dinnerText . ' ' . $noteText);
    $isLongSuggestion = mb_strlen($combinedText) > 140 || substr_count($combinedText, "\n") > 5;
    $icon = (string) ($item['icon'] ?? $topicIcon($topic));
    $createdAtRaw = (string) ($item['created_at'] ?? '');
    $createdAtLabel = $formatSuggestionTime($createdAtRaw);
    $canDelete = empty($item['is_sample'])
        && !empty($currentUser)
        && ((int) ($item['user_id'] ?? 0) === (int) ($currentUser['id'] ?? 0) || ($currentUser['role'] ?? '') === 'admin');
?>
<article class="meal-suggestion-message <?= !empty($item['is_sample']) ? 'is-sample' : '' ?>">
    <div class="meal-suggestion-avatar" aria-hidden="true"><?= e($icon) ?></div>
    <div class="meal-suggestion-content">
        <span class="meal-suggestion-author">
            <?= e($author) ?><?= $topic !== '' ? ' (주제: ' . e($topic) . ')' : '' ?>
            <?php if (!empty($item['is_sample'])): ?><em>예시</em><?php endif; ?>
            <?php if ($createdAtLabel !== ''): ?><time class="meal-suggestion-time" datetime="<?= e($createdAtRaw) ?>"><?= e($createdAtLabel) ?></time><?php endif; ?>
        </span>
        <div class="meal-suggestion-bubble">
            <?php if ($isLongSuggestion): ?>
                <details class="meal-suggestion-details">
                    <summary>
                        <div class="meal-suggestion-preview">
                            <?php if ($topic !== ''): ?><p><strong>주제:</strong> <?= e($topic) ?></p><?php endif; ?>
                            <p><strong>중식:</strong> <?= nl2br(e($lunchText)) ?></p>
                            <p><strong>석식:</strong> <?= nl2br(e($dinnerText)) ?></p>
                            <p class="meal-suggestion-note"><?= nl2br(e($noteText)) ?></p>
                        </div>
                        <span class="meal-suggestion-more-text">
                            <b class="more">더보기</b>
                            <b class="less">접기</b>
                        </span>
                    </summary>
                    <div class="meal-suggestion-full">
                        <?php if ($topic !== ''): ?><p><strong>주제:</strong> <?= e($topic) ?></p><?php endif; ?>
                        <p><strong>중식:</strong> <?= nl2br(e($lunchText)) ?></p>
                        <p><strong>석식:</strong> <?= nl2br(e($dinnerText)) ?></p>
                        <p class="meal-suggestion-note"><?= nl2br(e($noteText)) ?></p>
                    </div>
                </details>
            <?php else: ?>
                <?php if ($topic !== ''): ?><p><strong>주제:</strong> <?= e($topic) ?></p><?php endif; ?>
                <p><strong>중식:</strong> <?= nl2br(e($lunchText)) ?></p>
                <p><strong>석식:</strong> <?= nl2br(e($dinnerText)) ?></p>
                <p class="meal-suggestion-note"><?= nl2br(e($noteText)) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($canDelete): ?>
            <form class="meal-suggestion-delete" method="post" action="/meal-suggestions/delete" onsubmit="return confirm('이 식단 제안을 삭제할까요?');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= e((string) $item['id']) ?>">
                <button type="submit">삭제</button>
            </form>
        <?php endif; ?>
    </div>
</article>
