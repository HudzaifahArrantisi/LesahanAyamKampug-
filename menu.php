<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}
require_once __DIR__ . '/config.php';
// Ganti query yang mengambil data menu dengan:
$stmt = $pdo->query("SELECT * FROM menu ORDER BY category, COALESCE(display_order, 999), name");$menus = $stmt->fetchAll();

$menuByCategory = [];
foreach ($menus as $menu) {
    $category = $menu['category'];
    $menuByCategory[$category][] = $menu;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Menu - Restaurant Kasir</title>
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
        @keyframes popIn {
            0% { opacity: 0; transform: translate(-50%, -50%) scale(0.5); }
            70% { transform: translate(-50%, -50%) scale(1.05); }
            100% { opacity: 1; transform: translate(-50%, -50%) scale(1); }
        }
        @keyframes confetti {
            0% { transform: translateY(0) rotate(0); opacity: 1; }
            100% { transform: translateY(100vh) rotate(720deg); opacity: 0; }
        }
        .animate-fade-in-up { animation: fadeInUp 0.5s ease-out; }
        .animate-pulse { animation: pulse 1s ease-in-out infinite; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .scale-hover:hover { transform: scale(1.03); transition: transform 0.3s ease; }
        
        /* Popup styles */
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
        
        .success-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            z-index: 1002;
            text-align: center;
            max-width: 90%;
            width: 300px;
            display: none;
        }
        
        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            opacity: 0;
            z-index: 1003;
        }

        /* Responsive improvements */
        @media (max-width: 640px) {
            .nav-button {
                padding: 0.5rem 0.8rem;
                font-size: 0.75rem;
            }
            
            .menu-card {
                margin-bottom: 0.5rem;
            }
            
            .qty-controls {
                width: 100%;
            }
            
            .category-tabs-container {
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 0.5rem;
                -webkit-overflow-scrolling: touch;
            }
            
            .category-tabs-container::-webkit-scrollbar {
                display: none;
            }
        }

        @media (max-width: 380px) {
            .nav-button {
                padding: 0.4rem 0.6rem;
                font-size: 0.7rem;
            }
            
            .menu-grid {
                grid-template-columns: 1fr !important;
                gap: 0.8rem;
            }
            
            .cart-toggle {
                padding: 0.6rem;
            }
            
            .cart-badge {
                width: 1rem;
                height: 1rem;
                font-size: 0.7rem;
            }
        }

        @media (min-width: 641px) and (max-width: 1024px) {
            .menu-grid {
                grid-template-columns: repeat(3, 1fr) !important;
            }
        }

        /* Prevent zoom on input focus on mobile */
        @media (max-width: 480px) {
            input, select, textarea {
                font-size: 16px !important;
            }
        }

        /* Improved touch targets for mobile */
        .add-btn, .nav-button, .category-tab, .qty-btn {
            min-height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Bottom nav improvements */
        .bottom-nav {
            padding: 0.5rem 0;
        }

        .bottom-nav a {
            padding: 0.5rem;
            min-width: 60px;
        }

        /* Quantity controls */
        .qty-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .qty-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #f3f4f6;
            border: none;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }

        .qty-btn:hover {
            background-color: #e5e7eb;
        }

        .qty-display {
            font-weight: bold;
            min-width: 30px;
            text-align: center;
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
<nav class="fixed top-0 left-0 right-0 bg-white shadow-lg z-50 flex justify-between items-center px-4 py-3">
    <div class="text-xl md:text-2xl font-serif text-indigo-800">Menu</div>
    <div class="flex items-center gap-2">
        <a href="riwayat_bon.php" class="nav-button text-xs md:text-sm bg-indigo-100 hover:bg-indigo-200 px-3 md:px-4 py-2 rounded-full transition shadow-sm">Riwayat</a>
            <a href="logout.php" class="nav-button text-xs md:text-sm bg-red-600 hover:bg-red-700 text-white px-3 md:px-4 py-2 rounded-full transition shadow-sm">Logout</a>        <button id="cart-toggle" class="cart-toggle relative bg-indigo-700 text-white p-3 md:px-5 md:py-3 rounded-full hover:bg-indigo-800 transition shadow-md">
            <i class="fas fa-shopping-cart text-sm md:text-base"></i>
            <span id="cart-badge" class="cart-badge absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full w-4 h-4 md:w-5 md:h-5 flex items-center justify-center">0</span>
        </button>
    </div>
</nav>

<!-- Main Content -->
<div class="pt-20 md:pt-24 px-4 md:px-6 pb-28 md:pb-32">
    <div class="max-w-6xl mx-auto">

        <h2 class="text-2xl md:text-3xl font-serif mt-3 md:mt-5 font-bold mb-4 md:mb-6 text-indigo-900">Daftar Menu</h2>

        <!-- Category Tabs -->
        <div class="category-tabs-container flex mb-6 md:mb-8">
            <div class="flex gap-2 md:gap-3">
                <button class="category-tab active bg-indigo-700 text-white px-4 py-2 md:px-5 md:py-2 rounded-full text-xs md:text-sm shadow-md" data-category="all">Semua</button>
                <?php foreach ($menuByCategory as $category => $items): ?>
                    <button class="category-tab bg-gray-200 hover:bg-indigo-700 px-4 py-2 md:px-5 md:py-2 rounded-full text-xs md:text-sm shadow-sm transition" data-category="<?= htmlspecialchars($category) ?>">
                        <?= htmlspecialchars($category) ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Menu Grid -->
        <div id="menu-grid" class="menu-grid grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 md:gap-6">
            <?php foreach ($menus as $menu): ?>
                <div class="menu-card animate-fade-in-up bg-white rounded-xl md:rounded-2xl shadow-lg md:shadow-xl hover:shadow-2xl transition-all duration-300 scale-hover relative" data-id="<?= $menu['id'] ?>" data-category="<?= htmlspecialchars($menu['category']) ?>">
                    <div class="p-4 md:p-6">
                        <div class="font-serif font-semibold text-base md:text-lg text-indigo-900"><?= htmlspecialchars($menu['name']) ?></div>
                        <div class="text-xs md:text-sm text-gray-600"><?= htmlspecialchars($menu['category']) ?></div>
                        <div class="text-indigo-700 font-bold mt-2 md:mt-3 text-lg md:text-xl">Rp <?= number_format($menu['price'], 0, ',', '.') ?></div>
                        
                        <!-- Quantity Controls -->
                        <div class="qty-controls">
                            <button class="qty-btn minus-btn" data-id="<?= $menu['id'] ?>">-</button>
                            <span class="qty-display" id="qty-<?= $menu['id'] ?>">0</span>
                            <button class="qty-btn plus-btn" data-id="<?= $menu['id'] ?>">+</button>
                        </div>
                    </div>
                    <span class="item-badge absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full w-5 h-5 md:w-6 md:h-6 flex items-center justify-center hidden shadow-sm">0</span>
                </div>
            <?php endforeach; ?>
        </div>  
    </div>
</div>

<!-- Cart Popup (Modified to appear in center) -->
<div class="popup-overlay" id="cart-overlay"></div>
<div class="cart-popup" id="cart-popup">
    <div class="flex justify-between items-center mb-4 md:mb-6">
        <h3 class="text-xl md:text-2xl font-serif font-bold text-indigo-900">
            <i class="fa-solid fa-bag-shopping mr-2 text-gray-500"></i>Keranjang
        </h3>
        <button id="close-cart" class="text-gray-500 hover:text-black transition">
            <i class="fas fa-times text-lg md:text-xl"></i>
        </button>
    </div>
    <div id="cart-content" class="space-y-3 md:space-y-4 mb-4 md:mb-6 max-h-48 md:max-h-64 overflow-y-auto">
        <div class="text-gray-600 text-sm">Keranjang kosong</div>
    </div>
    <div>
        <input type="number" id="table-number" placeholder="Nomor Meja" class="w-full border border-gray-300 rounded-full px-3 md:px-4 py-2 md:py-3 mb-3 md:mb-4 shadow-sm">
        <div id="cart-total" class="font-serif font-bold text-lg md:text-xl mb-3 md:mb-4 text-indigo-900">Total: Rp 0</div>
        <button id="send-order" class="w-full bg-indigo-700 text-white py-2 md:py-3 rounded-full hover:bg-indigo-800 transition shadow-md text-sm md:text-base">Kirim Pesanan</button>
    </div>
</div>

<!-- Success Popup -->
<div class="popup-overlay" id="success-overlay"></div>
<div class="success-popup" id="success-popup">
    <div class="w-16 h-16 md:w-20 md:h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3 md:mb-4">
        <i class="fas fa-check-circle text-4xl md:text-5xl text-green-600"></i>
    </div>
    <h3 class="text-xl md:text-2xl font-bold text-gray-800 mb-1 md:mb-2">Pesanan Terkirim!</h3>
    <p class="text-sm md:text-base text-gray-600 mb-4 md:mb-6">Pesanan Anda telah berhasil dikirim</p>
    <button id="success-close-btn" class="bg-indigo-700 text-white px-4 md:px-6 py-1 md:py-2 rounded-full hover:bg-indigo-800 transition shadow-md text-sm md:text-base">
        Tutup
    </button>
</div>

<!-- Bottom Nav -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t flex justify-around py-2 md:py-3 text-xs md:text-sm text-gray-600 z-50 shadow-lg bottom-nav">
    <a href="menu.php" class="flex flex-col items-center gap-1 text-indigo-700 font-bold">
        <i class="fas fa-utensils text-lg md:text-xl"></i> Menu
    </a>
    <a href="bon.php" class="flex flex-col items-center gap-1">
        <i class="fas fa-receipt text-lg md:text-xl"></i> Bon
    </a>
</div>

<script>
    const cart = JSON.parse(localStorage.getItem('restaurant_cart')) || [];
    const cartBadge = document.getElementById('cart-badge');
    const cartOverlay = document.getElementById('cart-overlay');
    const cartPopup = document.getElementById('cart-popup');
    const cartContent = document.getElementById('cart-content');
    const cartTotal = document.getElementById('cart-total');
    const tableNumber = document.getElementById('table-number');
    const successOverlay = document.getElementById('success-overlay');
    const successPopup = document.getElementById('success-popup');

    function updateCartBadge() {
        const totalItems = cart.reduce((sum, item) => sum + item.qty, 0);
        cartBadge.textContent = totalItems;

        document.querySelectorAll('.item-badge').forEach(badge => {
            badge.textContent = '0';
            badge.classList.add('hidden');
        });

        document.querySelectorAll('.qty-display').forEach(display => {
            display.textContent = '0';
        });

        cart.forEach(item => {
            const card = document.querySelector(`.menu-card[data-id='${item.id}']`);
            if (card) {
                const badge = card.querySelector('.item-badge');
                badge.textContent = item.qty;
                badge.classList.remove('hidden');
                
                const display = card.querySelector('.qty-display');
                display.textContent = item.qty;
            }
        });
    }

    function toggleCart(open) {
        if (open) {
            cartOverlay.style.display = 'block';
            cartPopup.style.display = 'block';
            setTimeout(() => {
                cartPopup.style.animation = 'popIn 0.3s ease-out forwards';
            }, 10);
        } else {
            cartPopup.style.animation = '';
            cartPopup.style.display = 'none';
            cartOverlay.style.display = 'none';
        }
    }

    document.getElementById('cart-toggle').addEventListener('click', () => toggleCart(true));
    document.getElementById('close-cart').addEventListener('click', () => toggleCart(false));
    cartOverlay.addEventListener('click', () => toggleCart(false));

    // Event listeners for plus and minus buttons
    document.querySelectorAll('.plus-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const card = btn.closest('.menu-card');
            const name = card.querySelector('.font-serif').textContent;
            const price = parseFloat(card.querySelector('.text-indigo-700').textContent.replace('Rp ', '').replace('.', '').replace(',', ''));
            
            const existing = cart.find(item => item.id === id);
            if (existing) {
                existing.qty += 1;
            } else {
                cart.push({ id, name, price, qty: 1 });
            }

            localStorage.setItem('restaurant_cart', JSON.stringify(cart));
            updateCartBadge();
            renderCart();
        });
    });

    document.querySelectorAll('.minus-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const existingIndex = cart.findIndex(item => item.id === id);
            
            if (existingIndex !== -1) {
                if (cart[existingIndex].qty > 1) {
                    cart[existingIndex].qty -= 1;
                } else {
                    cart.splice(existingIndex, 1);
                }
                
                localStorage.setItem('restaurant_cart', JSON.stringify(cart));
                updateCartBadge();
                renderCart();
            }
        });
    });

    function renderCart() {
        if (cart.length === 0) {
            cartContent.innerHTML = '<div class="text-gray-600 text-sm">Keranjang kosong</div>';
            cartTotal.textContent = 'Total: Rp 0';
            return;
        }

        let html = '';
        let total = 0;

        cart.forEach((item, index) => {
            const subtotal = item.price * item.qty;
            total += subtotal;
            html += `
                <div class="flex justify-between items-center border-b border-gray-200 pb-2 md:pb-3">
                    <div class="flex-1 pr-2">
                        <div class="font-medium text-gray-800 text-sm md:text-base">${item.name}</div>
                        <div class="text-xs text-gray-600">Rp ${item.price.toLocaleString('id-ID')} x ${item.qty}</div>
                    </div>
                    <div class="flex items-center gap-1 md:gap-2">
                        <button class="qty-btn text-xs md:text-sm bg-gray-200 px-1 md:px-2 py-1 rounded-full shadow-sm" data-index="${index}" data-dir="-1">-</button>
                        <span class="text-xs md:text-sm text-gray-800 mx-1">${item.qty}</span>
                        <button class="qty-btn text-xs md:text-sm bg-gray-200 px-1 md:px-2 py-1 rounded-full shadow-sm" data-index="${index}" data-dir="1">+</button>
                        <button class="remove-btn text-xs md:text-sm bg-red-600 text-white px-1 md:px-2 py-1 rounded-full shadow-sm" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        cartContent.innerHTML = html;
        cartTotal.textContent = 'Total: Rp ' + total.toLocaleString('id-ID');

        document.querySelectorAll('.qty-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const index = parseInt(btn.dataset.index);
                const dir = parseInt(btn.dataset.dir);
                cart[index].qty += dir;
                if (cart[index].qty <= 0) cart.splice(index, 1);
                localStorage.setItem('restaurant_cart', JSON.stringify(cart));
                updateCartBadge();
                renderCart();
            });
        });

        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                cart.splice(parseInt(btn.dataset.index), 1);
                localStorage.setItem('restaurant_cart', JSON.stringify(cart));
                updateCartBadge();
                renderCart();
            });
        });
    }

    // Fungsi untuk membuat efek confetti
    function createConfetti() {
        const colors = ['#ff5757', '#ffde59', '#8c52ff', '#38b6ff', '#90ee90'];
        const container = document.body;
        
        for (let i = 0; i < 50; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + 'vw';
            confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
            confetti.style.animation = `confetti ${Math.random() * 3 + 2}s linear forwards`;
            container.appendChild(confetti);
            
            // Hapus confetti setelah animasi selesai
            setTimeout(() => {
                if (confetti.parentNode) {
                    confetti.remove();
                }
            }, 5000);
        }
    }
    
    // Fungsi untuk menampilkan popup sukses
    function showSuccessPopup() {
        successOverlay.style.display = 'block';
        successPopup.style.display = 'block';
        
        // Animasi masuk
        setTimeout(() => {
            successPopup.style.animation = 'popIn 0.5s ease-out forwards';
            createConfetti();
        }, 10);
        
        // Tutup popup ketika klik overlay atau tombol close
        successOverlay.addEventListener('click', closeSuccessPopup);
        document.getElementById('success-close-btn').addEventListener('click', closeSuccessPopup);
    }
    
    // Fungsi untuk menutup popup sukses
    function closeSuccessPopup() {
        successPopup.style.animation = '';
        successPopup.style.display = 'none';
        successOverlay.style.display = 'none';
    }

    document.getElementById('send-order').addEventListener('click', () => {
        if (cart.length === 0) return alert('Keranjang kosong');
        const table = tableNumber.value.trim();
        if (!table) return alert('Masukkan nomor meja');

        fetch('create_bon.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                table_number: parseInt(table),
                items: cart.map(item => ({
                    menu_id: parseInt(item.id),
                    quantity: item.qty
                }))
            })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Tampilkan popup sukses
                showSuccessPopup();
                
                localStorage.removeItem('restaurant_cart');
                cart.length = 0;
                updateCartBadge();
                renderCart();
                toggleCart(false);
            } else {
                alert('Gagal: ' + data.message);
            }
        });
    });

    // Category filter
    document.querySelectorAll('.category-tab').forEach(tab => {
        tab.addEventListener('click', () => {
            document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('bg-indigo-700', 'text-white'));
            tab.classList.add('bg-indigo-700', 'text-white');
            const category = tab.dataset.category;
            document.querySelectorAll('.menu-card').forEach(card => {
                card.style.display = (category === 'all' || card.dataset.category === category) ? 'block' : 'none';
            });
        });
    });

    // Init
    updateCartBadge();
    renderCart();
</script>

</body>
</html>