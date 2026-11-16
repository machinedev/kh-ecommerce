(function () {
    'use strict';

    async function initTopup() {
        if (!ui.requireAuth()) { return; }
        ui.header('/topup.html');
        const mount = document.getElementById('topup-mount');

        try {
            const data = await api.get('/balance');
            let gateway = { enabled: false, client_key: '', snap_js_url: '' };
            try {
                gateway = await api.get('/payments/config');
            } catch (error) {
                gateway = { enabled: false, client_key: '', snap_js_url: '' };
            }
            window.__midtransClientKey = gateway.client_key || '';

            if (!data.topup_available) {
                mount.innerHTML = '<div class="panel form-narrow">'
                    + '<h3 class="center">Top Up Saldo</h3>'
                    + '<p class="center">Saldo saat ini: <strong>' + ui.money(data.balance) + '</strong></p>'
                    + '<div class="notice">Layanan top up sedang dalam maintenance. Silakan coba beberapa saat lagi.</div>'
                    + '</div>';
                return;
            }

            mount.innerHTML = '<div class="panel form-narrow">'
                + '<h3 class="center">Top Up Saldo</h3>'
                + '<p class="center">Saldo saat ini: <strong id="balance-value">' + ui.money(data.balance) + '</strong></p>'
                + '<div class="form-row"><label for="topup-amount">Nominal</label>'
                + '<input type="number" id="topup-amount" min="10000" max="10000000" step="10000" value="100000"></div>'
                + '<button class="btn" id="topup-button">Top Up</button>'
                + '</div>';

            const button = document.getElementById('topup-button');
            const input = document.getElementById('topup-amount');
            const balance = document.getElementById('balance-value');

            button.addEventListener('click', async function () {
                const amount = Number(input.value);
                button.disabled = true;

                try {
                    const result = await api.post('/balance/topup', { amount: amount });
                    balance.textContent = ui.money(result.balance);

                    if (result.snap_token) {
                        try {
                            await payment.loadSnap(gateway.snap_js_url);
                        } catch (error) {
                            if (result.redirect_url) {
                                window.location.href = result.redirect_url;
                                return;
                            }
                            throw error;
                        }

                        payment.open(result.snap_token, {
                            onSuccess: function () {
                                button.disabled = false;
                                ui.toast('Pembayaran berhasil.');
                            },
                            onPending: function () {
                                button.disabled = false;
                                ui.toast('Pembayaran menunggu penyelesaian.');
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

                    button.disabled = false;
                    ui.toast('Saldo berhasil ditambahkan.');
                } catch (error) {
                    button.disabled = false;
                    ui.toast(error.message, true);
                }
            });
        } catch (error) {
            mount.innerHTML = '<div class="panel"><p class="muted">' + ui.escapeHtml(error.message) + '</p></div>';
        }
    }

    window.topupPage = { initTopup: initTopup };
})();
