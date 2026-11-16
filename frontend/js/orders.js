(function () {
    'use strict';

    function wait(ms) {
        return new Promise(function (resolve) { setTimeout(resolve, ms); });
    }

    function statusLabel(order) {
        if (order.status === 'pending_payment') { return 'Menunggu pembayaran'; }
        if (order.status === 'completed') { return 'Selesai'; }
        if (order.status === 'cancelled') { return 'Dibatalkan'; }
        return order.status;
    }

    function statusClass(order) {
        if (order.status === 'completed') { return 'badge success'; }
        if (order.status === 'pending_payment') { return 'badge warn'; }
        return 'badge';
    }

    async function syncOrder(orderId) {
        for (let attempt = 0; attempt < 5; attempt += 1) {
            try {
                const data = await api.get('/orders/' + orderId + '/payment');
                if (data.status === 'completed') {
                    return data;
                }
            } catch (error) {
                // Keep polling while the notification is in flight.
            }
            await wait(1200);
        }
        return null;
    }

    async function payOrder(order) {
        try {
            const config = await api.get('/payments/config');
            window.__midtransClientKey = config.client_key || '';
            await payment.loadSnap(config.snap_js_url);
        } catch (error) {
            if (order.redirect_url) {
                window.location.href = order.redirect_url;
                return;
            }
            ui.toast(error.message, true);
            return false;
        }

        return new Promise(function (resolve) {
            payment.open(order.snap_token, {
                onSuccess: async function () {
                    await syncOrder(order.id);
                    ui.toast('Pembayaran berhasil.');
                    setTimeout(function () { window.location.reload(); }, 700);
                    resolve(true);
                },
                onPending: async function () {
                    await syncOrder(order.id);
                    ui.toast('Pembayaran menunggu penyelesaian.');
                    setTimeout(function () { window.location.reload(); }, 700);
                    resolve(true);
                },
                onError: function () {
                    ui.toast('Pembayaran gagal.', true);
                    resolve(false);
                },
                onClose: function () {
                    resolve(false);
                }
            });
        });
    }

    async function initOrders() {
        if (!ui.requireAuth()) { return; }
        ui.header('/orders.html');
        const mount = document.getElementById('orders-mount');

        try {
            const orders = await api.get('/orders');
            if (!orders.length) {
                mount.innerHTML = '<div class="panel"><p class="muted">Belum ada pesanan.</p>'
                    + '<a class="btn" href="/products.html">Mulai belanja</a></div>';
                return;
            }

            mount.innerHTML = orders.map(function (order) {
                const canPay = order.status === 'pending_payment' && order.snap_token;
                const canCancel = order.status === 'pending_payment'
                    || (order.status === 'completed' && order.payment_method !== 'midtrans');
                return '<div class="panel">'
                    + '<div style="display:flex;justify-content:space-between;align-items:center">'
                    + '<h3>Pesanan #' + order.id + '</h3>'
                    + '<div>'
                    + '<span class="' + statusClass(order) + '">' + ui.escapeHtml(statusLabel(order)) + '</span> '
                    + (canPay ? '<button class="btn small" data-pay="' + order.id + '">Bayar</button> ' : '')
                    + (canCancel ? '<button class="btn small danger" data-cancel="' + order.id + '">Batalkan</button>' : '')
                    + '</div></div>'
                    + '<p class="muted">' + ui.formatDate(order.created_at)
                    + ' · Pembayaran: ' + (order.payment_method === 'midtrans' ? 'Midtrans' : 'Saldo') + '</p>'
                    + '<table><thead><tr><th>Produk</th><th>Penjual</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr></thead><tbody>'
                    + order.items.map(function (item) {
                        return '<tr><td><a href="/product.html?id=' + item.product_id + '">' + ui.escapeHtml(item.title) + '</a></td>'
                            + '<td>' + ui.escapeHtml(item.seller_name || item.seller_username) + '</td>'
                            + '<td>' + item.quantity + '</td>'
                            + '<td>' + ui.money(item.unit_price) + '</td>'
                            + '<td>' + ui.money(item.subtotal) + '</td></tr>';
                    }).join('')
                    + '</tbody></table>'
                    + '<p style="text-align:right;margin-top:12px">'
                    + (order.discount > 0 ? 'Diskon: <strong>' + ui.money(order.discount) + '</strong><br>' : '')
                    + 'Total: <strong>' + ui.money(order.total) + '</strong></p>'
                    + '</div>';
            }).join('');

            mount.querySelectorAll('[data-pay]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    const orderId = Number(button.getAttribute('data-pay'));
                    const order = orders.find(function (item) { return item.id === orderId; });
                    if (order) {
                        button.disabled = true;
                        const resolved = await payOrder(order);
                        if (!resolved) {
                            button.disabled = false;
                        }
                    }
                });
            });

            mount.querySelectorAll('[data-cancel]').forEach(function (button) {
                button.addEventListener('click', async function () {
                    if (!window.confirm('Batalkan pesanan ini?')) { return; }
                    button.disabled = true;
                    try {
                        await api.post('/orders/' + button.getAttribute('data-cancel') + '/cancel', {});
                        ui.toast('Pesanan dibatalkan.');
                        setTimeout(function () { window.location.reload(); }, 700);
                    } catch (error) {
                        button.disabled = false;
                        ui.toast(error.message, true);
                    }
                });
            });
        } catch (error) {
            mount.innerHTML = '<div class="panel"><p class="muted">' + ui.escapeHtml(error.message) + '</p></div>';
        }
    }

    window.ordersPage = { initOrders: initOrders };
})();
