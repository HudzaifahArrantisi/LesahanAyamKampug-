<?php
require 'config.php';
require_once 'vendor/autoload.php';

use Ramsey\Uuid\Uuid;

session_start();

// Helper: escape output
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// Generate UUID helper
function generateUUID() {
    return Uuid::uuid4()->toString();
}

// Return rendered HTML for the day modal
function render_day_modal_html($pdo, $date) {
    // Get bon history for that date
    $stmt = $pdo->prepare("SELECT * FROM bon_history WHERE DATE(payment_date) = ? ORDER BY payment_time DESC, id DESC");
    $stmt->execute([$date]);
    $bon_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total income for the day from bon_history
    $stmt = $pdo->prepare("SELECT SUM(total_amount) FROM bon_history WHERE DATE(payment_date) = ?");
    $stmt->execute([$date]);
    $total_income = (float)$stmt->fetchColumn() ?? 0.00;

    // Get daily report data
    $stmt = $pdo->prepare("SELECT dr.* FROM daily_reports dr WHERE dr.report_date = ?");
    $stmt->execute([$date]);
    $daily_report = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get expense items for that date
    $expense_items = [];
    $total_expenses = 0.00;
    $report_id = null;
    $is_submitted = false;

    if ($daily_report) {
        $report_id = $daily_report['id'];
        $is_submitted = (bool)$daily_report['is_submitted'];
        $total_expenses = (float)$daily_report['total_expenses'];
        
        // Get individual expense items
        try {
            $stmt = $pdo->prepare("SELECT * FROM expenses WHERE daily_report_id = ?");
            $stmt->execute([$report_id]);
            $expense_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Fallback if expenses table doesn't have the structure
            if ($total_expenses > 0) {
                $expense_items = [['item_name' => 'Pengeluaran Harian', 'total_expenses' => $total_expenses]];
            }
        }
    }
    
    $net_income = $total_income - $total_expenses;

    ob_start();
    ?>
    <div class="space-y-4">
      <div class="bg-gray-800 p-4 rounded-lg">
        <h3 class="text-lg font-semibold">Ringkasan Harian - <?= date('d M Y', strtotime($date)) ?></h3>
        <div class="mt-3 space-y-2">
          <div class="flex justify-between text-sm">
            <span>Pemasukan</span>
            <span class="text-green-400">Rp <?= number_format($total_income, 0, ',', '.') ?></span>
          </div>
          <div class="flex justify-between text-sm">
            <span>Pengeluaran</span>
            <span class="text-red-400">Rp <?= number_format($total_expenses, 0, ',', '.') ?></span>
          </div>
         <div class="flex justify-between text-sm font-semibold border-t border-gray-700 pt-2">
          <span>Pendapatan Bersih</span>
          <span class="<?= $net_income >= 0 
              ? 'text-blue-400 bg-blue-900/50 px-3 py-1 rounded-full' 
              : 'text-red-400 border border-red-400 px-3 py-1 rounded-full' ?>">
            Rp <?= number_format($net_income, 0, ',', '.') ?>
          </span>
        </div>

        </div>
        <?php if (!$is_submitted): ?>
          <div class="mt-4 flex gap-2 flex-wrap">
            <button onclick="openEditModal('<?= $date ?>')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
              <i class="fas fa-edit mr-2"></i>Edit Pengeluaran
            </button>
            <button onclick="submitReport('<?= $date ?>')" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
              <i class="fas fa-paper-plane mr-2"></i>Kirim Laporan
            </button>
            <button onclick="deleteDailyReport('<?= $date ?>')" class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
              <i class="fas fa-trash mr-2"></i>Hapus Hari Ini
            </button>
          </div>
        <?php else: ?>
          <div class="mt-4 p-3 bg-green-900 text-green-400 rounded-lg text-center">
            <i class="fas fa-check-circle mr-2"></i>Laporan sudah dikirim ke admin dan tidak dapat diedit.
          </div>
        <?php endif; ?>
      </div>

      <div class="bg-gray-800 p-4 rounded-lg">
        <h3 class="text-lg font-semibold mb-3">Daftar Pengeluaran</h3>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-left text-gray-400 text-xs uppercase bg-gray-700">
              <tr>
                <th class="p-3">Item Pengeluaran</th>
                <th class="p-3 text-right">Jumlah</th>
              </tr>
            </thead>
            <tbody>
            <?php if (!empty($expense_items)): ?>
              <?php foreach ($expense_items as $it): ?>
                <tr class="border-b border-gray-700">
                  <td class="p-3"><?= e($it['item_name'] ?? 'Pengeluaran Harian') ?></td>
                  <td class="p-3 text-right text-red-400 font-medium">
                    Rp <?= number_format($it['total_expenses'] ?? $it['amount'] ?? 0, 0, ',', '.') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="2" class="p-4 text-center text-gray-400">
                  <i class="fas fa-receipt text-2xl mb-2"></i><br>
                  Belum ada pengeluaran untuk hari ini.
                </td>
              </tr>
            <?php endif; ?>
            </tbody>
            <tfoot class="bg-gray-700">
              <tr>
                <td class="p-3 font-semibold">Total Pengeluaran</td>
                <td class="p-3 text-right font-semibold text-red-400">
                  Rp <?= number_format($total_expenses, 0, ',', '.') ?>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="bg-gray-800 p-4 rounded-lg">
        <h3 class="text-lg font-semibold mb-3">Daftar Bon</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <?php foreach ($bon_list as $b): 
              // get uuid if exists
              $stmt = $pdo->prepare("SELECT uuid FROM bon_uuid WHERE bon_history_id = ?");
              $stmt->execute([$b['id']]);
              $uuid = $stmt->fetchColumn();
              if (!$uuid) {
                  $uuid = generateUUID();
                  $stmt = $pdo->prepare("INSERT INTO bon_uuid (bon_history_id, uuid) VALUES (?, ?)");
                  try { 
                      $stmt->execute([$b['id'], $uuid]); 
                  } catch (Exception $e) {
                      // UUID mungkin sudah ada, ambil lagi
                      $stmt = $pdo->prepare("SELECT uuid FROM bon_uuid WHERE bon_history_id = ?");
                      $stmt->execute([$b['id']]);
                      $uuid = $stmt->fetchColumn() ?? $uuid;
                  }
              }

              // payment label
              $method = $b['payment_method'] ?? 'cash';
              $bank = $b['payment_bank'] ?? null;
              $label = getPaymentMethodLabel($method, $bank);
              $badgeClass = getPaymentMethodBadgeClass($method);
          ?>
          <div class="bg-gray-700 rounded-lg p-4 shadow-lg relative">
            <!-- Hapus Bon Button -->
            <button onclick="deleteBon(<?= $b['id'] ?>, '<?= $date ?>')" 
                    class="absolute top-2 right-2 text-red-400 hover:text-red-300 transition-colors"
                    title="Hapus Bon">
              <i class="fas fa-times-circle"></i>
            </button>
            
            <div class="flex justify-between items-start mb-3">
              <div>
                <div class="text-sm text-gray-300">Bon #<?= e($b['id']) ?></div>
                <div class="text-lg font-bold text-white mt-1">
                  Rp <?= number_format($b['total_amount'] ?? 0, 0, ',', '.') ?>
                </div>
              </div>
              <span class="text-xs px-2 py-1 rounded-full <?= $badgeClass ?>">
                <?= $label ?>
              </span>
            </div>
            
            <div class="text-xs text-gray-400 mb-3">
              <i class="fas fa-clock mr-1"></i>
              <?= date('H:i', strtotime($b['payment_time'] ?? $b['payment_date'])) ?>
              • Meja <?= e($b['table_number']) ?>
            </div>
            
            <div class="flex gap-2">
              <button onclick="openBonDetailModal(<?= $b['id'] ?>)" 
                      class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded text-sm transition-colors">
                <i class="fas fa-eye mr-1"></i> Detail
              </button>
              <button onclick="openBonActionModal(<?= $b['id'] ?>, '<?= $uuid ?>')" 
                      class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 px-3 rounded text-sm transition-colors">
                <i class="fas fa-cog mr-1"></i> Aksi
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        
        <?php if (empty($bon_list)): ?>
          <div class="text-center py-8 text-gray-400">
            <i class="fas fa-receipt text-3xl mb-2"></i>
            <p>Tidak ada transaksi bon untuk hari ini.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
    $html = ob_get_clean();
    return $html;
}

// Render bon detail HTML for modal
function render_bon_detail_html($pdo, $bon_id) {
    $stmt = $pdo->prepare("SELECT bh.*, u.username FROM bon_history bh LEFT JOIN users u ON bh.created_by = u.id WHERE bh.id = ?");
    $stmt->execute([$bon_id]);
    $bon = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT d.*, m.name, m.category FROM bon_detail_history d JOIN menu m ON d.menu_id = m.id WHERE d.bon_history_id = ?");
    $stmt->execute([$bon_id]);
    $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = array_reduce($details, function($carry, $item) {
        return $carry + $item['subtotal'];
    }, 0);

    // Group items by category
    $items_by_category = [];
    foreach ($details as $item) {
        $category = $item['category'];
        if (!isset($items_by_category[$category])) {
            $items_by_category[$category] = [];
        }
        $items_by_category[$category][] = $item;
    }

    ob_start();
    ?>
    <div class="space-y-6">
      <div class="text-center border-b border-gray-700 pb-4">
        <h3 class="text-2xl font-bold text-yellow-400">Detail Bon #<?= e($bon['id']) ?></h3>
        <div class="grid grid-cols-2 gap-4 mt-3 text-sm">
          <div class="text-left">
            <div class="text-gray-400">Meja</div>
            <div class="font-semibold"><?= e($bon['table_number']) ?></div>
          </div>
          <div class="text-right">
            <div class="text-gray-400">Kasir</div>
            <div class="font-semibold"><?= e($bon['username'] ?? 'Unknown') ?></div>
          </div>
          <div class="text-left">
            <div class="text-gray-400">Waktu</div>
            <div class="font-semibold"><?= date('d M Y H:i', strtotime($bon['payment_date'])) ?></div>
          </div>
          <div class="text-right">
            <div class="text-gray-400">Status</div>
            <div class="font-semibold <?= $bon['status'] === 'completed' ? 'text-green-400' : 'text-yellow-400' ?>">
              <?= strtoupper($bon['status']) ?>
            </div>
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <?php foreach ($items_by_category as $category => $items): ?>
          <div>
            <h4 class="font-semibold text-lg mb-2 text-blue-400 border-b border-gray-700 pb-1"><?= e($category) ?></h4>
            <div class="space-y-2">
              <?php foreach ($items as $item): ?>
                <div class="flex justify-between items-center bg-gray-700 p-3 rounded">
                  <div>
                    <div class="font-medium"><?= e($item['name']) ?></div>
                    <div class="text-sm text-gray-400">Rp <?= number_format($item['price'], 0, ',', '.') ?> x <?= $item['quantity'] ?></div>
                  </div>
                  <div class="font-semibold">Rp <?= number_format($item['subtotal'], 0, ',', '.') ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="border-t border-gray-700 pt-4">
        <div class="flex justify-between items-center text-lg font-bold">
          <span>TOTAL</span>
          <span class="text-yellow-400">Rp <?= number_format($total, 0, ',', '.') ?></span>
        </div>
        
        <div class="mt-3 grid grid-cols-2 gap-4 text-sm">
          <div>
            <div class="text-gray-400">Metode Bayar</div>
            <div class="font-semibold"><?= getPaymentMethodLabel($bon['payment_method'] ?? 'cash', $bon['payment_bank'] ?? null) ?></div>
          </div>
          <div class="text-right">
            <div class="text-gray-400">ID Pembayaran</div>
            <div class="font-semibold"><?= e($bon['payment_id'] ?? '-') ?></div>
          </div>
        </div>
      </div>

      <div class="flex gap-2 justify-end pt-4 border-t border-gray-700">
        <button onclick="printBon(<?= $bon['id'] ?>)" class="px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
          <i class="fas fa-print mr-2"></i>Print Bon
        </button>
        <button onclick="closeBonDetailModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors">
          Tutup
        </button>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX endpoints
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['action']) {
            case 'get_expenses':
                if (isset($_GET['date'])) {
                    $date = $_GET['date'];
                    $stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE report_date = ?");
                    $stmt->execute([$date]);
                    $report = $stmt->fetch();
                    
                    $result = ['expenses' => []];
                    if ($report) {
                        // Get individual expense items
                        try {
                            $stmt = $pdo->prepare("SELECT item_name, total_expenses as amount FROM expenses WHERE daily_report_id = ?");
                            $stmt->execute([$report['id']]);
                            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            if ($items) {
                                $result['expenses'] = $items;
                            } else {
                                // Fallback to single expense entry
                                $stmt = $pdo->prepare("SELECT total_expenses as amount FROM daily_reports WHERE id = ?");
                                $stmt->execute([$report['id']]);
                                $expense = $stmt->fetch();
                                if ($expense && $expense['amount'] > 0) {
                                    $result['expenses'] = [['item_name' => 'Pengeluaran Harian', 'amount' => $expense['amount']]];
                                }
                            }
                        } catch (Exception $e) {
                            // Use fallback if expenses table doesn't exist
                            $stmt = $pdo->prepare("SELECT total_expenses as amount FROM daily_reports WHERE id = ?");
                            $stmt->execute([$report['id']]);
                            $expense = $stmt->fetch();
                            if ($expense && $expense['amount'] > 0) {
                                $result['expenses'] = [['item_name' => 'Pengeluaran Harian', 'amount' => $expense['amount']]];
                            }
                        }
                    }
                    echo json_encode($result);
                }
                break;
                
            case 'get_day_data':
                if (isset($_GET['date'])) {
                    $date = $_GET['date'];
                    $html = render_day_modal_html($pdo, $date);
                    echo json_encode(['html' => $html]);
                }
                break;
                
            case 'get_bon_detail':
                if (isset($_GET['bon_id'])) {
                    $bon_id = $_GET['bon_id'];
                    $html = render_bon_detail_html($pdo, $bon_id);
                    echo json_encode(['html' => $html]);
                }
                break;
                
            case 'submit_report':
                if (isset($_GET['date'])) {
                    $date = $_GET['date'];
                    $stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE report_date = ?");
                    $stmt->execute([$date]);
                    $report = $stmt->fetch();
                    
                    if ($report) {
                        $stmt = $pdo->prepare("UPDATE daily_reports SET is_submitted = 1, submitted_at = NOW() WHERE id = ?");
                        $stmt->execute([$report['id']]);
                        echo json_encode(['success' => true]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Laporan tidak ditemukan']);
                    }
                }
                break;
                
            case 'delete_daily_report':
                if (isset($_GET['date'])) {
                    $date = $_GET['date'];
                    $pdo->beginTransaction();
                    
                    try {
                        // Delete related expenses first
                        $stmt = $pdo->prepare("DELETE FROM expenses WHERE daily_report_id IN (SELECT id FROM daily_reports WHERE report_date = ?)");
                        $stmt->execute([$date]);
                        
                        // Delete daily report
                        $stmt = $pdo->prepare("DELETE FROM daily_reports WHERE report_date = ?");
                        $stmt->execute([$date]);
                        
                        $pdo->commit();
                        echo json_encode(['success' => true]);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
                    }
                }
                break;
                
            case 'delete_bon':
                if (isset($_GET['bon_id']) && isset($_GET['date'])) {
                    $bon_id = $_GET['bon_id'];
                    $date = $_GET['date'];
                    $pdo->beginTransaction();
                    
                    try {
                        // Delete bon detail history
                        $stmt = $pdo->prepare("DELETE FROM bon_detail_history WHERE bon_history_id = ?");
                        $stmt->execute([$bon_id]);
                        
                        // Delete bon uuid
                        $stmt = $pdo->prepare("DELETE FROM bon_uuid WHERE bon_history_id = ?");
                        $stmt->execute([$bon_id]);
                        
                        // Delete bon history
                        $stmt = $pdo->prepare("DELETE FROM bon_history WHERE id = ?");
                        $stmt->execute([$bon_id]);
                        
                        $pdo->commit();
                        
                        // Recalculate daily report
                        recalculateDailyReport($pdo, $date);
                        
                        echo json_encode(['success' => true]);
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => 'Gagal menghapus bon: ' . $e->getMessage()]);
                    }
                }
                break;
        }
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit;
    }
}

// Function to recalculate daily report after bon deletion
function recalculateDailyReport($pdo, $date) {
    // Calculate total income from remaining bon_history
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM bon_history WHERE DATE(payment_date) = ?");
    $stmt->execute([$date]);
    $total_income = (float)$stmt->fetchColumn();

    // Get daily report
    $stmt = $pdo->prepare("SELECT id, total_expenses FROM daily_reports WHERE report_date = ?");
    $stmt->execute([$date]);
    $report = $stmt->fetch();

    if ($report) {
        $total_expenses = (float)$report['total_expenses'];
        $net_income = $total_income - $total_expenses;
        
        // Update daily report
        $stmt = $pdo->prepare("UPDATE daily_reports SET total_income = ?, net_income = ? WHERE id = ?");
        $stmt->execute([$total_income, $net_income, $report['id']]);
    }
}

// Handle update_expenses via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_expenses'])) {
    $report_date = $_POST['report_date'] ?? null;
    
    if (!$report_date) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Tanggal tidak valid']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Calculate total income from bon_history
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) FROM bon_history WHERE DATE(payment_date) = ?");
        $stmt->execute([$report_date]);
        $total_income = (float)$stmt->fetchColumn();

        // Get or create daily report
        $stmt = $pdo->prepare("SELECT id FROM daily_reports WHERE report_date = ?");
        $stmt->execute([$report_date]);
        $report = $stmt->fetch();

        if (!$report) {
            $stmt = $pdo->prepare("INSERT INTO daily_reports (report_date, total_income, total_expenses, net_income) VALUES (?, ?, 0, ?)");
            $stmt->execute([$report_date, $total_income, $total_income]);
            $report_id = $pdo->lastInsertId();
        } else {
            $report_id = $report['id'];
        }

        // Calculate total expenses from form
        $total_expenses = 0.00;
        $expenses = $_POST['expenses'] ?? [];

        // Clear existing expenses
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE daily_report_id = ?");
        $stmt->execute([$report_id]);

        // Insert new expenses
        foreach ($expenses as $exp) {
            $name = trim($exp['name'] ?? '');
            $amount = (float)($exp['amount'] ?? 0);
            
            if ($name !== '' && $amount > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO expenses (daily_report_id, report_date, item_name, total_income, total_expenses, net_income) 
                    VALUES (?, ?, ?, 0, ?, 0)
                ");
                $stmt->execute([$report_id, $report_date, $name, $amount]);

                $total_expenses += $amount;
            }
        }

        // If no individual expenses, use the first amount as total
        if ($total_expenses == 0 && !empty($expenses)) {
            $first_expense = $expenses[0];
            $total_expenses = (float)($first_expense['amount'] ?? 0);
        }

        // Update daily report
        $net_income = $total_income - $total_expenses;
        $stmt = $pdo->prepare("UPDATE daily_reports SET total_income = ?, total_expenses = ?, net_income = ? WHERE id = ?");
        $stmt->execute([$total_income, $total_expenses, $net_income, $report_id]);

        $pdo->commit();

        // Return updated data for grid
        $grid_data = [
            'total_income' => $total_income,
            'total_expenses' => $total_expenses,
            'net_income' => $net_income
        ];

        // Return updated HTML
        $html = render_day_modal_html($pdo, $report_date);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'html' => $html,
            'grid_data' => $grid_data
        ]);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
        exit;
    }
}

// The rest of the original page
$stmt = $pdo->query("
  SELECT DATE(bh.payment_date) as tanggal, 
       COUNT(*) as jumlah_transaksi,
       SUM(bh.total_amount) as total_pemasukan,
       COALESCE(MAX(dr.total_expenses), 0) as total_expenses,
       COALESCE(MAX(dr.net_income), SUM(bh.total_amount)) as net_income,
       COALESCE(MAX(dr.is_submitted), 0) as is_submitted
FROM bon_history bh
LEFT JOIN daily_reports dr 
       ON DATE(bh.payment_date) = dr.report_date
WHERE bh.payment_date IS NOT NULL
GROUP BY DATE(bh.payment_date)
ORDER BY tanggal DESC;
");
$riwayat_per_hari = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helpers used by the template
function formatHariTanggal($tanggal) {
    $hari = [
        'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
    ];
    $bulan = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
    ];
    $day = $hari[date('l', strtotime($tanggal))];
    $tgl = date('d', strtotime($tanggal));
    $bln = $bulan[date('m', strtotime($tanggal))];
    $thn = date('Y', strtotime($tanggal));
    return "$day, $tgl $bln $thn";
}

function getPaymentMethodLabel($method, $bank = null) {
    $methods = ['cash' => 'CASH', 'qris' => 'QRIS', 'transfer' => 'TRANSFER'];
    $label = $methods[$method] ?? strtoupper($method);
    if ($method === 'qris' && $bank) {
        $label .= " ($bank)";
    }
    return $label;
}

function getPaymentMethodBadgeClass($method) {
    switch ($method) {
        case 'cash': return 'bg-yellow-500 text-gray-900';
        case 'qris': return 'bg-purple-500 text-white';
        case 'transfer': return 'bg-blue-500 text-white';
        default: return 'bg-gray-500 text-white';
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Riwayat Bon - Kasir</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    .modal-overlay { 
        position: fixed; 
        inset: 0; 
        background: rgba(0,0,0,0.8); 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        z-index: 9999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    .modal-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    .modal-content { 
        background: #1f2937; 
        padding: 2rem;
        border-radius: 1rem; 
        width: 95%; 
        max-width: 900px; 
        max-height: 85vh; 
        overflow: auto;
        transform: scale(0.9);
        transition: transform 0.3s ease;
    }
    .modal-overlay.show .modal-content {
        transform: scale(1);
    }
    .card-hover {
        transition: all 0.3s ease;
    }
    .card-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }
    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid rgba(255,255,255,.3);
        border-radius: 50%;
        border-top-color: #fff;
        animation: spin 1s ease-in-out infinite;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    .fade-in {
        animation: fadeIn 0.5s ease-in;
    }
        #qrcodeContainer canvas,
        #qrcodeContainer img {
          width: 220px !important;
          height: 220px !important;
        }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-gray-900 to-gray-800 text-gray-100">
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
  <!-- Loading Overlay -->
  <div id="globalLoading" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-gray-800 p-6 rounded-lg flex items-center gap-3">
      <div class="loading-spinner"></div>
      <span class="text-white">Memuat...</span>
    </div>
  </div>

  <div class="container mx-auto px-4 py-8 max-w-7xl">
    <div class="text-center mb-10">
      <h1 class="text-4xl font-bold text-white mb-2">
        <i class="fas fa-history text-blue-400 mr-3"></i>Riwayat Bon
      </h1>
      <p class="text-gray-400">Kelola dan pantau semua transaksi harian</p>
    </div>

    <?php if (empty($riwayat_per_hari)): ?>
      <div class="text-center py-20 bg-gray-800 rounded-2xl shadow-lg">
        <i class="fas fa-receipt text-6xl text-gray-600 mb-4"></i>
        <p class="text-xl text-gray-400 mb-2">Belum ada riwayat transaksi</p>
        <p class="text-gray-500">Transaksi yang dilakukan akan muncul di sini</p>
      </div>
    <?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
  <?php foreach ($riwayat_per_hari as $hari): ?>
    <?php 
      $tanggal = $hari['tanggal'];
      $total_pemasukan = (float)($hari['total_pemasukan'] ?? 0);
      $total_pengeluaran = (float)($hari['total_expenses'] ?? 0);

      // pastikan net income selalu terhitung
      $net_income = $total_pemasukan - $total_pengeluaran;

      $is_submitted = (bool)($hari['is_submitted'] ?? false);
    ?>
    
    <div id="card_<?= $tanggal ?>" 
         class="card-hover bg-gray-800 rounded-2xl p-6 shadow-lg cursor-pointer border border-gray-700 fade-in"
         onclick="openDayModal('<?= $tanggal ?>')">

      <div class="flex justify-between items-start mb-4">
        <div>
          <div class="text-sm text-gray-400 mb-1"><?= formatHariTanggal($tanggal) ?></div>
          <!-- Net Income (Bersih) -->
          <div id="bersih_<?= $tanggal ?>" 
               class="text-2xl font-bold <?= $net_income < 0 ? 'text-red-400' : 'text-white' ?>">
            Rp <?= number_format($net_income, 0, ',', '.') ?>
          </div>
        </div>
        <?php if ($is_submitted): ?>
          <span class="px-2 py-1 bg-green-600 text-green-100 text-xs rounded-full">
            <i class="fas fa-check mr-1"></i> Terkirim
          </span>
        <?php else: ?>
          <span class="px-2 py-1 bg-yellow-600 text-yellow-100 text-xs rounded-full">
            <i class="fas fa-edit mr-1"></i> Draft
          </span>
        <?php endif; ?>
      </div>

      <div class="space-y-2 text-sm">
        <div class="flex justify-between">
          <span class="text-gray-400">Pemasukan:</span>
          <span id="pemasukan_<?= $tanggal ?>" class="text-green-400">
            Rp <?= number_format($total_pemasukan, 0, ',', '.') ?>
          </span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-400">Pengeluaran:</span>
          <span id="pengeluaran_<?= $tanggal ?>" class="text-red-400">
            Rp <?= number_format($total_pengeluaran, 0, ',', '.') ?>
          </span>
        </div>
      </div>

      <div class="mt-4 pt-4 border-t border-gray-700">
        <div class="flex justify-between items-center text-xs text-gray-400">
          <span><?= $hari['jumlah_transaksi'] ?> transaksi</span>
          <button onclick="event.stopPropagation(); openDayModal('<?= $tanggal ?>')" 
                  class="text-blue-400 hover:text-blue-300 transition-colors">
            <i class="fas fa-external-link-alt mr-1"></i> Buka
          </button>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

    <?php endif; ?>
  </div>

  <!-- Day Detail Modal -->
  <div id="dayModal" class="modal-overlay">
    <div class="modal-content">
      <div class="flex justify-between items-center mb-6">
        <h2 id="dayModalTitle" class="text-2xl font-bold text-white"></h2>
        <button onclick="closeDayModal()" class="text-gray-400 hover:text-white text-2xl">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div id="dayModalContent" class="space-y-4">
        <!-- Content will be loaded via AJAX -->
      </div>
    </div>
  </div>

  <!-- Edit Expenses Modal -->
  <div id="editExpensesModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-white">Edit Pengeluaran</h2>
        <button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-2xl">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <form id="expensesForm" method="POST">
        <input type="hidden" name="update_expenses" value="1">
        <input type="hidden" id="report_date" name="report_date">
        
        <div id="expensesContainer" class="space-y-4 mb-6">
          <!-- Dynamic expenses fields will be added here -->
        </div>
        
        <div class="flex gap-3 justify-end">
          <button type="button" onclick="addExpenseField()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors">
            <i class="fas fa-plus mr-2"></i>Tambah Item
          </button>
          <button type="submit" id="saveExpensesBtn" class="px-6 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
            <i class="fas fa-save mr-2"></i>Simpan
          </button>
          <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors">
            Batal
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Bon Detail Modal -->
  <div id="bonDetailModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 500px;">
      <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold text-white">Detail Bon</h2>
        <button onclick="closeBonDetailModal()" class="text-gray-400 hover:text-white text-2xl">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div id="bonDetailContent">
        <!-- Content will be loaded via AJAX -->
      </div>
    </div>
  </div>

<!-- Bon Action Modal -->
<div id="bonActionModal" class="modal-overlay">
  <div class="modal-content" style="max-width: 400px;">
    <div class="flex justify-between items-center mb-6">
      <h2 class="text-2xl font-bold text-white">Aksi Bon</h2>
      <button onclick="closeBonActionModal()" class="text-gray-400 hover:text-white text-2xl">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="space-y-4">
      
      <!-- QR Code Container -->
<div id="qrcodeContainer" 
     class="flex justify-center items-center mb-10 rounded-lg  w-[210px] h-[210px] mx-auto">
</div>


      <div class="grid grid-cols-1 gap-3">
        <a id="viewBonLink" target="_blank" class="block text-center px-4 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
          <i class="fas fa-eye mr-2"></i>Lihat Bon
        </a>
        <a id="printBonLink" target="_blank" class="block text-center px-4 py-3 bg-green-600 hover:bg-green-700 rounded-lg transition-colors">
          <i class="fas fa-print mr-2"></i>Print Bon
        </a>
        <button onclick="closeBonActionModal()" class="w-full px-4 py-3 bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors">
          <i class="fas fa-times mr-2"></i>Tutup
        </button>
      </div>
    </div>
  </div>
</div>

  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal-overlay">
    <div class="modal-content" style="max-width: 400px;">
      <div class="text-center">
        <i class="fas fa-exclamation-triangle text-4xl text-yellow-400 mb-4"></i>
        <h3 class="text-xl font-bold text-white mb-2">Hapus Bon?</h3>
        <p class="text-gray-400 mb-6">Apakah Anda yakin ingin menghapus bon ini? Tindakan ini tidak dapat dibatalkan.</p>
        <div class="flex gap-3 justify-center">
          <button id="confirmDeleteBtn" class="px-6 py-2 bg-red-600 hover:bg-red-700 rounded-lg transition-colors">
            <i class="fas fa-trash mr-2"></i>Ya, Hapus
          </button>
          <button onclick="closeDeleteModal()" class="px-6 py-2 bg-gray-600 hover:bg-gray-700 rounded-lg transition-colors">
            Batal
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Global variables
    let currentDate = null;
    let currentBonId = null;
    let currentUUID = null;
    let deleteCallback = null;

    // Show/hide global loading
    function showLoading() {
        document.getElementById('globalLoading').classList.remove('hidden');
    }
    function hideLoading() {
        document.getElementById('globalLoading').classList.add('hidden');
    }

    // Day Modal Functions
    function openDayModal(date) {
        showLoading();
        currentDate = date;
        
        fetch(`?action=get_day_data&date=${date}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('dayModalTitle').textContent = `Detail Tanggal: ${formatDate(date)}`;
                document.getElementById('dayModalContent').innerHTML = data.html;
                document.getElementById('dayModal').classList.add('show');
                hideLoading();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
                alert('Gagal memuat data');
            });
    }

    function closeDayModal() {
        document.getElementById('dayModal').classList.remove('show');
        setTimeout(() => {
            document.getElementById('dayModalContent').innerHTML = '';
        }, 300);
    }

    // Edit Expenses Modal Functions
    function openEditModal(date) {
        showLoading();
        currentDate = date;
        
        fetch(`?action=get_expenses&date=${date}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('report_date').value = date;
                const container = document.getElementById('expensesContainer');
                container.innerHTML = '';
                
                if (data.expenses.length > 0) {
                    data.expenses.forEach((expense, index) => {
                        addExpenseField(expense.item_name || 'Pengeluaran Harian', expense.amount, index);
                    });
                } else {
                    addExpenseField('', 0, 0);
                }
                
                document.getElementById('editExpensesModal').classList.add('show');
                hideLoading();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
                alert('Gagal memuat data pengeluaran');
            });
    }

    function closeEditModal() {
        document.getElementById('editExpensesModal').classList.remove('show');
    }

    function addExpenseField(name = '', amount = 0, index = null) {
        const container = document.getElementById('expensesContainer');
        const fieldIndex = index !== null ? index : container.children.length;
        
        const fieldHTML = `
            <div class="expense-field flex gap-3 items-end">
                <div class="flex-1">
                    <label class="block text-sm text-gray-400 mb-1">Nama Item</label>
                    <input type="text" name="expenses[${fieldIndex}][name]" 
                           value="${name}" 
                           class="w-full p-2 bg-gray-700 border border-gray-600 rounded" 
                           placeholder="Contoh: Belanja Bahan">
                </div>
                <div class="w-32">
                    <label class="block text-sm text-gray-400 mb-1">Jumlah</label>
                    <input type="number" name="expenses[${fieldIndex}][amount]" 
                           value="${amount}" 
                           class="w-full p-2 bg-gray-700 border border-gray-600 rounded" 
                           placeholder="0">
                </div>
                <button type="button" onclick="removeExpenseField(this)" 
                        class="p-2 text-red-400 hover:text-red-300 transition-colors" 
                        ${container.children.length === 0 ? 'disabled' : ''}>
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', fieldHTML);
    }

    function removeExpenseField(button) {
        const field = button.closest('.expense-field');
        if (field && document.querySelectorAll('.expense-field').length > 1) {
            field.remove();
            // Reindex the fields
            const fields = document.querySelectorAll('.expense-field');
            fields.forEach((field, index) => {
                const nameInput = field.querySelector('input[name*="[name]"]');
                const amountInput = field.querySelector('input[name*="[amount]"]');
                if (nameInput && amountInput) {
                    nameInput.name = `expenses[${index}][name]`;
                    amountInput.name = `expenses[${index}][amount]`;
                }
            });
        }
    }

    // Form submission with AJAX
    document.getElementById('expensesForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const saveBtn = document.getElementById('saveExpensesBtn');
        const originalText = saveBtn.innerHTML;
        
        saveBtn.innerHTML = '<div class="loading-spinner"></div> Menyimpan...';
        saveBtn.disabled = true;
        
        showLoading();
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the day modal content
                document.getElementById('dayModalContent').innerHTML = data.html;
                
                // Update the grid card
                if (data.grid_data) {
                    const date = document.getElementById('report_date').value;
                    updateGridCard(date, data.grid_data);
                }
                
                closeEditModal();
                showNotification('Pengeluaran berhasil disimpan!', 'success');
            } else {
                alert(data.message || 'Gagal menyimpan pengeluaran');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menyimpan');
        })
        .finally(() => {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
            hideLoading();
        });
    });

    // Update grid card after expense edit
    function updateGridCard(date, data) {
        const card = document.getElementById(`card_${date}`);
        if (card && data) {
            const bersihElem = document.getElementById(`bersih_${date}`);
            const pemasukanElem = document.getElementById(`pemasukan_${date}`);
            const pengeluaranElem = document.getElementById(`pengeluaran_${date}`);
            
            if (bersihElem) bersihElem.textContent = `Rp ${formatNumber(data.net_income)}`;
            if (pemasukanElem) pemasukanElem.textContent = `Rp ${formatNumber(data.total_income)}`;
            if (pengeluaranElem) pengeluaranElem.textContent = `Rp ${formatNumber(data.total_expenses)}`;
            
            // Update color for negative net income
            if (bersihElem) {
                bersihElem.className = `text-2xl font-bold ${data.net_income < 0 ? 'text-red-400' : 'text-white'}`;
            }
            
            // Add animation effect
            card.style.animation = 'none';
            setTimeout(() => {
                card.style.animation = 'fadeIn 0.5s ease-in';
            }, 10);
        }
    }

    // Bon Detail Modal Functions
    function openBonDetailModal(bonId) {
        showLoading();
        
        fetch(`?action=get_bon_detail&bon_id=${bonId}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('bonDetailContent').innerHTML = data.html;
                document.getElementById('bonDetailModal').classList.add('show');
                hideLoading();
            })
            .catch(error => {
                console.error('Error:', error);
                hideLoading();
                alert('Gagal memuat detail bon');
            });
    }

    function closeBonDetailModal() {
        document.getElementById('bonDetailModal').classList.remove('show');
    }

// Di fungsi openBonActionModal, perbaiki link:
function openBonActionModal(bonId, uuid) {
    currentBonId = bonId;
    currentUUID = uuid;

    // Generate QR Code dengan URL yang benar (tanpa ?uuid=)
    const qrContainer = document.getElementById('qrcodeContainer');
    qrContainer.innerHTML = '';
    
    const qrCode = new QRCode(qrContainer, {
        text: `${window.location.origin}/r_bon/${uuid}`,
        width: 150,
        height: 150,
        colorDark: "#ffffff",
        colorLight: "transparent",
        correctLevel: QRCode.CorrectLevel.H
    });

    // Set links dengan URL yang benar
    document.getElementById('viewBonLink').href = `r_bon/${uuid}`;
    document.getElementById('printBonLink').href = `cetak_bon.php?bon_id=${bonId}`;
    
    document.getElementById('bonActionModal').classList.add('show');
}


    function closeBonActionModal() {
        document.getElementById('bonActionModal').classList.remove('show');
    }
new QRCode(document.getElementById("qrcodeContainer"), {
  text: "isi-qr",
  width: 220,   // default biasanya 128
  height: 220,  // samain biar square
});

    // Delete Functions
    function deleteBon(bonId, date) {
        currentBonId = bonId;
        currentDate = date;
        
        document.getElementById('deleteModal').classList.add('show');
        deleteCallback = confirmDeleteBon;
    }

    function deleteDailyReport(date) {
        if (confirm('Apakah Anda yakin ingin menghapus semua data untuk hari ini? Tindakan ini akan menghapus semua bon dan pengeluaran.')) {
            showLoading();
            
            fetch(`?action=delete_daily_report&date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the card from grid
                        const card = document.getElementById(`card_${date}`);
                        if (card) {
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.8)';
                            setTimeout(() => {
                                card.remove();
                                if (document.querySelectorAll('.card-hover').length === 0) {
                                    location.reload();
                                }
                            }, 300);
                        }
                        closeDayModal();
                        showNotification('Data udah di apus!', 'success');
                    } else {
                        alert(data.message || 'Gagal menghapus data harian');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat menghapus');
                })
                .finally(() => {
                    hideLoading();
                });
        }
    }

    function confirmDeleteBon() {
        showLoading();
        
        fetch(`?action=delete_bon&bon_id=${currentBonId}&date=${currentDate}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Reload the day modal to reflect changes
                    openDayModal(currentDate);
                    showNotification('Bon berhasil dihapus!', 'success');
                } else {
                    alert(data.message || 'Gagal menghapus bon');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Terjadi kesalahan saat menghapus');
            })
            .finally(() => {
                hideLoading();
                closeDeleteModal();
            });
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('show');
        deleteCallback = null;
    }

    // Set up delete confirmation
    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if (deleteCallback) {
            deleteCallback();
        }
    });

    // Other Functions
    function submitReport(date) {
        if (confirm('Apakah Anda yakin ingin mengirim laporan ini ke admin? Setelah dikirim, laporan tidak dapat diedit lagi.')) {
            showLoading();
            
            fetch(`?action=submit_report&date=${date}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        openDayModal(date); // Reload modal
                        showNotification('Laporan berhasil dikirim!', 'success');
                    } else {
                        alert(data.message || 'Gagal mengirim laporan');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat mengirim laporan');
                })
                .finally(() => {
                    hideLoading();
                });
        }
    }

    function printBon(bonId) {
        window.open(`cetak_bon.php?bon_id=${bonId}`, '_blank');
    }

    // Utility Functions
    function formatDate(dateString) {
        const date = new Date(dateString);
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        return date.toLocaleDateString('id-ID', options);
    }

    function formatNumber(number) {
        return new Intl.NumberFormat('id-ID').format(number);
    }

    function showNotification(message, type = 'info') {
        // Simple notification implementation
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 fade-in ${
            type === 'success' ? 'bg-green-600' : 
            type === 'error' ? 'bg-red-600' : 'bg-blue-600'
        }`;
        notification.innerHTML = `
            <div class="flex items-center gap-2">
                <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation' : 'info'}-circle"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }

    // Close modals on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeDayModal();
            closeEditModal();
            closeBonDetailModal();
            closeBonActionModal();
            closeDeleteModal();
        }
    });

    // Close modals when clicking outside
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('show');
            }
        });
    });

    // Initialize first expense field if empty
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.getElementById('expensesContainer');
        if (container && container.children.length === 0) {
            addExpenseField();
        }
    });
  </script>
</body>
</html>