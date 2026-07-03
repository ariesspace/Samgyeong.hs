<?php
    $sections = [
        [
            'title' => '개인에 대한 징계 기준',
            'tone' => 'red',
            'items' => [
                ['score' => '-3점', 'text' => '참회록(반성문) 작성'],
                ['score' => '-5점', 'text' => '버피 또는 토끼뜀 20회, 참회록(반성문) 작성'],
                ['score' => '-8점', 'text' => '버피 또는 토끼뜀 30회, 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
                ['score' => '-10점', 'text' => '버피 또는 토끼뜀 40회, 예절 교육기간 1일'],
                ['score' => '-13점', 'text' => '버피 또는 토끼뜀 50회, 예절 교육기간 2일, 직속 3학년 선배(관장) 연대 참회록 작성'],
                ['score' => '-15점', 'text' => '퇴학 처리 (재입학 불가)', 'emphasis' => true],
            ],
        ],
        [
            'title' => '학년에 대한 징계 기준',
            'tone' => 'orange',
            'items' => [
                ['score' => '-10점', 'text' => '학년 전체 꼬리표 3일 부착, 학년 전체 참회록(반성문) 작성'],
                ['score' => '-15점', 'text' => '학년 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
                ['score' => '-20점', 'text' => '학년 릴레이 버피 또는 토끼뜀 30회, 학년 예절 교육기간 1일'],
                ['score' => '-25점', 'text' => '학년 릴레이 버피 또는 토끼뜀 40회, 학년 예절 교육기간 2일'],
                ['score' => '-30점', 'text' => '학년 전체 집합', 'emphasis' => true],
            ],
        ],
        [
            'title' => '관에 대한 징계 기준',
            'description' => '경천관, 경인관, 경물관 단위로 적용됩니다.',
            'tone' => 'gold',
            'items' => [
                ['score' => '-10점', 'text' => '관 전체 꼬리표 3일 부착, 관 전체 참회록(반성문) 작성'],
                ['score' => '-15점', 'text' => '관 소속 인원 릴레이 버피 또는 토끼뜀 20회, 릴레이 참회록(반성문) 작성, 삼경원 관찰 꼬리표 3일 부착'],
                ['score' => '-20점', 'text' => '관 소속 인원 릴레이 버피 또는 토끼뜀 30회, 관 예절 교육기간 1일'],
                ['score' => '-25점', 'text' => '관 소속 인원 릴레이 버피 또는 토끼뜀 40회, 관 예절 교육기간 2일'],
                ['score' => '-30점', 'text' => '관 전체 집합', 'emphasis' => true],
            ],
        ],
        [
            'title' => '전체에 대한 징계 기준',
            'tone' => 'gray',
            'items' => [
                ['score' => '-25점', 'text' => '전체 점호 실시 (삼경원 및 3학년 주도)', 'emphasis' => true],
            ],
        ],
    ];
?>

<section class="page point-rules-page">
    <h1>상벌점 리스트</h1>
    <p class="muted">학교생활 규정에 따른 벌점 누적 기준과 징계 적용 범위를 정리했습니다.</p>

    <section class="point-rules-intro">
        <p class="eyebrow">Samgyeong Conduct Standard</p>
        <h2>상벌점 및 징계 기준표</h2>
        <p>개인, 학년, 관, 전체 단위별 벌점 누적 기준을 한눈에 확인할 수 있습니다.</p>
    </section>

    <div class="point-rule-sections">
        <?php foreach ($sections as $section): ?>
            <section class="point-rule-block point-rule-<?= e($section['tone']) ?>">
                <div class="point-rule-head">
                    <h2><?= e($section['title']) ?></h2>
                    <?php if (!empty($section['description'])): ?>
                        <p><?= e($section['description']) ?></p>
                    <?php endif; ?>
                </div>

                <div class="point-rule-list">
                    <?php foreach ($section['items'] as $item): ?>
                        <div class="point-rule-row <?= !empty($item['emphasis']) ? 'is-emphasis' : '' ?>">
                            <strong><?= e($item['score']) ?></strong>
                            <span><?= e($item['text']) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</section>
