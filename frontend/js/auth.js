(function () {
    'use strict';

    function initLogin() {
        const form = document.getElementById('login-form');
        if (!form) { return; }
        ui.header('/login.html');

        const next = ui.param('next') || '/';

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const username = document.getElementById('login-username').value.trim();
            const password = document.getElementById('login-password').value;
            try {
                const data = await api.post('/auth/login', { username: username, password: password, next: next });
                api.setSession(data.token, data.user);
                window.location.href = data.redirect || '/';
            } catch (error) {
                ui.toast(error.message, true);
            }
        });
    }

    function initRegister() {
        const form = document.getElementById('register-form');
        if (!form) { return; }
        ui.header('/register.html');

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const password = document.getElementById('register-password').value;
            const confirm = document.getElementById('register-confirm').value;
            if (password !== confirm) {
                ui.toast('Konfirmasi password tidak sama.', true);
                return;
            }
            try {
                const data = await api.post('/auth/register', {
                    username: document.getElementById('register-username').value.trim(),
                    email: document.getElementById('register-email').value.trim(),
                    display_name: document.getElementById('register-name').value.trim(),
                    password: password
                });
                api.setSession(data.token, data.user);
                window.location.href = '/profile.html';
            } catch (error) {
                ui.toast(error.message, true);
            }
        });
    }

    function initForgot() {
        const emailForm = document.getElementById('forgot-form');
        if (!emailForm) { return; }
        ui.header('/login.html');

        const otpForm = document.getElementById('otp-form');
        const resetForm = document.getElementById('reset-form');
        let email = '';

        emailForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            email = document.getElementById('forgot-email').value.trim();
            try {
                await api.post('/auth/forgot-password', { email: email });
                emailForm.hidden = true;
                otpForm.hidden = false;
                ui.toast('Kode verifikasi telah dikirim.');
            } catch (error) {
                ui.toast(error.message, true);
            }
        });

        otpForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            try {
                const data = await api.post('/auth/verify-otp', {
                    email: email,
                    otp: document.getElementById('otp-code').value.trim()
                });
                window.resetToken = data.reset_token;
                otpForm.hidden = true;
                resetForm.hidden = false;
            } catch (error) {
                ui.toast(error.message, true);
            }
        });

        resetForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            try {
                await api.post('/auth/reset-password', {
                    reset_token: window.resetToken,
                    new_password: document.getElementById('reset-password').value
                });
                ui.toast('Password berhasil diperbarui.');
                setTimeout(function () { window.location.href = '/login.html'; }, 1200);
            } catch (error) {
                ui.toast(error.message, true);
            }
        });
    }

    window.authPages = {
        initLogin: initLogin,
        initRegister: initRegister,
        initForgot: initForgot
    };
})();
