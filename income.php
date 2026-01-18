<?php
// income.php
session_start();
include 'config.php';
include 'functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: index.php");
    exit();
}

$income = 0;
$expenses = 0;
$net_income = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $expense_description = sanitize_input($_POST['description']);
    $expense_amount = floatval($_POST['amount']);
    
    $sql = "INSERT INTO daily_expenses (description, amount, created_by) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sdi", $expense_description, $expense_amount, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menambahkan pengeluaran']);
    }
    exit();
}

// Calculate income from completed transactions
$sql_income = "SELECT SUM(amount) AS total_income FROM transactions WHERE status = 'completed'";
$result_income = $conn->query($sql_income);
$row_income = $result_income->fetch_assoc();
$income = $row_income['total_income'] ?? 0;

// Calculate expenses
$sql_expenses = "SELECT SUM(amount) AS total_expenses FROM daily_expenses";
$result_expenses = $conn->query($sql_expenses);
$row_expenses = $result_expenses->fetch_assoc();
$expenses = $row_expenses['total_expenses'] ?? 0;

$net_income = $income - $expenses;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Pendapatan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Rumah Makan Sederhana</a>
            <div class="d-flex">
                <span class="navbar-text text-white me-3">Welcome, <?= $_SESSION['username'] ?></span>
                <a href="dashboard_user.php" class="btn btn-outline-light btn-sm">Kembali</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Laporan Pendapatan</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-4">
                                <div class="bg-success text-white p-3 rounded text-center">
                                    <h3>Rp<?= number_format($income, 0, ',', '.') ?></h3>
                                    <p class="mb-0">Pemasukan</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-warning text-white p-3 rounded text-center">
                                    <h3>Rp<?= number_format($expenses, 0, ',', '.') ?></h3>
                                    <p class="mb-0">Pengeluaran</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="bg-info text-white p-3 rounded text-center">
                                    <h3>Rp<?= number_format($net_income, 0, ',', '.') ?></h3>
                                    <p class="mb-0">Bersih</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Tambah Pengeluaran</h5>
                    </div>
                    <div class="card-body">
                        <form id="expenseForm">
                            <div class="mb-3">
                                <label for="description" class="form-label">Deskripsi Pengeluaran</label>
                                <input type="text" class="form-control" id="description" name="description" required>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Jumlah Pengeluaran</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Tambah Pengeluaran</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Hasil Bersih</h5>
                    </div>
                    <div class="card-body">
                        <form id="sendResultForm">
                            <div class="mb-3">
                                <label for="result_description" class="form-label">Deskripsi Hasil</label>
                                <textarea class="form-control" id="result_description" rows="3" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="result_amount" class="form-label">Jumlah Hasil</label>
                                <input type="number" class="form-control" id="result_amount" value="<?= $net_income ?>" readonly>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Kirim Hasil</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('expenseForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            fetch('income.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Gagal menambahkan pengeluaran');
                }
            });
        });

        document.getElementById('sendResultForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Send results to admin dashboard
            fetch('send_results.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `amount=${document.getElementById('result_amount').value}&description=${document.getElementById('result_description').value}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Hasil berhasil dikirim ke admin');
                    // Reset form and income values
                    document.getElementById('result_description').value = '';
                    document.getElementById('result_amount').value = '0';
                    location.reload();
                } else {
                    alert('Gagal mengirim hasil');
                }
            });
        });
    </script>
</body>
</html>