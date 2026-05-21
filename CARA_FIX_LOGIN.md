# 🔧 Cara Fix Login Tidak Bisa — Aphelion Tea

## Masalah
Hash password di `database.sql` tidak cocok dengan password `admin123` dan `pass123`.
Ini menyebabkan login selalu gagal meski username & password sudah benar.

## Solusi Cepat (Pakai reset_password.php)

1. Pastikan project sudah ada di folder Laragon:
   ```
   C:\laragon\www\aphelion-tea\
   ```

2. Buka browser, akses:
   ```
   http://localhost/aphelion-tea/reset_password.php
   ```

3. Halaman akan otomatis memperbaiki semua password di database.

4. **Setelah berhasil, hapus file `reset_password.php`** dari folder project.

5. Login di `http://localhost/aphelion-tea/` dengan:

   | Username | Password | Role  |
   |----------|----------|-------|
   | admin    | admin123 | Admin |
   | booth1   | pass123  | Staff |
   | booth2   | pass123  | Staff |
   | booth3   | pass123  | Staff |

---

## Alternatif: Fix Manual via phpMyAdmin

1. Buka phpMyAdmin → pilih database `aphelion_tea`
2. Klik tab **SQL**
3. Paste query berikut, lalu klik **Go**:

```sql
UPDATE users SET password = '$2y$10$TKh8H1.PsgDq48y6mRfOuOLWQkP.wHJNvIeUSZa97kePRHETBSi1S'
WHERE username IN ('booth1','booth2','booth3');
```

4. Untuk admin, generate hash baru di PHP atau pakai `reset_password.php`.

---

## Kenapa Terjadi?
Hash `$2y$10$92IXU...` yang ada di seed data adalah hash default Laravel
untuk kata `"password"` — bukan `"admin123"`. Ini kesalahan copy-paste
saat membuat seed data.
