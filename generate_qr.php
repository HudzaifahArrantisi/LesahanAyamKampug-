<?php
require_once __DIR__ . '/config.php';

function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

$uuid = generateUUID();
$expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

try {
    $stmt = $pdo->prepare("INSERT INTO guest_sessions (uuid, status, expires_at) VALUES (?, 'active', ?)");
    $stmt->execute([$uuid, $expires_at]);
} catch (PDOException $e) {
    $uuid = generateUUID();
    $stmt = $pdo->prepare("INSERT INTO guest_sessions (uuid, status, expires_at) VALUES (?, 'active', ?)");
    $stmt->execute([$uuid, $expires_at]);
}

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];

// Generate URL yang benar - TANPA trailing slash di akhir
$clean_url = $protocol . "://" . $host . "/pesan/menu/" . $uuid;
$direct_url = $protocol . "://" . $host . "/pesan/menu2.php?uuid=" . $uuid;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>QR Code Menu - Sambel Uleg</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white p-8 rounded-2xl shadow-2xl max-w-md w-full text-center">
        <h1 class="text-3xl font-bold text-orange-600 mb-6">QR Code Menu</h1>
        
        <div id="qrcode" class="mb-4 mx-auto" style="width: 200px; height: 200px;"></div>
        
        <p class="text-gray-600 mb-2">Scan untuk memesan</p>
        <p class="text-sm text-gray-500 mb-1">UUID: <?= substr($uuid, 0, 8) ?>...</p>
        <p class="text-xs text-gray-400">Exp: <?= date('d/m H:i', strtotime($expires_at)) ?></p>
        
        <div class="mt-6 space-y-3">
            <a href="index.php" class="block bg-orange-600 text-white px-6 py-2 rounded-full hover:bg-orange-700 transition">
                Kembali ke Home
            </a>
            <a href="pesan/menu2.php?uuid=<?= $uuid ?>" class="block text-orange-600 border border-orange-600 px-6 py-2 rounded-full hover:bg-orange-50 transition">
                Buka Langsung
            </a>
        </div>
    </div>

    <script>
        // Generate QR Code dengan URL yang benar
        const url = '<?= $direct_url ?>'; // Gunakan URL langsung untuk sekarang
        
        const typeNumber = 0;
        const errorCorrectionLevel = 'H';
        const qr = qrcode(typeNumber, errorCorrectionLevel);
        qr.addData(url);
        qr.make();
        
        document.getElementById('qrcode').innerHTML = qr.createImgTag(4, 0);
        
        const qrImg = document.querySelector('#qrcode img');
        if (qrImg) {
            qrImg.style.border = '10px solid white';
            qrImg.style.borderRadius = '10px';
        }
        
        console.log('QR Code URL:', url);
    </script>
</body>
</html>