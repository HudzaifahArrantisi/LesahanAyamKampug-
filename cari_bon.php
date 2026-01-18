<?php
// cari_bon.php
session_start();
require_once 'config.php';

if (isset($_GET['q'])) {
    $search = $_GET['q'];
    
    $sql = "SELECT * FROM bon WHERE table_number LIKE ? OR customer_name LIKE ? OR bon_number LIKE ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    $search_param = "%$search%";
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bons = [];
    while ($row = $result->fetch_assoc()) {
        $bons[] = $row;
    }
    
    echo json_encode($bons);
} else {
    echo json_encode([]);
}
?>