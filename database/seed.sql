-- KH Commerce seed data.
-- All seed accounts share password: Password123!
SET NAMES utf8mb4;

INSERT INTO users (id, username, email, password_hash, display_name, bio, avatar_url, phone, address, balance) VALUES
(1, 'made',  'made@example.local',  '$2y$10$xs/VmkONFLGxZ92qGgxO1eIvjwO/9oU2t.1ePmoaX8jvJz/79oI..', 'Made',  'Penjual kerajinan lokal Denpasar.', NULL, '081200000001', 'Denpasar', 1000000.00),
(2, 'komang','komang@example.local','$2y$10$xs/VmkONFLGxZ92qGgxO1eIvjwO/9oU2t.1ePmoaX8jvJz/79oI..', 'Komang','Kolektor tas anyaman dan endek.',   NULL, '081200000002', 'Gianyar',  1000000.00),
(3, 'wayan', 'wayan@example.local', '$2y$10$xs/VmkONFLGxZ92qGgxO1eIvjwO/9oU2t.1ePmoaX8jvJz/79oI..', 'Wayan', 'Pengrajin perak Celuk.',            NULL, '081200000003', 'Gianyar',  1000000.00),
(4, 'putu',  'putu@example.local',  '$2y$10$xs/VmkONFLGxZ92qGgxO1eIvjwO/9oU2t.1ePmoaX8jvJz/79oI..', 'Putu',  'Penjual sandal dan produk spa.',    NULL, '081200000004', 'Badung',   1000000.00),
(5, 'gede',  'gede@example.local',  '$2y$10$xs/VmkONFLGxZ92qGgxO1eIvjwO/9oU2t.1ePmoaX8jvJz/79oI..', 'Gede',  'Seniman lukisan dan topeng.',       NULL, '081200000005', 'Ubud',     1000000.00),
(6, 'ayu',   'ayu@example.local',   '$2y$10$xs/VmkONFLGxZ92qGgxO1eIvjwO/9oU2t.1ePmoaX8jvJz/79oI..', 'Ayu',   'Kolektor kain dan camilan khas.',   NULL, '081200000006', 'Tabanan',  1000000.00);

INSERT INTO categories (id, name) VALUES
(1, 'Fashion'),
(2, 'Kerajinan'),
(3, 'Makanan'),
(4, 'Kecantikan');

INSERT INTO coupons (id, code, description, discount_type, discount_value, max_discount, max_uses, used_count, per_user_limit, starts_at, expires_at, is_active) VALUES
(1, 'BALI10', 'Diskon 10% untuk belanja produk lokal', 'percent', 10.00, 50000.00, 100, 0, 1, NULL, NULL, 1),
(2, 'HEMAT25K', 'Potongan Rp25.000 tanpa minimum belanja', 'fixed', 25000.00, NULL, 50, 0, 1, NULL, NULL, 1),
(3, 'PESTA15', 'Diskon 15% maksimal Rp75.000', 'percent', 15.00, 75000.00, 200, 0, 1, NULL, NULL, 1);

INSERT INTO products (id, seller_id, category_id, title, description, price, stock, image_url, document_path, is_deleted, created_at) VALUES
(1, 2, 2, 'Tas Anyaman Pandan', '<p>Tas anyaman dari daun pandan pilihan, cocok untuk aktivitas harian.</p>', 250000.00, 5, '/img/products/tas-anyaman-pandan.jpg', NULL, 0, '2026-01-02 09:00:00'),
(2, 2, 1, 'Kain Endek Bali', '<p>Kain endek tenun tradisional dengan motif khas Bali.</p>', 180000.00, 10, '/img/products/kain-endek-bali.jpg', NULL, 0, '2026-01-03 10:15:00'),
(3, 3, 3, 'Kopi Arabika Kintamani', '<p>Kopi arabika single origin dari dataran tinggi Kintamani.</p>', 120000.00, 20, '/img/products/kopi-arabika-kintamani.jpg', NULL, 0, '2026-01-04 08:30:00'),
(4, 3, 2, 'Perak Celuk', '<p>Perhiasan perak buatan tangan pengrajin Celuk.</p>', 450000.00, 3, '/img/products/perak-celuk.jpg', NULL, 0, '2026-01-05 11:45:00'),
(5, 4, 1, 'Sandal Ukir', '<p>Sandal kulit dengan ukiran tradisional Bali.</p>', 95000.00, 0, '/img/products/sandal-ukir.jpg', NULL, 0, '2026-01-06 14:20:00'),
(6, 4, 4, 'Minyak Kelapa Bali', '<p>Minyak kelapa murni untuk perawatan kulit dan rambut.</p>', 60000.00, 30, '/img/products/minyak-kelapa-bali.jpg', NULL, 0, '2026-01-07 09:05:00'),
(7, 5, 2, 'Lukisan Barong', '<p>Lukisan barong di atas kanvas dengan cat akrilik.</p>', 750000.00, 2, '/img/products/lukisan-barong.jpg', NULL, 0, '2026-01-08 16:40:00'),
(8, 5, 2, 'Topeng Tua', '<p>Topeng kayu ukiran tangan khas Ubud.</p>', 550000.00, 0, '/img/products/topeng-tua.jpg', NULL, 0, '2026-01-09 12:10:00'),
(9, 6, 1, 'Sarung Pantai', '<p>Sarung pantai ringan dengan warna cerah.</p>', 85000.00, 15, '/img/products/sarung-pantai.jpg', NULL, 0, '2026-01-10 10:00:00'),
(10, 6, 3, 'Kacang Mete', '<p>Kacang mete panggang premium dari Tabanan.</p>', 70000.00, 25, '/img/products/kacang-mete.jpg', NULL, 0, '2026-01-11 13:25:00'),
(11, 2, 2, 'Gelang Perak', '<p>Gelang perak dengan detail filigri.</p>', 150000.00, 8, '/img/products/gelang-perak.jpg', 'documents/sample-catalog.xml', 0, '2026-01-12 15:50:00'),
(12, 3, 1, 'Batik Bali', '<p>Kain batik tulis dengan pewarna alami.</p>', 200000.00, 12, '/img/products/batik-bali.jpg', NULL, 0, '2026-01-13 09:35:00'),
(13, 4, 4, 'Esensial Spa', '<p>Paket esensial spa dengan aroma bunga kamboja.</p>', 130000.00, 10, '/img/products/esensial-spa.jpg', NULL, 0, '2026-01-14 11:05:00'),
(14, 5, 2, 'Payung Tradisional', '<p>Payung upacara tradisional Bali.</p>', 175000.00, 6, '/img/products/payung-tradisional.jpg', NULL, 0, '2026-01-15 14:45:00'),
(15, 6, 1, 'Selendang Songket', '<p>Selendang songket dengan benang emas.</p>', 300000.00, 4, '/img/products/selendang-songket.jpg', NULL, 0, '2026-01-16 10:20:00');

INSERT INTO comments (id, product_id, user_id, body, created_at) VALUES
(1, 1, 1, 'Anyamannya rapi, pengiriman cepat.', '2026-02-01 09:10:00'),
(2, 1, 3, 'Bahan kuat dan nyaman dipakai.', '2026-02-01 10:20:00'),
(3, 2, 1, 'Motifnya bagus sekali.', '2026-02-02 08:40:00'),
(4, 2, 4, 'Cocok untuk acara adat.', '2026-02-02 11:15:00'),
(5, 3, 2, 'Aromanya mantap, akan beli lagi.', '2026-02-03 07:55:00'),
(6, 3, 5, 'Cocok diminum pagi hari.', '2026-02-03 12:30:00'),
(7, 4, 6, 'Detail peraknya halus.', '2026-02-04 09:45:00'),
(8, 4, 1, 'Kualitas pengrajin terbaik.', '2026-02-04 13:10:00'),
(9, 6, 2, 'Minyaknya lembut di kulit.', '2026-02-05 08:25:00'),
(10, 6, 3, 'Wangi alami, tidak menyengat.', '2026-02-05 10:50:00'),
(11, 7, 1, 'Lukisannya sangat ekspresif.', '2026-02-06 14:05:00'),
(12, 7, 4, 'Ukuran sesuai deskripsi.', '2026-02-06 15:35:00'),
(13, 9, 5, 'Warnanya cerah dan tidak luntur.', '2026-02-07 09:20:00'),
(14, 9, 6, 'Ringan dibawa ke pantai.', '2026-02-07 11:40:00'),
(15, 10, 1, 'Renyah dan gurih.', '2026-02-08 08:15:00'),
(16, 10, 4, 'Kemasan rapi dan higienis.', '2026-02-08 10:05:00'),
(17, 11, 3, 'Gelangnya elegan.', '2026-02-09 13:30:00'),
(18, 12, 2, 'Batik tulisnya halus.', '2026-02-09 16:00:00'),
(19, 14, 5, 'Payungnya kokoh.', '2026-02-10 09:50:00'),
(20, 15, 6, 'Songketnya mewah.', '2026-02-10 12:15:00');

INSERT INTO orders (id, buyer_id, total_amount, status, created_at) VALUES
(1, 1, 250000.00, 'completed', '2026-02-11 09:05:00'),
(2, 1, 240000.00, 'completed', '2026-02-12 10:15:00'),
(3, 2, 180000.00, 'completed', '2026-02-13 11:25:00'),
(4, 3, 450000.00, 'completed', '2026-02-14 12:35:00'),
(5, 4, 120000.00, 'completed', '2026-02-15 13:45:00'),
(6, 5,  85000.00, 'completed', '2026-02-16 14:55:00'),
(7, 6, 750000.00, 'completed', '2026-02-17 15:05:00'),
(8, 1, 210000.00, 'completed', '2026-02-18 16:15:00');

INSERT INTO order_items (order_id, product_id, seller_id, quantity, unit_price, subtotal) VALUES
(1, 1, 2, 1, 250000.00, 250000.00),
(2, 3, 3, 2, 120000.00, 240000.00),
(3, 2, 2, 1, 180000.00, 180000.00),
(4, 4, 3, 1, 450000.00, 450000.00),
(5, 6, 4, 2,  60000.00, 120000.00),
(6, 9, 6, 1,  85000.00,  85000.00),
(7, 7, 5, 1, 750000.00, 750000.00),
(8, 10, 6, 3, 70000.00, 210000.00);

INSERT INTO activities (user_id, activity_type, reference_type, reference_id, description, created_at) VALUES
(1, 'purchase',          'order',      1,  'Membeli Tas Anyaman Pandan',            '2026-02-11 09:05:00'),
(1, 'purchase',          'order',      2,  'Membeli Kopi Arabika Kintamani',        '2026-02-12 10:15:00'),
(1, 'comment',           'comment',    1,  'Mengomentari Tas Anyaman Pandan',       '2026-02-01 09:10:00'),
(1, 'purchase',          'order',      8,  'Membeli Kacang Mete',                   '2026-02-18 16:15:00'),
(2, 'product_created',   'product',    1,  'Menambahkan produk Tas Anyaman Pandan', '2026-01-02 09:00:00'),
(2, 'product_created',   'product',    2,  'Menambahkan produk Kain Endek Bali',    '2026-01-03 10:15:00'),
(2, 'seller_transaction','order_item', 1,  'Penjualan Tas Anyaman Pandan',          '2026-02-11 09:05:00'),
(2, 'purchase',          'order',      3,  'Membeli Kain Endek Bali',               '2026-02-13 11:25:00'),
(3, 'product_created',   'product',    3,  'Menambahkan produk Kopi Arabika Kintamani','2026-01-04 08:30:00'),
(3, 'seller_transaction','order_item', 2,  'Penjualan Kopi Arabika Kintamani',      '2026-02-12 10:15:00'),
(3, 'product_updated',   'product',    4,  'Memperbarui produk Perak Celuk',        '2026-02-20 09:00:00'),
(4, 'product_created',   'product',    5,  'Menambahkan produk Sandal Ukir',        '2026-01-06 14:20:00'),
(4, 'seller_transaction','order_item', 5,  'Penjualan Minyak Kelapa Bali',          '2026-02-15 13:45:00'),
(5, 'product_created',   'product',    7,  'Menambahkan produk Lukisan Barong',     '2026-01-08 16:40:00'),
(5, 'seller_transaction','order_item', 7,  'Penjualan Lukisan Barong',              '2026-02-17 15:05:00'),
(6, 'product_created',   'product',    9,  'Menambahkan produk Sarung Pantai',      '2026-01-10 10:00:00'),
(6, 'seller_transaction','order_item', 6,  'Penjualan Sarung Pantai',               '2026-02-16 14:55:00'),
(6, 'comment',           'comment',    7,  'Mengomentari Perak Celuk',              '2026-02-04 09:45:00');
