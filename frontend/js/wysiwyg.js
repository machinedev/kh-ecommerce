(function () {
    'use strict';

    function init() {
        const editor = document.getElementById('wysiwyg');
        if (!editor) { return; }

        const surface = editor.querySelector('.editor-surface');
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = 'image/*';
        fileInput.hidden = true;
        document.body.appendChild(fileInput);

        editor.querySelectorAll('[data-cmd]').forEach(function (button) {
            button.addEventListener('click', function () {
                surface.focus();
                document.execCommand(button.getAttribute('data-cmd'), false, null);
            });
        });

        const linkButton = editor.querySelector('[data-cmd-link]');
        if (linkButton) {
            linkButton.addEventListener('click', function () {
                const url = window.prompt('URL tautan:', 'https://');
                if (url) {
                    surface.focus();
                    document.execCommand('createLink', false, url);
                }
            });
        }

        const uploadButton = editor.querySelector('[data-upload]');
        if (uploadButton) {
            uploadButton.addEventListener('click', function () { fileInput.click(); });
        }

        fileInput.addEventListener('change', async function () {
            if (!fileInput.files.length) { return; }
            const form = new FormData();
            form.append('file', fileInput.files[0]);
            try {
                const data = await api.upload('/uploads/editor', form);
                surface.focus();
                document.execCommand('insertImage', false, data.url);
                ui.toast('Media ditambahkan.');
            } catch (error) {
                ui.toast(error.message, true);
            }
            fileInput.value = '';
        });

        return {
            getHtml: function () { return surface.innerHTML; },
            setHtml: function (html) { surface.innerHTML = html; }
        };
    }

    window.wysiwyg = { init: init };
})();
