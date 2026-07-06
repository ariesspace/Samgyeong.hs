<section class="page">
    <h1 class="page-main-title">삼경인 선서문 <span>三敬人 宣誓文</span></h1>
    <p class="muted">삼경인의 마음가짐을 한문과 우리말로 함께 새깁니다.</p>

    <div class="pledge-wrap">
        <div class="pledge-card">
            <div class="pledge-pattern"></div>
            <header>
                <h2>&lt;三敬人 宣誓文&gt;</h2>
                <p>삼경인 선서문</p>
            </header>

            <div class="pledge-lines">
                <?php
                    $lines = [
                        ['我爲三敬人', '나는 삼경인으로서'],
                        ['以敬天之心 仰天而行正道', '경천의 마음으로 하늘을 우러러 바르게 행동하고'],
                        ['以敬人之心 尊重他人', '경인의 마음으로 사람을 존중하며'],
                        ['以敬物之心 愛惜萬物', '경물의 마음으로 만물을 아끼겠습니다'],
                        ['又以校之名譽 爲己之名譽', '또한 학교의 명예를 나의 명예로 여기고'],
                        ['盡三敬人之矜持與責任', '삼경인의 긍지와 책임을 다하며'],
                        ['終始守正其行', '올바른 품행을 끝까지 지키겠습니다'],
                    ];
                ?>

                <?php foreach ($lines as $line): ?>
                    <p>
                        <strong><?= e($line[0]) ?></strong>
                        <span><?= e($line[1]) ?></span>
                    </p>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>
