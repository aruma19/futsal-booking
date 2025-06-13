<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';
$error = '';

// Handle various admin actions
if (isset($_POST['action']) && isset($_POST['booking_id'])) {
    $action = sanitize_input($_POST['action']);
    $booking_id = (int)$_POST['booking_id'];
    $reason = isset($_POST['reason']) ? sanitize_input($_POST['reason']) : '';
    
    switch ($action) {
        case 'confirm_payment':
            // Konfirmasi pembayaran - ubah status booking menjadi aktif
            $updateBooking = "UPDATE booking SET status = 'aktif' WHERE id = $booking_id AND status = 'pending'";
            $updatePayment = "UPDATE payments SET status = 'completed' WHERE booking_id = $booking_id";
            
            if (mysqli_query($connection, $updateBooking) && mysqli_query($connection, $updatePayment)) {
                $message = "Pembayaran berhasil dikonfirmasi dan booking diaktifkan.";
            } else {
                $error = "Gagal mengkonfirmasi pembayaran.";
            }
            break;
            
        case 'cancel_booking':
            // Batalkan booking dengan kalkulasi refund
            $bookingQuery = "SELECT b.*, l.harga FROM booking b JOIN lapangan l ON b.id_lapangan = l.id WHERE b.id = $booking_id";
            $bookingResult = mysqli_query($connection, $bookingQuery);
            $booking = mysqli_fetch_assoc($bookingResult);
            
            if ($booking) {
                $total_harga = $booking['harga'] * $booking['lama_sewa'];
                $refund_amount = 0;
                $penalty_amount = 0;
                
                // Kalkulasi refund berdasarkan waktu
                $booking_datetime = strtotime($booking['tanggal'] . ' ' . $booking['jam']);
                $now = time();
                $time_diff_hours = ($booking_datetime - $now) / 3600;
                
                if ($booking['status'] == 'pending') {
                    // Tidak ada penalty untuk pending bookings
                    $refund_amount = $booking['total_dibayar'];
                    $penalty_amount = 0;
                } elseif ($booking['status'] == 'aktif') {
                    if ($time_diff_hours > 24) {
                        // Tidak ada penalty jika lebih dari 24 jam
                        $refund_amount = $booking['total_dibayar'];
                        $penalty_amount = 0;
                    } else {
                        // Penalty berlaku
                        if ($booking['status_pembayaran'] == 'dp') {
                            // 0% penalty untuk DP (no refund)
                            $penalty_amount = $booking['total_dibayar'];
                            $refund_amount = 0;
                        } else {
                            // 50% penalty untuk full payment
                            $penalty_amount = $booking['total_dibayar'] * 0.5;
                            $refund_amount = $booking['total_dibayar'] - $penalty_amount;
                        }
                    }
                }
                
                // Update booking status
                $updateQuery = "UPDATE booking SET status = 'batal', total_pinalti = $penalty_amount WHERE id = $booking_id";
                mysqli_query($connection, $updateQuery);
                
                // Insert refund record jika ada
                if ($refund_amount > 0) {
                    $refundQuery = "INSERT INTO refunds (booking_id, user_id, refund_amount, penalty_amount, reason, status, created_at) 
                                   VALUES ($booking_id, {$booking['id_user']}, $refund_amount, $penalty_amount, '$reason', 'pending', NOW())";
                    mysqli_query($connection, $refundQuery);
                }
                
                $message = "Booking berhasil dibatalkan. Refund: Rp " . number_format($refund_amount, 0, ',', '.') . 
                          " | Penalty: Rp " . number_format($penalty_amount, 0, ',', '.');
            }
            break;
            
        case 'complete_booking':
            $updateQuery = "UPDATE booking SET status = 'selesai' WHERE id = $booking_id";
            if (mysqli_query($connection, $updateQuery)) {
                $message = "Booking berhasil diselesaikan.";
            }
            break;
            
        case 'reject_booking':
            // Tolak booking dengan full refund
            $updateQuery = "UPDATE booking SET status = 'batal' WHERE id = $booking_id";
            if (mysqli_query($connection, $updateQuery)) {
                // Full refund untuk rejected bookings
                $bookingQuery = "SELECT * FROM booking WHERE id = $booking_id";
                $bookingResult = mysqli_query($connection, $bookingQuery);
                $booking = mysqli_fetch_assoc($bookingResult);
                
                if ($booking && $booking['total_dibayar'] > 0) {
                    $refundQuery = "INSERT INTO refunds (booking_id, user_id, refund_amount, penalty_amount, reason, status, created_at) 
                                   VALUES ($booking_id, {$booking['id_user']}, {$booking['total_dibayar']}, 0, '$reason', 'pending', NOW())";
                    mysqli_query($connection, $refundQuery);
                }
                
                $message = "Booking berhasil ditolak dan refund penuh akan diproses.";
            }
            break;
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Filter
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query with filters
$whereConditions = [];

if ($status_filter && $status_filter != 'all') {
    $whereConditions[] = "b.status = '$status_filter'";
}

if ($date_filter) {
    $whereConditions[] = "b.tanggal = '$date_filter'";
}

if ($search) {
    $whereConditions[] = "(u.username LIKE '%$search%' OR u.full_name LIKE '%$search%' OR l.nama LIKE '%$search%')";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total records for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM booking b 
    JOIN users u ON b.id_user = u.id 
    JOIN lapangan l ON b.id_lapangan = l.id 
    $whereClause
";
$countResult = mysqli_query($connection, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $limit);

// Get booking data with payment and refund info
$bookingQuery = "
    SELECT b.*, u.username, u.full_name, u.email, u.phone, l.nama as nama_lapangan, l.harga, l.tipe,
           p.payment_method, p.status as payment_status, p.created_at as payment_date,
           r.refund_amount, r.penalty_amount, r.status as refund_status
    FROM booking b 
    JOIN users u ON b.id_user = u.id 
    JOIN lapangan l ON b.id_lapangan = l.id 
    LEFT JOIN payments p ON b.id = p.booking_id
    LEFT JOIN refunds r ON b.id = r.booking_id
    $whereClause
    ORDER BY b.created_at DESC
    LIMIT $limit OFFSET $offset
";

$bookingResult = mysqli_query($connection, $bookingQuery);
$all_booking = [];
while($row = mysqli_fetch_assoc($bookingResult)) {
    $all_booking[] = $row;
}

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as aktif,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as selesai,
        SUM(CASE WHEN status = 'batal' THEN 1 ELSE 0 END) as batal,
        SUM(CASE WHEN status IN ('aktif', 'selesai') THEN (SELECT l.harga * b.lama_sewa FROM lapangan l WHERE l.id = b.id_lapangan) ELSE 0 END) as total_revenue
    FROM booking b
";
$statsResult = mysqli_query($connection, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking - Admin Futsal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #e74c3c;
            --secondary-color: #c0392b;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --danger-color: #e74c3c;
        }
        
        body {
            background: linear-gradient(135deg,rgb(255, 237, 237) 0%,rgb(240, 224, 254) 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .admin-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stats-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
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

        .stat-item:hover { transform: translateY(-3px); }
        .stat-item.pending { background: linear-gradient(45deg, var(--warning-color), #e67e22); }
        .stat-item.aktif { background: linear-gradient(45deg, var(--success-color), #2ecc71); }
        .stat-item.selesai { background: linear-gradient(45deg, #95a5a6, #7f8c8d); }
        .stat-item.batal { background: linear-gradient(45deg, var(--danger-color), #c0392b); }
        .stat-item.revenue { background: linear-gradient(45deg, var(--info-color), #5dade2); }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .filter-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .booking-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .booking-card:hover {
            transform: translateX(5px);
            box-shadow: 0 12px 35px rgba(0,0,0,0.15);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f2f6;
        }

        .booking-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #2c3e50;
        }

        .booking-id {
            background: var(--primary-color);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .booking-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 18px;
            border-radius: 12px;
            border-left: 4px solid var(--info-color);
        }

        .info-section h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 1em;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #666;
            font-size: 0.9em;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
            font-size: 0.9em;
        }

        .payment-info {
            background: #e8f5e8;
            padding: 18px;
            border-radius: 12px;
            margin-top: 15px;
            border-left: 4px solid var(--success-color);
        }

        .payment-warning {
            background: #fff3cd;
            padding: 18px;
            border-radius: 12px;
            margin-top: 15px;
            border-left: 4px solid var(--warning-color);
        }

        .refund-info {
            background: #f8d7da;
            padding: 18px;
            border-radius: 12px;
            margin-top: 15px;
            border-left: 4px solid var(--danger-color);
        }

        .status-badge {
            padding: 8px 15px;
            border-radius: 20px;
            color: white;
            font-size: 0.9em;
            font-weight: 500;
            display: inline-block;
        }

        .status-pending { background: linear-gradient(45deg, var(--warning-color), #e67e22); }
        .status-aktif { background: linear-gradient(45deg, var(--success-color), #2ecc71); }
        .status-selesai { background: linear-gradient(45deg, #95a5a6, #7f8c8d); }
        .status-batal { background: linear-gradient(45deg, var(--danger-color), #c0392b); }

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
        .payment-pending { background: #6c757d; }
        .payment-completed { background: var(--success-color); }

        .admin-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .btn {
            border-radius: 20px;
            font-weight: 500;
            padding: 8px 16px;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
        }

        .btn-warning {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
        }

        .btn-info {
            background: linear-gradient(45deg, var(--info-color), #5dade2);
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--info-color), #5dade2);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #6c757d, #5a6268);
        }

        .revenue-highlight {
            font-weight: bold;
            color: var(--success-color);
            font-size: 1.1em;
        }

        .alert {
            border-radius: 12px;
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
            background: linear-gradient(45deg, rgba(39, 174, 96, 0.15), rgba(46, 204, 113, 0.15));
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(45deg, rgba(231, 76, 60, 0.15), rgba(236, 112, 99, 0.15));
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
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

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .booking-info {
                grid-template-columns: 1fr;
            }
            
            .admin-actions {
                justify-content: stretch;
            }
            
            .admin-actions .btn {
                flex: 1;
                margin: 2px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="container">
        <!-- Header -->
        <div class="admin-header fade-in">
            <h1><i class="bi bi-gear-fill me-2"></i>Kelola Semua Booking</h1>
            <p class="mb-0">Manajemen lengkap booking, pembayaran, dan refund</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-success fade-in">
            <i class="bi bi-check-circle"></i><?php echo $message; ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
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
                <div class="stat-item revenue">
                    <div class="stat-number">Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section fade-in">
            <h4 class="mb-3"><i class="bi bi-funnel me-2"></i>Filter Booking</h4>
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <select class="form-select" name="status">
                            <option value="all">Semua Status</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="aktif" <?php echo ($status_filter == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="selesai" <?php echo ($status_filter == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                            <option value="batal" <?php echo ($status_filter == 'batal') ? 'selected' : ''; ?>>Dibatal</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <input type="text" class="form-control" name="search" placeholder="Cari nama/email..." value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Booking List -->
        <div class="fade-in">
            <h4 class="mb-4">
                <i class="bi bi-list me-2"></i>Daftar Booking 
                <small class="text-muted">(<?php echo $totalRecords; ?> total)</small>
            </h4>

            <?php if (empty($all_booking)): ?>
            <div class="no-data">
                <i class="bi bi-inbox"></i>
                <h5>Tidak ada booking ditemukan</h5>
                <p>Belum ada booking dengan kriteria yang dipilih.</p>
            </div>
            <?php else: ?>
            
            <?php foreach ($all_booking as $booking): ?>
            <?php
            $total_harga = $booking['harga'] * $booking['lama_sewa'];
            $sisa_pembayaran = $total_harga - $booking['total_dibayar'];
            ?>
            <div class="booking-card">
                <div class="booking-header">
                    <div>
                        <div class="booking-title">
                            <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($booking['nama_lapangan']); ?>
                        </div>
                        <div class="mt-2">
                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                            <?php if ($booking['status_pembayaran']): ?>
                            <span class="payment-status payment-<?php echo $booking['status_pembayaran']; ?> ms-2">
                                <?php echo ($booking['status_pembayaran'] == 'dp') ? 'DP' : ucfirst($booking['status_pembayaran']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="booking-id">ID: #<?php echo $booking['id']; ?></div>
                        <div class="revenue-highlight mt-2">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></div>
                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></small>
                    </div>
                </div>

                <div class="booking-info">
                    <!-- Customer Info -->
                    <div class="info-section">
                        <h6><i class="bi bi-person me-2"></i>Customer</h6>
                        <div class="info-item">
                            <span class="info-label">Nama:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Telepon:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Kontak:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['kontak']); ?></span>
                        </div>
                    </div>

                    <!-- Booking Details -->
                    <div class="info-section">
                        <h6><i class="bi bi-calendar me-2"></i>Detail Booking</h6>
                        <div class="info-item">
                            <span class="info-label">Tanggal:</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($booking['tanggal'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Waktu:</span>
                            <span class="info-value"><?php echo date('H:i', strtotime($booking['jam'])); ?> WIB</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Durasi:</span>
                            <span class="info-value"><?php echo $booking['lama_sewa']; ?> jam</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Harga/jam:</span>
                            <span class="info-value">Rp <?php echo number_format($booking['harga'], 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <!-- Payment Info -->
                    <div class="info-section">
                        <h6><i class="bi bi-credit-card me-2"></i>Pembayaran</h6>
                        <div class="info-item">
                            <span class="info-label">Total Harga:</span>
                            <span class="info-value">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Sudah Dibayar:</span>
                            <span class="info-value">Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></span>
                        </div>
                        <?php if ($sisa_pembayaran > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Sisa:</span>
                            <span class="info-value text-warning">Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['payment_method']): ?>
                        <div class="info-item">
                            <span class="info-label">Metode:</span>
                            <span class="info-value"><?php echo ucfirst($booking['payment_method']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($booking['total_pinalti'] > 0): ?>
                        <div class="info-item">
                            <span class="info-label">Pinalti:</span>
                            <span class="info-value text-danger">Rp <?php echo number_format($booking['total_pinalti'], 0, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if ($booking['payment_method']): ?>
                <div class="payment-info">
                    <h6><i class="bi bi-credit-card me-2"></i>Informasi Pembayaran</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Metode:</strong> <?php echo ucfirst($booking['payment_method']); ?><br>
                            <strong>Status:</strong> 
                            <span class="payment-status payment-<?php echo $booking['payment_status'] ?? 'pending'; ?>">
                                <?php echo ucfirst($booking['payment_status'] ?? 'pending'); ?>
                            </span>
                        </div>
                        <div class="col-md-6">
                            <strong>Tanggal Bayar:</strong> 
                            <?php echo $booking['payment_date'] ? date('d/m/Y H:i', strtotime($booking['payment_date'])) : '-'; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Refund Information -->
                <?php if ($booking['refund_amount'] > 0 || $booking['penalty_amount'] > 0): ?>
                <div class="refund-info">
                    <h6><i class="bi bi-arrow-return-left me-2"></i>Informasi Refund</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Jumlah Refund:</strong> Rp <?php echo number_format($booking['refund_amount'], 0, ',', '.'); ?><br>
                            <strong>Pinalti:</strong> Rp <?php echo number_format($booking['penalty_amount'], 0, ',', '.'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Status Refund:</strong> 
                            <span class="badge bg-<?php echo ($booking['refund_status'] == 'completed') ? 'success' : 'warning'; ?>">
                                <?php echo ucfirst($booking['refund_status'] ?? 'pending'); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Admin Actions -->
                <div class="admin-actions">
                    <?php if ($booking['status'] == 'pending'): ?>
                    <button onclick="confirmAction('confirm_payment', <?php echo $booking['id']; ?>, 'Konfirmasi Pembayaran', 'Yakin ingin mengkonfirmasi pembayaran booking ini?')" class="btn btn-success btn-sm">
                        <i class="bi bi-check-circle me-1"></i>Konfirmasi Bayar
                    </button>
                    <button onclick="confirmAction('reject_booking', <?php echo $booking['id']; ?>, 'Tolak Booking', 'Yakin ingin menolak booking ini?', true)" class="btn btn-danger btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Tolak
                    </button>
                    <?php endif; ?>

                    <?php if ($booking['status'] == 'aktif'): ?>
                    <button onclick="confirmAction('complete_booking', <?php echo $booking['id']; ?>, 'Selesaikan Booking', 'Yakin ingin menandai booking ini sebagai selesai?')" class="btn btn-primary btn-sm">
                        <i class="bi bi-flag-fill me-1"></i>Selesaikan
                    </button>
                    <button onclick="confirmAction('cancel_booking', <?php echo $booking['id']; ?>, 'Batalkan Booking', 'Yakin ingin membatalkan booking ini?', true)" class="btn btn-warning btn-sm">
                        <i class="bi bi-x-circle me-1"></i>Batalkan
                    </button>
                    <?php endif; ?>

                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation">
                <ul class="pagination">
                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo $search; ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++): 
                    ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo $search; ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Admin Action Modal -->
    <div class="modal fade" id="actionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Konfirmasi Aksi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="actionForm">
                    <div class="modal-body">
                        <input type="hidden" name="booking_id" id="modalBookingId">
                        <input type="hidden" name="action" id="modalAction">
                        <p id="modalMessage"></p>
                        <div id="reasonSection" style="display: none;">
                            <label class="form-label">Alasan (opsional):</label>
                            <textarea class="form-control" name="reason" rows="3" placeholder="Masukkan alasan pembatalan atau penolakan..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary" id="modalSubmit">Konfirmasi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const actionModal = new bootstrap.Modal(document.getElementById('actionModal'));

        function confirmAction(action, bookingId, title, message, showReason = false) {
            document.getElementById('modalTitle').textContent = title;
            document.getElementById('modalMessage').textContent = message;
            document.getElementById('modalBookingId').value = bookingId;
            document.getElementById('modalAction').value = action;
            
            const reasonSection = document.getElementById('reasonSection');
            if (showReason) {
                reasonSection.style.display = 'block';
            } else {
                reasonSection.style.display = 'none';
            }
            
            // Change submit button color based on action
            const submitBtn = document.getElementById('modalSubmit');
            if (action.includes('cancel') || action.includes('reject')) {
                submitBtn.className = 'btn btn-danger';
            } else {
                submitBtn.className = 'btn btn-primary';
            }
            
            actionModal.show();
        }

        function viewDetail(bookingId) {
            window.open(`booking_detail.php?id=${bookingId}`, '_blank');
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

        // Auto refresh every 60 seconds
        setInterval(() => {
            if (!document.querySelector('.modal.show')) {
                const urlParams = new URLSearchParams(window.location.search);
                if (!urlParams.has('page') && !urlParams.has('status') && !urlParams.has('date') && !urlParams.has('search')) {
                    location.reload();
                }
            }
        }, 60000);
    </script>
</body>
</html>