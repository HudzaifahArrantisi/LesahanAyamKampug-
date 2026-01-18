<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// === Ambil bon baik dari admin maupun guest ===
$stmt = $pdo->prepare("
    SELECT b.*, 
           COALESCE(o.order_no, CONCAT('ADM-', b.id)) as order_number,
           COALESCE(b.source, 'admin') as order_source
    FROM bon b 
    LEFT JOIN orders o 
           ON b.guest_uuid = o.guest_uuid 
          AND o.status = 'pending'
    WHERE b.status = 'active' 
      AND b.archived = 0
    ORDER BY b.id ASC
");
$stmt->execute();
$bons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// === Ambil detail & total masing-masing bon ===
$bonDetails = [];
$bonTotals  = [];

foreach ($bons as $bon) {
    if ($bon['order_source'] === 'guest') {
        // === Guest orders (ambil dari order_items & orders) ===
        $stmt = $pdo->prepare("
            SELECT oi.id, oi.menu_id, m.name as menu_name, oi.quantity, oi.price, oi.subtotal
            FROM order_items oi
            JOIN menu m ON oi.menu_id = m.id
            JOIN orders o ON oi.order_id = o.id
            WHERE o.guest_uuid = ? 
              AND o.status = 'pending'
        ");
        $stmt->execute([$bon['guest_uuid']]);
        $bonDetails[$bon['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("
            SELECT total_amount 
            FROM orders 
            WHERE guest_uuid = ? 
              AND status = 'pending'
        ");
        $stmt->execute([$bon['guest_uuid']]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC);
        $bonTotals[$bon['id']] = [
            'total_amount' => $total['total_amount'] ?? 0
        ];
    } else {
        // === Admin orders (pakai logic lama: bon_detail) ===
        $bonDetails[$bon['id']] = getBonDetails($bon['id']);
        $totals = calculateBonTotals($bon['id']);
        $bonTotals[$bon['id']] = [
            'total_amount' => isset($totals['total_amount']) ? floatval($totals['total_amount']) : 0
        ];
    }
}

// === Ambil daftar menu per kategori (untuk tampilan pesan/menu) ===
$menusByCategory = [];
$stmtMenus = $pdo->prepare("SELECT * FROM menu ORDER BY category ASC, name ASC");
$stmtMenus->execute();
$allMenus = $stmtMenus->fetchAll(PDO::FETCH_ASSOC);
foreach ($allMenus as $menu) {
    $category = $menu['category'] ?? 'Uncategorized';
    $menusByCategory[$category][] = $menu;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Bon - Restaurant Kasir</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out; }
        .animate-pulse { animation: pulse 1s ease-in-out infinite; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #EF4444;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
        .new-item-highlight {
            animation: highlight 2s ease-out;
        }
        @keyframes highlight {
            0% { background-color: #fef08a; }
            100% { background-color: transparent; }
        }
    </style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 text-gray-900 min-h-screen">
      <!-- Loading Overlay -->
<div id="loading" class="fixed inset-0 bg-white flex items-center justify-center z-50 transition-opacity duration-500">
    <div class="text-center">
        <i class="fas fa-pepper-hot text-6xl text-red-600 animate-bounce mb-4"></i>
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-t-4 border-blue-500 border-t-yellow-400 mb-4"></div>
        <p class="text-xl font-bold text-gray-800">SAMBEL ULEG</p>
        <p class="mt-2 text-gray-700 animate-pulse">Memuat halaman...</p>
    </div>
</div>    
<script>
window.addEventListener('load', function() {
    const loading = document.getElementById('loading');
    const content = document.getElementById('content');

    // Tunggu sebentar biar efek smooth
    setTimeout(() => {
        // sembunyikan loading
        loading.classList.add('opacity-0');
        setTimeout(() => loading.style.display = 'none', 500);

        // tampilkan konten
        content.classList.remove('opacity-0');
        content.classList.add('opacity-100');
    }, 500); // bisa ganti 500ms sesuai kecepatan loading
});
</script>
<!-- Navbar -->
<nav class="fixed top-0 left-0 right-0 bg-white shadow-lg z-50 flex justify-between items-center px-6 py-4">
    <div class="text-2xl font-serif text-indigo-800">Bon kasir</div>
    <div class="flex gap-4">
        <a href="menu.php" class="text-sm bg-indigo-100 hover:bg-indigo-200 px-4 py-2 rounded-full transition shadow-sm">Menu</a>
        <a href="riwayat_bon.php" class="text-sm bg-indigo-100 hover:bg-indigo-200 px-4 py-2 rounded-full transition shadow-sm">Riwayat</a>
    </div>
</nav>

<!-- Main Content -->
<div class="pt-24 px-6 pb-32">
    <div class="max-w-7xl mx-auto">

        <h2 class="text-3xl font-serif font-bold mb-6 mt-5 text-indigo-900">Bon Aktif</h2>

        <!-- Bon Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="bon-grid">
            <?php foreach ($bons as $bon): ?>
            <div class="bg-white rounded-2xl shadow-xl hover:shadow-2xl transition-all duration-300 p-6 flex flex-col animate-fade-in-up" id="bon-card-<?= $bon['id'] ?>" data-bon-id="<?= $bon['id'] ?>">
                <!-- Header -->
                <div class="flex justify-between items-center mb-4">
                    <div class="bg-indigo-200 text-gray-600 font-bold ">No Meja <?= htmlspecialchars($bon['table_number']) ?></div>
                </div>

                <!-- Items -->
                <div class="flex-1 mb-6 max-h-60 overflow-y-auto">
                    <table class="w-full text-sm" id="items-table-<?= $bon['id'] ?>">
                        <tbody>
                            <?php foreach ($bonDetails[$bon['id']] as $item): ?>
                            <tr class="border-b border-gray-200 item-row" data-detail-id="<?= $item['id'] ?>" data-menu-id="<?= $item['menu_id'] ?>">
                                <td class="py-3 text-gray-800"><?= htmlspecialchars($item['menu_name']) ?></td>
                                <td class="py-3 text-center">
                                    <button class="qty-btn minus bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded-full transition" data-id="<?= $item['id'] ?>" data-bon-id="<?= $bon['id'] ?>">-</button>
                                    <span class="mx-3 text-gray-800 qty-span"><?= $item['quantity'] ?></span>
                                    <button class="qty-btn plus bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded-full transition" data-id="<?= $item['id'] ?>" data-bon-id="<?= $bon['id'] ?>">+</button>
                                </td>
                                <td class="py-3 text-right text-gray-800 subtotal-span">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></td>
                                <td class="py-3 text-center">
                                    <button class="delete-btn text-red-600 hover:text-red-800 transition" data-id="<?= $item['id'] ?>" data-bon-id="<?= $bon['id'] ?>"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Total -->
                <div class="flex justify-between items-center mb-6 font-serif font-bold text-xl text-indigo-900">
                    <span>Total:</span>
                    <span class="text-indigo-700 total-span">Rp <?= number_format($bonTotals[$bon['id']]['total_amount'], 0, ',', '.') ?></span>
                </div>

                <!-- Actions -->
                <div class="flex gap-4">
                    <button class="flex-1 bg-indigo-700 text-white py-3 rounded-full hover:bg-indigo-800 transition shadow-md" onclick="showPaymentModal(<?= $bon['id'] ?>)">
                        <i class="fas fa-money-bill-wave mr-2"></i>Bayar
                    </button>
                    <button class="flex-1 bg-indigo-600 text-white py-3 rounded-full hover:bg-indigo-700 transition shadow-md" onclick="showAddMenuModal(<?= $bon['id'] ?>)">
                        <i class="fas fa-plus mr-2"></i>Tambah
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>

<!-- Payment Modal -->
<div id="paymentModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-2xl p-8 w-11/12 max-w-md shadow-2xl transform scale-95 transition-transform duration-300">
        <h3 class="text-2xl font-serif font-bold mb-6 text-indigo-900">Pilih Metode Pembayaran</h3>
        <div class="flex gap-4">
            <button onclick="handleCash()" class="flex-1 bg-green-700 text-white py-4 rounded-full hover:bg-green-800 transition shadow-md">
                <i class="fas fa-money-bill-wave mr-2"></i>Cash
            </button>
            <button onclick="handleQris()" class="flex-1 bg-blue-700 text-white py-4 rounded-full hover:bg-blue-800 transition shadow-md">
                <i class="fas fa-qrcode mr-2"></i>QRIS
            </button>
        </div>
        <button onclick="closePaymentModal()" class="w-full mt-6 bg-gray-200 text-gray-800 py-3 rounded-full hover:bg-gray-300 transition shadow-sm">Batal</button>
    </div>
</div>

<!-- Cash Modal -->
<div id="cashModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-2xl p-8 w-11/12 max-w-md shadow-2xl transform scale-95 transition-transform duration-300">
        <h3 class="text-2xl font-serif font-bold mb-4 text-indigo-900">Konfirmasi Pembayaran Cash</h3>
        <p class="text-sm text-gray-600 mb-6" id="cashText"></p>
        <div class="flex gap-4">
            <button onclick="confirmCash()" class="flex-1 bg-green-700 text-white py-3 rounded-full hover:bg-green-800 transition shadow-md">Ya</button>
            <button onclick="closeCashModal()" class="flex-1 bg-gray-200 text-gray-800 py-3 rounded-full hover:bg-gray-300 transition shadow-sm">Batal</button>
        </div>
    </div>
</div>

<!-- QRIS Modal -->
<div id="qrisModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-2xl p-8 w-11/12 max-w-md text-center shadow-2xl transform scale-95 transition-transform duration-300">
        <h3 class="text-2xl font-serif font-bold mb-6 text-indigo-900">Pembayaran QRIS</h3>
        <div id="qrisContent">
            <p class="text-sm text-gray-600">Memuat kode QR...</p>
        </div>
        <button onclick="closeQrisModal()" class="w-full mt-6 bg-gray-200 text-gray-800 py-3 rounded-full hover:bg-gray-300 transition shadow-sm">Tutup</button>
    </div>
</div>

<!-- Add Menu Modal -->
<div id="addMenuModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-2xl p-8 w-11/12 max-w-4xl max-h-[80vh] overflow-y-auto shadow-2xl transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-serif font-bold text-indigo-900">Tambah Menu</h3>
            <button onclick="closeAddMenuModal()" class="text-gray-500 hover:text-black transition"><i class="fas fa-times text-xl"></i></button>
        </div>
                <!-- Tombol Tambahkan ke Bon -->
        <button onclick="addAllItemsToBon()" class="w-full mt-6 bg-green-600 text-white py-3 rounded-full hover:bg-green-700 transition shadow-md">
            <i class="fas fa-paper-plane mr-2"></i>Tambahkan ke Bon
        </button>
        
        <!-- Menu List -->
        <div id="menuList">
            <?php foreach ($menusByCategory as $category => $menus): ?>
                <div class="mb-6 mt-8">
                    <h4 class="font-serif font-semibold text-lg mb-4 text-indigo-800"><?= htmlspecialchars($category) ?></h4>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                        <?php foreach ($menus as $menu): ?>
                            <div class="bg-gray-50 hover:bg-gray-100 p-4 rounded-xl text-left text-sm shadow-sm transition add-menu-btn relative" data-menu-id="<?= $menu['id'] ?>" data-menu-name="<?= htmlspecialchars($menu['name']) ?>" data-menu-price="<?= $menu['price'] ?>">
                                <span class="font-medium text-gray-800"><?= htmlspecialchars($menu['name']) ?></span><br>
                                <span class="text-indigo-700 font-bold">Rp <?= number_format($menu['price'], 0, ',', '.') ?></span>
                                
                                <div class="mt-2 flex items-center justify-between">
                                    <button class="minus-btn bg-indigo-100 hover:bg-indigo-200 px-2 py-1 rounded-full transition text-xs" onclick="decreaseQuantity(<?= $menu['id'] ?>)">-</button>
                                    <span id="quantity-<?= $menu['id'] ?>" class="mx-2 text-gray-800">0</span>
                                    <button class="plus-btn bg-indigo-100 hover:bg-indigo-200 px-2 py-1 rounded-full transition text-xs" onclick="increaseQuantity(<?= $menu['id'] ?>)">+</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>


    </div>
</div>

<!-- Success Popup Modal -->
<div id="successPopupModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
  <div class="bg-white rounded-2xl p-6 max-w-sm w-11/12 text-center shadow-xl transform scale-95 transition-transform duration-300">
    <div class="text-5xl text-green-600 mb-4"><i class="fas fa-check-circle"></i></div>
    <h3 class="text-lg font-bold text-gray-800 mb-2">Berhasil!</h3>
    <p class="text-sm text-gray-600 mb-4">Item berhasil ditambahkan ke bon.</p>
    <button onclick="closeSuccessPopup()" class="bg-indigo-600 text-white px-4 py-2 rounded-full hover:bg-indigo-700 transition">Tutup</button>
  </div>
</div>

<!-- Success Modal -->
<div id="successModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-2xl p-8 max-w-md w-11/12 text-center shadow-2xl transform scale-95 transition-transform duration-300 animate-pulse">
        <div class="text-6xl text-green-600 mb-6"><i class="fas fa-check-circle"></i></div>
        <h3 class="text-2xl font-serif font-bold mb-4 text-indigo-900">Pembayaran Berhasil</h3>
        <p class="text-sm text-gray-600 mb-6">Terima kasih! Pesanan telah diselesaikan.</p>
        <button onclick="closeSuccessModal()" class="bg-indigo-700 text-white px-6 py-3 rounded-full hover:bg-indigo-800 transition shadow-md">Tutup</button>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-60 hidden flex items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white rounded-2xl p-8 w-11/12 max-w-md shadow-2xl transform scale-95 transition-transform duration-300 animate-fade-in-up">
        <h3 class="text-2xl font-serif font-bold mb-4 text-indigo-900">Hapus Menu?</h3>
        <p class="text-sm text-gray-600 mb-6">Apakah Anda yakin ingin menghapus item ini?</p>
        <div class="flex gap-4">
            <button id="confirmDeleteBtn" class="flex-1 bg-red-700 text-white py-3 rounded-full hover:bg-red-800 transition shadow-md">Ya, Hapus</button>
            <button onclick="closeDeleteModal()" class="flex-1 bg-gray-200 text-gray-800 py-3 rounded-full hover:bg-gray-300 transition shadow-sm">Batal</button>
        </div>
    </div>
</div>

<!-- Bottom Nav -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t flex justify-around py-3 text-sm text-gray-600 z-50 shadow-lg">
    <a href="menu.php" class="flex flex-col items-center gap-1">
        <i class="fas fa-utensils text-xl"></i> Menu
    </a>
    <a href="bon.php" class="flex flex-col items-center gap-1 text-indigo-700 font-bold">
        <i class="fas fa-receipt text-xl"></i> Bon
    </a>
</div>

<script>
    let selectedBonId = null;
    let pendingDeleteDetailId = null;
    let pendingDeleteBonId = null;
    let selectedItems = {};

    function formatCurrency(amount) {
        if (isNaN(amount) || amount === null || amount === undefined) return 'Rp 0';
        return 'Rp ' + Number(amount).toLocaleString('id-ID');
    }

    function parseCurrency(currencyString) {
        if (!currencyString) return 0;
        const numericString = currencyString.replace(/[^\d,.-]/g, '');
        const normalizedString = numericString.replace(',', '.');
        const value = parseFloat(normalizedString);
        return isNaN(value) ? 0 : value;
    }

    function showPaymentModal(bonId) {
        selectedBonId = bonId;
        document.getElementById('paymentModal').classList.remove('hidden');
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').classList.add('hidden');
    }

    function handleCash() {
        closePaymentModal();
        const card = document.getElementById(`bon-card-${selectedBonId}`);
        const total = card.querySelector('.total-span').textContent;
        document.getElementById('cashText').textContent = `Total pembayaran: ${total}`;
        document.getElementById('cashModal').classList.remove('hidden');
    }

    function confirmCash() {
        fetch('ajax_bon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=cash_payment&bon_id=${selectedBonId}`
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    document.getElementById(`bon-card-${selectedBonId}`).remove();
                    closeCashModal();
                    document.getElementById('successModal').classList.remove('hidden');
                } else {
                    alert('Gagal: ' + (data.message || 'Terjadi kesalahan'));
                }
            } catch (e) {
                console.error('JSON Parse Error:', text);
                alert('Gagal memproses respons server');
            }
        })
        .catch(err => {
            alert('Gagal: ' + err.message);
        });
    }

    function closeCashModal() {
        document.getElementById('cashModal').classList.add('hidden');
    }

   function handleQris() {
    closePaymentModal();
    document.getElementById('qrisModal').classList.remove('hidden');
    document.getElementById('qrisContent').innerHTML = '<p class="text-sm text-gray-600">Memuat kode QR...</p>';

    fetch('ajax_bon.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=create_qris&bon_id=${selectedBonId}`
    })
    .then(async (res) => {
        if (!res.ok) {
            const errorText = await res.text();
            throw new Error(`HTTP ${res.status}: ${errorText}`);
        }
        return res.json();
    })
    .then(data => {
        if (data.success) {
            let qrContent = '';
            
            if (data.qr_image_url) {
                // Jika ada URL gambar QR
                qrContent = `
                    <img src="${data.qr_image_url}" alt="QRIS" class="mx-auto mb-4 rounded-lg shadow-md w-48 h-48">
                    <p class="text-xs text-gray-500 mb-2">Scan kode QR untuk membayar</p>
                `;
            } else if (data.qr_code) {
                // Jika ada string QR code (base64)
                qrContent = `
                    <img src="data:image/png;base64,${data.qr_code}" alt="QRIS" class="mx-auto mb-4 rounded-lg shadow-md w-48 h-48">
                    <p class="text-xs text-gray-500 mb-2">Scan kode QR untuk membayar</p>
                `;
            } else {
                qrContent = '<p class="text-red-500">QR code tidak tersedia</p>';
            }
            
            qrContent += `
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 mt-4">
                    <p class="text-xs text-yellow-800 text-center">
                        <i class="fas fa-info-circle mr-1"></i>
                        Total: <strong>${document.querySelector(`#bon-card-${selectedBonId} .total-span`).textContent}</strong>
                    </p>
                </div>
            `;
            
            document.getElementById('qrisContent').innerHTML = qrContent;
            
            // Mulai polling status pembayaran
            pollQrisStatus(selectedBonId, data.order_id);
        } else {
            document.getElementById('qrisContent').innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-500 text-center">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Gagal memuat QRIS: ${data.message || 'Unknown error'}
                    </p>
                </div>
            `;
        }
    })
    .catch(err => {
        console.error('QRIS Error:', err);
        document.getElementById('qrisContent').innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-red-500 text-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Error: ${err.message}
                </p>
            </div>
        `;
    });
}

let qrisInterval;
function pollQrisStatus(bonId, orderId) {
    // Clear existing interval
    if (qrisInterval) clearInterval(qrisInterval);
    
    qrisInterval = setInterval(() => {
        fetch(`ajax_bon.php?action=check_txn_status&local_txn_id=${bonId}&order_id=${orderId}`)
            .then(res => res.json())
            .then(data => {
                if (data.success && data.status === 'paid') {
                    clearInterval(qrisInterval);
                    document.getElementById(`bon-card-${bonId}`).remove();
                    closeQrisModal();
                    document.getElementById('successModal').classList.remove('hidden');
                }
            })
            .catch(err => {
                console.error('Polling error:', err);
            });
    }, 3000); // Poll every 3 seconds
}

function closeQrisModal() {
    if (qrisInterval) clearInterval(qrisInterval);
    document.getElementById('qrisModal').classList.add('hidden');
}
    function showAddMenuModal(bonId) {
        selectedBonId = bonId;
        selectedItems = {};
        document.querySelectorAll('[id^="quantity-"]').forEach(el => {
            el.textContent = '0';
        });
        document.getElementById('addMenuModal').classList.remove('hidden');
    }

    function closeAddMenuModal() {
        document.getElementById('addMenuModal').classList.add('hidden');
    }

    function increaseQuantity(menuId) {
        const el = document.getElementById(`quantity-${menuId}`);
        let qty = parseInt(el.textContent) || 0;
        el.textContent = qty + 1;
        selectedItems[menuId] = (selectedItems[menuId] || 0) + 1;
    }

    function decreaseQuantity(menuId) {
        const el = document.getElementById(`quantity-${menuId}`);
        let qty = parseInt(el.textContent) || 0;
        if (qty > 0) {
            el.textContent = qty - 1;
            selectedItems[menuId] = (selectedItems[menuId] || 0) - 1;
            if (selectedItems[menuId] <= 0) delete selectedItems[menuId];
        }
    }

    function addSingleItem(menuId) {
        const qty = parseInt(document.getElementById(`quantity-${menuId}`).textContent) || 0;
        if (qty <= 0) {
            alert('Jumlah harus lebih dari 0');
            return;
        }

        fetch('ajax_bon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add_item&bon_id=${selectedBonId}&menu_id=${menuId}&quantity=${qty}`
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    updateBonCard(selectedBonId);
                    closeAddMenuModal();
                    showSuccessPopup();
                } else {
                    alert('Gagal menambahkan menu: ' + (data.message || 'Terjadi kesalahan'));
                }
            } catch (e) {
                console.error('JSON Parse Error:', text);
                alert('Gagal menambahkan menu: Format respons tidak valid');
            }
        })
        .catch(err => {
            alert('Gagal menambahkan menu: ' + err.message);
        });
    }

    function addAllItemsToBon() {
        const items = [];
        for (let menuId in selectedItems) {
            const qty = selectedItems[menuId];
            if (qty > 0) items.push({ menu_id: menuId, quantity: qty });
        }

        if (items.length === 0) {
            alert('Belum ada item yang dipilih.');
            return;
        }

        fetch('ajax_bon.php', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add_multiple_items&bon_id=${selectedBonId}&items=${encodeURIComponent(JSON.stringify(items))}`
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    updateBonCard(selectedBonId);
                    closeAddMenuModal();
                    showSuccessPopup();
                    selectedItems = {};
                } else {
                    alert('Gagal menambahkan item: ' + (data.message || 'Terjadi kesalahan'));
                }
            } catch (e) {
                console.error('JSON Parse Error:', text);
                alert('Gagal menambahkan item: Format respons tidak valid');
            }
        })
        .catch(err => {
            alert('Error: ' + err.message);
        });
    }

    function updateBonCard(bonId) {
        fetch(`ajax_bon.php?action=get_bon&bon_id=${bonId}`)
            .then(res => {
                if (!res.ok) {
                    throw new Error('Network response was not ok');
                }
                return res.text();
            })
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        const tbody = document.querySelector(`#items-table-${bonId} tbody`);
                        tbody.innerHTML = '';
                        if (data.details && data.details.length > 0) {
                            data.details.forEach(item => {
                                const row = document.createElement('tr');
                                row.className = 'border-b border-gray-200 item-row';
                                row.dataset.detailId = item.id;
                                row.dataset.menuId = item.menu_id;
                                row.innerHTML = `
                                    <td class="py-3 text-gray-800">${item.menu_name || 'Unknown Item'}</td>
                                    <td class="py-3 text-center">
                                        <button class="qty-btn minus bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded-full transition" data-id="${item.id}" data-bon-id="${bonId}">-</button>
                                        <span class="mx-3 text-gray-800 qty-span">${item.quantity}</span>
                                        <button class="qty-btn plus bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded-full transition" data-id="${item.id}" data-bon-id="${bonId}">+</button>
                                    </td>
                                    <td class="py-3 text-right text-gray-800 subtotal-span">${formatCurrency(item.subtotal)}</td>
                                    <td class="py-3 text-center">
                                        <button class="delete-btn text-red-600 hover:text-red-800 transition" data-id="${item.id}" data-bon-id="${bonId}"><i class="fas fa-trash"></i></button>
                                    </td>
                                `;
                                tbody.appendChild(row);
                            });
                        } else {
                            tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-gray-500">Tidak ada item</td></tr>';
                        }
                        const totalEl = document.querySelector(`#bon-card-${bonId} .total-span`);
                        if (totalEl) totalEl.textContent = formatCurrency(data.totals.total_amount);
                        attachEventListeners();
                    } else {
                        console.error('Failed to update bon card:', data.message);
                    }
                } catch (e) {
                    console.error('JSON Parse Error:', text);
                    console.error('Failed to update bon card: Invalid JSON response');
                }
            })
            .catch(err => {
                console.error('Error updating bon card:', err);
            });
    }

    function showSuccessPopup() {
        document.getElementById('successPopupModal').classList.remove('hidden');
        setTimeout(() => {
            closeSuccessPopup();
        }, 2000);
    }

    function closeSuccessPopup() {
        document.getElementById('successPopupModal').classList.add('hidden');
    }

    function closeSuccessModal() {
        document.getElementById('successModal').classList.add('hidden');
    }

    function showDeleteModal(detailId, bonId) {
        pendingDeleteDetailId = detailId;
        pendingDeleteBonId = bonId;
        document.getElementById('deleteModal').classList.remove('hidden');
    }

    function closeDeleteModal() {
        pendingDeleteDetailId = null;
        pendingDeleteBonId = null;
        document.getElementById('deleteModal').classList.add('hidden');
    }

    function confirmDelete() {
        if (!pendingDeleteDetailId || !pendingDeleteBonId) return;
        
        fetch('ajax_bon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=delete_item&detail_id=${pendingDeleteDetailId}&bon_id=${pendingDeleteBonId}`
        })
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok');
            }
            return res.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success) {
                    if (data.totals && data.totals.empty) {
                        document.getElementById(`bon-card-${pendingDeleteBonId}`).remove();
                    } else {
                        updateBonCard(pendingDeleteBonId);
                    }
                    closeDeleteModal();
                } else {
                    alert('Gagal menghapus item: ' + (data.message || 'Terjadi kesalahan'));
                }
            } catch (e) {
                console.error('JSON Parse Error:', text);
                alert('Gagal menghapus item: Format respons tidak valid');
            }
        })
        .catch(err => {
            alert('Gagal menghapus item: ' + err.message);
        });
    }

    function attachEventListeners() {
        document.querySelectorAll('.qty-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const detailId = this.dataset.id;
                const bonId = this.dataset.bonId;
                const isPlus = this.classList.contains('plus');
                
                fetch('ajax_bon.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=update_qty&detail_id=${detailId}&delta=${isPlus ? 1 : -1}&bon_id=${bonId}`
                })
                .then(res => {
                    if (!res.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return res.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.success) {
                            updateBonCard(bonId);
                        } else {
                            alert('Gagal mengupdate jumlah: ' + (data.message || 'Terjadi kesalahan'));
                        }
                    } catch (e) {
                        console.error('JSON Parse Error:', text);
                        alert('Gagal mengupdate jumlah: Format respons tidak valid');
                    }
                })
                .catch(err => {
                    alert('Gagal mengupdate jumlah: ' + err.message);
                });
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const detailId = this.dataset.id;
                const bonId = this.dataset.bonId;
                showDeleteModal(detailId, bonId);
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        attachEventListeners();
        document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);
    });
</script>
</body>
</html>