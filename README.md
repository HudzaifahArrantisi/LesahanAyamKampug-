# 🍗 Sistem Kasir Rumah Makan Lesehan Ayam Kampung

Aplikasi sistem kasir berbasis web untuk rumah makan lesehan ayam kampung dengan fitur lengkap mulai dari landing page, manajemen menu, dashboard admin, hingga payment gateway terintegrasi dengan Midtrans.

## ✨ Fitur Utama

### 1. 🏠 Landing Page
- Tampilan menu untuk pelanggan
- Interface yang user-friendly
- Informasi lengkap tentang rumah makan

### 2. 🔐 Sistem Login & Authentication
- Login untuk admin
- Keamanan akses dashboard
- Session management

### 3. 📊 Dashboard Admin
- Overview transaksi harian
- Statistik penjualan
- Monitoring real-time

### 4. 🍽️ Manajemen Menu
- Tambah, edit, dan hapus menu
- Upload gambar menu
- Atur harga dan kategori
- Manajemen stok menu

### 5. 🛒 Sistem Kasir
- Tambah item ke keranjang
- Update quantity
- Hitung total otomatis
- Proses pembayaran

### 6. 🧾 Bon & Transaksi
- Generate bon otomatis
- Print bon transaksi
- Riwayat transaksi
- Pencarian bon
- QR Code untuk bon

### 7. 💰 Pemasukan & Pengeluaran
- Catat pemasukan
- Catat pengeluaran
- Laporan keuangan
- Report harian/periode

### 8. 💳 Payment Gateway (Midtrans)
- Pembayaran QRIS
- Multiple metode pembayaran
- Notifikasi pembayaran otomatis
- Check status pembayaran

### 9. 📱 Fitur Tambahan
- Generate UUID untuk transaksi
- QR Code generator
- Responsive design
- Ajax untuk update real-time

## 🛠️ Teknologi yang Digunakan

- **Backend:** PHP Native
- **Database:** MySQL
- **Payment Gateway:** Midtrans
- **QR Code:** Endroid QR Code
- **Dependencies Manager:** Composer
- **Frontend:** HTML, CSS, JavaScript
- **AJAX:** jQuery (optional)

## 📋 Requirements

- PHP >= 7.4
- MySQL/MariaDB
- Composer
- Web Server (Apache/Nginx)
- Extension PHP:
  - php-curl
  - php-json
  - php-mbstring
  - php-gd

## 🚀 Instalasi

### 1. Clone Repository

```bash
git clone <repository-url>
cd rm_kasir
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Setup Database

```bash
# Import database
mysql -u root -p nama_database < db.sql
```

Atau jika menggunakan file `daftartransaksi.sql`:

```bash
mysql -u root -p nama_database < daftartransaksi.sql
```

### 4. Konfigurasi Database

Buat file `config.php` dengan struktur:

```php
<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'nama_database');

// Midtrans Configuration
define('MIDTRANS_SERVER_KEY', 'your-server-key');
define('MIDTRANS_CLIENT_KEY', 'your-client-key');
define('MIDTRANS_IS_PRODUCTION', false);

// Base URL
define('BASE_URL', 'http://localhost:8000');
?>
```

### 5. Setup Midtrans

1. Daftar di [Midtrans](https://midtrans.com)
2. Dapatkan Server Key dan Client Key
3. Masukkan ke file `config.php`
4. Untuk testing, gunakan Sandbox mode (`MIDTRANS_IS_PRODUCTION = false`)

### 6. Jalankan Aplikasi

**Menggunakan PHP Built-in Server:**

```bash
php -S localhost:8000
```

**Menggunakan Laragon:**

```bash
# Akses via
http://rm_kasir.test
```

**Menggunakan XAMPP/WAMP:**

```bash
# Akses via
http://localhost/rm_kasir
```

## 📁 Struktur File

```
rm_kasir/
├── config.php              # Konfigurasi database & Midtrans
├── index.php              # Landing page
├── login.php              # Halaman login
├── dashboard_admin.php    # Dashboard admin
├── menu_management.php    # Manajemen menu
├── menu.php               # Tampilan menu
├── add_menu.php           # Tambah menu
├── full_menu.php          # Daftar menu lengkap
├── add_to_cart.php        # Tambah ke keranjang
├── remove_from_cart.php   # Hapus dari keranjang
├── update_quantity.php    # Update jumlah item
├── bon.php                # Halaman bon
├── create_bon.php         # Buat bon baru
├── process_bon.php        # Proses bon
├── cetak_bon.php          # Cetak bon
├── riwayat_bon.php        # Riwayat bon
├── cari_bon.php           # Cari bon
├── ajax_bon.php           # AJAX handler bon
├── income.php             # Pemasukan
├── get_expenses.php       # Data pengeluaran
├── get_report_details.php # Detail laporan
├── get_day_data.php       # Data harian
├── process_payment.php    # Proses pembayaran
├── process_qris.php       # Proses QRIS
├── check_payment_status.php # Cek status pembayaran
├── callback_doku.php      # Callback payment
├── generate_qr.php        # Generate QR Code
├── generate_uuid.php      # Generate UUID
├── update_transaction.php # Update transaksi
├── functions.php          # Helper functions
├── logout.php             # Logout
├── img/                   # Folder gambar
├── pesan/                 # Modul pemesanan
│   ├── menu2.php
│   ├── ajax_guest.php
│   └── order_success.php
├── r_bon/                 # Report bon
│   └── index.php
└── vendor/                # Composer dependencies
```

## 👤 Default Login

**Admin:**
- Username: `admin`
- Password: `admin123`

*Catatan: Ubah password default setelah instalasi pertama*

## 💳 Testing Payment (Sandbox)

Untuk testing pembayaran Midtrans di mode sandbox:

**QRIS:**
- Scan QR Code yang muncul dengan aplikasi Midtrans Simulator

**Credit Card:**
- Card Number: `4811 1111 1111 1114`
- CVV: `123`
- Exp: `01/25`

## 📱 Cara Penggunaan

### Admin

1. **Login ke Dashboard**
   - Akses `/login.php`
   - Masukkan username dan password

2. **Kelola Menu**
   - Akses `Menu Management`
   - Tambah menu baru dengan foto
   - Edit atau hapus menu existing

3. **Proses Transaksi**
   - Pilih menu dari daftar
   - Tambah ke keranjang
   - Proses pembayaran
   - Print bon

4. **Laporan Keuangan**
   - Lihat pemasukan harian
   - Catat pengeluaran
   - Generate report

### Pelanggan

1. **Lihat Menu**
   - Akses halaman utama
   - Browse menu yang tersedia

2. **Pesan Menu**
   - Pilih menu yang diinginkan
   - Tentukan jumlah
   - Lanjut ke pembayaran

3. **Pembayaran**
   - Pilih metode pembayaran
   - Scan QRIS atau bayar tunai
   - Terima bon digital

## 🔒 Keamanan

- ⚠️ **Jangan commit** file `config.php` ke repository
- ⚠️ **Jangan commit** file SQL dengan data sensitif
- ⚠️ **Ubah password default** setelah instalasi
- ⚠️ Gunakan **HTTPS** untuk production
- ⚠️ Validasi semua input dari user
- ⚠️ Gunakan **prepared statements** untuk query database

## 🐛 Troubleshooting

### Error Database Connection

```php
// Pastikan config.php sudah benar
// Cek MySQL service sudah berjalan
// Cek database sudah di-import
```

### Error Midtrans

```php
// Pastikan Server Key dan Client Key benar
// Cek mode sandbox/production
// Pastikan composer dependencies terinstall
```

### QR Code Tidak Muncul

```php
// Pastikan extension GD terinstall
// Cek permission folder untuk write
composer require endroid/qr-code
```

## 📝 TODO / Future Development

- [ ] Multi-user role (kasir, manager, admin)
- [ ] Export laporan ke Excel/PDF
- [ ] Notifikasi real-time (WebSocket)
- [ ] Mobile app integration
- [ ] Customer loyalty program
- [ ] Inventory management
- [ ] Multi-branch support
- [ ] API REST untuk mobile app

## 🤝 Contributing

Pull requests are welcome! Untuk perubahan besar, silakan buka issue terlebih dahulu.

## 📄 License

[MIT License](LICENSE)

## 👨‍💻 Developer

Developed with ❤️ for Rumah Makan Lesehan Ayam Kampung

## 📞 Support

Jika ada pertanyaan atau masalah, silakan buat issue di repository ini.

---

**Happy Coding! 🚀**
