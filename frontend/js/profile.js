(function () {
    'use strict';

    async function renderActivity(selector, userId) {
        const mount = document.getElementById(selector);
        if (!mount) { return; }
        try {
            const activities = await api.get('/users/' + userId + '/activity');
            if (!activities.length) {
                mount.innerHTML = '<p class="muted">Belum ada aktivitas.</p>';
                return;
            }
            mount.innerHTML = '<ul class="timeline">' + activities.map(function (activity) {
                return '<li><span class="when">' + ui.formatDate(activity.created_at) + '</span>'
                    + '<span>' + ui.escapeHtml(activity.description) + '</span></li>';
            }).join('') + '</ul>';
        } catch (error) {
            mount.innerHTML = '<p class="muted">' + ui.escapeHtml(error.message) + '</p>';
        }
    }

    async function renderMiniProducts(selector, userId) {
        const mount = document.getElementById(selector);
        if (!mount) { return; }
        try {
            const products = await api.get('/users/' + userId + '/products');
            if (!products.length) {
                mount.innerHTML = '<p class="muted">Belum ada produk.</p>';
                return;
            }
            mount.innerHTML = products.map(function (product) {
                return '<article class="card"><div class="body">'
                    + '<a class="title" href="/product.html?id=' + product.id + '">' + ui.escapeHtml(product.title) + '</a>'
                    + '<div class="price">' + ui.money(product.price) + '</div>'
                    + '<div class="meta">Stok: ' + product.stock + '</div>'
                    + '</div></article>';
            }).join('');
        } catch (error) {
            mount.innerHTML = '<p class="muted">' + ui.escapeHtml(error.message) + '</p>';
        }
    }

    async function initProfile() {
        if (!ui.requireAuth()) { return; }
        ui.header('/profile.html');
        try {
            const me = await api.get('/me');
            api.setUser(me);
            ui.header('/profile.html');
            document.getElementById('profile-mount').innerHTML = '<div class="panel">'
                + '<div class="profile-head">'
                + (me.avatar_url ? '<img class="avatar" src="' + me.avatar_url + '" alt="">' : '<div class="avatar"></div>')
                + '<div><h1 class="profile-name">' + ui.escapeHtml(me.display_name) + '</h1>'
                + '<p class="profile-username">@' + ui.escapeHtml(me.username) + '</p>'
                + '<p class="muted">' + ui.escapeHtml(me.bio || '') + '</p></div>'
                + '<div style="margin-left:auto;text-align:right"><div class="muted">Saldo</div>'
                + '<div class="price-lg">' + ui.money(me.balance) + '</div></div>'
                + '</div>'
                + '<div class="toolbar" style="margin-top:16px">'
                + '<a class="btn" href="/sell.html">Jual Produk</a>'
                + '<a class="btn secondary" href="/orders.html">Pesanan Saya</a>'
                + '<button class="btn secondary" id="toggle-edit">Ubah Profil</button>'
                + '</div>'
                + '<form id="profile-form" hidden style="margin-top:10px">'
                + '<div class="form-row"><label>Nama tampilan</label><input type="text" id="edit-display-name" value="' + ui.escapeHtml(me.display_name) + '"></div>'
                + '<div class="form-row"><label>Bio</label><textarea id="edit-bio">' + ui.escapeHtml(me.bio || '') + '</textarea></div>'
                + '<div class="form-row"><label>Avatar URL</label><input type="text" id="edit-avatar" value="' + ui.escapeHtml(me.avatar_url || '') + '"></div>'
                + '<div class="form-row"><label>Telepon</label><input type="text" id="edit-phone" value="' + ui.escapeHtml(me.phone || '') + '"></div>'
                + '<div class="form-row"><label>Alamat</label><input type="text" id="edit-address" value="' + ui.escapeHtml(me.address || '') + '"></div>'
                + '<button class="btn" type="submit">Simpan</button>'
                + '</form>'
                + '</div>';

            document.getElementById('toggle-edit').addEventListener('click', function () {
                const form = document.getElementById('profile-form');
                form.hidden = !form.hidden;
            });

            document.getElementById('profile-form').addEventListener('submit', async function (event) {
                event.preventDefault();
                try {
                    const updated = await api.patch('/me', {
                        display_name: document.getElementById('edit-display-name').value,
                        bio: document.getElementById('edit-bio').value,
                        avatar_url: document.getElementById('edit-avatar').value,
                        phone: document.getElementById('edit-phone').value,
                        address: document.getElementById('edit-address').value
                    });
                    api.setUser(updated);
                    ui.toast('Profil diperbarui.');
                    window.location.reload();
                } catch (error) {
                    ui.toast(error.message, true);
                }
            });

            renderActivity('profile-activity', me.id);
            renderMiniProducts('profile-products', me.id);
        } catch (error) {
            ui.toast(error.message, true);
        }
    }

    async function initPublicUser() {
        ui.header('/products.html');
        const id = ui.param('id');
        const mount = document.getElementById('user-mount');
        if (!id || !mount) { return; }

        try {
            const user = await api.get('/users/' + encodeURIComponent(id));
            let card = '<div class="profile-head">'
                + (user.avatar_url ? '<img class="avatar" src="' + user.avatar_url + '" alt="">' : '<div class="avatar"></div>')
                + '<div><h1 class="profile-name">' + ui.escapeHtml(user.display_name) + '</h1>'
                + '<p class="profile-username">@' + ui.escapeHtml(user.username) + '</p>'
                + '<p class="muted">' + ui.escapeHtml(user.bio || '') + '</p></div></div>';

            mount.innerHTML = '<div class="panel" id="user-card">' + card + '</div>';

            fetch('/api/v1/users/' + encodeURIComponent(id) + '/card')
                .then(function (response) { return response.text(); })
                .then(function (html) {
                    const cardMount = document.getElementById('user-card');
                    if (cardMount) { cardMount.innerHTML = html; }
                })
                .catch(function () {});

            renderActivity('user-activity', user.id);
            renderMiniProducts('user-products', user.id);
        } catch (error) {
            mount.innerHTML = '<div class="panel"><p class="muted">' + ui.escapeHtml(error.message) + '</p></div>';
        }
    }

    window.profilePages = { initProfile: initProfile, initPublicUser: initPublicUser };
})();
