<?php
// ============================================================
//  api/reports.php — Laporan Keuangan & Dashboard
//  GET /api/reports.php?type=dashboard
//  GET /api/reports.php?type=revenue7days
//  GET /api/reports.php?type=finance&date_from=&date_to=
// ============================================================
session_start();
require_once __DIR__ . '/../config.php';

if (empty($_SESSION['user_id'])) jsonResponse(false, null, 'Belum login.');
$db   = getDB();
$type = $_GET['type'] ?? 'dashboard';

// ---- Dashboard summary ----
if ($type === 'dashboard') {
    $boothId = isset($_GET['booth_id']) ? (int)$_GET['booth_id'] : 0;
    $date    = $_GET['date'] ?? date('Y-m-d');

    $where  = ['t.tx_date = ?'];
    $params = [$date];
    if ($boothId) { $where[] = 't.booth_id = ?'; $params[] = $boothId; }
    $w = implode(' AND ', $where);

    $row = $db->prepare(
        "SELECT COUNT(t.id) AS total_tx,
                COALESCE(SUM(t.total),0) AS total_rev,
                COALESCE(SUM(CASE WHEN t.payment='cash' THEN t.total ELSE 0 END),0) AS cash_rev,
                COALESCE(SUM(CASE WHEN t.payment='qris' THEN t.total ELSE 0 END),0) AS qris_rev,
                COALESCE(SUM(ti.qty),0) AS total_qty
         FROM transactions t
         JOIN transaction_items ti ON ti.tx_id = t.id
         WHERE $w"
    );
    $row->execute($params);
    $summary = $row->fetch();

    // Per booth
    $boothSql = "SELECT t.booth_id, b.name AS booth_name,
                        COUNT(DISTINCT t.id) AS tx_count,
                        COALESCE(SUM(t.total),0) AS revenue,
                        COALESCE(SUM(ti.qty),0) AS qty
                 FROM transactions t
                 JOIN booths b ON b.id=t.booth_id
                 JOIN transaction_items ti ON ti.tx_id=t.id
                 WHERE t.tx_date = ?";
    $bParams = [$date];
    if ($boothId) { $boothSql .= ' AND t.booth_id=?'; $bParams[] = $boothId; }
    $boothSql .= ' GROUP BY t.booth_id ORDER BY revenue DESC';
    $boothStmt = $db->prepare($boothSql);
    $boothStmt->execute($bParams);

    // Top flavors
    $flavorSql = "SELECT ti.flavor_id, f.name, f.icon, SUM(ti.qty) AS qty_sold
                  FROM transaction_items ti
                  JOIN transactions t ON t.id = ti.tx_id
                  JOIN flavors f ON f.id = ti.flavor_id
                  WHERE t.tx_date = ?";
    $fParams = [$date];
    if ($boothId) { $flavorSql .= ' AND t.booth_id=?'; $fParams[] = $boothId; }
    $flavorSql .= ' GROUP BY ti.flavor_id ORDER BY qty_sold DESC LIMIT 10';
    $flavorStmt = $db->prepare($flavorSql);
    $flavorStmt->execute($fParams);

    jsonResponse(true, [
        'summary'    => $summary,
        'per_booth'  => $boothStmt->fetchAll(),
        'top_flavors'=> $flavorStmt->fetchAll(),
    ]);
}

// ---- Revenue 7 hari ----
if ($type === 'revenue7days') {
    $boothId = isset($_GET['booth_id']) ? (int)$_GET['booth_id'] : 0;
    $rows = [];
    for ($i = 6; $i >= 0; $i--) {
        $date   = date('Y-m-d', strtotime("-$i days"));
        $label  = date('d M', strtotime($date));
        $sql    = "SELECT COALESCE(SUM(total),0) AS rev FROM transactions WHERE tx_date=?";
        $params = [$date];
        if ($boothId) { $sql .= ' AND booth_id=?'; $params[] = $boothId; }
        $rev = $db->prepare($sql);
        $rev->execute($params);
        $rows[] = ['date' => $label, 'rev' => (int)$rev->fetchColumn()];
    }
    jsonResponse(true, $rows);
}

// ---- Finance per booth (range tanggal) ----
if ($type === 'finance') {
    $from = $_GET['date_from'] ?? date('Y-m-d');
    $to   = $_GET['date_to']   ?? date('Y-m-d');
    $stmt = $db->prepare(
        "SELECT b.id AS booth_id, b.name AS booth_name, b.location,
                COUNT(DISTINCT t.id) AS total_tx,
                COALESCE(SUM(ti.qty),0) AS total_qty,
                COALESCE(SUM(t.total),0) AS total_rev,
                COALESCE(SUM(CASE WHEN t.payment='cash' THEN t.total ELSE 0 END),0) AS cash_rev,
                COALESCE(SUM(CASE WHEN t.payment='qris' THEN t.total ELSE 0 END),0) AS qris_rev
         FROM booths b
         LEFT JOIN transactions t ON t.booth_id=b.id AND t.tx_date BETWEEN ? AND ?
         LEFT JOIN transaction_items ti ON ti.tx_id=t.id
         WHERE b.active=1
         GROUP BY b.id
         ORDER BY b.id"
    );
    $stmt->execute([$from,$to]);
    jsonResponse(true, $stmt->fetchAll());
}

jsonResponse(false, null, 'Type laporan tidak dikenal.');
