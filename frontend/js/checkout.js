(function () {
    'use strict';

    function wait(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    async function syncPayment(orderId) {
        for (let attempt = 0; attempt < 5; attempt += 1) {
            try {
                const data = await api.get('/orders/' + orderId + '/payment');
                if (data.status === 'completed') {
                    return data;
                }
            } catch (error) {
                // The webhook may still be in flight.
            }
            await wait(1200);
        }
        return null;
    }

    async function initCheckout() {
        if (!ui.requireAuth()) { return; }
        ui.header('/checkout.html');
        const mount = document.getElementById('checkout-mount');

        try {
            const cart = await api.get('/cart');
            const me = await api.get('/me');
            let gateway = { enabled: false };
            try {
                gateway = await api.get('/payments/config');
            } catch (error) {
                gateway = { enabled: false };
            }
            window.__midtransClientKey = gateway.client_key || '';

            if (!cart.items.length) {
                mount.innerHTML = '<div class="panel"><p class="muted">Keranjang masih kosong.</p>'
                    + '<a class="btn" href="/products.html">Mulai belanja</a></div>';
                return;
            }

            const gatewayOption = gateway.enabled
                ? '<label class="payment-option"><input type="radio" name="payment-method" value="midtrans"> '
                    + 'Midtrans Sandbox</label>'
                : '';

            mount.innerHTML = '<div class="panel"><h3>Ringkasan pesanan</h3>'
                + '<table><thead><tr><th>Produk</th><th>Jumlah</th><th>Subtotal</th></tr></thead><tbody>'
                + cart.items.map(function (item) {
                    return '<tr><td>' + ui.escapeHtml(item.title) + '</td><td>' + item.quantity + '</td><td>' + ui.money(item.subtotal) + '</td></tr>';
                }).join('')
                + '</tbody></table>'
                + '<p style="margin-top:14px">Total: <strong>' + ui.money(cart.total) + '</strong></p>'
                + '<p>Saldo Anda: <strong>' + ui.money(me.balance) + '</strong></p>'
                + '<div class="form-row"><label for="coupon-code">Kode promo (opsional)</label>'
                + '<input type="text" id="coupon-code" placeholder="Masukkan kode promo"></div>'
                + '<div class="form-row"><label>Metode pembayaran</label>'
                + '<label class="payment-option"><input type="radio" name="payment-method" value="balance" checked> '
                + 'Saldo KH Commerce</label>'
                + gatewayOption
                + '</div>'
                + '<button class="btn" id="confirm-order">Buat Pesanan</button></div>';

            const config = gateway;
            document.getElementById('confirm-order').addEventListener('click', async function () {
                const button = this;
                const couponCode = (document.getElementById('coupon-code').value || '').trim();
                const methodInput = document.querySelector('input[name="payment-method"]:checked');
                const paymentMethod = methodInput ? methodInput.value : 'balance';
                button.disabled = true;

                try {
                    const payload = { payment_method: paymentMethod };
                    if (couponCode) { payload.coupon_code = couponCode; }
                    const result = await api.post('/checkout', payload);

                    if (result.payment_method === 'midtrans' && result.snap_token) {
                        try {
                            await payment.loadSnap(config.snap_js_url);
                        } catch (error) {
                            if (result.redirect_url) {
                                window.location.href = result.redirect_url;
                                return;
                            }
                            throw error;
                        }

                        payment.open(result.snap_token, {
                            onSuccess: async function () {
                                await syncPayment(result.order_id);
                                ui.toast('Pembayaran berhasil.');
                                setTimeout(function () { window.location.href = '/orders.html'; }, 700);
                            },
                            onPending: async function () {
                                await syncPayment(result.order_id);
                                ui.toast('Pembayaran menunggu penyelesaian.');
                                setTimeout(function () { window.location.href = '/orders.html'; }, 700);
                            },
                            onError: function () {
                                button.disabled = false;
                                ui.toast('Pembayaran gagal.', true);
                            },
                            onClose: function () {
                                button.disabled = false;
                                ui.toast('Pembayaran belum diselesaikan.');
                            }
                        });
                        return;
                    }

                    let message = 'Pesanan #' + result.order_id + ' berhasil.';
                    if (result.discount > 0) {
                        message += ' Diskon ' + ui.money(result.discount) + '.';
                    }
                    ui.toast(message);
                    setTimeout(function () { window.location.href = '/orders.html'; }, 900);
                } catch (error) {
                    button.disabled = false;
                    ui.toast(error.message, true);
                }
            });
        } catch (error) {
            mount.innerHTML = '<div class="panel"><p class="muted">' + ui.escapeHtml(error.message) + '</p></div>';
        }
    }

    window.checkoutPage = { initCheckout: initCheckout };
})();
