(function () {
    'use strict';

    const DEFAULT_PRODUCT_IMAGE = '/img/products/default.svg';

    function money(value) {
        return 'Rp' + Number(value || 0).toLocaleString('id-ID');
    }

    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function toast(message, isError) {
        let node = document.querySelector('.toast');
        if (!node) {
            node = document.createElement('div');
            node.className = 'toast';
            document.body.appendChild(node);
        }
        node.textContent = message;
        node.classList.toggle('error', !!isError);
        node.classList.add('show');
        clearTimeout(node._t);
        node._t = setTimeout(function () { node.classList.remove('show'); }, 3200);
    }

    function qs(selector) {
        return document.querySelector(selector);
    }

    function param(name) {
        return new URLSearchParams(window.location.search).get(name);
    }

    function requireAuth() {
        if (!window.api.getToken()) {
            window.location.href = '/login.html?next=' + encodeURIComponent(window.location.pathname + window.location.search);
            return false;
        }
        return true;
    }

    function header(active) {
        const user = window.api.getUser();
        const nav = user
            ? [
                ['/', 'Home'], ['/products.html', 'Produk'], ['/search.html', 'Cari'],
                ['/sell.html', 'Jual'], ['/orders.html', 'Pesanan'],
                ['/topup.html', 'Saldo'], ['/profile.html', 'Profil']
            ]
            : [
                ['/', 'Home'], ['/products.html', 'Produk'], ['/search.html', 'Cari'],
                ['/login.html', 'Masuk'], ['/register.html', 'Daftar']
            ];

        const links = nav.map(function (item) {
            const cls = item[0] === active ? ' class="active"' : '';
            return '<a href="' + item[0] + '"' + cls + '>' + item[1] + '</a>';
        }).join('');

        const cartLink = '<a href="/cart.html" class="cart-link" title="Keranjang" aria-label="Keranjang">'
            + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + '<circle cx="9" cy="20" r="1.4"></circle><circle cx="18" cy="20" r="1.4"></circle>'
            + '<path d="M3 4h2l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.8a1.6 1.6 0 0 0 1.6-1.3L21 8H6"></path>'
            + '</svg><span class="cart-count" id="header-cart-count" aria-hidden="true"></span></a>';

        const right = user
            ? '<span class="balance-pill" id="header-balance">' + money(user.balance) + '</span>'
                + cartLink
                + '<a href="#" id="logout-link" class="btn small secondary">Keluar</a>'
            : cartLink + '<a href="/login.html" class="btn small">Masuk</a>';

        const mount = document.getElementById('app-header');
        if (mount) {
            mount.innerHTML = '<header class="site-header"><div class="container inner">'
                + '<a href="/" class="brand">KH <span>Commerce</span></a>'
                + '<nav class="nav">' + links + '</nav>'
                + '<div class="header-right">' + right + '</div>'
                + '</div></header>';
        }

        const logout = document.getElementById('logout-link');
        if (logout) {
            logout.addEventListener('click', function (e) {
                e.preventDefault();
                window.api.clearSession();
                window.location.href = '/';
            });
        }

        if (user) {
            window.api.get('/balance').then(function (data) {
                const pill = document.getElementById('header-balance');
                if (pill) { pill.textContent = money(data.balance); }
            }).catch(function () {});
        }
        refreshCartCount();
    }

    function productImage(product) {
        return product && product.image_url ? product.image_url : DEFAULT_PRODUCT_IMAGE;
    }

    function cartItemCount(items) {
        return (items || []).reduce(function (total, item) {
            return total + (Number(item.quantity) || 0);
        }, 0);
    }

    function setCartCount(count) {
        const badge = document.getElementById('header-cart-count');
        if (!badge) { return; }
        const total = Math.max(0, Math.floor(Number(count) || 0));
        if (total > 0) {
            badge.textContent = total > 99 ? '99+' : String(total);
            badge.classList.add('show');
            badge.setAttribute('aria-hidden', 'false');
        } else {
            badge.textContent = '';
            badge.classList.remove('show');
            badge.setAttribute('aria-hidden', 'true');
        }
    }

    function refreshCartCount() {
        if (!window.api || !window.api.getToken()) {
            setCartCount(0);
            return Promise.resolve();
        }
        return window.api.get('/cart').then(function (cart) {
            setCartCount(cartItemCount(cart.items));
        }).catch(function () {});
    }

    function productCard(product) {
        const img = '<div class="thumb" style="background-image:url(\'' + encodeURI(productImage(product)) + '\')"></div>';

        return '<article class="card">' + img
            + '<div class="body">'
            + '<a class="title" href="/product.html?id=' + product.id + '">' + escapeHtml(product.title) + '</a>'
            + '<div class="price">' + money(product.price) + '</div>'
            + '<div class="meta">Stok: ' + product.stock + '</div>'
            + '<div class="meta">Penjual: <a href="/user.html?id=' + product.seller.id + '">' + escapeHtml(product.seller.display_name || product.seller.username) + '</a></div>'
            + '<div class="actions"><a class="btn small" href="/product.html?id=' + product.id + '">Lihat</a></div>'
            + '</div></article>';
    }

    function renderProducts(container, items) {
        if (!container) { return; }
        if (!items || items.length === 0) {
            container.innerHTML = '<p class="muted">Belum ada produk.</p>';
            return;
        }
        container.innerHTML = items.map(productCard).join('');
    }

    function formatDate(value) {
        if (!value) { return ''; }
        const date = new Date(String(value).replace(' ', 'T'));
        if (isNaN(date.getTime())) { return value; }
        return date.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });
    }

    window.ui = {
        money: money,
        escapeHtml: escapeHtml,
        toast: toast,
        qs: qs,
        param: param,
        requireAuth: requireAuth,
        header: header,
        productImage: productImage,
        cartItemCount: cartItemCount,
        setCartCount: setCartCount,
        refreshCartCount: refreshCartCount,
        productCard: productCard,
        renderProducts: renderProducts,
        formatDate: formatDate
    };
})();
