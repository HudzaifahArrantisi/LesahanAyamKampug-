<?php
require 'config.php';
session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] != "admin") {
    header("Location: index.php");
    exit;
}

// Handle delete report
if (isset($_GET['delete_report'])) {
    $report_id = $_GET['delete_report'];
    
  
    try {
        $pdo->beginTransaction();
        
        // Delete expenses related to the report
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE daily_report_id = ?");
        $stmt->execute([$report_id]);
        
        // Delete the report
        $stmt = $pdo->prepare("DELETE FROM daily_reports WHERE id = ?");
        $stmt->execute([$report_id]);
        
        $pdo->commit();
        
        header("Location: dashboard_admin.php?delete_success=1");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Gagal menghapus laporan: " . $e->getMessage();
    }
}

// Ambil data laporan harian
$stmt = $pdo->query("SELECT dr.*, 
                    (SELECT COUNT(*) FROM expenses e WHERE e.daily_report_id = dr.id) as expense_count
                    FROM daily_reports dr 
                    WHERE dr.is_submitted = TRUE
                    ORDER BY dr.report_date DESC");
$daily_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Hitung total kumulatif bersih
$cumulative_net = 0;
$daily_reports_with_cumulative = [];
foreach ($daily_reports as $report) {
    $cumulative_net += $report['net_income'];
    $report['cumulative_net'] = $cumulative_net;
    $daily_reports_with_cumulative[] = $report;
}
$daily_reports = array_reverse($daily_reports_with_cumulative);

// Ambil data untuk chart (7 hari terakhir)
$chart_stmt = $pdo->query("SELECT report_date, total_income, total_expenses, net_income 
                          FROM daily_reports 
                          WHERE is_submitted = TRUE 
                          ORDER BY report_date DESC 
                          LIMIT 7");
$chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
$chart_data = array_reverse($chart_data);

// Hitung total mingguan dan bulanan
$weekly_stmt = $pdo->query("SELECT SUM(net_income) as weekly_net
                           FROM daily_reports 
                           WHERE is_submitted = TRUE 
                           AND report_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$weekly_totals = $weekly_stmt->fetch(PDO::FETCH_ASSOC);

$monthly_stmt = $pdo->query("SELECT SUM(net_income) as monthly_net
                            FROM daily_reports 
                            WHERE is_submitted = TRUE 
                            AND report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
$monthly_totals = $monthly_stmt->fetch(PDO::FETCH_ASSOC);

// Ambil data untuk perhitungan harian
$today = date('Y-m-d');
$today_stmt = $pdo->prepare("SELECT net_income FROM daily_reports WHERE report_date = ? AND is_submitted = TRUE");
$today_stmt->execute([$today]);
$today_net = $today_stmt->fetch(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin - Kasir</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
      max-width: 800px;
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
    .chart-container {
      position: relative;
      height: 300px;
      width: 100%;
    }
    @media (max-width: 768px) {
      .chart-container {
        height: 250px;
      }
    }
  </style>
</head>
<body class="bg-gradient-to-br from-gray-900 to-gray-800 min-h-screen text-gray-100 pb-20">
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
            <a href="dashboard_admin.php" class="flex items-center p-2 text-white bg-blue-700 rounded-lg">
              <i class="fas fa-tachometer-alt mr-3"></i> Dashboard
            </a>
          </li>
          <li>
            <a href="menu_management.php" class="flex items-center p-2 text-gray-300 hover:bg-gray-700 rounded-lg">
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
        <?php if (isset($error_message)): ?>
          <div class="bg-red-600 text-white p-4 rounded-lg mb-6 fade-in popup">
            <div class="flex justify-between items-center">
              <p><?= $error_message ?></p>
              <button onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['delete_success'])): ?>
          <div class="bg-green-600 text-white p-4 rounded-lg mb-6 fade-in popup">
            <div class="flex justify-between items-center">
              <p>Laporan berhasil dihapus!</p>
              <button onclick="this.parentElement.parentElement.style.display='none'">
                <i class="fas fa-times"></i>
              </button>
            </div>
          </div>
        <?php endif; ?>

        <h1 class="text-3xl md:text-4xl font-bold text-center mb-8 text-white">
          <i class="fas fa-tachometer-alt mr-3"></i>Dashboard Admin
        </h1>
        
        <!-- Ringkasan Statistik -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
          <div class="card bg-gradient-to-r from-blue-600 to-blue-800 rounded-lg shadow-lg p-6 fade-in">
            <div class="flex justify-between items-center">
              <div>
                <h3 class="text-sm font-medium text-blue-200 mb-1">Pendapatan Bersih Hari Ini</h3>
                <p class="text-2xl font-bold text-white">
                  Rp <?= isset($today_net['net_income']) ? number_format($today_net['net_income'], 0, ',', '.') : '0' ?>
                </p>
              </div>
              <i class="fas fa-money-bill-wave text-3xl text-blue-200"></i>
            </div>
          </div>
          
          <div class="card bg-gradient-to-r from-green-600 to-green-800 rounded-lg shadow-lg p-6 fade-in">
            <div class="flex justify-between items-center">
              <div>
                <h3 class="text-sm font-medium text-green-200 mb-1">Pendapatan Mingguan</h3>
                <p class="text-2xl font-bold text-white">
                  Rp <?= number_format($weekly_totals['weekly_net'] ?? 0, 0, ',', '.') ?>
                </p>
              </div>
              <i class="fas fa-chart-line text-3xl text-green-200"></i>
            </div>
          </div>
          
          <div class="card bg-gradient-to-r from-purple-600 to-purple-800 rounded-lg shadow-lg p-6 fade-in">
            <div class="flex justify-between items-center">
              <div>
                <h3 class="text-sm font-medium text-purple-200 mb-1">Pendapatan Bulanan</h3>
                <p class="text-2xl font-bold text-white">
                  Rp <?= number_format($monthly_totals['monthly_net'] ?? 0, 0, ',', '.') ?>
                </p>
              </div>
              <i class="fas fa-calendar-alt text-3xl text-purple-200"></i>
            </div>
          </div>
        </div>

        <!-- Chart Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
          <!-- Income vs Expenses Chart -->
          <div class="bg-gray-800 rounded-lg shadow-lg p-6 card fade-in">
            <h2 class="text-xl font-semibold mb-6 text-white border-b border-gray-700 pb-2">
              <i class="fas fa-chart-bar mr-2 text-yellow-400"></i>Pendapatan vs Pengeluaran (7 Hari)
            </h2>
            <div class="chart-container">
              <canvas id="incomeExpensesChart"></canvas>
            </div>
          </div>

          <!-- Net Income Trend -->
          <div class="bg-gray-800 rounded-lg shadow-lg p-6 card fade-in">
            <h2 class="text-xl font-semibold mb-6 text-white border-b border-gray-700 pb-2">
              <i class="fas fa-chart-line mr-2 text-yellow-400"></i>Trend Pendapatan Bersih (7 Hari)
            </h2>
            <div class="chart-container">
              <canvas id="netIncomeChart"></canvas>
            </div>
          </div>
        </div>

        <!-- Daftar Laporan Harian -->
        <div class="bg-gray-800 rounded-lg shadow-lg p-6 card fade-in">
          <h2 class="text-xl font-semibold mb-6 text-white border-b border-gray-700 pb-2">
            <i class="fas fa-file-alt mr-2 text-yellow-400"></i>Laporan Harian
          </h2>
          
          <?php if (empty($daily_reports)): ?>
            <div class="text-center py-8 bg-gray-700 rounded-lg">
              <i class="fas fa-file-alt text-4xl text-gray-500 mb-3"></i>
              <p class="text-gray-400">Belum ada laporan harian</p>
            </div>
          <?php else: ?>
            <div class="overflow-x-auto">
              <table class="w-full text-sm text-left text-gray-300">
                <thead class="text-xs text-gray-400 uppercase bg-gray-700">
                  <tr>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">Pendapatan</th>
                    <th class="px-4 py-3">Pengeluaran</th>
                    <th class="px-4 py-3">Bersih</th>
                    <th class="px-4 py-3">Kumulatif</th>
                    <th class="px-4 py-3">Aksi</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($daily_reports as $report): ?>
                    <tr class="border-b border-gray-700 hover:bg-gray-700 transition-colors">
                      <td class="px-4 py-3 font-medium text-white">
                        <?= formatHariTanggal($report['report_date']) ?>
                      </td>
                      <td class="px-4 py-3 text-green-400">
                        Rp <?= number_format($report['total_income'], 0, ',', '.') ?>
                      </td>
                      <td class="px-4 py-3 text-red-400">
                        Rp <?= number_format($report['total_expenses'], 0, ',', '.') ?>
                      </td>
                      <td class="px-4 py-3 font-semibold <?= $report['net_income'] >= 0 ? 'text-green-400' : 'text-red-400' ?>">
                        Rp <?= number_format($report['net_income'], 0, ',', '.') ?>
                      </td>
                      <td class="px-4 py-3 font-semibold <?= $report['cumulative_net'] >= 0 ? 'text-blue-400' : 'text-red-400' ?>">
                        Rp <?= number_format($report['cumulative_net'], 0, ',', '.') ?>
                      </td>
                      <td class="px-4 py-3">
                        <div class="flex space-x-2">
                          <button onclick="viewReportDetails(<?= $report['id'] ?>)"
                                  class="px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs transition-colors transform hover:scale-110">
                            <i class="fas fa-eye"></i>
                          </button>
                          <button onclick="confirmDelete(<?= $report['id'] ?>, '<?= formatHariTanggal($report['report_date']) ?>')"
                                  class="px-3 py-1 bg-red-600 hover:bg-red-700 rounded text-xs transition-colors transform hover:scale-110">
                            <i class="fas fa-trash"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Report Details Modal -->
  <div id="reportModal" class="modal-overlay hidden">
    <div class="modal-content popup">
      <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold text-white">Detail Laporan</h3>
        <button onclick="closeReportModal()" class="text-gray-400 hover:text-white">
          <i class="fas fa-times text-xl"></i>
        </button>
      </div>
      <div id="reportDetails"></div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal-overlay hidden">
    <div class="modal-content popup">
      <div class="text-center">
        <i class="fas fa-exclamation-triangle text-4xl text-yellow-400 mb-4"></i>
        <h3 class="text-xl font-semibold text-white mb-2">Hapus Laporan</h3>
        <p class="text-gray-300 mb-6" id="deleteMessage">Apakah Anda yakin ingin menghapus laporan ini?</p>
        <div class="flex space-x-4 justify-center">
          <button id="confirmDelete" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-md font-semibold transition-colors transform hover:scale-105">
            <i class="fas fa-trash mr-2"></i>Hapus
          </button>
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

    // Chart Data
    const chartData = <?= json_encode($chart_data) ?>;

    // Income vs Expenses Chart
    const incomeExpensesCtx = document.getElementById('incomeExpensesChart').getContext('2d');
    new Chart(incomeExpensesCtx, {
      type: 'bar',
      data: {
        labels: chartData.map(item => new Date(item.report_date).toLocaleDateString('id-ID', { 
          day: 'numeric', 
          month: 'short' 
        })),
        datasets: [
          {
            label: 'Pendapatan',
            data: chartData.map(item => item.total_income),
            backgroundColor: 'rgba(34, 197, 94, 0.8)',
            borderColor: 'rgba(34, 197, 94, 1)',
            borderWidth: 1
          },
          {
            label: 'Pengeluaran',
            data: chartData.map(item => item.total_expenses),
            backgroundColor: 'rgba(239, 68, 68, 0.8)',
            borderColor: 'rgba(239, 68, 68, 1)',
            borderWidth: 1
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(255, 255, 255, 0.1)'
            },
            ticks: {
              color: 'rgba(255, 255, 255, 0.7)',
              callback: function(value) {
                return 'Rp ' + value.toLocaleString('id-ID');
              }
            }
          },
          x: {
            grid: {
              color: 'rgba(255, 255, 255, 0.1)'
            },
            ticks: {
              color: 'rgba(255, 255, 255, 0.7)'
            }
          }
        },
        plugins: {
          legend: {
            labels: {
              color: 'rgba(255, 255, 255, 0.7)'
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return context.dataset.label + ': Rp ' + context.raw.toLocaleString('id-ID');
              }
            }
          }
        }
      }
    });

    // Net Income Chart
    const netIncomeCtx = document.getElementById('netIncomeChart').getContext('2d');
    new Chart(netIncomeCtx, {
      type: 'line',
      data: {
        labels: chartData.map(item => new Date(item.report_date).toLocaleDateString('id-ID', { 
          day: 'numeric', 
          month: 'short' 
        })),
        datasets: [{
          label: 'Pendapatan Bersih',
          data: chartData.map(item => item.net_income),
          backgroundColor: 'rgba(59, 130, 246, 0.2)',
          borderColor: 'rgba(59, 130, 246, 1)',
          borderWidth: 2,
          fill: true,
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            grid: {
              color: 'rgba(255, 255, 255, 0.1)'
            },
            ticks: {
              color: 'rgba(255, 255, 255, 0.7)',
              callback: function(value) {
                return 'Rp ' + value.toLocaleString('id-ID');
              }
            }
          },
          x: {
            grid: {
              color: 'rgba(255, 255, 255, 0.1)'
            },
            ticks: {
              color: 'rgba(255, 255, 255, 0.7)'
            }
          }
        },
        plugins: {
          legend: {
            labels: {
              color: 'rgba(255, 255, 255, 0.7)'
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return 'Pendapatan Bersih: Rp ' + context.raw.toLocaleString('id-ID');
              }
            }
          }
        }
      }
    });

    // Modal functions
    async function viewReportDetails(reportId) {
      try {
        const response = await fetch(`get_report_details.php?report_id=${reportId}`);
        const data = await response.text();
        
        document.getElementById('reportDetails').innerHTML = data;
        document.getElementById('reportModal').classList.remove('hidden');
        
        setTimeout(() => {
          document.getElementById('reportModal').classList.add('show');
        }, 10);
      } catch (error) {
        console.error('Error:', error);
      }
    }

    function closeReportModal() {
      document.getElementById('reportModal').classList.remove('show');
      setTimeout(() => {
        document.getElementById('reportModal').classList.add('hidden');
      }, 300);
    }

    let reportIdToDelete = null;

    function confirmDelete(id, date) {
      reportIdToDelete = id;
      document.getElementById('deleteMessage').textContent = `Apakah Anda yakin ingin menghapus laporan tanggal ${date}?`;
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
      reportIdToDelete = null;
    }

    document.getElementById('confirmDelete').addEventListener('click', function() {
      if (reportIdToDelete) {
        window.location.href = `dashboard_admin.php?delete_report=${reportIdToDelete}`;
      }
    });

    // Auto-hide success messages
    setTimeout(() => {
      const successMessages = document.querySelectorAll('.bg-green-600');
      successMessages.forEach(msg => {
        msg.style.display = 'none';
      });
    }, 5000);
  </script>
</body>
</html>