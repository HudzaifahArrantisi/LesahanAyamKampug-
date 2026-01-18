<?php
session_start();
require_once __DIR__ . '/../config.php';

// Cek UUID dari URL parameter atau dari path
if (isset($_GET['uuid']) && !empty($_GET['uuid'])) {
    $uuid = $_GET['uuid'];
} else {
    // Coba extract dari path jika menggunakan clean URL
    $request_uri = $_SERVER['REQUEST_URI'];
    if (preg_match('#/pesan/([a-f0-9\-]+)/?$#', $request_uri, $matches)) {
        $uuid = $matches[1];
    } else {
        // Jika tidak ada UUID, redirect ke generate_uuid
        header("Location: ../generate_uuid.php");
        exit;
    }
}

// Validasi UUID format
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid)) {
    header("Location: ../generate_uuid.php");
    exit;
}

// Cek status guest session dengan expires_at
$stmt = $pdo->prepare("SELECT * FROM guest_sessions WHERE uuid = ? AND status = 'active' AND expires_at > NOW()");
$stmt->execute([$uuid]);
$guest_session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$guest_session) {
    // Jika session expired atau tidak ada, buat session baru
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $stmt = $pdo->prepare("INSERT INTO guest_sessions (uuid, status, expires_at) VALUES (?, 'active', ?)");
    $stmt->execute([$uuid, $expires_at]);
}

// Ambil menu dari database
$stmt = $pdo->query("SELECT * FROM menu ORDER BY category, COALESCE(display_order, 999), name");
$menus = $stmt->fetchAll();
$menuByCategory = [];

foreach ($menus as $menu) {
    $category = $menu['category'];
    $menuByCategory[$category][] = $menu;
}

// Ambil pesanan yang sedang aktif untuk guest ini
$stmt = $pdo->prepare("SELECT o.* FROM orders o WHERE o.guest_uuid = ? AND o.status = 'pending'");
$stmt->execute([$uuid]);
$active_order = $stmt->fetch(PDO::FETCH_ASSOC);

$cart = [];
if ($active_order) {
    // Ambil items dari order yang aktif
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name as menu_name 
        FROM order_items oi 
        JOIN menu m ON oi.menu_id = m.id 
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$active_order['id']]);
    $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pesan Menu - Sambel Uleg</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        
        .scale-hover:hover { transform: scale(1.03); transition: transform 0.3s ease; }
        
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            display: none;
        }
        
        .cart-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 1001;
            width: 90%;
            max-width: 500px;
            max-height: 80vh;
            overflow-y: auto;
            display: none;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 text-gray-900 min-h-screen">

<!-- Loading Overlay -->
<div id="loading" class="fixed inset-0 bg-white flex items-center justify-center z-50">
    <div class="text-center">
        <i class="fas fa-pepper-hot text-6xl text-red-600 animate-bounce mb-4"></i>
        <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-t-4 border-blue-500 border-t-yellow-400 mb-4"></div>
        <p class="text-xl font-bold text-gray-800">SAMBEL ULEG</p>
        <p class="mt-2 text-gray-700 animate-pulse">Memuat halaman...</p>
    </div>
</div>

<!-- Navbar -->
<nav class="fixed top-0 left-0 right-0 bg-white shadow-lg z-50 flex justify-between items-center px-4 py-3">
    <div class="text-xl md:text-2xl font-serif text-orange-600">Pesan Menu</div>
    <div class="flex items-center gap-2">
        <button id="cart-toggle" class="cart-toggle relative bg-orange-600 text-white p-3 md:px-5 md:py-3 rounded-full hover:bg-orange-700 transition shadow-md">
            <i class="fas fa-shopping-cart text-sm md:text-base"></i>
            <span id="cart-badge" class="cart-badge absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full w-4 h-4 md:w-5 md:h-5 flex items-center justify-center"><?= count($cart) ?></span>
        </button>
    </div>
</nav>

<!-- Main Content -->
<div class="pt-20 md:pt-24 px-4 md:px-6 pb-28 md:pb-32">
    <div class="max-w-6xl mx-auto">
        <h2 class="text-2xl md:text-3xl font-serif mt-3 md:mt-5 font-bold mb-4 md:mb-6 text-orange-800">Daftar Menu Sambel Uleg</h2>

        <!-- Category Tabs -->
        <div class="category-tabs-container flex mb-6 md:mb-8 overflow-x-auto">
            <div class="flex gap-2 md:gap-3">
                <button class="category-tab active bg-orange-600 text-white px-4 py-2 md:px-5 md:py-2 rounded-full text-xs md:text-sm shadow-md" data-category="all">Semua</button>
                <?php foreach ($menuByCategory as $category => $items): ?>
                    <button class="category-tab bg-gray-200 text-gray-800 px-4 py-2 md:px-5 md:py-2 rounded-full text-xs md:text-sm shadow-sm transition" data-category="<?= htmlspecialchars($category) ?>">
                        <?= htmlspecialchars($category) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Menu Grid -->
        <div id="menu-grid" class="menu-grid grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            <?php foreach ($menuByCategory as $category => $items): ?>
                <?php foreach ($items as $menu): ?>
                    <div class="menu-card bg-white p-4 rounded-xl shadow-md hover:shadow-lg transition-all scale-hover animate-fade-in-up" data-category="<?= htmlspecialchars($category) ?>">
                        <img src="<?= htmlspecialchars($menu['image_url'] ?? 'placeholder.jpg') ?>" alt="<?= htmlspecialchars($menu['name']) ?>" class="w-full h-40 object-cover rounded-lg mb-3">
                        <h3 class="text-lg font-semibold text-gray-800"><?= htmlspecialchars($menu['name']) ?></h3>
                        <p class="text-sm text-gray-600 mb-2"><?= htmlspecialchars($menu['description'] ?? '') ?></p>
                        <div class="flex justify-between items-center">
                            <span class="text-md font-bold text-orange-600">Rp <?= number_format($menu['price'], 0, ',', '.') ?></span>
                            <div class="flex items-center gap-2">
                                <button class="minus-btn bg-gray-200 text-gray-700 p-1 rounded-full hover:bg-gray-300 transition" data-id="<?= $menu['id'] ?>"><i class="fas fa-minus text-xs"></i></button>
                                <span class="qty text-sm">0</span>
                                <button class="plus-btn bg-orange-600 text-white p-1 rounded-full hover:bg-orange-700 transition" data-id="<?= $menu['id'] ?>"><i class="fas fa-plus text-xs"></i></button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Cart Popup Overlay -->
<div id="cart-overlay" class="popup-overlay"></div>
<div id="cart-popup" class="cart-popup">
    <button id="close-cart" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
    <h3 class="text-xl font-bold text-orange-600 mb-4">Keranjang Pesanan</h3>
    <div id="cart-content" class="space-y-4 mb-6"></div>
    <div class="flex justify-between items-center font-bold text-lg">
        <span>Total</span>
        <span id="cart-total">Total: Rp 0</span>
    </div>
    <input type="text" id="table-number" placeholder="Masukkan Nomor Meja" class="w-full p-2 mt-4 border rounded">
    <button id="send-order" class="w-full bg-green-600 text-white p-2 mt-4 rounded-full hover:bg-green-700 transition">Kirim Pesanan</button>
</div>

<script>
    let cart = <?= json_encode($cart) ?>;
    const uuid = "<?= $uuid ?>";

    function updateCartBadge() {
        document.getElementById('cart-badge').textContent = cart.length;
    }

    function renderCart() {
        const cartContent = document.getElementById('cart-content');
        const cartTotal = document.getElementById('cart-total');
        let total = 0;

        let html = cart.map(item => {
            total += item.subtotal;
            return `
                <div class="flex justify-between items-center p-2 bg-gray-50 rounded">
                    <span>${item.menu_name} (x${item.quantity})</span>
                    <span>Rp ${item.subtotal.toLocaleString('id-ID')}</span>
                    <div>
                        <button class="qty-btn bg-gray-200 text-gray-700 p-1 rounded-full hover:bg-gray-300 transition" data-item-id="${item.id}" data-dir="-1"><i class="fas fa-minus text-xs"></i></button>
                        <button class="qty-btn bg-orange-600 text-white p-1 rounded-full hover:bg-orange-700 transition" data-item-id="${item.id}" data-dir="1"><i class="fas fa-plus text-xs"></i></button>
                        <button class="remove-btn bg-red-500 text-white p-1 rounded-full hover:bg-red-600 transition" data-item-id="${item.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        }).join('');

        cartContent.innerHTML = html;
        cartTotal.textContent = 'Total: Rp ' + total.toLocaleString('id-ID');

        // Attach event listeners to new buttons
        document.querySelectorAll('.qty-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const itemId = parseInt(this.dataset.itemId);
                const dir = parseInt(this.dataset.dir);
                updateCartItem(itemId, dir);
            });
        });

        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const itemId = parseInt(this.dataset.itemId);
                removeCartItem(itemId);
            });
        });
    }

    function updateCartItem(itemId, direction) {
        fetch('ajax_guest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_cart_item&item_id=${itemId}&direction=${direction}&uuid=${uuid}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cart = data.cart;
                updateCartBadge();
                renderCart();
            }
        });
    }

    function removeCartItem(itemId) {
        fetch('ajax_guest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=remove_cart_item&item_id=${itemId}&uuid=${uuid}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cart = data.cart;
                updateCartBadge();
                renderCart();
            }
        });
    }

    function toggleCart(open) {
        const overlay = document.getElementById('cart-overlay');
        const popup = document.getElementById('cart-popup');
        
        if (open) {
            overlay.style.display = 'block';
            popup.style.display = 'block';
        } else {
            overlay.style.display = 'none';
            popup.style.display = 'none';
        }
    }

    // Event listeners
    document.getElementById('cart-toggle').addEventListener('click', () => toggleCart(true));
    document.getElementById('close-cart').addEventListener('click', () => toggleCart(false));
    document.getElementById('cart-overlay').addEventListener('click', () => toggleCart(false));

    document.querySelectorAll('.plus-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const menuId = parseInt(this.dataset.id);
            addToCart(menuId, 1);
        });
    });

    document.querySelectorAll('.minus-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const menuId = parseInt(this.dataset.id);
            addToCart(menuId, -1);
        });
    });

    function addToCart(menuId, quantityChange) {
        fetch('ajax_guest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=add_to_cart&menu_id=${menuId}&quantity_change=${quantityChange}&uuid=${uuid}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                cart = data.cart;
                updateCartBadge();
                renderCart();
            }
        });
    }

    document.getElementById('send-order').addEventListener('click', function() {
        const tableNumber = document.getElementById('table-number').value.trim();
        
        if (!tableNumber) {
            alert('Masukkan nomor meja terlebih dahulu');
            return;
        }

        if (cart.length === 0) {
            alert('Keranjang masih kosong');
            return;
        }

        // Update table number
        fetch('ajax_guest.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_table&table_number=${tableNumber}&uuid=${uuid}`
        })
        .then(() => {
            alert('Pesanan berhasil dikirim ke kasir!');
            toggleCart(false);
        });
    });

    // Category filter
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.category-tab').forEach(t => {
                t.classList.remove('bg-orange-600', 'text-white');
                t.classList.add('bg-gray-200', 'text-gray-800');
            });
            this.classList.add('bg-orange-600', 'text-white');
            this.classList.remove('bg-gray-200', 'text-gray-800');
            
            const category = this.dataset.category;
            document.querySelectorAll('.menu-card').forEach(card => {
                if (category === 'all' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    });

    // Hide loading
    window.addEventListener('load', function() {
        setTimeout(() => {
            document.getElementById('loading').style.display = 'none';
        }, 500);
    });

    // Initial render
    updateCartBadge();
    renderCart();
</script>
</body>
</html>