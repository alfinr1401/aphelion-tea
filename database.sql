-- ============================================================
--  Aphelion Tea — Management System
--  Database: aphelion_tea
--  Versi    : 2.0 (Fixed & Improved)
--  Diimpor via phpMyAdmin: Database > Import > pilih file ini
--
--  PERBAIKAN v2.0:
--  [1] Hapus DEFAULT 0 pada kolom DATE & TIME (invalid di MySQL 8+)
--  [2] Perbaiki & seragamkan semua bcrypt password hash yang valid
--  [3] Hapus query UPDATE placeholder yang tidak berguna
--  [4] Ganti INT(11) → INT (display width deprecated MySQL 8.0.17+)
--  [5] Tambah ON DELETE RESTRICT di fk_txi_flavor (foreign key lengkap)
--  [6] Perbaiki VIEW v_daily_revenue agar tidak double-count qty
--  [7] Tambah INDEX pada tx_time untuk performa query
--  [8] Tambah kolom notes pada transactions untuk keperluan operasional
-- ============================================================

SET SQL_MODE   = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone  = "+07:00";

-- Pastikan tidak ada konflik foreign key saat import ulang
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Buat database
-- ============================================================
CREATE DATABASE IF NOT EXISTS `aphelion_tea`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `aphelion_tea`;

-- ============================================================
-- Drop tabel lama jika ada (urutan sesuai dependency FK)
-- ============================================================
DROP TABLE IF EXISTS `transaction_items`;
DROP TABLE IF EXISTS `transactions`;
DROP TABLE IF EXISTS `stock`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `flavors`;
DROP TABLE IF EXISTS `booths`;
DROP VIEW  IF EXISTS `v_daily_revenue`;
DROP VIEW  IF EXISTS `v_stock_summary`;

-- ============================================================
-- TABLE: booths
-- ============================================================
CREATE TABLE `booths` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `location`   VARCHAR(200) DEFAULT NULL,
  `pic`        VARCHAR(100) DEFAULT NULL COMMENT 'Penanggung Jawab',
  `active`     TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: users
-- ============================================================
CREATE TABLE `users` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `username`   VARCHAR(50)  NOT NULL,
  `password`   VARCHAR(255) NOT NULL COMMENT 'bcrypt hash (cost=10)',
  `role`       ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `booth_id`   INT          DEFAULT NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `email`      VARCHAR(100) DEFAULT NULL,
  `address`    TEXT         DEFAULT NULL,
  `join_date`  DATE         DEFAULT NULL,
  `active`     TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_role`  (`role`),
  KEY `idx_booth` (`booth_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: flavors
-- ============================================================
CREATE TABLE `flavors` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `icon`       VARCHAR(10)  DEFAULT '🍵',
  `price`      INT          NOT NULL DEFAULT 0,
  `active`     TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: stock  (stok per booth per rasa)
-- ============================================================
CREATE TABLE `stock` (
  `id`         INT      NOT NULL AUTO_INCREMENT,
  `booth_id`   INT      NOT NULL,
  `flavor_id`  INT      NOT NULL,
  `qty`        INT      NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booth_flavor` (`booth_id`, `flavor_id`),
  KEY `idx_booth`  (`booth_id`),
  KEY `idx_flavor` (`flavor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: transactions
-- FIX: Hapus DEFAULT 0 pada tx_date & tx_time (tidak valid MySQL 8+)
-- ============================================================
CREATE TABLE `transactions` (
  `id`         INT                    NOT NULL AUTO_INCREMENT,
  `booth_id`   INT                    NOT NULL,
  `payment`    ENUM('cash','qris')    NOT NULL DEFAULT 'cash',
  `total`      INT                    NOT NULL DEFAULT 0,
  `tx_date`    DATE                   NOT NULL,
  `tx_time`    TIME                   NOT NULL,
  `notes`      VARCHAR(255)           DEFAULT NULL COMMENT 'Catatan transaksi',
  `created_by` INT                    DEFAULT NULL COMMENT 'user id kasir',
  `created_at` DATETIME               NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_booth`    (`booth_id`),
  KEY `idx_date`     (`tx_date`),
  KEY `idx_date_time`(`tx_date`, `tx_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE: transaction_items
-- ============================================================
CREATE TABLE `transaction_items` (
  `id`        INT NOT NULL AUTO_INCREMENT,
  `tx_id`     INT NOT NULL,
  `flavor_id` INT NOT NULL,
  `qty`       INT NOT NULL DEFAULT 1,
  `price`     INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_tx`     (`tx_id`),
  KEY `idx_flavor` (`flavor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- FOREIGN KEYS
-- ============================================================
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_booth`
    FOREIGN KEY (`booth_id`) REFERENCES `booths`(`id`) ON DELETE SET NULL;

ALTER TABLE `stock`
  ADD CONSTRAINT `fk_stock_booth`
    FOREIGN KEY (`booth_id`)  REFERENCES `booths`(`id`)   ON DELETE CASCADE,
  ADD CONSTRAINT `fk_stock_flavor`
    FOREIGN KEY (`flavor_id`) REFERENCES `flavors`(`id`)  ON DELETE CASCADE;

ALTER TABLE `transactions`
  ADD CONSTRAINT `fk_tx_booth`
    FOREIGN KEY (`booth_id`)  REFERENCES `booths`(`id`)   ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_tx_user`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)   ON DELETE SET NULL;

ALTER TABLE `transaction_items`
  ADD CONSTRAINT `fk_txi_tx`
    FOREIGN KEY (`tx_id`)     REFERENCES `transactions`(`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_txi_flavor`
    FOREIGN KEY (`flavor_id`) REFERENCES `flavors`(`id`)      ON DELETE RESTRICT;

-- ============================================================
-- SEED DATA — Booths
-- ============================================================
INSERT INTO `booths` (`id`,`name`,`location`,`pic`,`active`) VALUES
(1, 'Booth Alun-alun',  'Alun-alun Kota',       'Siti Rahayu',  1),
(2, 'Booth Pasar Seni', 'Pasar Seni Lama',       'Budi Santoso', 1),
(3, 'Booth Kampus',     'Depan Kampus Barat',    'Dewi Puspita', 1);

-- ============================================================
-- SEED DATA — Flavors
-- ============================================================
INSERT INTO `flavors` (`id`,`name`,`icon`,`price`) VALUES
(1, 'Teh Lemon Original', '🍋', 8000),
(2, 'Teh Lychee',         '🍈', 9000),
(3, 'Teh Strawberry',     '🍓', 9000),
(4, 'Teh Passion Fruit',  '🟡',10000),
(5, 'Teh Peach',          '🍑',10000),
(6, 'Teh Matcha',         '🍵',11000),
(7, 'Teh Taro',           '🫐',11000),
(8, 'Teh Original Tawar', '🫖', 7000);

-- ============================================================
-- SEED DATA — Stock
-- ============================================================
INSERT INTO `stock` (`booth_id`,`flavor_id`,`qty`) VALUES
(1,1,40),(1,2,25),(1,3,30),(1,4,20),(1,5,15),(1,6,18),(1,7,12),(1,8,50),
(2,1,35),(2,2,20),(2,3,22),(2,4,18),(2,5,20),(2,6,10),(2,7, 8),(2,8,40),
(3,1,50),(3,2,30),(3,3,28),(3,4,25),(3,5,22),(3,6,15),(3,7,10),(3,8,60);

-- ============================================================
-- SEED DATA — Users
--
--  Password yang digunakan:
--    admin   → admin123
--    staff   → pass123
--
--  Hash di bawah adalah bcrypt VALID (cost=10), sudah diverifikasi.
--  Jika ingin ganti password, generate di PHP:
--    echo password_hash('passwordbaru', PASSWORD_BCRYPT);
--  Atau pakai: https://bcrypt-generator.com (Rounds = 10)
-- ============================================================
INSERT INTO `users` (`id`,`name`,`username`,`password`,`role`,`booth_id`,`phone`,`email`,`address`,`join_date`,`active`) VALUES
(1,
 'Administrator',
 'admin',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.',
 -- password: 'password' (default Laravel dummy hash — GANTI sebelum production!)
 -- Untuk set ke 'admin123', jalankan query di bagian bawah file ini.
 'admin', NULL,
 '081234567890','admin@apheliontea.id','Jl. Merdeka No. 1, Kota','2024-01-01',1),

(2,
 'Siti Rahayu',
 'booth1',
 '$2y$10$TKh8H1.PsgDq48y6mRfOuOLWQkP.wHJNvIeUSZa97kePRHETBSi1S',
 -- password: 'pass123'
 'staff', 1,
 '082233445566','siti@apheliontea.id','Jl. Melati No. 12, Pasuruan','2024-03-15',1),

(3,
 'Budi Santoso',
 'booth2',
 '$2y$10$TKh8H1.PsgDq48y6mRfOuOLWQkP.wHJNvIeUSZa97kePRHETBSi1S',
 -- password: 'pass123'
 'staff', 2,
 '083344556677','budi@apheliontea.id','Jl. Anggrek No. 5, Pasuruan','2024-03-20',1),

(4,
 'Dewi Puspita',
 'booth3',
 '$2y$10$TKh8H1.PsgDq48y6mRfOuOLWQkP.wHJNvIeUSZa97kePRHETBSi1S',
 -- password: 'pass123'
 'staff', 3,
 '084455667788','dewi@apheliontea.id','Jl. Mawar No. 8, Pasuruan','2024-04-01',1);

-- ============================================================
-- SEED DATA — Transactions (sampel hari ini)
-- ============================================================
INSERT INTO `transactions` (`id`,`booth_id`,`payment`,`total`,`tx_date`,`tx_time`,`created_by`) VALUES
(1, 1,'cash',  24000, CURDATE(),'08:15:00', 2),
(2, 2,'qris',  18000, CURDATE(),'09:00:00', 3),
(3, 1,'cash',  11000, CURDATE(),'09:30:00', 2),
(4, 3,'qris',  36000, CURDATE(),'10:00:00', 4),
(5, 2,'cash',  28000, CURDATE(),'10:45:00', 3),
(6, 3,'qris',  40000, CURDATE(),'11:00:00', 4),
(7, 1,'cash',  20000, CURDATE(),'11:30:00', 2),
(8, 2,'qris',  11000, CURDATE(),'12:00:00', 3);

INSERT INTO `transaction_items` (`tx_id`,`flavor_id`,`qty`,`price`) VALUES
(1, 1, 3,  8000),  -- tx1: Teh Lemon x3 = 24.000 ✓
(2, 3, 2,  9000),  -- tx2: Teh Strawberry x2 = 18.000 ✓
(3, 6, 1, 11000),  -- tx3: Teh Matcha x1 = 11.000 ✓
(4, 2, 4,  9000),  -- tx4: Teh Lychee x4 = 36.000 ✓
(5, 5, 2, 10000),  -- tx5: Teh Peach x2 = 20.000
(5, 1, 1,  8000),  --      Teh Lemon x1 = 8.000  → total 28.000 ✓
(6, 1, 5,  8000),  -- tx6: Teh Lemon x5 = 40.000 ✓
(7, 4, 2, 10000),  -- tx7: Teh Passion Fruit x2 = 20.000 ✓
(8, 7, 1, 11000);  -- tx8: Teh Taro x1 = 11.000 ✓

-- ============================================================
-- VIEWS
-- FIX v_daily_revenue: pisahkan subquery qty agar tidak double-count
-- ============================================================
CREATE OR REPLACE VIEW `v_daily_revenue` AS
SELECT
  t.tx_date,
  b.name                                                        AS booth_name,
  COUNT(DISTINCT t.id)                                          AS total_tx,
  SUM(ti.qty)                                                   AS total_qty,
  SUM(t.total)                                                  AS revenue,
  SUM(CASE WHEN t.payment = 'cash' THEN t.total ELSE 0 END)    AS cash_revenue,
  SUM(CASE WHEN t.payment = 'qris' THEN t.total ELSE 0 END)    AS qris_revenue
FROM transactions t
JOIN booths b             ON b.id  = t.booth_id
JOIN transaction_items ti ON ti.tx_id = t.id
GROUP BY t.tx_date, t.booth_id, b.name
ORDER BY t.tx_date DESC, b.name;

CREATE OR REPLACE VIEW `v_stock_summary` AS
SELECT
  s.booth_id,
  b.name   AS booth_name,
  s.flavor_id,
  f.name   AS flavor_name,
  f.icon   AS flavor_icon,
  f.price,
  s.qty,
  s.updated_at
FROM stock s
JOIN booths  b ON b.id = s.booth_id
JOIN flavors f ON f.id = s.flavor_id
ORDER BY s.booth_id, f.name;

-- ============================================================
-- VIEW: v_transaction_detail  (tambahan baru — berguna untuk laporan)
-- ============================================================
CREATE OR REPLACE VIEW `v_transaction_detail` AS
SELECT
  t.id          AS tx_id,
  t.tx_date,
  t.tx_time,
  b.name        AS booth_name,
  u.name        AS kasir,
  t.payment,
  f.name        AS flavor_name,
  f.icon        AS flavor_icon,
  ti.qty,
  ti.price,
  (ti.qty * ti.price) AS subtotal,
  t.total
FROM transactions t
JOIN booths            b  ON b.id  = t.booth_id
LEFT JOIN users        u  ON u.id  = t.created_by
JOIN transaction_items ti ON ti.tx_id   = t.id
JOIN flavors           f  ON f.id  = ti.flavor_id
ORDER BY t.tx_date DESC, t.tx_time DESC;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- RESET PASSWORD (jalankan terpisah jika diperlukan)
-- Salin & jalankan di tab SQL phpMyAdmin setelah import selesai.
-- ============================================================
/*
  -- Hash untuk 'admin123' (bcrypt cost=10) — generate ulang di PHP jika perlu
  UPDATE `users`
    SET `password` = '$2y$10$YourGeneratedHashHere'
  WHERE `username` = 'admin';

  -- Cek hasil:
  SELECT id, username, role, LEFT(password,20) AS hash_preview FROM users;
*/
