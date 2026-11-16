# KH Commerce - CMS E-Commerce Bali

Platform CMS E-Commerce untuk berjualan produk lokal Bali. Setiap pengguna
dapat berperan sebagai **pembeli sekaligus penjual**: mengelola katalog produk,
mengatur keranjang, checkout, memantau pesanan, dan mengelola saldo — semuanya
dalam satu akun.

> Repositori ini adalah *vulnerable-by-design training platform*. Semua fitur
> berjalan layaknya toko online biasa; lihat [Dokumen](#dokumentasi) untuk
> detail arsitektur dan kontrol keamanan.

## Fitur Utama

### Autentikasi & Akun
- Registrasi, login, dan logout berbasis JWT.
- Lupa password dengan verifikasi OTP lalu reset password.
- Middleware otentikasi untuk seluruh endpoint privat.

### Katalog & Manajemen Produk (CMS)
- Buat, ubah, dan hapus produk milik sendiri.
- Field produk: judul, deskripsi (editor WYSIWYG), harga, stok, kategori,
  gambar, dan dokumen produk opsional.
- Unggah gambar/dokumen produk serta preview gambar dari URL.
- Halaman detail produk, pencarian, filter kategori, dan paginasi.
- Komentar produk dari pengguna lain.
- Produk yang dihapus disembunyikan dari katalog; produk dapat berstatus
  habis stok.

### Keranjang & Checkout
- Tambah, ubah jumlah, dan hapus item keranjang.
- Ringkasan keranjang dengan total otomatis.
- Badge jumlah item pada ikon keranjang di navigasi kanan atas.
- Checkout dengan validasi stok dan penerapan kode kupon.
- Dua metode pembayaran: **saldo internal** dan **Midtrans Snap Sandbox**.

### Pesanan & Saldo
- Riwayat pesanan beserta detail item.
- Pembatalan pesanan dengan pengembalian dana (refund) ke saldo.
- Saldo awal setiap pengguna **Rp1.000.000**, terlihat di header dan profil.
- Top-up saldo via Midtrans Snap (nominal Rp10.000 – Rp10.000.000).

### Profil & Aktivitas
- Lihat dan ubah profil: nama tampilan, bio, avatar, alamat, telepon.
- Lihat profil publik pengguna lain dan produk yang dijualnya.
- Timeline aktivitas publik: produk dibuat/diubah, pembelian, komentar, dan
  transaksi penjualan.

### Halaman
| Halaman | Fungsi |
| --- | --- |
| Home | Hero, produk pilihan & terbaru, kategori, penjual populer |
| Produk | Daftar & pencarian produk |
| Detail Produk | Info produk, komentar, tombol keranjang |
| Jual | Unggah/kelola produk (CMS) |
| Keranjang | Kelola item sebelum checkout |
| Checkout | Pilih pembayaran, terapkan kupon |
| Pesanan | Riwayat & pembatalan pesanan |
| Saldo | Top-up saldo |
| Profil | Data akun, aktivitas, produk |

## Stack

- **Backend:** Slim PHP 4 (Controller / Service / Repository / Middleware)
- **Frontend:** HTML, CSS, Vanilla JavaScript
- **Database:** MariaDB 11
- **Web server:** Nginx (reverse proxy)
- **Auth:** JWT
- **Pembayaran:** Midtrans Snap Sandbox
- **Container:** Docker Compose

## Menjalankan

```bash
cp .env.example .env
docker compose up --build -d
curl http://localhost:8080/api/v1/health
```

Buka `http://localhost:8080`. Jika port `8080` terpakai, ubah `APP_PORT` di
`.env` (mis. `APP_PORT=18080`).

Akun seed (semua password `Password123!`): `made`, `komang`, `wayan`, `putu`,
`gede`, `ayu`.

## Struktur

```text
backend/    Slim PHP (Controller/Service/Repository/Middleware/Domain/Support)
frontend/   Halaman HTML + CSS + modul JS
database/   schema.sql + seed.sql
nginx/      konfigurasi reverse proxy
uploads/    file unggahan (media & dokumen produk)
```

## Konfigurasi Midtrans Sandbox

Checkout dan top-up saldo memakai Midtrans Snap Sandbox. Isi kredensial di
`.env` (jangan commit):

```dotenv
MIDTRANS_ENABLED=1
MIDTRANS_SERVER_KEY=SB-Mid-server-...
MIDTRANS_CLIENT_KEY=Mid-client-...
```

Endpoint notifikasi:

```text
POST /api/v1/payments/midtrans/notification
```

Status pembayaran juga dapat disinkronkan lewat
`GET /api/v1/orders/{id}/payment` untuk pengujian lokal.

## Reset Data

```bash
docker compose down -v
docker compose up --build -d
```

## Dokumentasi

Dokumen lengkap ada di [`docs/`](docs) (`01-PRD.md` … `12-TASKS.md`), mencakup
PRD, arsitektur, skema database, spesifikasi API, alur UI, hingga matriks
kerentanan dan rencana pengujian.

## Guardrail

Mode lab hanya aktif saat `APP_ENV=local` dan `LAB_MODE=1`. Jangan pernah
mengekspos instance ini ke jaringan publik.
