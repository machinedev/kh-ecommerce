(function () {
    'use strict';

    async function initCart() {
        if (!ui.requireAuth()) { return; }
        ui.header('/cart.html');
        const mount = document.getElementById('cart-mount');

        async function load() {
            try {
                const cart = await api.get('/cart');
                ui.setCartCount(ui.cartItemCount(cart.items));
                if (!cart.items.length) {
                    mount.innerHTML = '<div class="panel"><p class="muted">Keranjang masih kosong.</p>'
                        + '<a class="btn" href="/products.html">Mulai belanja</a></div>';
                    return;
                }
                mount.innerHTML = '<div class="panel"><table><thead><tr>'
                    + '<th>Gambar</th><th>Produk</th><th>Penjual</th><th>Harga</th><th>Jumlah</th><th>Subtotal</th><th></th>'
                    + '</tr></thead><tbody>'
                    + cart.items.map(function (item) {
                        return '<tr data-item="' + item.id + '">'
                            + '<td><img class="cart-thumb" src="' + ui.escapeHtml(ui.productImage(item)) + '" alt="' + ui.escapeHtml(item.title) + '"></td>'
                            + '<td><a href="/product.html?id=' + item.product_id + '">' + ui.escapeHtml(item.title) + '</a></td>'
                            + '<td>' + ui.escapeHtml(item.seller.display_name || item.seller.username) + '</td>'
                            + '<td>' + ui.money(item.price) + '</td>'
                            + '<td><input type="number" min="1" value="' + item.quantity + '" class="qty" data-item="' + item.id + '" style="width:80px"></td>'
                            + '<td>' + ui.money(item.subtotal) + '</td>'
                            + '<td><button class="btn small danger" data-remove="' + item.id + '">Hapus</button></td>'
                            + '</tr>';
                    }).join('')
                    + '</tbody></table>'
                    + '<div style="display:flex;justify-content:flex-end;align-items:center;gap:16px;margin-top:18px">'
                    + '<strong>Total: ' + ui.money(cart.total) + '</strong>'
                    + '<a class="btn" href="/checkout.html">Checkout</a>'
                    + '</div></div>';

                mount.querySelectorAll('[data-remove]').forEach(function (button) {
                    button.addEventListener('click', async function () {
                        try {
                            await api.del('/cart/items/' + button.getAttribute('data-remove'));
                            load();
                        } catch (error) { ui.toast(error.message, true); }
                    });
                });

                mount.querySelectorAll('.qty').forEach(function (input) {
                    input.addEventListener('change', async function () {
                        const value = parseInt(input.value, 10);
                        if (value <= 0) { load(); return; }
                        try {
                            await api.patch('/cart/items/' + input.getAttribute('data-item'), { quantity: value });
                            load();
                        } catch (error) { ui.toast(error.message, true); }
                    });
                });
            } catch (error) {
                mount.innerHTML = '<div class="panel"><p class="muted">' + ui.escapeHtml(error.message) + '</p></div>';
            }
        }

        load();
    }

    window.cartPage = { initCart: initCart };
})();
