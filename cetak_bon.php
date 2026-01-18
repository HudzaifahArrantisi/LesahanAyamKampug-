<?php
require 'config.php';

// Helper: escape output
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Get bon_id from query parameter
$bon_id = $_GET['bon_id'] ?? null;

if (!$bon_id) {
    die("Bon ID tidak valid");
}

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
    die("Bon tidak ditemukan");
}

// Fetch bon details
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

// Payment method label
function getPaymentMethodLabel($method, $bank = null) {
    $methods = ['cash' => 'CASH', 'qris' => 'QRIS', 'transfer' => 'TRANSFER'];
    $label = $methods[$method] ?? strtoupper($method);
    if ($method === 'qris' && $bank) {
        $label .= " ($bank)";
    }
    return $label;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Bon #<?= e($bon_id) ?></title>
    <style>
        @media print {
            @page {
                margin: 0;
                size: 80mm auto;
            }
            body {
                margin: 0;
                padding: 2mm;
                font-family: 'Courier New', monospace;
                font-size: 10px;
                line-height: 1.2;
                width: 76mm;
            }
            .no-print {
                display: none !important;
            }
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.2;
            max-width: 76mm;
            margin: 0 auto;
            padding: 2mm;
            background: white;
            color: black;
        }
        
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 3mm;
            margin-bottom: 3mm;
        }
        
        .restaurant-name {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 1mm;
        }
        
        .bon-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2mm;
            font-size: 9px;
        }
        
        .items {
            margin: 3mm 0;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
        }
        
        .item-name {
            flex: 1;
        }
        
        .item-qty {
            width: 15mm;
            text-align: center;
        }
        
        .item-price {
            width: 20mm;
            text-align: right;
        }
        
        .category-header {
            font-weight: bold;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 1mm 0;
            margin: 2mm 0 1mm 0;
            text-align: center;
        }
        
        .total-section {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
            padding: 2mm 0;
            margin: 3mm 0;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
        }
        
        .payment-info {
            margin: 2mm 0;
            font-size: 9px;
        }
        
        .footer {
            text-align: center;
            margin-top: 4mm;
            border-top: 1px dashed #000;
            padding-top: 2mm;
            font-size: 8px;
        }
        
        .barcode {
            text-align: center;
            margin: 2mm 0;
            font-family: 'Libre Barcode 128', cursive;
            font-size: 20px;
        }
        
        .button-container {
            text-align: center;
            margin: 5mm 0;
        }
        
        .print-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 5mm 10mm;
            font-size: 12px;
            border-radius: 5mm;
            cursor: pointer;
        }
    </style>
    
    <!-- Barcode font -->
    <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&display=swap" rel="stylesheet">
</head>
<body>
    <div class="header">
        <div class="restaurant-name">RESTORAN KITA</div>
        <div>Jl. Contoh No. 123, Kota</div>
        <div>Telp: (021) 123-4567</div>
    </div>
    
    <div class="bon-info">
        <div>Bon: #<?= e($bon_id) ?></div>
        <div><?= date('d/m/Y H:i', strtotime($bon['payment_date'])) ?></div>
    </div>
    
    <div class="bon-info">
        <div>Meja: <?= e($bon['table_number']) ?></div>
        <div>Kasir: <?= e($bon['username'] ?? '-') ?></div>
    </div>
    
    <div class="items">
        <?php
        $current_category = '';
        foreach ($details as $item):
            if ($current_category !== $item['category']):
                $current_category = $item['category'];
        ?>
            <div class="category-header"><?= e($current_category) ?></div>
        <?php endif; ?>
        
        <div class="item-row">
            <div class="item-name"><?= e($item['name']) ?></div>
            <div class="item-qty"><?= e($item['quantity']) ?>x</div>
            <div class="item-price"><?= number_format($item['subtotal'], 0, ',', '.') ?></div>
        </div>
        
        <?php if ($item['quantity'] > 1): ?>
            <div class="item-row" style="font-size: 8px; margin-bottom: 0.5mm;">
                <div class="item-name"></div>
                <div class="item-qty">@<?= number_format($item['price'], 0, ',', '.') ?></div>
                <div class="item-price"></div>
            </div>
        <?php endif; ?>
        
        <?php endforeach; ?>
    </div>
    
    <div class="total-section">
        TOTAL: Rp <?= number_format($total, 0, ',', '.') ?>
    </div>
    
    <div class="payment-info">
        <div>Metode Bayar: <?= getPaymentMethodLabel($bon['payment_method'] ?? 'cash', $bon['payment_bank'] ?? null) ?></div>
        <?php if ($bon['payment_id']): ?>
            <div>ID Pembayaran: <?= e($bon['payment_id']) ?></div>
        <?php endif; ?>
    </div>
    
    <div class="barcode">
        *<?= e($bon_id) ?>*
    </div>
    
    <div class="footer">
        Terima kasih atas kunjungan Anda<br>
        Silakan datang kembali
    </div>
    
    <div class="button-container no-print">
        <button class="print-btn" onclick="window.print()">
            🖨️ CETAK BON
        </button>
    </div>

    <script>
        // Auto-print if opened in new window
        if (window.location.search.includes('autoprint=1')) {
            window.print();
        }
        
        // Close window after print (optional)
        window.onafterprint = function() {
            setTimeout(function() {
                window.close();
            }, 1000);
        };
    </script>
</body>
</html>