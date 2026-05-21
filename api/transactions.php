<?php
// ============================================================
//  api/transactions.php — Kasir & Laporan Transaksi
//  GET  /api/transactions.php                — list semua (admin) / booth saya (staff)
//  GET  /api/transactions.php?booth_id=1     — filter booth
//  GET  /api/transactions.php?date=2025-04-09 — filter tanggal
//  POST /api/transactions.php                — buat transaksi baru (kasir)
// ============================================================
session_start();
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

if (empty($_SESSION['user_id'])) jsonResponse(false, null, 'Belum login.');

// Ambil data user saat ini
$curStmt = $db->prepare('SELECT id,role,booth_id FROM users WHERE id=? LIMIT 1');
$curStmt->execute([$_SESSION['user_id']]);
$curUser = $curStmt->fetch();
if (!$curUser) jsonResponse(false, null, 'Session tidak valid.');

// ======================================================
// GET — list transaksi
// ======================================================
if ($method === 'GET') {
    $boothId  = isset($_GET['booth_id'])  ? (int)$_GET['booth_id']  : 0;
    $payment  = $_GET['payment']  ?? '';
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d'); // default hari ini
    $dateTo   = $_GET['date_to']   ?? date('Y-m-d');

    // Staff hanya bisa lihat booth sendiri
    if ($curUser['role'] === 'staff') {
        $boothId = (int)$curUser['booth_id'];
    }

    $where  = ['t.tx_date BETWEEN ? AND ?'];
    $params = [$dateFrom, $dateTo];

    if ($boothId)  { $where[] = 't.booth_id = ?';  $params[] = $boothId; }
    if ($payment)  { $where[] = 't.payment = ?';   $params[] = $payment; }

    $sql = "SELECT t.id, t.booth_id, b.name AS booth_name,
                   t.payment, t.total, t.tx_date, t.tx_time,
                   t.created_by,
                   JSON_ARRAYAGG(
                     JSON_OBJECT(
                       'flavor_id', ti.flavor_id,
                       'flavor_name', f.name,
                       'icon', f.icon,
                       'qty', ti.qty,
                       'price', ti.price
                     )
                   ) AS items
            FROM transactions t
            JOIN booths b ON b.id = t.booth_id
            JOIN transaction_items ti ON ti.tx_id = t.id
            JOIN flavors f ON f.id = ti.flavor_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY t.id
            ORDER BY t.id DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    // Parse items JSON string
    foreach ($rows as &$row) {
        $row['items'] = json_decode($row['items'], true);
    }
    jsonResponse(true, $rows);
}

// ======================================================
// POST — buat transaksi baru
// ======================================================
if ($method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $boothId = (int)($body['booth_id'] ?? 0);
    $payment = $body['payment'] ?? 'cash';
    $items   = $body['items']   ?? [];  // [{flavor_id, qty, price}]
    $total   = (int)($body['total'] ?? 0);

    // Staff hanya bisa transaksi di booth sendiri
    if ($curUser['role'] === 'staff') {
        $boothId = (int)$curUser['booth_id'];
    }

    if (!$boothId || empty($items)) jsonResponse(false, null, 'Data transaksi tidak lengkap.');
    if (!in_array($payment, ['cash','qris'])) jsonResponse(false, null, 'Metode bayar tidak valid.');

    $db->beginTransaction();
    try {
        // Insert header transaksi
        $db->prepare(
            'INSERT INTO transactions (booth_id,payment,total,tx_date,tx_time,created_by)
             VALUES (?,?,?,CURDATE(),CURTIME(),?)'
        )->execute([$boothId,$payment,$total,$_SESSION['user_id']]);
        $txId = $db->lastInsertId();

        foreach ($items as $item) {
            $fid   = (int)($item['flavor_id'] ?? 0);
            $qty   = (int)($item['qty']       ?? 0);
            $price = (int)($item['price']     ?? 0);
            if (!$fid || $qty < 1) continue;

            // Cek stok
            $sStmt = $db->prepare('SELECT qty FROM stock WHERE booth_id=? AND flavor_id=? FOR UPDATE');
            $sStmt->execute([$boothId,$fid]);
            $currentStock = (int)$sStmt->fetchColumn();
            if ($currentStock < $qty) {
                $db->rollBack();
                jsonResponse(false, null, "Stok tidak mencukupi untuk flavor_id $fid.");
            }

            // Kurangi stok
            $db->prepare('UPDATE stock SET qty = qty - ? WHERE booth_id=? AND flavor_id=?')
               ->execute([$qty,$boothId,$fid]);

            // Insert item
            $db->prepare('INSERT INTO transaction_items (tx_id,flavor_id,qty,price) VALUES (?,?,?,?)')
               ->execute([$txId,$fid,$qty,$price]);
        }

        $db->commit();
        jsonResponse(true, ['id' => (int)$txId], 'Transaksi berhasil disimpan.');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, null, 'Transaksi gagal: ' . $e->getMessage());
    }
}

jsonResponse(false, null, 'Method tidak dikenal.');
