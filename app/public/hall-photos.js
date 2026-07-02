(function () {
    const modal = document.querySelector('[data-photo-modal]');
    const preview = document.querySelector('[data-photo-preview]');
    const applyButton = document.querySelector('[data-photo-apply]');
    const cancelButton = document.querySelector('[data-photo-cancel]');
    let activeInput = null;
    let activeFile = null;
    let objectUrl = null;

    if (!modal || !preview || !applyButton || !cancelButton) {
        return;
    }

    function closeModal(clearInput) {
        modal.hidden = true;
        if (objectUrl) {
            URL.revokeObjectURL(objectUrl);
            objectUrl = null;
        }
        preview.removeAttribute('src');
        if (clearInput && activeInput) {
            activeInput.value = '';
        }
        activeInput = null;
        activeFile = null;
    }

    function updateRowPreview(input, url) {
        const row = input.closest('.hall-admin-row, .hall-list-row, .add-member-panel, .hall-edit-form');
        const image = row ? row.querySelector('.hall-photo-preview img') : null;
        if (image) {
            image.src = url;
        }
    }

    function cropToAlbumRatio(file, callback) {
        const image = new Image();
        const url = URL.createObjectURL(file);
        image.onload = function () {
            const ratio = 4 / 5;
            let sourceWidth = image.naturalWidth;
            let sourceHeight = image.naturalHeight;

            if (sourceWidth / sourceHeight > ratio) {
                sourceWidth = sourceHeight * ratio;
            } else {
                sourceHeight = sourceWidth / ratio;
            }

            const sourceX = (image.naturalWidth - sourceWidth) / 2;
            const sourceY = (image.naturalHeight - sourceHeight) / 2;
            const canvas = document.createElement('canvas');
            canvas.width = 800;
            canvas.height = 1000;
            canvas.getContext('2d').drawImage(image, sourceX, sourceY, sourceWidth, sourceHeight, 0, 0, canvas.width, canvas.height);
            URL.revokeObjectURL(url);
            canvas.toBlob((blob) => callback(blob), 'image/jpeg', 0.9);
        };
        image.src = url;
    }

    document.querySelectorAll('[data-hall-photo-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file) {
                return;
            }
            activeInput = input;
            activeFile = file;
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            modal.hidden = false;
        });
    });

    cancelButton.addEventListener('click', () => closeModal(true));

    applyButton.addEventListener('click', () => {
        if (!activeInput || !activeFile) {
            closeModal(false);
            return;
        }

        cropToAlbumRatio(activeFile, (blob) => {
            if (blob && window.DataTransfer) {
                const croppedFile = new File([blob], activeFile.name.replace(/\.[^.]+$/, '') + '-album.jpg', { type: 'image/jpeg' });
                const transfer = new DataTransfer();
                transfer.items.add(croppedFile);
                activeInput.files = transfer.files;
                updateRowPreview(activeInput, URL.createObjectURL(croppedFile));
            } else {
                updateRowPreview(activeInput, objectUrl);
            }
            closeModal(false);
        });
    });
})();
