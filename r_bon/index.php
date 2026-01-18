<?php
require '../config.php';

// Helper: escape output
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Ambil UUID dari query
$uuid = $_GET['uuid'] ?? '';

// Coba ambil dari PATH_INFO jika kosong
if (!$uuid && isset($_SERVER['PATH_INFO'])) {
    $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
    if (isset($parts[0]) && preg_match('/^[a-f0-9-]{36}$/', $parts[0])) {
        $uuid = $parts[0];
    }
}

// Kalau tetap kosong → tampilkan error
if (!$uuid) {
    header('HTTP/1.0 404 Not Found');
    die("
        <div style='
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;
            background:linear-gradient(135deg,#1e293b,#0f172a);
            color:#e2e8f0;
            font-family:Segoe UI,Tahoma,Geneva,Verdana,sans-serif;
            text-align:center;
            flex-direction:column;
        '>
            <i class='fas fa-exclamation-triangle' style='font-size:64px;color:#f87171;margin-bottom:20px;'></i>
            <h1 style='font-size:2.5rem;margin:0;'>404 NOT FOUND</h1>
            <p style='margin:10px 0 20px;font-size:1.1rem;color:#cbd5e1;'>
                Mau nari apaan ler
            </p>
        </div>
    ");
}


// Cari bon_id dari uuid
$stmt = $pdo->prepare("SELECT bon_history_id FROM bon_uuid WHERE uuid = ?");
$stmt->execute([$uuid]);
$bon_uuid = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bon_uuid) {
    header('HTTP/1.0 404 Not Found');
    die("
        <div style='text-align: center; padding: 50px; font-family: sans-serif;'>
            <h1>Bon Tidak Ditemukan</h1>
            <p>Bon dengan UUID tersebut tidak ditemukan dalam database.</p>
        </div>
    ");
}

$bon_id = $bon_uuid['bon_history_id'];

// Fetch bon data
$stmt = $pdo->prepare("
    SELECT bh.*, u.username 
    FROM bon_history bh 
    LEFT JOIN users u ON bh.created_by = u.id 
    WHERE bh.id = ?
");
$stmt->execute([$bon_id]);
$bon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$bon) {
    header('HTTP/1.0 404 Not Found');
    die("
        <div style='text-align: center; padding: 50px; font-family: sans-serif;'>
            <h1>Bon Tidak Ditemukan</h1>
            <p>Data bon tidak ditemukan dalam database.</p>
        </div>
    ");
}

// Fetch bon detail
$stmt = $pdo->prepare("
    SELECT d.*, m.name, m.category 
    FROM bon_detail_history d 
    JOIN menu m ON d.menu_id = m.id 
    WHERE d.bon_history_id = ? 
    ORDER BY m.category, d.id
");
$stmt->execute([$bon_id]);
$details = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = array_reduce($details, function($carry, $item) {
    return $carry + $item['subtotal'];
}, 0);

// Payment method label function
function getPaymentMethodLabel($method, $bank = null) {
    $methods = ['cash' => 'CASH', 'qris' => 'QRIS', 'transfer' => 'TRANSFER'];
    $label = $methods[$method] ?? strtoupper($method);
    if ($method === 'qris' && $bank) {
        $label .= " ($bank)";
    }
    return $label;
}

// Set page title
$page_title = "Bon #" . e($bon_id);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-small { font-size: 12px !important; }
        }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from {opacity:0;transform:translateY(10px);} to {opacity:1;transform:translateY(0);} }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<!-- Loading Spinner -->
<div id="loading" class="fixed inset-0 bg-gradient-to-br from-yellow-100 via-red-200 to-orange-300 flex flex-col items-center justify-center z-50 fade-in">
    <!-- Logo atau ikon sambel uleg -->
    <div class="mb-4">
        <i class="fas fa-pepper-hot text-6xl text-red-600 animate-bounce"></i>
    </div>
    <!-- Spinner -->
    <div class="inline-block animate-spin rounded-full h-14 w-14 border-4 border-t-4 border-blue-500 border-t-yellow-400 mb-4"></div>
    <!-- Tulisan loading -->
    <p class="text-xl font-semibold text-gray-800">SAMBEL ULEG</p>
    <p class="mt-2 text-gray-700 text-sm animate-pulse">Memuat detail bon...</p>
</div>


    <div class="container mx-auto px-4 py-8 max-w-md print-small">
        <div class="bg-white rounded-lg shadow-lg overflow-hidden fade-in" id="content" style="display:none;">
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-6 text-center">
                <h1 class="text-2xl font-bold">SAMBEL ULEG KEBAGUSAN</h1>
                <p class="text-blue-100 text-sm mt-1">Detail Bon Transaksi</p>
<div class="mt-2 text-xs text-blue-200">
    <i class="fas fa-qrcode mr-1"></i>ID: 
    <span title="<?= e($uuid) ?>">
        <?= e(substr($uuid,0,8)) ?>...
    </span>
</div>

            </div>
            
            <!-- Bon Information -->
            <div class="p-4 border-b">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="text-center p-2 bg-gray-50 rounded">
                        <div class="text-gray-600 text-xs">NO. BON</div>
                        <div class="font-bold text-lg text-blue-600">#<?= e($bon_id) ?></div>
                    </div>
                    <div class="text-center p-2 bg-gray-50 rounded">
                        <div class="text-gray-600 text-xs">TANGGAL</div>
                        <div class="font-semibold"><?= date('d/m/Y', strtotime($bon['payment_date'])) ?></div>
                        <div class="text-xs text-gray-500"><?= date('H:i', strtotime($bon['payment_date'])) ?></div>
                    </div>
                    <div class="text-center p-2 bg-gray-50 rounded">
                        <div class="text-gray-600 text-xs">MEJA</div>
                        <div class="font-semibold text-lg"><?= e($bon['table_number']) ?></div>
                    </div>
                    <div class="text-center p-2 bg-gray-50 rounded">
                        <div class="text-gray-600 text-xs">KASIR</div>
                        <div class="font-semibold"><?= e($bon['username'] ?? '-') ?></div>
                    </div>
                </div>
            </div>

            <!-- QR Code Section -->
            <div class="p-4 bg-gradient-to-r from-gray-50 to-gray-100 border-b">
                <div class="text-center">
                    <div class="inline-block bg-white p-3 rounded-lg shadow">
                        <div id="qrcode" class="mx-auto"></div>
                    </div>
                    <p class="text-xs text-gray-600 mt-2">Scan QR code untuk mengakses bon ini</p>
                </div>
            </div>
            
            <!-- Items List -->
            <div class="p-4 border-b">
                <h3 class="font-semibold text-lg mb-3 flex items-center">
                    <i class="fas fa-receipt mr-2 text-blue-500"></i>Detail Pesanan
                </h3>
                <div class="space-y-3">
                    <?php
                    $current_category = '';
                    foreach ($details as $item):
                        if ($current_category !== $item['category']):
                            $current_category = $item['category'];
                    ?>
                        <div class="font-semibold text-gray-700 bg-gray-50 p-2 rounded border-l-4 border-blue-500">
                            <i class="fas fa-utensils mr-2 text-blue-500"></i><?= e($current_category) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex justify-between items-start p-2 hover:bg-gray-50 rounded transition-colors">
                        <div class="flex-1">
                            <div class="font-medium"><?= e($item['name']) ?></div>
                            <div class="text-sm text-gray-600">
                                Rp <?= number_format($item['price'], 0, ',', '.') ?> × <?= $item['quantity'] ?>
                            </div>
                        </div>
                        <div class="font-semibold text-blue-600">
                            Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Total Section -->
            <div class="p-4 bg-gradient-to-r from-green-50 to-green-100 border-b">
                <div class="flex justify-between items-center text-lg font-bold">
                    <span class="text-gray-700">TOTAL</span>
                    <span class="text-green-600 text-xl">Rp <?= number_format($total, 0, ',', '.') ?></span>
                </div>
            </div>
            
            <!-- Payment Information -->
            <div class="p-4">
                <h4 class="font-semibold mb-3 flex items-center">
                    <i class="fas fa-credit-card mr-2 text-purple-500"></i>Informasi Pembayaran
                </h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div class="text-center p-2 bg-purple-50 rounded">
                        <div class="text-purple-600 text-xs">METODE BAYAR</div>
                        <div class="font-semibold"><?= getPaymentMethodLabel($bon['payment_method'] ?? 'cash', $bon['payment_bank'] ?? null) ?></div>
                    </div>
                    <div class="text-center p-2 <?= $bon['status'] === 'completed' ? 'bg-green-50' : 'bg-yellow-50' ?> rounded">
                        <div class="text-xs <?= $bon['status'] === 'completed' ? 'text-green-600' : 'text-yellow-600' ?>">STATUS</div>
                        <div class="font-semibold <?= $bon['status'] === 'completed' ? 'text-green-600' : 'text-yellow-600' ?>">
                            <?= strtoupper($bon['status']) ?>
                        </div>
                    </div>
                    <?php if ($bon['payment_id']): ?>
                    <div class="col-span-2 p-2 bg-gray-50 rounded text-center">
                        <div class="text-gray-600 text-xs">ID PEMBAYARAN</div>
                        <div class="font-mono text-sm"><?= e($bon['payment_id']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer -->
            <div class="bg-gray-800 text-white p-4 text-center">
                <p class="text-sm">Terima kasih atas kunjungan Anda</p>
                <p class="text-xs text-gray-400 mt-1">Bon ini dapat diakses kembali dengan scan QR code</p>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="mt-6 flex gap-3 no-print fade-in" id="action-buttons" style="display:none;">
            <button onclick="window.print()" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-lg font-semibold transition-colors shadow-lg">
                <i class="fas fa-print mr-2"></i>Print Bon
            </button>
        </div>

        <!-- Error Message (hidden by default) -->
        <div id="error-message" class="hidden">
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                <strong class="font-bold">Error!</strong>
                <span class="block sm:inline">Gagal memuat detail bon.</span>
            </div>
        </div>
    </div>

    <!-- QR Code Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <script>
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('content').style.display = 'block';
                document.getElementById('action-buttons').style.display = 'flex';
                
                try {
                    new QRCode(document.getElementById("qrcode"), {
                        text: window.location.href,
                        width: 120,
                        height: 120,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });
                } catch (error) {
                    console.error('QR Code generation error:', error);
                }
            }, 500);
        });

        if (window.location.search.includes('print=1')) {
            window.addEventListener('load', function() {
                setTimeout(function() { window.print(); }, 1000);
            });
        }

        window.addEventListener('error', function() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('error-message').classList.remove('hidden');
        });
    </script>
</body>
</html>
