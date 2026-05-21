<?php
// ============================================================
//  api/booths.php — CRUD Booth
// ============================================================
session_start();
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$db     = getDB();

if (empty($_SESSION['user_id'])) jsonResponse(false, null, 'Belum login.');

if ($method === 'GET') {
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM booths WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) jsonResponse(false, null, 'Booth tidak ditemukan.');
        jsonResponse(true, $row);
    }
    $stmt = $db->query('SELECT * FROM booths ORDER BY id ASC');
    jsonResponse(true, $stmt->fetchAll());
}

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = trim($body['name'] ?? '');
    $loc  = trim($body['location'] ?? '');
    $pic  = trim($body['pic'] ?? '');
    if (!$name) jsonResponse(false, null, 'Nama booth wajib.');
    $db->prepare('INSERT INTO booths (name,location,pic,active) VALUES (?,?,?,1)')
       ->execute([$name, $loc, $pic]);
    $newId = $db->lastInsertId();
    // Inisialisasi stok 0 untuk semua rasa
    $flavors = $db->query('SELECT id FROM flavors WHERE active=1')->fetchAll();
    foreach ($flavors as $f) {
        $db->prepare('INSERT IGNORE INTO stock (booth_id,flavor_id,qty) VALUES (?,?,0)')
           ->execute([$newId, $f['id']]);
    }
    jsonResponse(true, ['id' => (int)$newId], 'Booth berhasil ditambahkan.');
}

if ($method === 'PUT') {
    if (!$id) jsonResponse(false, null, 'ID booth wajib.');
    $body   = json_decode(file_get_contents('php://input'), true);
    $name   = trim($body['name']     ?? '');
    $loc    = trim($body['location'] ?? '');
    $pic    = trim($body['pic']      ?? '');
    $active = isset($body['active']) ? (int)$body['active'] : 1;
    if (!$name) jsonResponse(false, null, 'Nama booth wajib.');
    $db->prepare('UPDATE booths SET name=?,location=?,pic=?,active=? WHERE id=?')
       ->execute([$name,$loc,$pic,$active,$id]);
    jsonResponse(true, null, 'Booth berhasil diperbarui.');
}

if ($method === 'DELETE') {
    if (!$id) jsonResponse(false, null, 'ID booth wajib.');
    $db->prepare('DELETE FROM booths WHERE id=?')->execute([$id]);
    jsonResponse(true, null, 'Booth berhasil dihapus.');
}

jsonResponse(false, null, 'Method tidak dikenal.');
