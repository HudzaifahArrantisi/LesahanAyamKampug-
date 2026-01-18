<?php
require 'config.php';

if (isset($_GET['date'])) {
    $date = $_GET['date'];
    
    // Ambil data laporan harian
    $stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE report_date = ?");
    $stmt->execute([$date]);
    $daily_report = $stmt->fetch();
    
    // Ambil data pengeluaran
    $expenses = [];
    if ($daily_report) {
        $stmt = $pdo->prepare("SELECT * FROM expenses WHERE daily_report_id = ?");
        $stmt->execute([$daily_report['id']]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode(['expenses' => $expenses]);
}
?>