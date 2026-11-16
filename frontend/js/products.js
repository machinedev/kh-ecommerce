(function () {
    'use strict';

    async function initHome() {
        ui.header('/');
        try {
            const data = await api.get('/home');
            ui.renderProducts(ui.qs('#featured'), data.featured);
            ui.renderProducts(ui.qs('#latest'), data.latest);

            const categoryMount = ui.qs('#category-list');
            if (categoryMount) {
                categoryMount.innerHTML = data.categories.map(function (category) {
                    return '<a class="badge" href="/products.html?category=' + category.id + '">' + ui.escapeHtml(category.name) + '</a>';
                }).join(' ');
            }

            const sellers = await api.get('/sellers');
            const sellerMount = ui.qs('#seller-list');
            if (sellerMount) {
                sellerMount.innerHTML = sellers.map(function (seller) {
                    return '<article class="card"><div class="body">'
                        + '<a class="title" href="/user.html?id=' + seller.id + '">' + ui.escapeHtml(seller.display_name) + '</a>'
                        + '<div class="meta">@' + ui.escapeHtml(seller.username) + '</div>'
                        + '<div class="meta">' + seller.product_count + ' produk</div>'
                        + '</div></article>';
                }).join('');
            }
        } catch (error) {
            ui.toast(error.message, true);
        }
    }

    async function initListing() {
        ui.header('/products.html');
        const grid = ui.qs('#product-grid');
        const pager = ui.qs('#pagination');
        const categorySelect = ui.qs('#filter-category');
        const searchInput = ui.qs('#filter-search');

        let page = parseInt(ui.param('page') || '1', 10);
        let category = ui.param('category') || '';
        let q = ui.param('q') || '';

        if (searchInput) { searchInput.value = q; }
        if (categorySelect && category) { categorySelect.value = category; }

        try {
            const categories = await api.get('/categories');
            if (categorySelect) {
                categorySelect.innerHTML = '<option value="">Semua kategori</option>' + categories.map(function (c) {
                    return '<option value="' + c.id + '">' + ui.escapeHtml(c.name) + '</option>';
                }).join('');
                categorySelect.value = category;
            }
        } catch (error) { /* categories are optional for the listing */ }

        async function load() {
            const query = '/products?page=' + page + '&limit=12'
                + (category ? '&category=' + encodeURIComponent(category) : '')
                + (q ? '&q=' + encodeURIComponent(q) : '');
            try {
                const data = await api.get(query);
                ui.renderProducts(grid, data.items);
                const pages = data.pagination.pages;
                let buttons = '';
                for (let i = 1; i <= pages; i++) {
                    buttons += '<button class="btn small ' + (i === page ? '' : 'secondary') + '" data-page="' + i + '">' + i + '</button>';
                }
                pager.innerHTML = pages > 1 ? buttons : '';
                pager.querySelectorAll('button').forEach(function (button) {
                    button.addEventListener('click', function () {
                        page = parseInt(button.getAttribute('data-page'), 10);
                        load();
                    });
                });
            } catch (error) {
                ui.toast(error.message, true);
            }
        }

        const applyButton = ui.qs('#filter-apply');
        if (applyButton) {
            applyButton.addEventListener('click', function () {
                q = searchInput ? searchInput.value.trim() : '';
                category = categorySelect ? categorySelect.value : '';
                page = 1;
                load();
            });
        }

        load();
    }

    function initSearch() {
        ui.header('/search.html');
        const term = ui.param('q') || '';
        const heading = ui.qs('#search-heading');

        const input = ui.qs('#search-input');
        if (input) { input.value = term; }

        ui.renderProducts(ui.qs('#search-results'), []);
        api.get('/search?q=' + encodeURIComponent(term))
            .then(function (data) {
                if (heading) { heading.innerHTML = data.heading; }
                ui.renderProducts(ui.qs('#search-results'), data.items);
            })
            .catch(function (error) { ui.toast(error.message, true); });
    }

    async function initProductDetail() {
        ui.header('/products.html');
        const id = ui.param('id');
        const mount = ui.qs('#product-detail');
        if (!id || !mount) { return; }

        try {
            const product = await api.get('/products/' + encodeURIComponent(id));
            const user = api.getUser();

            mount.innerHTML = '<div class="detail-layout">'
                + '<div>'
                + '<img class="detail-image" src="' + ui.escapeHtml(ui.productImage(product)) + '" alt="' + ui.escapeHtml(product.title) + '">'
                + '<div class="panel" style="margin-top:18px"><h3>Deskripsi</h3><div>' + product.description + '</div>'
                + (product.document_url
                    ? '<p><a href="' + product.document_url + '" target="_blank" rel="noopener">Unduh dokumen produk</a></p>'
                    : '')
                + '</div>'
                + '<div class="panel"><h3>Komentar</h3><div id="comment-list"></div>'
                + '<div id="comment-form-wrap"></div></div>'
                + '</div>'
                + '<aside class="panel">'
                + '<h1 class="profile-name">' + ui.escapeHtml(product.title) + '</h1>'
                + '<p class="profile-username">Penjual: <a href="/user.html?id=' + product.seller.id + '">' + ui.escapeHtml(product.seller.display_name || product.seller.username) + '</a></p>'
                + '<p class="price-lg">' + ui.money(product.price) + '</p>'
                + '<p class="muted">Stok tersedia: ' + product.stock + '</p>'
                + '<div class="form-row"><label for="qty">Jumlah</label><input type="number" id="qty" value="1" min="1"></div>'
                + '<button class="btn" id="add-to-cart">Tambah ke keranjang</button>'
                + '</aside></div>';

            const addButton = ui.qs('#add-to-cart');
            addButton.addEventListener('click', async function () {
                if (!api.getToken()) {
                    window.location.href = '/login.html?next=' + encodeURIComponent(window.location.pathname + window.location.search);
                    return;
                }
                try {
                    const quantity = parseInt(ui.qs('#qty').value, 10) || 1;
                    await api.post('/cart/items', { product_id: product.id, quantity: quantity });
                    ui.toast('Produk ditambahkan ke keranjang.');
                    ui.refreshCartCount();
                } catch (error) {
                    ui.toast(error.message, true);
                }
            });

            comments.init(product.id, user);
        } catch (error) {
            mount.innerHTML = '<div class="panel"><p class="muted">' + ui.escapeHtml(error.message) + '</p></div>';
        }
    }

    async function initSell() {
        if (!ui.requireAuth()) { return; }
        ui.header('/sell.html');
        const editor = wysiwyg.init();
        const id = ui.param('id');
        const form = ui.qs('#sell-form');
        const titleNode = ui.qs('#sell-title');

        try {
            const categories = await api.get('/categories');
            ui.qs('#sell-category').innerHTML = '<option value="">Pilih kategori</option>' + categories.map(function (c) {
                return '<option value="' + c.id + '">' + ui.escapeHtml(c.name) + '</option>';
            }).join('');
        } catch (error) { /* optional */ }

        if (id) {
            try {
                const product = await api.get('/products/' + encodeURIComponent(id));
                titleNode.textContent = 'Ubah Produk';
                ui.qs('#sell-name').value = product.title;
                ui.qs('#sell-price').value = product.price;
                ui.qs('#sell-stock').value = product.stock;
                ui.qs('#sell-category').value = product.category_id || '';
                ui.qs('#sell-image').value = product.image_url || '';
                editor.setHtml(product.description);
                if (product.document_url) {
                    ui.qs('#document-status').textContent = 'Dokumen sudah tersedia.';
                }
            } catch (error) {
                ui.toast(error.message, true);
            }
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            const payload = {
                title: ui.qs('#sell-name').value.trim(),
                description: editor.getHtml(),
                price: parseFloat(ui.qs('#sell-price').value),
                stock: parseInt(ui.qs('#sell-stock').value, 10),
                category_id: ui.qs('#sell-category').value,
                image_url: ui.qs('#sell-image').value.trim()
            };
            try {
                let product;
                if (id) {
                    product = await api.patch('/products/' + id, payload);
                    ui.toast('Produk diperbarui.');
                } else {
                    product = await api.post('/products', payload);
                    ui.toast('Produk dibuat.');
                    window.location.href = '/product.html?id=' + product.id;
                    return;
                }
                await uploadDocumentIfAny(product.id);
            } catch (error) {
                ui.toast(error.message, true);
            }
        });

        async function uploadDocumentIfAny(productId) {
            const fileInput = ui.qs('#sell-document');
            if (!fileInput || !fileInput.files.length) { return; }
            const text = await fileInput.files[0].text();
            await fetch('/api/v1/products/' + productId + '/document', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/xml',
                    Accept: 'application/json',
                    Authorization: 'Bearer ' + api.getToken()
                },
                body: text
            });
            ui.toast('Dokumen produk diunggah.');
        }

        const previewButton = ui.qs('#preview-image');
        if (previewButton) {
            previewButton.addEventListener('click', async function () {
                const url = ui.qs('#sell-image').value.trim();
                if (!url) { return; }
                try {
                    const data = await api.get('/products/image-preview?url=' + encodeURIComponent(url));
                    ui.toast('Pratinjau berhasil dimuat (HTTP ' + data.status + ').');
                } catch (error) {
                    ui.toast(error.message, true);
                }
            });
        }
    }

    window.productPages = {
        initHome: initHome,
        initListing: initListing,
        initSearch: initSearch,
        initProductDetail: initProductDetail,
        initSell: initSell
    };
})();
