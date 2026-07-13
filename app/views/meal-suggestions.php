<?php
    $samples = [
        [
            'topic' => '환경',
            'lunch_text' => '산채비빔밥, 콩나물국, 연두부, 백김치',
            'dinner_text' => '두부스테이크, 크림스프, 모닝빵, 샐러드',
            'note' => '고기 없는 하루를 통해 탄소 발자국을 줄여보자는 의미로 구성했습니다. 제철 나물 비빔밥이라 맛도 좋을 것 같습니다.',
            'author_name' => '익명의 학우',
            'icon' => '🌱',
            'created_at' => '2026-07-11 09:00:00',
            'is_sample' => true,
        ],
        [
            'topic' => '건강',
            'lunch_text' => '현미밥, 닭가슴살 미역국, 삼치구이, 시금치나물',
            'dinner_text' => '귀리밥, 소고기 뭇국, 계란말이, 오징어젓갈',
            'note' => '단백질 위주로 구성해서 체력 관리에 도움을 주고 싶습니다. 든든하게 먹고 모두 힘냈으면 좋겠습니다.',
            'author_name' => '운동조아',
            'icon' => '💪',
            'created_at' => '2026-07-11 09:05:00',
            'is_sample' => true,
        ],
    ];
    $items = $suggestions ?: $samples;
    $topicIcon = function (string $topic): string {
        return match (trim($topic)) {
            '환경' => '🌱',
            '건강' => '💪',
            '제철' => '🍅',
            '치팅데이' => '🍔',
            default => '🍽️',
        };
    };
?>
<section class="page meal-page meal-suggestions-page">
    <header class="page-main-head">
        <p>SAMGYEONG MEAL TABLE</p>
        <h1>식단제안 <span>提案</span></h1>
        <p>삼경밥상 후보 식단을 자유롭게 남기고, 매주 금요일 선정 결과를 함께 확인합니다.</p>
    </header>

    <nav class="meal-tabs" aria-label="급식 메뉴">
        <span>급식사진</span>
        <a href="/meal">식단표</a>
        <a href="/meal-board">급식 게시판</a>
        <strong>식단제안</strong>
    </nav>

    <section id="chat-board" class="meal-suggestion-board">
        <header class="meal-suggestion-head">
            <div>
                <span aria-hidden="true">💬</span>
                <h2>실시간 삼경밥상 제안 톡</h2>
            </div>
            <strong><i></i>참여 중</strong>
        </header>

        <div class="meal-suggestion-feed">
            <div class="meal-suggestion-date"><?= e(date('Y년 n월 j일')) ?></div>

            <?php foreach ($items as $item): ?>
                <?php
                    $author = trim((string) (($item['author_name'] ?? '') ?: ($item['author_username'] ?? '익명의 학우')));
                    $topic = trim((string) ($item['topic'] ?? ''));
                    $lunchText = (string) ($item['lunch_text'] ?? '');
                    $dinnerText = (string) ($item['dinner_text'] ?? '');
                    $noteText = (string) ($item['note'] ?? '');
                    $combinedText = trim($topic . ' ' . $lunchText . ' ' . $dinnerText . ' ' . $noteText);
                    $isLongSuggestion = mb_strlen($combinedText) > 140 || substr_count($combinedText, "\n") > 5;
                    $icon = (string) ($item['icon'] ?? $topicIcon($topic));
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
            <?php endforeach; ?>
        </div>

        <form id="meal-suggestion-form" class="meal-suggestion-form" method="post" action="/meal-suggestions/store">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                주제
                <input name="topic" maxlength="40" required placeholder="예: 환경, 건강, 제철, 치팅데이">
            </label>
            <div class="meal-suggestion-menu-fields">
                <label>
                    중식
                    <textarea name="lunch_text" rows="3" required placeholder="예: 산채비빔밥, 콩나물국, 연두부, 백김치"></textarea>
                </label>
                <label>
                    석식
                    <textarea name="dinner_text" rows="3" required placeholder="예: 두부스테이크, 크림스프, 모닝빵, 샐러드"></textarea>
                </label>
            </div>
            <label>
                기획 의도와 느낀 점
                <textarea name="note" rows="4" required placeholder="식단의 의미, 구성 이유, 기대되는 점을 자유롭게 작성해 주세요."></textarea>
            </label>
            <div class="meal-suggestion-actions">
                <span>줄바꿈을 자유롭게 사용하여 작성할 수 있습니다.</span>
                <button type="submit">전송</button>
            </div>
        </form>
    </section>
</section>
