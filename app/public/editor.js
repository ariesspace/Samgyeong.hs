(function () {
    const form = document.querySelector('[data-editor-form]');
    const editor = document.querySelector('#post-editor');
    const bodyInput = document.querySelector('#body-input');

    if (!form || !editor || !bodyInput) {
        return;
    }

    const sizeMap = {
        1: '12px',
        2: '14px',
        3: '16px',
        4: '18px',
        5: '20px',
        6: '24px',
        7: '28px'
    };

    function syncBody() {
        bodyInput.value = editor.innerHTML.trim();
    }

    function insertImage(src) {
        editor.focus();
        const img = document.createElement('img');
        img.src = src;
        img.alt = '';
        img.loading = 'lazy';
        img.className = 'post-inline-image';
        const selection = window.getSelection();
        if (selection && selection.rangeCount > 0) {
            const range = selection.getRangeAt(0);
            range.deleteContents();
            range.insertNode(img);
            range.setStartAfter(img);
            range.setEndAfter(img);
            selection.removeAllRanges();
            selection.addRange(range);
        } else {
            editor.appendChild(img);
        }
        editor.appendChild(document.createElement('br'));
        syncBody();
    }

    function normalizeFonts() {
        editor.querySelectorAll('font').forEach((font) => {
            const span = document.createElement('span');
            const size = font.getAttribute('size');
            const face = font.getAttribute('face');

            if (size && sizeMap[size]) {
                span.style.fontSize = sizeMap[size];
            }

            if (face) {
                span.style.fontFamily = face;
            }

            span.innerHTML = font.innerHTML;
            font.replaceWith(span);
        });
        syncBody();
    }

    function runCommand(command, value = null) {
        editor.focus();
        document.execCommand(command, false, value);
        normalizeFonts();
    }

    document.querySelectorAll('[data-command]').forEach((button) => {
        button.addEventListener('click', () => runCommand(button.dataset.command));
    });

    document.querySelector('[data-font-family]')?.addEventListener('change', (event) => {
        runCommand('fontName', event.target.value);
    });

    document.querySelector('[data-font-size]')?.addEventListener('change', (event) => {
        const size = Object.entries(sizeMap).find((entry) => entry[1] === event.target.value)?.[0] || '3';
        runCommand('fontSize', size);
    });

    document.querySelector('[data-image-upload]')?.addEventListener('click', () => {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/jpeg,image/png,image/webp,image/gif';
        input.addEventListener('change', async () => {
            const file = input.files?.[0];
            if (!file) {
                return;
            }

            const formData = new FormData();
            formData.append('csrf', form.querySelector('input[name="csrf"]')?.value || '');
            formData.append('image', file);

            const response = await fetch('/editor/image-upload', {
                method: 'POST',
                body: formData
            });
            const result = await response.json().catch(() => null);

            if (!response.ok || !result?.url) {
                alert(result?.error || '이미지를 업로드할 수 없습니다.');
                return;
            }

            insertImage(result.url);
        });
        input.click();
    });

    document.querySelectorAll('[data-delete-file]').forEach((button) => {
        button.addEventListener('click', () => {
            const value = button.dataset.deleteFile;
            const item = button.closest('[data-current-file-item]');
            if (!value || !item) {
                return;
            }

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'delete_files[]';
            hidden.value = value;
            form.appendChild(hidden);
            item.remove();
        });
    });

    editor.addEventListener('input', () => {
        editor.classList.remove('is-invalid');
        syncBody();
    });

    form.addEventListener('submit', (event) => {
        syncBody();
        if (editor.textContent.trim() === '' && !editor.querySelector('img')) {
            event.preventDefault();
            editor.focus();
            editor.classList.add('is-invalid');
        }
    });
})();
