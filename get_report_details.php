<?php
require 'config.php';
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] != "admin") {
    header("HTTP/1.1 403 Forbidden");
    exit("Akses ditolak");
}

if (!isset($_GET['report_id']) || empty($_GET['report_id'])) {
    header("HTTP/1.1 400 Bad Request");
    exit("ID GA VALID DER");
}

$report_id = intval($_GET['report_id']);

try {
    // Ambil data laporan utama
    $stmt = $pdo->prepare("SELECT * FROM daily_reports WHERE id = ?");
    $stmt->execute([$report_id]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        header("HTTP/1.1 404 Not Found");
        exit("Laporan tidak ditemukan");
    }

    // Ambil data pengeluaran untuk laporan ini
    $expenses_stmt = $pdo->prepare("SELECT * FROM expenses WHERE daily_report_id = ? ORDER BY created_at DESC");
    $expenses_stmt->execute([$report_id]);
    $expenses = $expenses_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil data bon history untuk laporan ini dengan filter tanggal yang sesuai
    $bon_history_stmt = $pdo->prepare("SELECT bh.*, u.username 
                                     FROM bon_history bh 
                                     LEFT JOIN users u ON bh.created_by = u.id 
                                     WHERE bh.daily_report_id = ? 
                                     ORDER BY bh.payment_time DESC");
    $bon_history_stmt->execute([$report_id]);
    $bon_histories = $bon_history_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ambil detail pesanan untuk setiap bon history
    $bon_details = [];
    foreach ($bon_histories as $bon) {
        $detail_stmt = $pdo->prepare("SELECT bdh.*, m.name as menu_name, m.code as menu_code
                                    FROM bon_detail_history bdh 
                                    LEFT JOIN menus m ON bdh.menu_id = m.id 
                                    WHERE bdh.bon_history_id = ?");
        $detail_stmt->execute([$bon['id']]);
        $bon_details[$bon['id']] = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function formatHariTanggal($tanggal) {
        $hari = array(
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
        );
        
        $bulan = array(
            '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
            '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
            '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
        );
        
        $day = $hari[date('l', strtotime($tanggal))];
        $tgl = date('d', strtotime($tanggal));
        $bln = $bulan[date('m', strtotime($tanggal))];
        $thn = date('Y', strtotime($tanggal));
        
        return $day . ', ' . $tgl . ' ' . $bln . ' ' . $thn;
    }

    function formatRupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }

    function formatWaktu($timestamp) {
        return date('H:i', strtotime($timestamp));
    }

    // Hitung statistik transaksi
    $total_transaksi = count($bon_histories);
    $total_pendapatan_transaksi = array_sum(array_column($bon_histories, 'total_amount'));
    $rata_rata_transaksi = $total_transaksi > 0 ? $total_pendapatan_transaksi / $total_transaksi : 0;
?>
<div class="space-y-6">
    <!-- Header Laporan -->
    <div class="bg-gray-700 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-yellow-400 mb-2">Informasi Laporan</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-300">Tanggal Laporan</p>
                <p class="text-white font-medium"><?= formatHariTanggal($report['report_date']) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-300">Waktu Submit</p>
                <p class="text-white font-medium"><?= $report['submitted_at'] ? date('d/m/Y H:i', strtotime($report['submitted_at'])) : '-' ?></p>
            </div>
        </div>
    </div>

    <!-- Ringkasan Keuangan -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-green-900/30 rounded-lg p-4 border border-green-600">
            <p class="text-sm text-green-300">Total Pendapatan</p>
            <p class="text-2xl font-bold text-green-400"><?= formatRupiah($report['total_income']) ?></p>
        </div>
        <div class="bg-red-900/30 rounded-lg p-4 border border-red-600">
            <p class="text-sm text-red-300">Total Pengeluaran</p>
            <p class="text-2xl font-bold text-red-400"><?= formatRupiah($report['total_expenses']) ?></p>
        </div>
        <div class="bg-blue-900/30 rounded-lg p-4 border border-blue-600">
            <p class="text-sm text-blue-300">Pendapatan Bersih</p>
            <p class="text-2xl font-bold text-blue-400"><?= formatRupiah($report['net_income']) ?></p>
        </div>
    </div>

    <!-- Statistik Transaksi -->
    <?php if ($total_transaksi > 0): ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-purple-900/30 rounded-lg p-4 border border-purple-600">
            <p class="text-sm text-purple-300">Total Transaksi</p>
            <p class="text-2xl font-bold text-purple-400"><?= $total_transaksi ?> Transaksi</p>
        </div>
        <div class="bg-indigo-900/30 rounded-lg p-4 border border-indigo-600">
            <p class="text-sm text-indigo-300">Pendapatan dari Transaksi</p>
            <p class="text-2xl font-bold text-indigo-400"><?= formatRupiah($total_pendapatan_transaksi) ?></p>
        </div>
        <div class="bg-teal-900/30 rounded-lg p-4 border border-teal-600">
            <p class="text-sm text-teal-300">Rata-rata per Transaksi</p>
            <p class="text-2xl font-bold text-teal-400"><?= formatRupiah($rata_rata_transaksi) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Daftar Pengeluaran -->
    <div class="bg-gray-700 rounded-lg p-4">
        <h4 class="text-lg font-semibold text-yellow-400 mb-4 flex items-center">
            <i class="fas fa-receipt mr-2"></i>Daftar Pengeluaran
        </h4>
        
        <?php if (empty($expenses)): ?>
            <div class="text-center py-4 bg-gray-600 rounded-lg">
                <i class="fas fa-receipt text-3xl text-gray-400 mb-2"></i>
                <p class="text-gray-300">Tidak ada data pengeluaran</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-300">
                    <thead class="text-xs text-gray-400 uppercase bg-gray-600">
                        <tr>
                            <th class="px-4 py-3">Item Pengeluaran</th>
                            <th class="px-4 py-3">Jumlah</th>
                            <th class="px-4 py-3">Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <tr class="border-b border-gray-600 hover:bg-gray-600 transition-colors">
                                <td class="px-4 py-3 font-medium text-white">
                                    <?= htmlspecialchars($expense['item_name'] ?? 'Pengeluaran') ?>
                                </td>
                                <td class="px-4 py-3 text-red-400 font-semibold">
                                    <?= formatRupiah($expense['total_expenses']) ?>
                                </td>
                                <td class="px-4 py-3 text-gray-400">
                                    <?= $expense['created_at'] ? date('d/m/Y H:i', strtotime($expense['created_at'])) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Daftar Transaksi -->
    <div class="bg-gray-700 rounded-lg p-4">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-lg font-semibold text-yellow-400 flex items-center">
                <i class="fas fa-utensils mr-2"></i>Daftar Transaksi
            </h4>
            <?php if ($total_transaksi > 0): ?>
                <span class="bg-yellow-600 text-white px-3 py-1 rounded-full text-sm font-medium">
                    <?= $total_transaksi ?> Transaksi
                </span>
            <?php endif; ?>
        </div>
        
        <?php if (empty($bon_histories)): ?>
            <div class="text-center py-8 bg-gray-600 rounded-lg">
                <i class="fas fa-utensils text-4xl text-gray-400 mb-3"></i>
                <p class="text-gray-300 text-lg">Tidak ada data transaksi</p>
                <p class="text-gray-400 text-sm mt-1">Pada tanggal <?= formatHariTanggal($report['report_date']) ?></p>
            </div>
        <?php else: ?>
            <!-- Grid Layout untuk Transaksi -->
            <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach ($bon_histories as $index => $bon): ?>
                    <div class="bg-gray-600 rounded-lg overflow-hidden border border-gray-500 hover:border-yellow-400 transition-all duration-300">
                        <!-- Header Transaksi -->
                        <div class="bg-gradient-to-r from-gray-700 to-gray-800 p-4 border-b border-gray-500">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <div class="flex items-center mb-1">
                                        <span class="bg-blue-600 text-white px-2 py-1 rounded text-xs font-bold mr-2">
                                            #<?= str_pad($bon['id'], 4, '0', STR_PAD_LEFT) ?>
                                        </span>
                                        <span class="text-white font-semibold">Meja <?= $bon['table_number'] ?></span>
                                    </div>
                                    <p class="text-xs text-gray-300">
                                        Kasir: <?= htmlspecialchars($bon['username'] ?? 'System') ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold text-green-400">
                                        <?= formatRupiah($bon['total_amount'] ?? 0) ?>
                                    </p>
                                    <span class="px-2 py-1 rounded text-xs font-medium 
                                        <?= $bon['payment_method'] === 'qris' ? 'bg-purple-600 text-white' : 
                                           ($bon['payment_method'] === 'transfer' ? 'bg-blue-600 text-white' : 
                                           'bg-green-600 text-white') ?>">
                                        <?= strtoupper($bon['payment_method'] ?? 'CASH') ?>
                                    </span>
                                </div>
                            </div>
                            <div class="flex justify-between items-center text-xs text-gray-400">
                                <span>
                                    <i class="fas fa-clock mr-1"></i>
                                    <?= $bon['payment_time'] ? date('H:i', strtotime($bon['payment_time'])) : '--:--' ?>
                                </span>
                                <span>
                                    <?= $bon['payment_time'] ? date('d/m/Y', strtotime($bon['payment_time'])) : date('d/m/Y') ?>
                                </span>
                            </div>
                        </div>

                        <!-- Detail Pesanan -->
                        <div class="p-3">
                            <h6 class="font-semibold text-yellow-400 mb-2 text-sm flex items-center">
                                <i class="fas fa-list mr-1"></i> Detail Pesanan:
                            </h6>
                            
                            <?php if (!empty($bon_details[$bon['id']])): ?>
                                <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                                    <?php foreach ($bon_details[$bon['id']] as $detail): ?>
                                        <div class="flex justify-between items-center text-xs bg-gray-700/50 rounded px-2 py-1">
                                            <div class="flex-1 truncate mr-2">
                                                <span class="font-medium text-white">
                                                    <?= htmlspecialchars($detail['menu_name'] ?? 'Menu') ?>
                                                </span>
                                                <?php if (!empty($detail['menu_code'])): ?>
                                                    <span class="text-gray-400 text-xs">(<?= $detail['menu_code'] ?>)</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="text-gray-300">x<?= $detail['quantity'] ?></span>
                                                <span class="text-green-400 font-semibold">
                                                    <?= formatRupiah($detail['subtotal']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Summary -->
                                <div class="mt-3 pt-2 border-t border-gray-500">
                                    <div class="flex justify-between text-xs">
                                        <span class="text-gray-300">Total Item:</span>
                                        <span class="text-white font-semibold">
                                            <?= count($bon_details[$bon['id']]) ?> item
                                        </span>
                                    </div>
                                    <div class="flex justify-between text-xs mt-1">
                                        <span class="text-gray-300">Total Qty:</span>
                                        <span class="text-white font-semibold">
                                            <?= array_sum(array_column($bon_details[$bon['id']], 'quantity')) ?> pcs
                                        </span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-3 text-gray-400 text-xs">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    Tidak ada detail pesanan tersedia
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Footer -->
                        <div class="bg-gray-700/50 px-3 py-2 border-t border-gray-500">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-gray-300">
                                    Status: 
                                    <span class="font-semibold <?= $bon['status'] === 'completed' ? 'text-green-400' : 'text-yellow-400' ?>">
                                        <?= strtoupper($bon['status']) ?>
                                    </span>
                                </span>
                                <button onclick="toggleBonDetails(<?= $bon['id'] ?>)" 
                                        class="text-yellow-400 hover:text-yellow-300 transition-colors">
                                    <i class="fas fa-expand-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Modal untuk Detail Bon Lengkap -->
            <div id="bonDetailModal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 hidden">
                <div class="bg-gray-800 rounded-lg max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden">
                    <div class="bg-gray-900 px-6 py-4 border-b border-gray-700 flex justify-between items-center">
                        <h5 class="text-lg font-semibold text-yellow-400">Detail Transaksi Lengkap</h5>
                        <button onclick="closeBonDetailModal()" class="text-gray-400 hover:text-white">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <div id="bonDetailContent" class="p-6 overflow-y-auto max-h-[70vh]">
                        <!-- Content akan diisi oleh JavaScript -->
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Tombol Tutup -->
    <div class="flex justify-end">
        <button onclick="closeReportModal()" class="px-6 py-2 bg-gray-600 hover:bg-gray-700 rounded-md font-semibold transition-colors">
            <i class="fas fa-times mr-2"></i>Tutup Laporan
        </button>
    </div>
</div>

<script>
function toggleBonDetails(bonId) {
    // Untuk versi sederhana, kita bisa expand/collapse detail di tempat
    const card = event.target.closest('.bg-gray-600');
    const detailSection = card.querySelector('.max-h-40');
    
    if (detailSection.classList.contains('max-h-40')) {
        detailSection.classList.remove('max-h-40');
        detailSection.classList.add('max-h-96');
        event.target.classList.remove('fa-expand-alt');
        event.target.classList.add('fa-compress-alt');
    } else {
        detailSection.classList.remove('max-h-96');
        detailSection.classList.add('max-h-40');
        event.target.classList.remove('fa-compress-alt');
        event.target.classList.add('fa-expand-alt');
    }
}

function showFullBonDetail(bonId) {
    // Implementasi untuk modal detail lengkap
    fetch('get_bon_detail.php?bon_id=' + bonId)
        .then(response => response.text())
        .then(data => {
            document.getElementById('bonDetailContent').innerHTML = data;
            document.getElementById('bonDetailModal').classList.remove('hidden');
        });
}

function closeBonDetailModal() {
    document.getElementById('bonDetailModal').classList.add('hidden');
}

// Close modal ketika klik di luar konten
document.getElementById('bonDetailModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeBonDetailModal();
    }
});
</script>
<?php
} catch (Exception $e) {
    echo "<div class='bg-red-600 text-white p-4 rounded-lg'>";
    echo "<p class='font-semibold'>Error:</p>";
    echo "<p>Gagal memuat detail laporan: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>