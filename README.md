# 🍵 Aphelion Tea — Management System

## Struktur File
```
aphelion-tea/
├── index.html          ← Frontend utama (buka di browser)
├── config.php          ← Konfigurasi koneksi database
├── database.sql        ← Schema + seed data (import ke phpMyAdmin)
├── .htaccess           ← Konfigurasi Apache
└── api/
    ├── auth.php        ← Login / Logout / Session
    ├── users.php       ← CRUD User & Password
    ├── booths.php      ← CRUD Booth
    ├── flavors.php     ← CRUD Rasa Teh
    ├── stock.php       ← Kelola Stok
    ├── transactions.php← Kasir & Riwayat Transaksi
    └── reports.php     ← Laporan Keuangan & Dashboard
```

---

## ⚙️ Cara Setup

### 1. Import Database
- Buka **phpMyAdmin** → klik tab **Import**
- Pilih file `database.sql` → klik **Go**
- Database `aphelion_tea` beserta semua tabel & data awal akan terbuat otomatis

### 2. Konfigurasi Koneksi
Edit file `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // ← ganti dengan user MySQL kamu
define('DB_PASS', '');           // ← ganti dengan password MySQL kamu
define('DB_NAME', 'aphelion_tea');
```

### 3. Upload ke Server
- Taruh seluruh folder `aphelion-tea/` di dalam folder **htdocs** (XAMPP) atau **www** (WAMP/Laragon)
- Contoh: `C:\xampp\htdocs\aphelion-tea\`

### 4. Buka di Browser
```
http://localhost/aphelion-tea/
```

---

## 🔑 Akun Default

| Username | Password  | Peran    | Booth           |
|----------|-----------|----------|-----------------|
| admin    | admin123  | Admin    | —               |
| booth1   | pass123   | Karyawan | Booth Alun-alun |
| booth2   | pass123   | Karyawan | Booth Pasar Seni|
| booth3   | pass123   | Karyawan | Booth Kampus    |

> ⚠️ Segera ganti password setelah pertama login!

---

## 📦 Kebutuhan Server
- PHP 8.0+ dengan ekstensi **PDO** dan **pdo_mysql**
- MySQL 5.7+ atau MariaDB 10.3+
- Apache dengan `mod_rewrite` aktif (XAMPP sudah include)

---

## 🔌 API Endpoints

| Method | Endpoint                              | Fungsi                        |
|--------|---------------------------------------|-------------------------------|
| POST   | api/auth.php?action=login             | Login                         |
| GET    | api/auth.php?action=logout            | Logout                        |
| GET    | api/auth.php?action=me                | Cek sesi aktif                |
| GET    | api/users.php                         | List semua user               |
| POST   | api/users.php                         | Tambah user baru              |
| PUT    | api/users.php?id=1                    | Update data user              |
| DELETE | api/users.php?id=1                    | Hapus user                    |
| PUT    | api/users.php?action=change_password  | Ganti password sendiri        |
| PUT    | api/users.php?action=reset_password&id=1 | Reset password user (admin)|
| GET    | api/booths.php                        | List semua booth              |
| POST   | api/booths.php                        | Tambah booth                  |
| GET    | api/flavors.php                       | List semua rasa               |
| POST   | api/flavors.php                       | Tambah rasa                   |
| GET    | api/stock.php                         | Semua stok (nested JSON)      |
| POST   | api/stock.php?action=restock          | Tambah stok                   |
| GET    | api/transactions.php                  | List transaksi (hari ini)     |
| POST   | api/transactions.php                  | Buat transaksi baru (kasir)   |
| GET    | api/reports.php?type=dashboard        | Data dashboard                |
| GET    | api/reports.php?type=revenue7days     | Pendapatan 7 hari             |
| GET    | api/reports.php?type=finance          | Laporan keuangan per booth    |
