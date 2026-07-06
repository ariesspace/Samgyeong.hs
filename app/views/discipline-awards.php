<?php
$activeTab = $activeTab ?? 'penalty';
$showPenalty = $activeTab === 'penalty';
$showReward = $activeTab === 'reward';
$showRule = $activeTab === 'rule';
?>
<section class="page discipline-awards-page discipline-policy-page">
    <header class="discipline-policy-hero">
        <div class="discipline-policy-mark" aria-hidden="true">律</div>
        <p class="eyebrow">Samgyeong Discipline & Rewards</p>
        <h1>징계 및 포상</h1>
        <p>
            상벌점 기록을 바탕으로 적용되는 징계 기준과 상점 혜택을 한곳에 정리했습니다.
            기준은 처벌보다 회복과 책임을 분명히 하기 위한 약속입니다.
        </p>
    </header>

    <nav class="discipline-policy-tabs" aria-label="징계 및 포상 바로가기">
        <a class="is-penalty <?= $showPenalty ? 'is-active' : '' ?>" href="/rules/discipline?tab=penalty"><span>!</span> 징계 기준</a>
        <a class="is-reward <?= $showReward ? 'is-active' : '' ?>" href="/rules/discipline?tab=reward"><span>★</span> 포상 기준</a>
        <a class="is-rule <?= $showRule ? 'is-active' : '' ?>" href="/rules/discipline?tab=rule"><span>i</span> 적용 원칙</a>
    </nav>

    <section class="discipline-policy-viewer" aria-label="징계 및 포상 규정">
        <div class="discipline-policy-viewer-head">
            <div>
                <span class="viewer-dot"></span>
                공식 규정지침 열람
            </div>
            <strong>三敬</strong>
        </div>

        <div class="discipline-policy-scroll">
            <?php if ($showPenalty): ?>
            <section id="discipline-penalties" class="discipline-policy-section discipline-penalty-section">
                <div class="discipline-policy-section-head">
                    <div>
                        <span class="section-symbol">!</span>
                        <h2>단위별 징계 기준</h2>
                    </div>
                    <em>Penalties</em>
                </div>

                <div class="discipline-rule-grid">
                    <?php foreach ($sections as $section): ?>
                        <section class="discipline-rule-block">
                            <div class="discipline-rule-head">
                                <h3><?= e($section['title']) ?></h3>
                                <?php if (!empty($section['description'])): ?>
                                    <p><?= e($section['description']) ?></p>
                                <?php endif; ?>
                            </div>

                            <div class="discipline-rule-list">
                                <?php if (empty($section['items'])): ?>
                                    <p class="empty-board">등록된 기준이 없습니다.</p>
                                <?php endif; ?>
                                <?php foreach ($section['items'] as $item): ?>
                                    <div class="discipline-rule-item <?= !empty($item['emphasis']) ? 'is-emphasis' : '' ?>">
                                        <span class="discipline-score"><?= e($item['score']) ?></span>
                                        <p><?= e($item['text']) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($showReward): ?>
            <section id="discipline-rewards" class="discipline-policy-section discipline-reward-section">
                <div class="discipline-policy-section-head">
                    <div>
                        <span class="section-symbol">★</span>
                        <h2>상점 혜택 기준</h2>
                    </div>
                    <em>Rewards</em>
                </div>

                <div class="reward-card-grid">
                    <article class="reward-card">
                        <div>
                            <strong>인사 면제권</strong>
                            <span>10점</span>
                        </div>
                        <p>인사 예절 수행을 1회 면제받을 수 있습니다.</p>
                    </article>
                    <article class="reward-card">
                        <div>
                            <strong>관등 면제권</strong>
                            <span>10점</span>
                        </div>
                        <p>관등 예절 수행을 1회 면제받을 수 있습니다.</p>
                    </article>
                    <article class="reward-card">
                        <div>
                            <strong>출석 면제권</strong>
                            <span>10점</span>
                        </div>
                        <p>정해진 기준에 따라 출석 관련 예외 1회를 신청할 수 있습니다.</p>
                    </article>
                    <article class="reward-card">
                        <div>
                            <strong>치킨 기프티콘 변경권</strong>
                            <span>15점</span>
                        </div>
                        <p>치킨 기프티콘으로 교환을 신청할 수 있습니다.</p>
                    </article>
                    <article class="reward-card">
                        <div>
                            <strong>피자 기프티콘 변경권</strong>
                            <span>15점</span>
                        </div>
                        <p>피자 기프티콘으로 교환을 신청할 수 있습니다.</p>
                    </article>
                    <article class="reward-card">
                        <div>
                            <strong>커피 기프티콘 변경권</strong>
                            <span>15점</span>
                        </div>
                        <p>커피 기프티콘으로 교환을 신청할 수 있습니다.</p>
                    </article>
                    <article class="reward-card">
                        <div>
                            <strong>소속 변경권</strong>
                            <span>15점</span>
                        </div>
                        <p>타 인원의 소속 관 변경을 24시간 신청할 수 있습니다.</p>
                    </article>
                    <article class="reward-card">
                        <div>
                            <strong>직속 교류권</strong>
                            <span>20점</span>
                        </div>
                        <ul>
                            <li>타 직속 1회 체험권 24시간</li>
                        </ul>
                    </article>
                    <article class="reward-card">
                        <div>
                            <strong>학년 변경권</strong>
                            <span>30점</span>
                        </div>
                        <ul>
                            <li>타 인원 학년 변경 24시간</li>
                            <li>본인 학년 체험 24시간</li>
                        </ul>
                    </article>
                    <article class="reward-card reward-card-featured">
                        <div>
                            <strong>특별 사면권</strong>
                            <span>40점</span>
                        </div>
                        <p>개인 징계 1회를 즉시 무효화할 수 있습니다. 단, 퇴학 조치 등 중대 사안은 삼경원 심의를 거쳐 적용합니다.</p>
                    </article>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($showRule): ?>
            <section id="discipline-principles" class="discipline-policy-section discipline-principle-section">
                <div class="discipline-policy-section-head">
                    <div>
                        <span class="section-symbol">i</span>
                        <h2>관리 및 적용 원칙</h2>
                    </div>
                    <em>Rules of Application</em>
                </div>

                <div class="discipline-note-grid">
                    <article>
                        <h3>상벌점 관리</h3>
                        <ul>
                            <li>상점은 월 단위로 누적되며, 월말 초기화 이전에 혜택을 구매할 수 있습니다.</li>
                            <li>혜택 구매 후 남은 잔여 상점은 다음 달로 이월되지 않고 소멸합니다.</li>
                            <li>월말 부과된 벌점은 익월 1일까지 이전 달 상점으로 상쇄할 수 있습니다.</li>
                            <li>벌점은 상쇄 전까지 다음 달로 계속 이월됩니다.</li>
                        </ul>
                    </article>
                    <article>
                        <h3>적용 원칙</h3>
                        <ul>
                            <li>징계가 적용 중인 자 또는 단위는 상점 혜택을 사용할 수 없습니다.</li>
                            <li>벌점 부과 후 24시간 이내 상점으로 상쇄한 경우 해당 징계는 면제됩니다.</li>
                            <li>동일 사안 복수 기준 적용 및 미명시 사항은 삼경원 판단에 따릅니다.</li>
                            <li>징계 및 혜택 적용 시 대상자에게 분류와 사유를 명확히 고지합니다.</li>
                        </ul>
                    </article>
                </div>
            </section>
            <?php endif; ?>
        </div>
    </section>
</section>
