<?php
require 'config.php';
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != "admin") {
    header("Location: index.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_menu'])) {
        // Tambah menu baru
        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $display_order = $_POST['display_order'];

        $stmt = $pdo->prepare("INSERT INTO menu (name, category, price, display_order) VALUES (?, ?, ?, ?)");
        
        if ($stmt->execute([$name, $category, $price, $display_order])) {
            $message = "Menu berhasil ditambahkan!";
        } else {
            $message = "Gagal menambahkan menu.";
        }
    } elseif (isset($_POST['update_menu'])) {
        // Update menu yang sudah ada
        $id = $_POST['menu_id'];
        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $display_order = $_POST['display_order'];

        $stmt = $pdo->prepare("UPDATE menu SET name = ?, category = ?, price = ?, display_order = ? WHERE id = ?");
        
        if ($stmt->execute([$name, $category, $price, $display_order, $id])) {
            $message = "Menu berhasil diperbarui!";
        } else {
            $message = "Gagal memperbarui menu.";
        }
    } elseif (isset($_POST['delete_menu'])) {
        // Hapus menu
        $id = $_POST['menu_id'];
        
        $stmt = $pdo->prepare("DELETE FROM menu WHERE id = ?");
        
        if ($stmt->execute([$id])) {
            $message = "Menu berhasil dihapus!";
        } else {
            $message = "Gagal menghapus menu.";
        }
    }
}

// Ambil data menu
$menu_stmt = $pdo->query("SELECT * FROM menu ORDER BY category, display_order, name");
$menu_items = $menu_stmt->fetchAll();

// Ambil urutan terakhir per kategori
$last_orders_stmt = $pdo->query("SELECT category, MAX(display_order) as max_order FROM menu GROUP BY category");
$last_orders = [];
while ($row = $last_orders_stmt->fetch()) {
    $last_orders[$row['category']] = $row['max_order'] + 1;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Menu - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease-in-out;
        }
        .modal-overlay.show {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-content {
            background: #1F2937;
            border-radius: 0.5rem;
            padding: 1.5rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: translateY(-50px);
            transition: transform 0.3s ease-in-out;
        }
        .modal-overlay.show .modal-content {
            transform: translateY(0);
        }
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        .sidebar.open {
            transform: translateX(0);
        }
        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 40;
        }
        .overlay.open {
            display: block;
        }
        @media (min-width: 1024px) {
            .sidebar {
                transform: translateX(0);
                position: static;
                height: auto;
            }
            .overlay {
                display: none !important;
            }
        }
        .menu-item {
            transition: all 0.3s ease;
        }
        .menu-item:hover {
            background-color: #374151;
            transform: scale(1.02);
        }
        .popup {
            animation: popup 0.3s ease-out;
        }
        @keyframes popup {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-900 to-gray-800 min-h-screen text-gray-100">
    <!-- Loading Overlay -->
    <div id="loading" class="fixed inset-0 bg-white flex items-center justify-center z-50 transition-opacity duration-500">
        <div class="text-center">
            <i class="fas fa-pepper-hot text-6xl text-red-600 animate-bounce mb-4"></i>
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-t-4 border-blue-500 border-t-yellow-400 mb-4"></div>
            <p class="text-xl font-bold text-gray-800">SAMBEL ULEG</p>
            <p class="mt-2 text-gray-700 animate-pulse">Memuat halaman...</p>
        </div>
    </div>

    <div id="content" class="opacity-0 transition-opacity duration-500">
        <!-- Sidebar Toggle -->
        <button id="sidebarToggle" class="fixed top-4 left-4 z-50 p-3 bg-blue-600 rounded-lg lg:hidden">
            <i class="fas fa-bars text-white"></i>
        </button>

        <!-- Overlay -->
        <div id="overlay" class="overlay" onclick="closeSidebar()"></div>

        <!-- Sidebar -->
        <div id="sidebar" class="sidebar fixed top-0 left-0 h-full bg-gray-800 z-50 lg:relative lg:z-0 lg:float-left lg:min-h-screen">
            <div class="p-4 border-b border-gray-700 flex justify-between items-center">
                <h2 class="text-xl font-bold text-white">Menu Admin</h2>
                <button class="lg:hidden" onclick="closeSidebar()">
                    <i class="fas fa-times text-white"></i>
                </button>
            </div>
            
            <div class="p-4">
                <ul class="space-y-2">
                    <li>
                        <a href="dashboard_admin.php" class="flex items-center p-2 text-gray-300 hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="menu_management.php" class="flex items-center p-2 text-gray-300 hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-utensils mr-3"></i> Kelola Urutan Menu
                        </a>
                    </li>
                    <li>
                        <a href="add_menu.php" class="flex items-center p-2 text-white bg-blue-700 rounded-lg">
                            <i class="fas fa-layer-group mr-3"></i> Kelola Menu
                        </a>
                    </li>
                    <li>
                        <a href="order_management.php" class="flex items-center p-2 text-gray-300 hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-list-alt mr-3"></i> Kelola Pesanan
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="flex items-center p-2 text-gray-300 hover:bg-gray-700 rounded-lg">
                            <i class="fas fa-sign-out-alt mr-3"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:ml-80">
            <div class="container mx-auto px-4 py-8">
                <h1 class="text-3xl md:text-4xl font-bold text-center mb-8 text-white">
                    <i class="fas fa-utensils mr-3"></i>Kelola Menu
                </h1>

                <?php if ($message): ?>
                    <div id="messagePopup" class="bg-green-600 text-white p-4 rounded-lg mb-6 fade-in popup">
                        <div class="flex justify-between items-center">
                            <p><?= htmlspecialchars($message) ?></p>
                            <button onclick="closeMessage()">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Tambah/Edit Menu -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
                    <!-- Form Input -->
                    <div class="bg-gray-800 rounded-lg shadow-lg p-6 card fade-in">
                        <h2 class="text-xl font-semibold mb-6 text-white border-b border-gray-700 pb-2">
                            <i class="fas fa-plus-circle mr-2 text-yellow-400"></i>
                            <span id="formTitle">Tambah Menu Baru</span>
                        </h2>
                        
                        <form method="POST" id="menuForm">
                            <input type="hidden" name="menu_id" id="menu_id">
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Nama Menu</label>
                                    <input type="text" name="name" id="name" required
                                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Kategori</label>
                                    <select name="category" id="category" required
                                            class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-all">
                                        <option value="Makanan">Makanan</option>
                                        <option value="Minuman">Minuman</option>
                                        <option value="Tambahan">Tambahan</option>
                                        <option value="Sate">Sate</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Harga</label>
                                    <input type="number" step="100" min="0" name="price" id="price" required
                                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">Urutan Tampil</label>
                                    <input type="number" name="display_order" id="display_order" required min="1"
                                           class="w-full px-4 py-2 bg-gray-700 border border-gray-600 rounded-md text-white focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-all">
                                    <p class="text-xs text-gray-400 mt-1">Urutan menampilkan menu dalam kategori</p>
                                </div>
                                
                                <div class="flex space-x-4 pt-4">
                                    <button type="submit" name="add_menu" id="addBtn"
                                            class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 rounded-md font-semibold transition-colors transform hover:scale-105">
                                        <i class="fas fa-plus mr-2"></i>Tambah Menu
                                    </button>
                                    <button type="submit" name="update_menu" id="updateBtn" style="display: none;"
                                            class="flex-1 px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-md font-semibold transition-colors transform hover:scale-105">
                                        <i class="fas fa-save mr-2"></i>Update Menu
                                    </button>
                                    <button type="button" onclick="resetForm()"
                                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-md font-semibold transition-colors transform hover:scale-105">
                                        <i class="fas fa-times mr-2"></i>Batal
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Daftar Menu -->
                    <div class="bg-gray-800 rounded-lg shadow-lg p-6 card fade-in">
                        <h2 class="text-xl font-semibold mb-6 text-white border-b border-gray-700 pb-2">
                            <i class="fas fa-list mr-2 text-yellow-400"></i>Daftar Menu
                        </h2>
                        
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php if (empty($menu_items)): ?>
                                <div class="text-center py-8 bg-gray-700 rounded-lg">
                                    <i class="fas fa-utensils text-4xl text-gray-500 mb-3"></i>
                                    <p class="text-gray-400">Belum ada menu</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                $grouped_menu = [];
                                foreach ($menu_items as $item) {
                                    $grouped_menu[$item['category']][] = $item;
                                }
                                ?>
                                
                                <?php foreach ($grouped_menu as $category => $items): ?>
                                    <div class="mb-4">
                                        <h3 class="text-lg font-semibold text-yellow-400 mb-2 flex items-center">
                                            <i class="fas fa-folder mr-2"></i><?= $category ?>
                                            <span class="ml-2 text-sm bg-gray-700 px-2 py-1 rounded-full">
                                                <?= count($items) ?> menu
                                            </span>
                                        </h3>
                                        <div class="space-y-2">
                                            <?php foreach ($items as $item): ?>
                                                <div class="menu-item bg-gray-700 p-3 rounded-md flex justify-between items-center">
                                                    <div class="flex-grow">
                                                        <p class="font-medium text-white"><?= htmlspecialchars($item['name']) ?></p>
                                                        <p class="text-sm text-gray-400">Rp <?= number_format($item['price'], 0, ',', '.') ?> • Urutan: <?= $item['display_order'] ?></p>
                                                    </div>
                                                    <div class="flex space-x-2">
                                                        <button onclick="editMenu(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>', '<?= $item['category'] ?>', <?= $item['price'] ?>, <?= $item['display_order'] ?>)"
                                                                class="px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs transition-colors transform hover:scale-110">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button onclick="confirmDelete(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>')"
                                                                class="px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-xs transition-colors transform hover:scale-110">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal-overlay hidden">
        <div class="modal-content popup">
            <div class="text-center">
                <i class="fas fa-exclamation-triangle text-4xl text-yellow-400 mb-4"></i>
                <h3 class="text-xl font-semibold text-white mb-2">Hapus Menu</h3>
                <p class="text-gray-300 mb-6" id="deleteMessage">Apakah Anda yakin ingin menghapus menu ini?</p>
                <div class="flex space-x-4 justify-center">
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="menu_id" id="delete_menu_id">
                        <button type="submit" name="delete_menu" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-md font-semibold transition-colors transform hover:scale-105">
                            <i class="fas fa-trash mr-2"></i>Hapus
                        </button>
                    </form>
                    <button onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-md font-semibold transition-colors transform hover:scale-105">
                        <i class="fas fa-times mr-2"></i>Batal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Loading effect
        window.addEventListener('load', function() {
            const loading = document.getElementById('loading');
            const content = document.getElementById('content');

            setTimeout(() => {
                loading.classList.add('opacity-0');
                setTimeout(() => loading.style.display = 'none', 500);
                content.classList.remove('opacity-0');
                content.classList.add('opacity-100');
            }, 500);
        });

        // Sidebar functions
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('open');
            overlay.classList.toggle('open');
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.remove('open');
            overlay.classList.remove('open');
        }

        document.getElementById('sidebarToggle').addEventListener('click', toggleSidebar);

        // Menu form functions
        function editMenu(id, name, category, price, display_order) {
            document.getElementById('menu_id').value = id;
            document.getElementById('name').value = name;
            document.getElementById('category').value = category;
            document.getElementById('price').value = price;
            document.getElementById('display_order').value = display_order;
            
            document.getElementById('formTitle').textContent = 'Edit Menu';
            document.getElementById('addBtn').style.display = 'none';
            document.getElementById('updateBtn').style.display = 'block';
            
            // Scroll to form dengan animasi
            document.getElementById('name').scrollIntoView({ 
                behavior: 'smooth',
                block: 'center'
            });
            
            // Highlight form
            document.getElementById('menuForm').classList.add('popup');
            setTimeout(() => {
                document.getElementById('menuForm').classList.remove('popup');
            }, 300);
        }

        function resetForm() {
            document.getElementById('menuForm').reset();
            document.getElementById('menu_id').value = '';
            document.getElementById('formTitle').textContent = 'Tambah Menu Baru';
            document.getElementById('addBtn').style.display = 'block';
            document.getElementById('updateBtn').style.display = 'none';
            
            // Set default display order based on selected category
            updateDisplayOrder();
        }

        function confirmDelete(id, name) {
            document.getElementById('delete_menu_id').value = id;
            document.getElementById('deleteMessage').textContent = `Apakah Anda yakin ingin menghapus menu "${name}"?`;
            document.getElementById('deleteModal').classList.remove('hidden');
            setTimeout(() => {
                document.getElementById('deleteModal').classList.add('show');
            }, 10);
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            setTimeout(() => {
                document.getElementById('deleteModal').classList.add('hidden');
            }, 300);
        }

        function closeMessage() {
            document.getElementById('messagePopup').style.display = 'none';
        }

        // Update display order when category changes
        document.getElementById('category').addEventListener('change', updateDisplayOrder);

        function updateDisplayOrder() {
            const category = document.getElementById('category').value;
            const lastOrder = <?= json_encode($last_orders) ?>[category] || 1;
            document.getElementById('display_order').value = lastOrder;
        }

        // Format price input
        document.getElementById('price').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\./g, '');
            if (value === '') return;
            
            value = parseInt(value);
            if (!isNaN(value)) {
                e.target.value = value.toLocaleString('id-ID');
            }
        });

        // Initialize
        updateDisplayOrder();

        // Auto-hide message after 5 seconds
        <?php if ($message): ?>
            setTimeout(() => {
                const message = document.getElementById('messagePopup');
                if (message) {
                    message.style.display = 'none';
                }
            }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>