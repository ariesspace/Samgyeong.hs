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
    $archiveCutoff = new DateTimeImmutable('2026-07-17 00:00:00', new DateTimeZone('Asia/Seoul'));
    $createdAtToLocal = function (?string $value): ?DateTimeImmutable {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
                ->setTimezone(new DateTimeZone('Asia/Seoul'));
        } catch (Throwable) {
            return null;
        }
    };
    $archivedItems = [];
    $currentItems = [];
    foreach ($items as $item) {
        $createdAt = $createdAtToLocal($item['created_at'] ?? null);
        if (empty($item['is_sample']) && $createdAt !== null && $createdAt < $archiveCutoff) {
            $archivedItems[] = $item;
            continue;
        }

        $currentItems[] = $item;
    }
    $weekStart = new DateTimeImmutable('monday this week', new DateTimeZone('Asia/Seoul'));
    $weekEnd = $weekStart->modify('+6 days');
    $weekLabel = $weekStart->format('n월 j일') . ' - ' . $weekEnd->format('n월 j일');
    $topicIcon = function (string $topic): string {
        return match (trim($topic)) {
            '환경' => '🌱',
            '건강' => '💪',
            '제철' => '🍅',
            '치팅데이' => '🍔',
            default => '🍽️',
        };
    };
    $formatSuggestionTime = function (?string $value) use ($createdAtToLocal): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            $createdAt = $createdAtToLocal($value);
            if ($createdAt === null) {
                return '';
            }
            $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Seoul'));

            return $createdAt->format($createdAt->format('Y') === $now->format('Y') ? 'n월 j일 H:i' : 'Y.n.j H:i');
        } catch (Throwable) {
            return str_replace('-', '.', substr($value, 0, 16));
        }
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
            <strong><i></i>이번 주차 접수 중</strong>
        </header>

        <div class="meal-suggestion-feed">
            <div class="meal-suggestion-date"><?= e(date('Y년 n월 j일')) ?></div>

            <?php if ($archivedItems !== []): ?>
                <details class="meal-suggestion-archive">
                    <summary>
                        <span>7월 17일 이전 제안 <?= e((string) count($archivedItems)) ?>건 보기</span>
                        <b>더보기</b>
                    </summary>
                    <div class="meal-suggestion-archive-list">
                        <?php foreach ($archivedItems as $item): ?>
                            <?php require __DIR__ . '/partials/meal-suggestion-message.php'; ?>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <div class="meal-suggestion-date">이번 주차 제안 접수 <?= e($weekLabel) ?></div>

            <?php if ($currentItems === []): ?>
                <div class="meal-suggestion-empty">이번 주차에 새로 올라온 식단 제안이 없습니다.</div>
            <?php endif; ?>

            <?php foreach ($currentItems as $item): ?>
                <?php require __DIR__ . '/partials/meal-suggestion-message.php'; ?>
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
