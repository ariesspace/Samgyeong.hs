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

    editor.addEventListener('input', () => {
        editor.classList.remove('is-invalid');
        syncBody();
    });

    form.addEventListener('submit', (event) => {
        syncBody();
        if (editor.textContent.trim() === '') {
            event.preventDefault();
            editor.focus();
            editor.classList.add('is-invalid');
        }
    });
})();
