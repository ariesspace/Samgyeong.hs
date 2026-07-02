<section class="page narrow">
    <h1><?= e($board['name']) ?> 글쓰기</h1>
    <form method="post" action="/board/<?= e($board['slug']) ?>/store" enctype="multipart/form-data" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>
            제목
            <input name="title" required maxlength="120">
        </label>
        <label>
            내용
            <textarea name="body" required rows="10"></textarea>
        </label>
        <label>
            첨부 파일
            <input type="file" name="file">
        </label>
        <button type="submit">등록</button>
    </form>
</section>
