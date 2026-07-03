<section class="page admissions-page">
    <h1>모집요강</h1>
    <p class="muted">삼경인문고등학교의 전통과 공동체 생활에 함께할 학생을 모집합니다.</p>

    <section class="admissions-hero">
        <p class="eyebrow">Samgyeong Admissions</p>
        <h2>배움이 깊어지고 마음이 자라는 학교</h2>
        <p>
            삼경의 정신을 바탕으로 예절, 책임, 공동체 의식을 함께 배우며
            하늘과 사람과 만물을 공경하는 삼경인을 기다립니다.
        </p>
    </section>

    <section class="admissions-section">
        <div class="section-title-row">
            <h2>모집 인원</h2>
            <span>소수 정예 선발</span>
        </div>

        <p class="admissions-note">
            각 관별, 학년별 소수 정예로 선발하며 지원 현황과 전형 결과에 따라 최종 인원은 조정될 수 있습니다.
        </p>

        <div class="admissions-hall-grid">
            <?php foreach (hall_definitions() as $hall): ?>
                <article class="admissions-hall-card <?= e($hall['color']) ?>">
                    <h3><?= e($hall['name']) ?></h3>
                    <span><?= e($hall['meaning']) ?>을 공경하는 관</span>
                    <dl>
                        <div>
                            <dt>1학년</dt>
                            <dd>2명</dd>
                        </div>
                        <div>
                            <dt>2학년</dt>
                            <dd>2명</dd>
                        </div>
                        <div>
                            <dt>3학년</dt>
                            <dd>2명</dd>
                        </div>
                    </dl>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="admissions-total">
            <span>총 모집 인원</span>
            <strong>최대 18명</strong>
        </div>
    </section>

    <section class="admissions-section">
        <div class="section-title-row">
            <h2>지원 자격</h2>
            <span>공동체 적합성 중심</span>
        </div>

        <div class="admissions-criteria">
            <article>
                <strong>01</strong>
                <h3>성별 무관</h3>
                <p>남학생, 여학생 모두 지원할 수 있습니다.</p>
            </article>
            <article>
                <strong>02</strong>
                <h3>예절과 책임감</h3>
                <p>학교생활 규정을 존중하고 공동체 생활에 성실히 참여할 수 있어야 합니다.</p>
            </article>
            <article>
                <strong>03</strong>
                <h3>정기 활동 참여</h3>
                <p>관별 활동, 점호, 삼경원 주관 행사에 적극적으로 참여할 수 있어야 합니다.</p>
            </article>
            <article>
                <strong>04</strong>
                <h3>삼경의 자세</h3>
                <p>하늘을 우러러 이치를 깨닫고, 사람을 공경하며, 만물을 아끼는 마음을 지향합니다.</p>
            </article>
        </div>
    </section>

    <section class="admissions-section">
        <div class="section-title-row">
            <h2>전형 절차</h2>
            <span>학년별 절차 안내</span>
        </div>

        <div class="admissions-process">
            <article>
                <div>
                    <strong>1학년</strong>
                    <span>기본 전형</span>
                </div>
                <ol>
                    <li>인성 테스트</li>
                    <li>기본 예절 면접</li>
                    <li>최종 안내</li>
                </ol>
            </article>
            <article>
                <div>
                    <strong>2학년</strong>
                    <span>심화 전형</span>
                </div>
                <ol>
                    <li>인성 테스트</li>
                    <li>인성 면접</li>
                    <li>삼경 심층 면접</li>
                </ol>
            </article>
            <article>
                <div>
                    <strong>3학년</strong>
                    <span>종합 전형</span>
                </div>
                <ol>
                    <li>인성 테스트</li>
                    <li>인성 면접</li>
                    <li>1, 2차 심층 면접</li>
                </ol>
            </article>
        </div>
    </section>

    <section class="admissions-closing">
        <p>당신이 있기에 삼경의 전통이 더욱 빛납니다.</p>
        <a class="button" href="/board/notice">입학 게시판 보기</a>
    </section>
</section>
