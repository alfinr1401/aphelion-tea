<?php
// ============================================================
//  api/stock.php — Stok per Booth per Rasa
//  GET  /api/stock.php                       — semua stok
//  GET  /api/stock.php?booth_id=1            — stok booth tertentu
//  POST /api/stock.php?action=restock        — tambah stok
//  PUT  /api/stock.php                       — set stok langsung
// ============================================================
session_start();
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$db     = getDB();

if (empty($_SESSION['user_id'])) jsonResponse(false, null, 'Belum login.');

if ($method === 'GET') {
    $boothId = isset($_GET['booth_id']) ? (int)$_GET['booth_id'] : 0;
    if ($boothId) {
        $stmt = $db->prepare(
            'SELECT s.booth_id, s.flavor_id, f.name AS flavor_name, f.icon, f.price, s.qty
             FROM stock s JOIN flavors f ON f.id=s.flavor_id
             WHERE s.booth_id=? AND f.active=1 ORDER BY f.id'
        );
        $stmt->execute([$boothId]);
        jsonResponse(true, $stmt->fetchAll());
    }
    // Semua stok — format nested: {booth_id: {flavor_id: qty}}
    $rows = $db->query(
        'SELECT s.booth_id, s.flavor_id, s.qty FROM stock s
         JOIN flavors f ON f.id=s.flavor_id WHERE f.active=1'
    )->fetchAll();
    $map = [];
    foreach ($rows as $r) {
        $map[$r['booth_id']][$r['flavor_id']] = (int)$r['qty'];
    }
    jsonResponse(true, $map);
}

if ($method === 'POST' && $action === 'restock') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $boothId  = (int)($body['booth_id']  ?? 0);
    $flavorId = (int)($body['flavor_id'] ?? 0);
    $qty      = (int)($body['qty']       ?? 0);
    if (!$boothId || !$flavorId || $qty < 1) jsonResponse(false, null, 'Parameter tidak lengkap.');
    $db->prepare(
        'INSERT INTO stock (booth_id,flavor_id,qty) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE qty = qty + VALUES(qty)'
    )->execute([$boothId,$flavorId,$qty]);
    // Ambil qty terbaru
    $stmt = $db->prepare('SELECT qty FROM stock WHERE booth_id=? AND flavor_id=?');
    $stmt->execute([$boothId,$flavorId]);
    $newQty = $stmt->fetchColumn();
    jsonResponse(true, ['qty' => (int)$newQty], "Stok berhasil ditambah $qty unit.");
}

if ($method === 'POST' && $action === 'setstock') {
    $body     = json_decode(file_get_contents('php://input'), true);
    $boothId  = (int)($body['booth_id']  ?? 0);
    $flavorId = (int)($body['flavor_id'] ?? 0);
    $qty      = (int)($body['qty']       ?? -1);
    if (!$boothId || !$flavorId || $qty < 0) jsonResponse(false, null, 'Parameter tidak lengkap.');
    $db->prepare(
        'INSERT INTO stock (booth_id,flavor_id,qty) VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE qty = VALUES(qty)'
    )->execute([$boothId, $flavorId, $qty]);
    jsonResponse(true, ['qty' => $qty], "Stok berhasil diset ke $qty unit.");
}

jsonResponse(false, null, 'Method/action tidak dikenal.');
