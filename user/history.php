<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

// Check if connection is valid
check_connection();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';

// Handle cancel booking
if (isset($_GET['cancel']) && isset($_GET['id'])) {
    $booking_id = (int)$_GET['id'];
    
    // Cek apakah booking milik user dan bisa dibatalkan
    $checkQuery = "SELECT * FROM booking WHERE id = $booking_id AND id_user = $user_id AND status IN ('pending', 'aktif')";
    $checkResult = mysqli_query($connection, $checkQuery);
    
    if (mysqli_num_rows($checkResult) > 0) {
        $booking_data = mysqli_fetch_assoc($checkResult);
        
        // Calculate refund and penalty
        $total_harga = 0;
        $penalty_amount = 0;
        $refund_amount = 0;
        
        // Get lapangan price
        $lapanganQuery = "SELECT harga FROM lapangan WHERE id = " . $booking_data['id_lapangan'];
        $lapanganResult = mysqli_query($connection, $lapanganQuery);
        $lapangan = mysqli_fetch_assoc($lapanganResult);
        
        if ($lapangan) {
            $total_harga = $lapangan['harga'] * $booking_data['lama_sewa'];
            
            // Calculate penalty based on timing and payment status
            $booking_datetime = strtotime($booking_data['tanggal'] . ' ' . $booking_data['jam']);
            $now = time();
            $time_diff_hours = ($booking_datetime - $now) / 3600;
            
            if ($booking_data['status'] == 'pending') {
                // No penalty for pending bookings
                $penalty_amount = 0;
                $refund_amount = $booking_data['total_dibayar'];
            } elseif ($booking_data['status'] == 'aktif') {
                if ($time_diff_hours > 24) {
                    // No penalty if cancelled more than 24 hours before
                    $penalty_amount = 0;
                    $refund_amount = $booking_data['total_dibayar'];
                } else {
                    // Penalty applies
                    if ($booking_data['status_pembayaran'] == 'dp') {
                        // 0% penalty for DP (no refund)
                        $penalty_amount = $booking_data['total_dibayar'];
                        $refund_amount = 0;
                    } else {
                        // 50% penalty for full payment
                        $penalty_amount = $booking_data['total_dibayar'] * 0.5;
                        $refund_amount = $booking_data['total_dibayar'] - $penalty_amount;
                    }
                }
            }
        }
        
        // Redirect to cancellation page with details
        header("Location: cancel_booking.php?id=$booking_id&penalty=$penalty_amount&refund=$refund_amount");
        exit();
    } else {
        $error = "Booking tidak ditemukan atau tidak bisa dibatalkan!";
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';

// Build query with filters
$whereConditions = ["b.id_user = $user_id"];

if ($status_filter && $status_filter != 'all') {
    $whereConditions[] = "b.status = '$status_filter'";
}

if ($date_filter) {
    $whereConditions[] = "b.tanggal = '$date_filter'";
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Get total records for pagination
$countQuery = "SELECT COUNT(*) as total FROM booking b $whereClause";
$countResult = mysqli_query($connection, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $limit);

// Get booking history with payment information
$historyQuery = "
    SELECT b.*, l.nama as nama_lapangan, l.harga, l.tipe,
           p.amount as payment_amount, p.payment_type, p.payment_method, p.status as payment_status,
           r.refund_amount, r.penalty_amount
    FROM booking b 
    JOIN lapangan l ON b.id_lapangan = l.id 
    LEFT JOIN payments p ON b.id = p.booking_id AND p.payment_type IN ('dp', 'lunas')
    LEFT JOIN refunds r ON b.id = r.booking_id
    $whereClause
    ORDER BY b.created_at DESC
    LIMIT $limit OFFSET $offset
";

$historyResult = mysqli_query($connection, $historyQuery);
$booking_history = [];
while($row = mysqli_fetch_assoc($historyResult)) {
    $booking_history[] = $row;
}

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as batal
    FROM booking 
    WHERE id_user = $user_id
";
$statsResult = mysqli_query($connection, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Booking - Futsal Booking</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #5dade2;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --text-color: #495057;
            --card-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            --hover-transform: translateY(-5px);
            --transition-speed: 0.3s;
            --border-radius: 12px;
        }
        
        body {
            background: linear-gradient(135deg, rgb(235, 245, 255) 0%, rgb(240, 248, 255) 100%);
            font-family: 'Poppins', 'Segoe UI', Roboto, sans-serif;
            color: var(--text-color);
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            text-align: center;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .page-header h1 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stats-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
        }

        .stat-item.pending {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .stat-item.aktif {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
        }

        .stat-item.selesai {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
        }

        .stat-item.batal {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .filter-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .history-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .booking-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
            transition: all var(--transition-speed) ease;
        }

        .booking-card:hover {
            background: #e3f2fd;
            transform: translateX(5px);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .booking-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.2em;
        }

        .booking-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .booking-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .info-label {
            font-weight: 500;
            color: var(--primary-color);
            font-size: 0.9em;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
        }

        .payment-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid var(--success-color);
        }

        .payment-warning {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            border-left: 4px solid var(--warning-color);
        }

        .status {
            padding: 8px 15px;
            border-radius: 20px;
            color: white;
            font-size: 0.9em;
            font-weight: 500;
            display: inline-block;
        }

        .pending { background: linear-gradient(45deg, var(--warning-color), #e67e22); }
        .aktif { background: linear-gradient(45deg, var(--success-color), #2ecc71); }
        .batal { background: linear-gradient(45deg, var(--danger-color), #c0392b); }
        .selesai { background: linear-gradient(45deg, #95a5a6, #7f8c8d); }

        .payment-status {
            padding: 6px 12px;
            border-radius: 15px;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
            display: inline-block;
        }

        .payment-dp { background: var(--warning-color); }
        .payment-lunas { background: var(--success-color); }
        .payment-refund { background: #9b59b6; }

        .btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.9rem;
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
        }

        .btn-warning {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .btn-info {
            background: linear-gradient(45deg, #17a2b8, #138496);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
            display: flex;
            align-items: center;
        }

        .alert i {
            margin-right: 10px;
            font-size: 1.1rem;
        }

        .alert-success {
            background: linear-gradient(45deg, rgba(39, 174, 96, 0.1), rgba(46, 204, 113, 0.1));
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(45deg, rgba(231, 76, 60, 0.1), rgba(236, 112, 99, 0.1));
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .pagination {
            justify-content: center;
            margin-top: 30px;
        }

        .page-link {
            color: var(--primary-color);
            border-radius: 8px;
            margin: 0 2px;
            border: none;
        }

        .page-link:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .booking-info {
                grid-template-columns: 1fr;
            }

            .booking-actions {
                width: 100%;
                justify-content: stretch;
            }

            .booking-actions .btn {
                flex: 1;
                margin: 2px;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease forwards;
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include 'user_sidebar.php'; ?>
    
    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1><i class="bi bi-clock-history me-2"></i>Riwayat Booking</h1>
            <p class="mb-0">Lihat semua riwayat booking lapangan futsal Anda</p>
        </div>

        <!-- Messages -->
        <?php if (isset($message)): ?>
        <div class="alert alert-success fade-in">
            <i class="bi bi-check-circle"></i><?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger fade-in">
            <i class="bi bi-exclamation-circle"></i><?php echo $error; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-section fade-in">
            <h4 class="mb-4"><i class="bi bi-bar-chart me-2"></i>Statistik Booking</h4>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Booking</div>
                </div>
                <div class="stat-item pending">
                    <div class="stat-number"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-item aktif">
                    <div class="stat-number"><?php echo $stats['aktif']; ?></div>
                    <div class="stat-label">Aktif</div>
                </div>
                <div class="stat-item selesai">
                    <div class="stat-number"><?php echo $stats['selesai']; ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
                <div class="stat-item batal">
                    <div class="stat-number"><?php echo $stats['batal']; ?></div>
                    <div class="stat-label">Dibatal</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section fade-in">
            <h4 class="mb-3"><i class="bi bi-funnel me-2"></i>Filter</h4>
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo ($status_filter == 'all' || $status_filter == '') ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="aktif" <?php echo ($status_filter == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="selesai" <?php echo ($status_filter == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                            <option value="batal" <?php echo ($status_filter == 'batal') ? 'selected' : ''; ?>>Dibatal</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search me-1"></i>Filter
                            </button>
                            <a href="history.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise me-1"></i>Reset
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- History Section -->
        <div class="history-section fade-in">
            <h4 class="mb-4">
                <i class="bi bi-list me-2"></i>Daftar Booking 
                <small class="text-muted">(<?php echo $totalRecords; ?> total)</small>
            </h4>

            <?php if (empty($booking_history)): ?>
            <div class="no-data">
                <i class="bi bi-inbox"></i>
                <h5>Belum ada riwayat booking</h5>
                <p>Anda belum memiliki riwayat booking. <a href="booking.php">Buat booking pertama Anda!</a></p>
            </div>
            <?php else: ?>
            <?php foreach ($booking_history as $booking): ?>
            <?php
            $total_harga = $booking['harga'] * $booking['lama_sewa'];
            $sisa_pembayaran = $total_harga - $booking['total_dibayar'];
            $can_cancel = in_array($booking['status'], ['pending', 'aktif']);
            $can_complete_payment = ($booking['status_pembayaran'] == 'dp' && $booking['status'] == 'aktif');
            ?>
            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">
                        <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($booking['nama_lapangan']); ?>
                    </div>
                    <div class="booking-actions">
                        <?php if ($booking['status'] == 'pending' || $booking['status'] == 'aktif'): ?>
                        <span class="status <?php echo $booking['status']; ?>">
                            <?php 
                            $status_icons = [
                                'pending' => 'clock',
                                'aktif' => 'check-circle',
                                'batal' => 'x-circle',
                                'selesai' => 'check2-circle'
                            ];
                            $icon = $status_icons[$booking['status']] ?? 'circle';
                            echo '<i class="bi bi-' . $icon . ' me-1"></i>' . ucfirst($booking['status']); 
                            ?>
                        </span>
                        <?php else: ?>
                        <span class="status <?php echo $booking['status']; ?>">
                            <?php 
                            $icon = $status_icons[$booking['status']] ?? 'circle';
                            echo '<i class="bi bi-' . $icon . ' me-1"></i>' . ucfirst($booking['status']); 
                            ?>
                        </span>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                <?php if ($can_complete_payment): ?>
                    <a href="complete_payment.php?id=<?php echo $booking['id']; ?>" class="btn btn-warning btn-sm">
                        <i class="bi bi-credit-card me-1"></i>Lunasi
                    </a>
                    <?php endif; ?>

                    <?php if ($can_cancel): ?>
                    <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" class="btn btn-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Batal
                    </button>
                    <?php endif; ?>

                    <!-- Payment Receipt Button - Fixed Logic -->
                    <?php if ($booking['status_pembayaran'] && $booking['total_dibayar'] > 0): ?>
                    <a href="payment_receipt.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-info btn-sm" target="_blank">
                        <i class="bi bi-printer me-1"></i>Struk
                    </a>
                    <?php endif; ?>

                    <!-- Detail Button untuk semua status -->
                    <a href="booking_detail.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary btn-sm">
                        <i class="bi bi-eye me-1"></i>Detail
                    </a>
                                        </div>
                </div>

                <div class="booking-info">
                    <div class="info-item">
                        <div class="info-label">📅 Tanggal Booking</div>
                        <div class="info-value"><?php echo date('d/m/Y', strtotime($booking['tanggal'])); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">⏰ Waktu</div>
                        <div class="info-value"><?php echo date('H:i', strtotime($booking['jam'])); ?> WIB</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">⏱️ Durasi</div>
                        <div class="info-value"><?php echo $booking['lama_sewa']; ?> jam</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">💰 Total Harga</div>
                        <div class="info-value">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">📱 Kontak</div>
                        <div class="info-value"><?php echo htmlspecialchars($booking['kontak']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">📋 Dibuat</div>
                        <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></div>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if ($booking['status_pembayaran']): ?>
                <div class="payment-info">
                    <h6><i class="bi bi-credit-card me-2"></i>Informasi Pembayaran</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Status Pembayaran:</strong> 
                                <span class="payment-status payment-<?php echo $booking['status_pembayaran']; ?>">
                                    <?php 
                                    if ($booking['status_pembayaran'] == 'dp') {
                                        echo 'DP (50%) - Belum Lunas';
                                    } elseif ($booking['status_pembayaran'] == 'lunas') {
                                        echo 'Lunas (100%)';
                                    } elseif ($booking['status_pembayaran'] == 'refund') {
                                        echo 'Refund';
                                    }
                                    ?>
                                </span>
                            </p>
                            <p><strong>Jumlah Dibayar:</strong> Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($booking['payment_method']): ?>
                            <p><strong>Metode:</strong> <?php echo ucfirst($booking['payment_method']); ?></p>
                            <?php endif; ?>
                            <?php if ($sisa_pembayaran > 0): ?>
                            <p><strong>Sisa Pembayaran:</strong> <span style="color: var(--warning-color);">Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></span></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($booking['total_pinalti'] > 0): ?>
                    <div class="alert alert-warning mt-2 mb-0">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Pinalti Pembatalan:</strong> Rp <?php echo number_format($booking['total_pinalti'], 0, ',', '.'); ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['refund_amount'] > 0): ?>
                    <div class="alert alert-success mt-2 mb-0">
                        <i class="bi bi-check-circle"></i>
                        <strong>Jumlah Refund:</strong> Rp <?php echo number_format($booking['refund_amount'], 0, ',', '.'); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Payment Warning for DP -->
                <?php if ($can_complete_payment): ?>
                <div class="payment-warning">
                    <h6><i class="bi bi-exclamation-triangle me-2"></i>Perhatian</h6>
                    <p class="mb-2">Anda masih memiliki sisa pembayaran sebesar <strong>Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></strong></p>
                    <p class="mb-0">Silakan lakukan pelunasan sebelum waktu bermain untuk memastikan booking Anda tetap aktif.</p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>">Previous</a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function cancelBooking(id) {
            Swal.fire({
                title: 'Konfirmasi Pembatalan',
                text: 'Yakin ingin membatalkan booking ini?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Tidak'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?cancel=true&id=${id}`;
                }
            });
        }

        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>