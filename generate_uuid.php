<?php
require_once __DIR__ . '/config.php';

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

$uuid = generateUUID();

// Simpan session guest baru dengan expires_at
$expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

try {
    $stmt = $pdo->prepare("INSERT INTO guest_sessions (uuid, status, expires_at) VALUES (?, 'active', ?)");
    $stmt->execute([$uuid, $expires_at]);
    
    // Redirect ke halaman pesan dengan UUID
    header("Location: pesan/menu2.php?uuid=" . $uuid);
    exit;
    
} catch (PDOException $e) {
    // Fallback jika ada error
    header("Location: pesan/menu2.php?uuid=" . $uuid);
    exit;
}
?>