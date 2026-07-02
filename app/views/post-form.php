<section class="page write-page">
    <header class="write-head">
        <a class="back-link" href="/board/<?= e($board['slug']) ?>">목록으로</a>
        <h1><?= e($board['name']) ?> 글쓰기</h1>
    </header>

    <form method="post" action="/board/<?= e($board['slug']) ?>/store" enctype="multipart/form-data" class="write-form" data-editor-form>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="body" id="body-input" required>

        <label class="write-field">
            <span>제목</span>
            <input class="write-title-input" name="title" required maxlength="120" placeholder="제목을 입력하세요">
        </label>

        <div class="write-field">
            <span>내용</span>
            <div class="editor-shell">
                <div class="editor-toolbar" aria-label="본문 서식 도구">
                    <button type="button" data-command="bold" title="굵게"><strong>B</strong></button>
                    <button type="button" data-command="italic" title="기울임"><em>I</em></button>
                    <button type="button" data-command="underline" title="밑줄"><u>U</u></button>
                    <select data-font-family aria-label="글꼴">
                        <option value="Noto Sans KR">기본</option>
                        <option value="Malgun Gothic">맑은 고딕</option>
                        <option value="serif">명조</option>
                        <option value="Georgia">Georgia</option>
                        <option value="monospace">고정폭</option>
                    </select>
                    <select data-font-size aria-label="글자 크기">
                        <option value="16px">16</option>
                        <option value="18px">18</option>
                        <option value="20px">20</option>
                        <option value="24px">24</option>
                        <option value="28px">28</option>
                    </select>
                </div>
                <div id="post-editor" class="post-editor" contenteditable="true" role="textbox" aria-label="내용" data-placeholder="내용을 입력하세요"></div>
            </div>
        </div>

        <label class="write-field">
            <span>첨부 파일</span>
            <input class="write-file-input" type="file" name="file">
        </label>

        <div class="write-actions">
            <a class="button ghost-button" href="/board/<?= e($board['slug']) ?>">취소</a>
            <button type="submit">등록</button>
        </div>
    </form>
</section>

<script src="/editor.js"></script>
