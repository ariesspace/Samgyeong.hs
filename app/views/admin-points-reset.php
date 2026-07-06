<section class="page admin-users-page point-reset-admin-page">
    <h1>상벌점 초기화</h1>
    <p class="muted">상벌점 기록은 삭제하지 않고, 현재 합계 계산 기준 시점만 새로 지정합니다.</p>

    <?php if ($saved): ?>
        <div class="notice success">상벌점 합계 기준이 0점으로 초기화되었습니다. 기존 히스토리는 그대로 보존됩니다.</div>
    <?php endif; ?>

    <section class="reset-panel">
        <div class="reset-summary">
            <article>
                <span>대상 계정</span>
                <strong><?= e((string) $activeUsers) ?>명</strong>
            </article>
            <article>
                <span>보존 기록</span>
                <strong><?= e((string) $recordCount) ?>건</strong>
            </article>
            <article>
                <span>마지막 초기화</span>
                <strong><?= e($resetAt ?: '없음') ?></strong>
            </article>
        </div>

        <div class="reset-warning">
            <h2>초기화 방식</h2>
            <p>초기화 버튼을 누르면 이후 화면의 상점, 벌점, 삼경몰 사용 가능 포인트는 새 기준 시점 이후 기록만 계산합니다.</p>
            <p>과거 상벌점 히스토리와 취소 기록, 삼경몰 주문 내역은 삭제하지 않습니다.</p>
        </div>

        <form method="post" action="/admin/points/reset/store" class="reset-form" onsubmit="return confirm('상벌점 합계를 0점 기준으로 초기화할까요? 기존 히스토리는 삭제되지 않습니다.');">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <label>
                메모
                <input name="note" maxlength="120" placeholder="예: 2026년 2학기 관 변경 전 정산">
            </label>
            <div class="form-actions">
                <a class="button ghost-button" href="/admin/users">돌아가기</a>
                <button type="submit" class="danger-button">상벌점 0점 초기화</button>
            </div>
        </form>
    </section>
</section>
