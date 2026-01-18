<?php
require 'config.php';

if (isset($_GET['date'])) {
    $date = $_GET['date'];
    
    // Ambil data bon untuk tanggal tertentu
    $stmt = $pdo->prepare("SELECT * FROM bon_history WHERE DATE(payment_date) = ? ORDER BY payment_date DESC");
    $stmt->execute([$date]);
    $bon_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ambil data laporan harian
    $stmt = $pdo->prepare("SELECT * FROM daily_reports WHERE report_date = ?");
    $stmt->execute([$date]);
    $daily_report = $stmt->fetch();
    
    // Ambil data pengeluaran
    $expenses = [];
    if ($daily_report) {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE daily_report_id = ?");
        $stmt->execute([$daily_report['id']]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Hitung total pemasukan
    $total_income = 0;
    foreach ($bon_history as $bon) {
        $stmt = $pdo->prepare("SELECT SUM(subtotal) as total FROM bon_detail_history WHERE bon_history_id = ?");
        $stmt->execute([$bon['id']]);
        $total = $stmt->fetchColumn();
        $total_income += $total;
    }
    
    // Hitung total pengeluaran
    $total_expenses = 0;
    foreach ($expenses as $expense) {
        $total_expenses += $expense['amount'];
    }
    
    $net_income = $total_income - $total_expenses;
    
    // Generate HTML
    $html = '<div class="mb-6">';
    $html .= '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">';
    $html .= '<div class="bg-gray-700 p-4 rounded-lg">';
    $html .= '<p class="text-gray-300 text-sm">Pemasukan</p>';
    $html .= '<p class="text-2xl font-bold text-green-400">Rp ' . number_format($total_income, 0, ',', '.') . '</p>';
    $html .= '</div>';
    $html .= '<div class="bg-gray-700 p-4 rounded-lg">';
    $html .= '<p class="text-gray-300 text-sm">Pengeluaran</p>';
    $html .= '<p class="text-2xl font-bold text-red-400">Rp ' . number_format($total_expenses, 0, ',', '.') . '</p>';
    $html .= '</div>';
    $html .= '<div class="bg-gray-700 p-4 rounded-lg">';
    $html .= '<p class="text-gray-300 text-sm">Pendapatan Bersih</p>';
    $html .= '<p class="text-2xl font-bold ' . ($net_income >= 0 ? 'text-blue-400' : 'text-red-400') . '">Rp ' . number_format($net_income, 0, ',', '.') . '</p>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Tombol aksi
    $html .= '<div class="flex flex-wrap gap-3 mb-6">';
    $html .= '<button onclick="openEditModal(\'' . $date . '\', ' . $total_income . ', ' . $total_expenses . ')" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 text-white rounded-lg font-medium transition-all duration-300 shadow">';
    $html .= '<i class="fas fa-edit mr-2"></i> Edit Laporan';
    $html .= '</button>';
    $html .= '<button onclick="openDeleteDayModal(\'' . $date . '\')" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white rounded-lg font-medium transition-all duration-300 shadow">';
    $html .= '<i class="fas fa-trash mr-2"></i> Hapus Hari Ini';
    $html .= '</button>';
    
    if (!$daily_report || !$daily_report['is_submitted']) {
        $html .= '<form method="POST" action="" class="inline">';
        $html .= '<input type="hidden" name="report_date" value="' . $date . '">';
        $html .= '<input type="hidden" name="total_income" value="' . $total_income . '">';
        $html .= '<input type="hidden" name="total_expenses" value="' . $total_expenses . '">';
        $html .= '<input type="hidden" name="net_income" value="' . $net_income . '">';
        $html .= '<input type="hidden" name="submit_report" value="1">';
        $html .= '<button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-white rounded-lg font-medium transition-all duration-300 shadow">';
        $html .= '<i class="fas fa-paper-plane mr-2"></i> Kirim ke Admin';
        $html .= '</button>';
        $html .= '</form>';
    } else {
        $html .= '<span class="px-4 py-2 bg-green-600 text-white rounded-lg font-medium">';
        $html .= '<i class="fas fa-check-circle mr-2"></i> Sudah Dikirim';
        $html .= '</span>';
    }
    $html .= '</div>';
    
    // Daftar transaksi
    $html .= '<h4 class="text-xl font-semibold text-white mb-4">Daftar Transaksi</h4>';
    $html .= '<div class="overflow-x-auto">';
    $html .= '<table class="w-full text-white border-collapse">';
    $html .= '<thead>';
    $html .= '<tr class="bg-gray-700">';
    $html .= '<th class="p-3 text-left">No. Meja</th>';
    $html .= '<th class="p-3 text-left">Waktu</th>';
    $html .= '<th class="p-3 text-left">Total</th>';
    $html .= '<th class="p-3 text-left">Metode Bayar</th>';
    $html .= '<th class="p-3 text-left">Lihat</th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    
    foreach ($bon_history as $index => $bon) {
        $stmt = $pdo->prepare("SELECT SUM(subtotal) as total FROM bon_detail_history WHERE bon_history_id = ?");
        $stmt->execute([$bon['id']]);
        $total = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT uuid FROM bon_uuid WHERE bon_history_id = ?");
        $stmt->execute([$bon['id']]);
        $uuid = $stmt->fetchColumn();
        
        $bgClass = $index % 2 === 0 ? 'bg-gray-800' : 'bg-gray-700';
        $html .= '<tr class="' . $bgClass . '">';
        $html .= '<td class="p-3">' . $bon['table_number'] . '</td>';
        $html .= '<td class="p-3">' . date('H:i', strtotime($bon['payment_date'])) . '</td>';
        $html .= '<td class="p-3">Rp ' . number_format($total, 0, ',', '.') . '</td>';
        $html .= '<td class="p-3">';
        $html .= '<span class="px-2 py-1 rounded text-xs ' . ($bon['payment_method'] === 'cash' ? 'bg-yellow-500 text-gray-900' : 'bg-purple-500 text-white') . '">';
        $html .= $bon['payment_method'] === 'cash' ? 'CASH' : 'QRIS (' . $bon['payment_bank'] . ')';
        $html .= '</span>';
        $html .= '</td>';
        $html .= '<td class="p-3">';
        $html .= '<button onclick="openBonActionModal(' . $bon['id'] . ', \'' . $uuid . '\')" class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white rounded text-sm transition-all duration-300 shadow">';
        $html .= '<i class="fas fa-eye mr-1"></i> Lihat';
        $html .= '</button>';
        $html .= '</td>';
        $html .= '</tr>';
    }
    
    $html .= '</tbody>';
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    
    echo json_encode(['html' => $html]);
}
?>