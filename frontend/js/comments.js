(function () {
    'use strict';

    async function load(productId) {
        const list = document.getElementById('comment-list');
        if (!list) { return; }
        try {
            const comments = await api.get('/products/' + productId + '/comments');
            if (!comments.length) {
                list.innerHTML = '<p class="muted">Belum ada komentar.</p>';
                return;
            }
            list.innerHTML = comments.map(function (comment) {
                return '<div class="comment">'
                    + '<span class="who">' + ui.escapeHtml(comment.user.display_name || comment.user.username) + '</span>'
                    + '<span class="when">' + ui.formatDate(comment.created_at) + '</span>'
                    + '<div class="text">' + comment.body + '</div>'
                    + '</div>';
            }).join('');
        } catch (error) {
            ui.toast(error.message, true);
        }
    }

    function init(productId, user) {
        load(productId);
        const wrap = document.getElementById('comment-form-wrap');
        if (!wrap) { return; }

        if (!user) {
            wrap.innerHTML = '<p class="muted">Masuk untuk menulis komentar.</p>';
            return;
        }

        wrap.innerHTML = '<form id="comment-form" style="margin-top:14px">'
            + '<div class="form-row"><label for="comment-body">Tulis komentar</label>'
            + '<textarea id="comment-body" placeholder="Bagikan pengalaman Anda"></textarea></div>'
            + '<button class="btn" type="submit">Kirim</button></form>';

        document.getElementById('comment-form').addEventListener('submit', async function (event) {
            event.preventDefault();
            try {
                await api.post('/products/' + productId + '/comments', { body: document.getElementById('comment-body').value });
                document.getElementById('comment-body').value = '';
                load(productId);
                ui.toast('Komentar dikirim.');
            } catch (error) {
                ui.toast(error.message, true);
            }
        });
    }

    window.comments = { init: init };
})();
