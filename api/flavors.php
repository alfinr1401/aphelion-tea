<?php
// ============================================================
//  api/flavors.php — CRUD Rasa Teh
// ============================================================
session_start();
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db     = getDB();

if (empty($_SESSION['user_id'])) jsonResponse(false, null, 'Belum login.');

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM flavors WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, null, 'Rasa tidak ditemukan.');
        jsonResponse(true, $row);
    }
    $stmt = $db->query('SELECT * FROM flavors WHERE active=1 ORDER BY id ASC');
    jsonResponse(true, $stmt->fetchAll());
}

if ($method === 'POST') {
    $body  = json_decode(file_get_contents('php://input'), true);
    $name  = trim($body['name']  ?? '');
    $icon  = trim($body['icon']  ?? '🍵');
    $price = (int)($body['price'] ?? 0);
    $stock = (int)($body['stock_init'] ?? 0);
    if (!$name || $price <= 0) jsonResponse(false, null, 'Nama dan harga wajib diisi.');
    $db->prepare('INSERT INTO flavors (name,icon,price,active) VALUES (?,?,?,1)')
       ->execute([$name,$icon,$price]);
    $fid = $db->lastInsertId();
    // Tambah stok awal ke semua booth
    $booths = $db->query('SELECT id FROM booths WHERE active=1')->fetchAll();
    foreach ($booths as $b) {
        $db->prepare('INSERT IGNORE INTO stock (booth_id,flavor_id,qty) VALUES (?,?,?)')
           ->execute([$b['id'], $fid, $stock]);
    }
    jsonResponse(true, ['id' => (int)$fid], 'Rasa berhasil ditambahkan.');
}

if ($method === 'PUT') {
    if (!$id) jsonResponse(false, null, 'ID rasa wajib.');
    $body  = json_decode(file_get_contents('php://input'), true);
    $name  = trim($body['name']  ?? '');
    $icon  = trim($body['icon']  ?? '🍵');
    $price = (int)($body['price'] ?? 0);
    if (!$name || $price <= 0) jsonResponse(false, null, 'Nama dan harga wajib.');
    $db->prepare('UPDATE flavors SET name=?,icon=?,price=? WHERE id=?')
       ->execute([$name,$icon,$price,$id]);
    jsonResponse(true, null, 'Rasa berhasil diperbarui.');
}

if ($method === 'DELETE') {
    if (!$id) jsonResponse(false, null, 'ID rasa wajib.');
    $db->prepare('UPDATE flavors SET active=0 WHERE id=?')->execute([$id]);
    jsonResponse(true, null, 'Rasa berhasil dihapus.');
}

jsonResponse(false, null, 'Method tidak dikenal.');
