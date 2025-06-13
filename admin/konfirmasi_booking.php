<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';
$error = '';

// Handle konfirmasi booking
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = sanitize_input($_GET['action']);
    $booking_id = (int)$_GET['id'];
    $reason = isset($_GET['reason']) ? sanitize_input($_GET['reason']) : '';
    
    if ($action == 'approve') {
        // Update booking status to aktif
        $updateBooking = "UPDATE booking SET status = 'aktif' WHERE id = $booking_id AND status = 'pending'";
        // Update payment status to completed
        $updatePayment = "UPDATE payments SET status = 'completed' WHERE booking_id = $booking_id";
        
        if (mysqli_query($connection, $updateBooking) && mysqli_query($connection, $updatePayment)) {
            if (mysqli_affected_rows($connection) > 0) {
                $message = 'Booking berhasil dikonfirmasi dan disetujui! Status booking diubah menjadi aktif.';
            } else {
                $message = 'Booking tidak ditemukan atau sudah diproses!';
            }
        } else {
            $error = 'Terjadi kesalahan: ' . mysqli_error($connection);
        }
    } elseif ($action == 'reject') {
        // Get booking details untuk refund calculation
        $bookingQuery = "SELECT b.*, u.id as user_id FROM booking b JOIN users u ON b.id_user = u.id WHERE b.id = $booking_id AND b.status = 'pending'";
        $bookingResult = mysqli_query($connection, $bookingQuery);
        $booking = mysqli_fetch_assoc($bookingResult);
        
        if ($booking) {
            // Update booking status to batal
            $updateQuery = "UPDATE booking SET status = 'batal' WHERE id = $booking_id";
            if (mysqli_query($connection, $updateQuery)) {
                // Create full refund untuk rejected bookings
                if ($booking['total_dibayar'] > 0) {
                    $refundQuery = "INSERT INTO refunds (booking_id, user_id, refund_amount, penalty_amount, reason, status, created_at) 
                                   VALUES ($booking_id, {$booking['user_id']}, {$booking['total_dibayar']}, 0, 'Booking ditolak oleh admin: $reason', 'pending', NOW())";
                    mysqli_query($connection, $refundQuery);
                }
                
                $message = 'Booking berhasil ditolak! Refund penuh sebesar Rp ' . number_format($booking['total_dibayar'], 0, ',', '.') . ' akan diproses.';
            } else {
                $error = 'Terjadi kesalahan: ' . mysqli_error($connection);
            }
        } else {
            $error = 'Booking tidak ditemukan atau sudah diproses!';
        }
    }
}

// Ambil booking pending dengan informasi lengkap termasuk payment
$pendingQuery = "
    SELECT b.*, u.username, u.full_name, u.email, u.phone, l.nama as nama_lapangan, l.harga, l.tipe,
           p.payment_method, p.payment_type, p.status as payment_status, p.created_at as payment_date
    FROM booking b 
    JOIN users u ON b.id_user = u.id 
    JOIN lapangan l ON b.id_lapangan = l.id 
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.status = 'pending' 
    ORDER BY b.created_at ASC
";

$pendingResult = mysqli_query($connection, $pendingQuery);
$booking_pending = [];
while($row = mysqli_fetch_assoc($pendingResult)) {
    $booking_pending[] = $row;
}

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_pending,
        COUNT(CASE WHEN DATE(b.created_at) = CURDATE() THEN 1 END) as today_pending,
        SUM(CASE WHEN b.status_pembayaran = 'dp' THEN b.total_dibayar ELSE 0 END) as total_dp,
        SUM(CASE WHEN b.status_pembayaran = 'lunas' THEN b.total_dibayar ELSE 0 END) as total_lunas,
        SUM(b.total_dibayar) as total_pending_amount
    FROM booking b 
    WHERE b.status = 'pending'
";
$statsResult = mysqli_query($connection, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Booking Pending - Admin Futsal</title>

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
            color: #495057;
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
            border-radius: 15px;
            text-align: center;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stats-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
        }

        .stat-item.today {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        }

        .stat-item.dp {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .stat-item.lunas {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
        }

        .stat-item.amount {
            background: linear-gradient(45deg, var(--info-color), #5dade2);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-top: 5px;
        }

        .booking-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .booking-section h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .booking-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            border-left: 5px solid var(--warning-color);
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .booking-card:hover {
            background: #fff8e1;
            transform: translateX(5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #dee2e6;
        }

        .booking-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.3em;
        }

        .booking-id {
            background: var(--warning-color);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
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
            background: white;
            padding: 18px;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.08);
            border-left: 4px solid var(--info-color);
        }

        .info-section h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 1em;
            display: flex;
            align-items: center;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
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

        .payment-section {
            background: #e8f5e8;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            border-left: 5px solid var(--success-color);
        }

        .payment-section h6 {
            color: var(--success-color);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .payment-method {
            display: inline-block;
            background: var(--success-color);
            color: white;
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
            margin-right: 10px;
        }

        .payment-type {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
        }

        .payment-dp {
            background: var(--warning-color);
        }

        .payment-lunas {
            background: var(--success-color);
        }

        .total-price {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin: 20px 0;
            box-shadow: 0 5px 20px rgba(39, 174, 96, 0.3);
        }

        .total-price h5 {
            margin: 0;
            font-weight: 700;
            font-size: 1.3em;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 25px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.95rem;
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-info {
            background: linear-gradient(45deg, var(--info-color), #5dade2);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-success:hover {
            box-shadow: 0 6px 20px rgba(39, 174, 96, 0.4);
        }

        .btn-danger:hover {
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-info:hover {
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
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
            margin-right: 12px;
            font-size: 1.2rem;
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

        .alert-info {
            background: linear-gradient(45deg, rgba(52, 152, 219, 0.15), rgba(93, 173, 226, 0.15));
            color: var(--info-color);
            border-left: 4px solid var(--info-color);
        }

        .no-data {
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }

        .no-data i {
            font-size: 5rem;
            margin-bottom: 25px;
            opacity: 0.4;
            color: var(--success-color);
        }

        .no-data h4 {
            color: var(--success-color);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .no-data p {
            font-size: 1.1rem;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .booking-info {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                justify-content: stretch;
            }
            
            .action-buttons .btn {
                flex: 1;
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

        .urgent-indicator {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75em;
            font-weight: 500;
            margin-left: 10px;
        }
    </style>
</head>

<body>
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1><i class="bi bi-check-circle me-2"></i>Konfirmasi Booking Pending</h1>
            <p class="mb-0">Review dan konfirmasi booking yang masuk dari customer dengan sistem pembayaran</p>
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
            <h4 class="mb-4"><i class="bi bi-bar-chart me-2"></i>Statistik Booking Pending</h4>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $stats['total_pending']; ?></div>
                    <div class="stat-label">Total Pending</div>
                </div>
                <div class="stat-item today">
                    <div class="stat-number"><?php echo $stats['today_pending']; ?></div>
                    <div class="stat-label">Pending Hari Ini</div>
                </div>
                <div class="stat-item dp">
                    <div class="stat-number">Rp <?php echo number_format($stats['total_dp'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total DP</div>
                </div>
                <div class="stat-item lunas">
                    <div class="stat-number">Rp <?php echo number_format($stats['total_lunas'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Lunas</div>
                </div>
                <div class="stat-item amount">
                    <div class="stat-number">Rp <?php echo number_format($stats['total_pending_amount'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Menunggu</div>
                </div>
            </div>
        </div>

        <!-- Booking Confirmation Section -->
        <div class="booking-section fade-in">
            <h3>
                <i class="bi bi-clock-history me-2"></i>Daftar Booking Menunggu Konfirmasi
                <small class="text-muted">(<?php echo count($booking_pending); ?> booking)</small>
                <?php if (count($booking_pending) > 5): ?>
                <span class="urgent-indicator">
                    <i class="bi bi-exclamation-triangle me-1"></i>Perlu Perhatian
                </span>
                <?php endif; ?>
            </h3>

            <?php if (empty($booking_pending)): ?>
            <div class="no-data">
                <i class="bi bi-check-circle-fill"></i>
                <h4>Semua booking sudah dikonfirmasi!</h4>
                <p>Tidak ada booking yang perlu dikonfirmasi saat ini. Silakan cek kembali nanti.</p>
            </div>
            <?php else: ?>
            
            <?php foreach ($booking_pending as $booking): ?>
            <?php
            $total_harga = $booking['harga'] * $booking['lama_sewa'];
            $sisa_pembayaran = $total_harga - $booking['total_dibayar'];
            $is_urgent = (strtotime($booking['tanggal'] . ' ' . $booking['jam']) - time()) < (24 * 3600); // Less than 24 hours
            ?>
            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">
                        <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($booking['nama_lapangan']); ?>
                        <?php if ($is_urgent): ?>
                        <span class="urgent-indicator">
                            <i class="bi bi-clock me-1"></i>Urgent
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="booking-id">
                        ID: #<?php echo $booking['id']; ?>
                    </div>
                </div>

                <div class="booking-info">
                    <!-- Customer Information -->
                    <div class="info-section">
                        <h6><i class="bi bi-person me-2"></i>Informasi Customer</h6>
                        <div class="info-item">
                            <span class="info-label">Nama Lengkap:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Username:</span>
                            <span class="info-value">@<?php echo htmlspecialchars($booking['username']); ?></span>
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
                            <span class="info-label">Kontak Booking:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['kontak']); ?></span>
                        </div>
                    </div>

                    <!-- Booking Details -->
                    <div class="info-section">
                        <h6><i class="bi bi-calendar me-2"></i>Detail Booking</h6>
                        <div class="info-item">
                            <span class="info-label">Tanggal Main:</span>
                            <span class="info-value"><?php echo date('l, d F Y', strtotime($booking['tanggal'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Waktu:</span>
                            <span class="info-value"><?php echo date('H:i', strtotime($booking['jam'])); ?> - <?php echo date('H:i', strtotime($booking['jam'] . ' + ' . $booking['lama_sewa'] . ' hours')); ?> WIB</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Durasi:</span>
                            <span class="info-value"><?php echo $booking['lama_sewa']; ?> jam</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Harga per Jam:</span>
                            <span class="info-value">Rp <?php echo number_format($booking['harga'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Booking Dibuat:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></span>
                        </div>
                    </div>

                    <!-- Field Information -->
                    <div class="info-section">
                        <h6><i class="bi bi-building me-2"></i>Informasi Lapangan</h6>
                        <div class="info-item">
                            <span class="info-label">Nama Lapangan:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['nama_lapangan']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tipe:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['tipe']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Lokasi:</span>
                            <span class="info-value">Futsal Center, Depok</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Fasilitas:</span>
                            <span class="info-value">Ruang Ganti, Parkir, Kantin</span>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <?php if ($booking['payment_method']): ?>
                <div class="payment-section">
                    <h6><i class="bi bi-credit-card me-2"></i>Informasi Pembayaran</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Metode Pembayaran:</span>
                                <span class="payment-method"><?php echo ucfirst($booking['payment_method']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Jenis Pembayaran:</span>
                                <span class="payment-type payment-<?php echo $booking['status_pembayaran']; ?>">
                                    <?php echo ($booking['status_pembayaran'] == 'dp') ? 'DP (50%)' : 'Lunas (100%)'; ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status Pembayaran:</span>
                                <span class="badge bg-<?php echo ($booking['payment_status'] == 'completed') ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($booking['payment_status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Total Harga:</span>
                                <span class="info-value">Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Sudah Dibayar:</span>
                                <span class="info-value text-success">Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></span>
                            </div>
                            <?php if ($sisa_pembayaran > 0): ?>
                            <div class="info-item">
                                <span class="info-label">Sisa Pembayaran:</span>
                                <span class="info-value text-warning">Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if ($booking['payment_date']): ?>
                            <div class="info-item">
                                <span class="info-label">Tanggal Bayar:</span>
                                <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['payment_date'])); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Total Price Display -->
                <div class="total-price">
                    <h5>
                        <i class="bi bi-cash me-2"></i>
                        Total yang Harus Dibayar: Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?>
                        <?php if ($booking['status_pembayaran'] == 'dp'): ?>
                        <small style="opacity: 0.8; display: block; font-size: 0.8em; margin-top: 5px;">
                            (DP 50% dari total Rp <?php echo number_format($total_harga, 0, ',', '.'); ?>)
                        </small>
                        <?php endif; ?>
                    </h5>
                </div>

                <!-- Time Warning -->
                <?php if ($is_urgent): ?>
                <div class="alert alert-info">
                    <i class="bi bi-clock-history"></i>
                    <strong>Perhatian:</strong> Booking ini akan dimulai dalam waktu kurang dari 24 jam. 
                    Segera konfirmasi untuk memastikan customer dapat menggunakan lapangan.
                </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button onclick="confirmBooking(<?php echo $booking['id']; ?>, 'approve')" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Setujui & Aktifkan Booking
                    </button>
                    <button onclick="confirmBooking(<?php echo $booking['id']; ?>, 'reject')" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Tolak & Refund
                    </button>
                    <a href="payment_receipt.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn btn-info">
                        <i class="bi bi-receipt me-2"></i>Lihat Invoice
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Alasan Penolakan Booking</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Pilih alasan penolakan:</label>
                        <select class="form-select" id="rejectReason">
                            <option value="">-- Pilih Alasan --</option>
                            <option value="Lapangan sedang maintenance">Lapangan sedang maintenance</option>
                            <option value="Jadwal bentrok dengan event">Jadwal bentrok dengan event</option>
                            <option value="Pembayaran tidak sesuai">Pembayaran tidak sesuai</option>
                            <option value="Data customer tidak lengkap">Data customer tidak lengkap</option>
                            <option value="Lapangan rusak/tidak tersedia">Lapangan rusak/tidak tersedia</option>
                            <option value="other">Alasan lainnya...</option>
                        </select>
                    </div>
                    <div class="mb-3" id="customReasonSection" style="display: none;">
                        <label class="form-label">Alasan khusus:</label>
                        <textarea class="form-control" id="customReason" rows="3" placeholder="Masukkan alasan penolakan..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Perhatian:</strong> Menolak booking akan menghasilkan refund penuh kepada customer.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="button" class="btn btn-danger" id="confirmReject">Tolak Booking</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        const rejectModal = new bootstrap.Modal(document.getElementById('rejectModal'));
        let currentBookingId = null;

        function confirmBooking(id, action) {
            currentBookingId = id;
            
            if (action === 'approve') {
                Swal.fire({
                    title: 'Konfirmasi Persetujuan',
                    text: 'Yakin ingin menyetujui booking ini? Status akan berubah menjadi AKTIF.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#27ae60',
                    cancelButtonColor: '#95a5a6',
                    confirmButtonText: 'Ya, Setujui!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = `?action=approve&id=${id}`;
                    }
                });
            } else if (action === 'reject') {
                rejectModal.show();
            }
        }

        // Handle reason selection
        document.getElementById('rejectReason').addEventListener('change', function() {
            const customSection = document.getElementById('customReasonSection');
            if (this.value === 'other') {
                customSection.style.display = 'block';
            } else {
                customSection.style.display = 'none';
            }
        });

        // Handle rejection confirmation
        document.getElementById('confirmReject').addEventListener('click', function() {
            const reasonSelect = document.getElementById('rejectReason');
            const customReason = document.getElementById('customReason');
            
            let reason = reasonSelect.value;
            if (reason === 'other') {
                reason = customReason.value;
            }
            
            if (!reason) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Alasan Diperlukan',
                    text: 'Silakan pilih atau masukkan alasan penolakan.'
                });
                return;
            }
            
            rejectModal.hide();
            
            Swal.fire({
                title: 'Konfirmasi Penolakan',
                text: `Yakin ingin menolak booking ini? Customer akan mendapat refund penuh.\n\nAlasan: ${reason}`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Ya, Tolak!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?action=reject&id=${currentBookingId}&reason=${encodeURIComponent(reason)}`;
                }
            });
        });

        // Auto hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                if (!alert.classList.contains('alert-info')) { // Keep info alerts
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 300);
                }
            });
        }, 5000);

        // Auto refresh every 30 seconds to check for new bookings
        setInterval(function() {
            // Only refresh if no modal is open
            if (!document.querySelector('.modal.show')) {
                location.reload();
            }
        }, 30000);

        // Add sound notification for new bookings (optional)
        <?php if (count($booking_pending) > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // You can add a subtle notification sound here
            console.log('<?php echo count($booking_pending); ?> booking(s) menunggu konfirmasi');
        });
        <?php endif; ?>
    </script>
</body>
</html>