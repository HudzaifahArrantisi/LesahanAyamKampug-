<?php
require 'config.php';
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != "admin") {
    header("Location: index.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $orders = $_POST['order'];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($orders as $menu_id => $order_data) {
            $display_order = $order_data['display_order'];
            $category = $order_data['category'];
            
            $stmt = $pdo->prepare("UPDATE menu SET display_order = ?, category = ? WHERE id = ?");
            $stmt->execute([$display_order, $category, $menu_id]);
        }
        
        $pdo->commit();
        $message = "Urutan menu berhasil diperbarui!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Gagal memperbarui urutan menu: " . $e->getMessage();
    }
}

// Ambil data menu
$stmt = $pdo->query("SELECT * FROM menu ORDER BY category, display_order, name");
$menu_items = $stmt->fetchAll();

// Kelompokkan menu berdasarkan kategori
$grouped_menu = [];
foreach ($menu_items as $item) {
    $grouped_menu[$item['category']][] = $item;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Urutan Menu - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
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
        .sortable-ghost {
            opacity: 0.4;
            background: #4B5563;
        }
        .sortable-chosen {
            background: #374151;
            transform: scale(1.02);
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
        .popup {
            animation: popup 0.3s ease-out;
        }
        @keyframes popup {
            0% { transform: scale(0.8); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
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
            cursor: grab;
        }
        .menu-item:active {
            cursor: grabbing;
        }
        .menu-item:hover {
            background-color: #374151;
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
                        <a href="menu_management.php" class="flex items-center p-2 text-white bg-blue-700 rounded-lg">
                            <i class="fas fa-utensils mr-3"></i> Kelola Urutan Menu
                        </a>
                    </li>
                    <li>
                        <a href="add_menu.php" class="flex items-center p-2 text-gray-300 hover:bg-gray-700 rounded-lg">
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
                    <i class="fas fa-sort-amount-down mr-3"></i>Kelola Urutan Menu
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

                <div class="bg-gray-800 rounded-lg shadow-lg p-6 card fade-in">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                        <h2 class="text-xl font-semibold text-white mb-4 md:mb-0">
                            <i class="fas fa-arrows-alt-v mr-2 text-yellow-400"></i>Atur Urutan Tampilan Menu
                        </h2>
                        <div class="flex space-x-4">
                            <button onclick="resetOrders()" 
                                    class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-md font-semibold transition-colors transform hover:scale-105">
                                <i class="fas fa-undo mr-2"></i>Reset
                            </button>
                            <button onclick="saveOrders()" 
                                    class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-md font-semibold transition-colors transform hover:scale-105">
                                <i class="fas fa-save mr-2"></i>Simpan Urutan
                            </button>
                        </div>
                    </div>

                    <form method="POST" id="orderForm">
                        <input type="hidden" name="update_order" value="1">
                        
                        <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                            <?php foreach ($grouped_menu as $category => $items): ?>
                                <div class="bg-gray-700 rounded-lg p-4">
                                    <h3 class="text-lg font-semibold text-yellow-400 mb-3 flex items-center">
                                        <i class="fas fa-folder mr-2"></i><?= $category ?>
                                        <span class="ml-2 text-sm bg-gray-600 px-2 py-1 rounded-full">
                                            <?= count($items) ?> menu
                                        </span>
                                    </h3>
                                    
                                    <div id="sortable-<?= preg_replace('/[^a-z0-9]/', '-', strtolower($category)) ?>" 
                                         class="sortable-list space-y-2">
                                        <?php foreach ($items as $index => $item): ?>
                                            <div class="menu-item bg-gray-600 p-3 rounded-md flex items-center justify-between" 
                                                 data-id="<?= $item['id'] ?>">
                                                <div class="flex items-center space-x-3 flex-grow">
                                                    <i class="fas fa-grip-vertical text-gray-400 cursor-move"></i>
                                                    <div class="flex-grow">
                                                        <p class="font-medium text-white"><?= htmlspecialchars($item['name']) ?></p>
                                                        <p class="text-sm text-gray-400">Rp <?= number_format($item['price'], 0, ',', '.') ?></p>
                                                    </div>
                                                </div>
                                                
                                                <div class="flex items-center space-x-2">
                                                    <input type="number" 
                                                           name="order[<?= $item['id'] ?>][display_order]" 
                                                           value="<?= $item['display_order'] ?>" 
                                                           min="1"
                                                           class="w-16 px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm">
                                                    
                                                    <select name="order[<?= $item['id'] ?>][category]"
                                                            class="px-2 py-1 bg-gray-700 border border-gray-600 rounded text-white text-sm">
                                                        <option value="Makanan" <?= $item['category'] == 'Makanan' ? 'selected' : '' ?>>Makanan</option>
                                                        <option value="Minuman" <?= $item['category'] == 'Minuman' ? 'selected' : '' ?>>Minuman</option>
                                                        <option value="Tambahan" <?= $item['category'] == 'Tambahan' ? 'selected' : '' ?>>Tambahan</option>
                                                        <option value="Sate" <?= $item['category'] == 'Sate' ? 'selected' : '' ?>>Sate</option>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <?php if (empty($menu_items)): ?>
                        <div class="text-center py-8 bg-gray-700 rounded-lg mt-6">
                            <i class="fas fa-utensils text-4xl text-gray-500 mb-3"></i>
                            <p class="text-gray-400">Belum ada menu</p>
                            <a href="add_menu.php" class="inline-block mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-md font-semibold transition-colors">
                                <i class="fas fa-plus mr-2"></i>Tambah Menu
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 bg-gray-800 rounded-lg shadow-lg p-6 card fade-in">
                    <h3 class="text-lg font-semibold text-white mb-4">
                        <i class="fas fa-info-circle mr-2 text-yellow-400"></i>Petunjuk Penggunaan
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-300">
                        <div class="flex items-start space-x-2">
                            <i class="fas fa-grip-vertical text-yellow-400 mt-1"></i>
                            <p>Drag and drop menu untuk mengubah urutan tampilan</p>
                        </div>
                        <div class="flex items-start space-x-2">
                            <i class="fas fa-sort-numeric-down text-yellow-400 mt-1"></i>
                            <p>Atur angka urutan untuk menentukan posisi menu</p>
                        </div>
                        <div class="flex items-start space-x-2">
                            <i class="fas fa-folder text-yellow-400 mt-1"></i>
                            <p>Pilih kategori untuk mengelompokkan menu</p>
                        </div>
                        <div class="flex items-start space-x-2">
                            <i class="fas fa-save text-yellow-400 mt-1"></i>
                            <p>Jangan lupa klik Simpan Urutan setelah melakukan perubahan</p>
                        </div>
                    </div>
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

        // Initialize Sortable for each category
        document.querySelectorAll('.sortable-list').forEach(list => {
            new Sortable(list, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                onEnd: function(evt) {
                    updateOrderNumbers(evt.from);
                }
            });
        });

        // Update order numbers after sorting
        function updateOrderNumbers(container) {
            const items = container.querySelectorAll('.menu-item');
            items.forEach((item, index) => {
                const input = item.querySelector('input[type="number"]');
                if (input) {
                    input.value = index + 1;
                }
            });
        }

        // Reset order numbers to original
        function resetOrders() {
            if (confirm('Reset urutan ke nilai semula?')) {
                document.querySelectorAll('.menu-item').forEach(item => {
                    const originalOrder = item.getAttribute('data-original-order');
                    const input = item.querySelector('input[type="number"]');
                    if (input && originalOrder) {
                        input.value = originalOrder;
                    }
                });
            }
        }

        // Save orders
        function saveOrders() {
            // Update all order numbers before submit
            document.querySelectorAll('.sortable-list').forEach(updateOrderNumbers);
            
            // Show confirmation
            if (confirm('Simpan perubahan urutan menu?')) {
                document.getElementById('orderForm').submit();
            }
        }

        // Store original orders
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.menu-item').forEach(item => {
                const input = item.querySelector('input[type="number"]');
                if (input) {
                    item.setAttribute('data-original-order', input.value);
                }
            });
        });

        function closeMessage() {
            document.getElementById('messagePopup').style.display = 'none';
        }

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