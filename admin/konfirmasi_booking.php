<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';

// Handle konfirmasi booking
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = sanitize_input($_GET['action']);
    $booking_id = (int)$_GET['id'];
    
    if ($action == 'approve') {
        $updateQuery = "UPDATE booking SET status = 'aktif' WHERE id = $booking_id AND status = 'pending'";
        if (mysqli_query($connection, $updateQuery)) {
            if (mysqli_affected_rows($connection) > 0) {
                $message = 'Booking berhasil dikonfirmasi dan disetujui!';
            } else {
                $message = 'Booking tidak ditemukan atau sudah diproses!';
            }
        } else {
            $message = 'Terjadi kesalahan: ' . mysqli_error($connection);
        }
    } elseif ($action == 'reject') {
        $updateQuery = "UPDATE booking SET status = 'batal' WHERE id = $booking_id AND status = 'pending'";
        if (mysqli_query($connection, $updateQuery)) {
            if (mysqli_affected_rows($connection) > 0) {
                $message = 'Booking berhasil ditolak!';
            } else {
                $message = 'Booking tidak ditemukan atau sudah diproses!';
            }
        } else {
            $message = 'Terjadi kesalahan: ' . mysqli_error($connection);
        }
    }
}

// Ambil booking pending dengan informasi lengkap
$pendingQuery = "
    SELECT b.*, u.username, u.full_name, u.email, u.phone, l.nama as nama_lapangan, l.harga, l.tipe 
    FROM booking b 
    JOIN users u ON b.id_user = u.id 
    JOIN lapangan l ON b.id_lapangan = l.id 
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
        COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_pending
    FROM booking 
    WHERE status = 'pending'
";
$statsResult = mysqli_query($connection, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Booking - Admin Futsal Booking</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #e74c3c;
            --secondary-color: #c0392b;
            --accent-color: #ec7063;
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
            background: linear-gradient(135deg,rgb(255, 237, 237) 0%,rgb(240, 224, 254) 100%);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
        }

        .stat-item.today {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .booking-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
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
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border-left: 4px solid var(--warning-color);
            transition: all var(--transition-speed) ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .booking-card:hover {
            background: #fff8e1;
            transform: translateX(5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        .booking-title {
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.3em;
        }

        .booking-id {
            background: var(--warning-color);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: 500;
        }

        .booking-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-section {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .info-section h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.9em;
            text-transform: uppercase;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #666;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 600;
        }

        .total-price {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
        }

        .total-price h5 {
            margin: 0;
            font-weight: 700;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
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

        .alert-info {
            background: linear-gradient(45deg, rgba(52, 152, 219, 0.1), rgba(93, 173, 226, 0.1));
            color: #3498db;
            border-left: 4px solid #3498db;
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
            
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
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
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1><i class="bi bi-check-circle me-2"></i>Konfirmasi Booking Pending</h1>
            <p class="mb-0">Review dan konfirmasi booking yang masuk dari user</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert <?php echo (strpos($message, 'berhasil') !== false) ? 'alert-success' : 'alert-info'; ?> fade-in">
            <i class="bi bi-<?php echo (strpos($message, 'berhasil') !== false) ? 'check-circle' : 'info-circle'; ?>"></i>
            <?php echo $message; ?>
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
            </div>
        </div>

        <!-- Booking Confirmation Section -->
        <div class="booking-section fade-in">
            <h3>
                <i class="bi bi-clock-history me-2"></i>Daftar Booking Menunggu Konfirmasi
                <small class="text-muted">(<?php echo count($booking_pending); ?> booking)</small>
            </h3>

            <?php if (empty($booking_pending)): ?>
            <div class="no-data">
                <i class="bi bi-check-circle-fill text-success"></i>
                <h5>Tidak ada booking yang perlu dikonfirmasi</h5>
                <p>Semua booking sudah diproses. Cek kembali nanti!</p>
            </div>
            <?php else: ?>
            <?php foreach ($booking_pending as $booking): ?>
            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-title">
                        <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($booking['nama_lapangan']); ?>
                    </div>
                    <div class="booking-id">
                        ID: #<?php echo $booking['id']; ?>
                    </div>
                </div>

                <div class="booking-info">
                    <!-- User Information -->
                    <div class="info-section">
                        <h6><i class="bi bi-person me-1"></i>Informasi User</h6>
                        <div class="info-item">
                            <span class="info-label">Nama:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['full_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Username:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['username']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Telepon:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['phone']); ?></span>
                        </div>
                    </div>

                    <!-- Booking Details -->
                    <div class="info-section">
                        <h6><i class="bi bi-calendar me-1"></i>Detail Booking</h6>
                        <div class="info-item">
                            <span class="info-label">Tanggal:</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($booking['tanggal'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Jam:</span>
                            <span class="info-value"><?php echo date('H:i', strtotime($booking['jam'])); ?> WIB</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Durasi:</span>
                            <span class="info-value"><?php echo $booking['lama_sewa']; ?> jam</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Kontak:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['kontak']); ?></span>
                        </div>
                    </div>

                    <!-- Field Information -->
                    <div class="info-section">
                        <h6><i class="bi bi-building me-1"></i>Informasi Lapangan</h6>
                        <div class="info-item">
                            <span class="info-label">Nama:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['nama_lapangan']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Tipe:</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['tipe']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Harga/jam:</span>
                            <span class="info-value">Rp <?php echo number_format($booking['harga'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Dibuat:</span>
                            <span class="info-value"><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Total Price -->
                <div class="total-price">
                    <h5><i class="bi bi-cash me-2"></i>Total: Rp <?php echo number_format($booking['harga'] * $booking['lama_sewa'], 0, ',', '.'); ?></h5>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button onclick="confirmBooking(<?php echo $booking['id']; ?>, 'approve')" class="btn btn-success">
                        <i class="bi bi-check-circle me-2"></i>Setujui Booking
                    </button>
                    <button onclick="confirmBooking(<?php echo $booking['id']; ?>, 'reject')" class="btn btn-danger">
                        <i class="bi bi-x-circle me-2"></i>Tolak Booking
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function confirmBooking(id, action) {
            const actionText = action === 'approve' ? 'menyetujui' : 'menolak';
            const actionColor = action === 'approve' ? '#27ae60' : '#e74c3c';
            
            Swal.fire({
                title: `Konfirmasi ${actionText.charAt(0).toUpperCase() + actionText.slice(1)}`,
                text: `Yakin ingin ${actionText} booking ini?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: actionColor,
                cancelButtonColor: '#95a5a6',
                confirmButtonText: `Ya, ${actionText.charAt(0).toUpperCase() + actionText.slice(1)}!`,
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?action=${action}&id=${id}`;
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

        // Auto refresh every 30 seconds to check for new bookings
        setInterval(function() {
            if (<?php echo count($booking_pending); ?> === 0) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>