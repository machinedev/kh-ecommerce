(function () {
    'use strict';

    let snapLoader = null;

    function loadSnap(url) {
        if (window.snap) {
            return Promise.resolve();
        }
        if (!snapLoader) {
            snapLoader = new Promise(function (resolve, reject) {
                const script = document.createElement('script');
                script.src = url || 'https://app.sandbox.midtrans.com/snap/snap.js';
                script.setAttribute('data-client-key', (window.__midtransClientKey || ''));
                script.onload = function () { resolve(); };
                script.onerror = function () {
                    snapLoader = null;
                    reject(new Error('Gagal memuat halaman pembayaran.'));
                };
                document.head.appendChild(script);
            });
        }
        return snapLoader;
    }

    function open(token, handlers) {
        if (!window.snap) {
            throw new Error('Halaman pembayaran belum siap.');
        }
        window.snap.pay(token, handlers || {});
    }

    window.payment = { loadSnap: loadSnap, open: open };
})();
