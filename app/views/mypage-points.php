<section class="page mypage-page">
    <h1>상벌점 현황</h1>
    <p class="muted">상벌점 기능을 연결하기 전까지는 예시 현황으로 표시합니다.</p>

    <div class="points-summary">
        <article>
            <span>상점</span>
            <strong>12점</strong>
        </article>
        <article>
            <span>벌점</span>
            <strong>5점</strong>
        </article>
        <article>
            <span>소원권</span>
            <strong>1개</strong>
        </article>
    </div>

    <table class="board-table points-table">
        <thead>
            <tr>
                <th>일자</th>
                <th>구분</th>
                <th>점수</th>
                <th>사유</th>
                <th>담당</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ([
                ['2026-07-01', '상점', '+2', '경물관 자습실 청소 자원', '경물관장'],
                ['2026-06-28', '벌점', '-2', '생활 규정 미준수', '삼경원'],
                ['2026-06-25', '상점', '+5', '전통 예절 교육 주간 우수 참여', '인성교육부'],
                ['2026-06-20', '벌점', '-3', '주간 일지 미작성', '사감위'],
                ['2026-06-15', '상점', '+5', '신입생 안내 도우미 활동', '학생부'],
            ] as $row): ?>
                <tr>
                    <td><?= e($row[0]) ?></td>
                    <td><span class="point-type <?= $row[1] === '상점' ? 'good' : 'bad' ?>"><?= e($row[1]) ?></span></td>
                    <td><?= e($row[2]) ?></td>
                    <td class="title-cell"><?= e($row[3]) ?></td>
                    <td><?= e($row[4]) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
