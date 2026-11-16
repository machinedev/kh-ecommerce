(function () {
    'use strict';

    const API_BASE = '/api/v1';
    const TOKEN_KEY = 'kh_token';
    const USER_KEY = 'kh_user';

    function getToken() {
        return localStorage.getItem(TOKEN_KEY);
    }

    function getUser() {
        try {
            return JSON.parse(localStorage.getItem(USER_KEY) || 'null');
        } catch (e) {
            return null;
        }
    }

    function setSession(token, user) {
        localStorage.setItem(TOKEN_KEY, token);
        localStorage.setItem(USER_KEY, JSON.stringify(user));
    }

    function setUser(user) {
        localStorage.setItem(USER_KEY, JSON.stringify(user));
    }

    function clearSession() {
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
    }

    async function request(path, options) {
        const opts = options || {};
        const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
        const token = getToken();
        if (token) {
            headers.Authorization = 'Bearer ' + token;
        }

        let body;
        if (opts.form) {
            body = opts.form;
        } else if (opts.body !== undefined) {
            headers['Content-Type'] = 'application/json';
            body = JSON.stringify(opts.body);
        }

        const response = await fetch(API_BASE + path, {
            method: opts.method || 'GET',
            headers: headers,
            body: body
        });

        let payload;
        try {
            payload = await response.json();
        } catch (e) {
            payload = { success: false, error: { message: 'Respons tidak valid dari server.' } };
        }

        if (!payload.success) {
            const error = new Error((payload.error && payload.error.message) || 'Terjadi kesalahan.');
            error.code = payload.error && payload.error.code;
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return payload.data;
    }

    window.api = {
        request: request,
        get: function (path) { return request(path); },
        post: function (path, body) { return request(path, { method: 'POST', body: body }); },
        patch: function (path, body) { return request(path, { method: 'PATCH', body: body }); },
        del: function (path) { return request(path, { method: 'DELETE' }); },
        upload: function (path, formData) { return request(path, { method: 'POST', form: formData }); },
        getToken: getToken,
        getUser: getUser,
        setSession: setSession,
        setUser: setUser,
        clearSession: clearSession
    };
})();
