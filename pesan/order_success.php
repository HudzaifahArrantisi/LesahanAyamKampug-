<?php
require_once __DIR__ . '/../config.php';

$uuid = $_GET['uuid'] ?? '';

if (empty($uuid)) {
    header("Location: /");
    exit;
}

// Cek order
$stmt = $pdo->prepare("
    SELECT o.*, gs.table_number 
    FROM orders o 
    JOIN guest_sessions gs ON o.guest_uuid = gs.uuid 
    WHERE o.guest_uuid = ? 
    ORDER BY o.created_at DESC 
    LIMIT 1
");
$stmt->execute([$uuid]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pesanan Berhasil - Sambel Uleg</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-2xl shadow-xl p-8 max-w-md w-full mx-4 text-center">
        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-check text-4xl text-green-600"></i>
        </div>
        
        <h1 class="text-2xl font-bold text-gray-800 mb-2">Pesanan Berhasil!</h1>
        <p class="text-gray-600 mb-4">Pesanan Anda telah dikirim ke dapur.</p>
        
        <?php if ($order): ?>
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <div class="flex justify-between mb-2">
                    <span class="text-gray-600">No. Meja:</span>
                    <span class="font-semibold"><?= htmlspecialchars($order['table_number']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Total:</span>
                    <span class="font-semibold text-green-600">Rp <?= number_format($order['total_amount'], 0, ',', '.') ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="space-y-3">
            <a href="/pesan/<?= generateUUID() ?>" class="block w-full bg-orange-600 text-white py-3 rounded-lg hover:bg-orange-700 transition font-semibold">
                Pesan Lagi
            </a>
            <a href="/" class="block w-full bg-gray-200 text-gray-800 py-3 rounded-lg hover:bg-gray-300 transition font-semibold">
                Kembali ke Home
            </a>
        </div>
        
        <p class="text-xs text-gray-500 mt-6">
            Pesanan akan diproses segera. Terima kasih!
        </p>
    </div>
</body>
</html>