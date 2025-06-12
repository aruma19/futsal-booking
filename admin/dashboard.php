<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

include('../config/database.php');

// Check if connection is valid
check_connection();

// Get admin username from session
$admin_username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Admin';

// Get total count of lapangan
$totalLapanganQuery = "SELECT COUNT(*) as total FROM lapangan";
$totalResult = mysqli_query($connection, $totalLapanganQuery);
$totalLapangan = mysqli_fetch_assoc($totalResult)['total'];

// Get total count of booking
$totalBookingQuery = "SELECT COUNT(*) as total FROM booking";
$bookingResult = mysqli_query($connection, $totalBookingQuery);
$totalBooking = mysqli_fetch_assoc($bookingResult)['total'];

// Get count of pending booking
$pendingQuery = "SELECT COUNT(*) as pending FROM booking WHERE status = 'pending'";
$pendingResult = mysqli_query($connection, $pendingQuery);
$pendingCount = mysqli_fetch_assoc($pendingResult)['pending'];

// Get count of active booking
$activeQuery = "SELECT COUNT(*) as active FROM booking WHERE status = 'aktif'";
$activeResult = mysqli_query($connection, $activeQuery);
$activeCount = mysqli_fetch_assoc($activeResult)['active'];

// Get recent bookings
$recentBookingsQuery = "
    SELECT b.*, u.username, u.full_name, l.nama as nama_lapangan 
    FROM booking b 
    JOIN users u ON b.id_user = u.id 
    JOIN lapangan l ON b.id_lapangan = l.id 
    ORDER BY b.created_at DESC 
    LIMIT 5
";
$recentResult = mysqli_query($connection, $recentBookingsQuery);
$recentBookings = [];
while ($row = mysqli_fetch_assoc($recentResult)) {
    $recentBookings[] = $row;
}

// Get total revenue this month
$revenueQuery = "
    SELECT SUM(l.harga * b.lama_sewa) as revenue 
    FROM booking b 
    JOIN lapangan l ON b.id_lapangan = l.id 
    WHERE b.status IN ('aktif', 'selesai') 
    AND MONTH(b.created_at) = MONTH(CURRENT_DATE()) 
    AND YEAR(b.created_at) = YEAR(CURRENT_DATE())
";
$revenueResult = mysqli_query($connection, $revenueQuery);
$revenue = mysqli_fetch_assoc($revenueResult)['revenue'] ?? 0;
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Futsal Booking</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: #e74c3c;
            --primary-dark: #c0392b;
            --secondary-color: #ec7063;
            --success-color: #38b27b;
            --warning-color: #fbb13c;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --light-bg: #f8f9fa;
            --dark-bg: #343a40;
            --text-color: #495057;
            --card-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            --hover-transform: translateY(-5px);
            --transition-speed: 0.3s;
            --border-radius: 12px;
        }

        body {
            background: linear-gradient(135deg, rgb(255, 237, 237) 0%, rgb(240, 224, 254) 100%);
            font-family: 'Poppins', 'Segoe UI', Roboto, sans-serif;
            color: var(--text-color);
            min-height: 100vh;
        }

        .container {
            max-width: 1400px;
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

        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border-left: 5px solid var(--primary-color);
            display: flex;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .welcome-card i {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-right: 20px;
        }

        .welcome-message {
            font-size: 1.3rem;
            font-weight: 500;
            color: #333;
        }

        .welcome-message span {
            font-weight: 700;
            color: var(--primary-color);
        }

        .stats-section {
            margin-bottom: 30px;
        }

        .stat-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            padding: 25px;
            height: 100%;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            position: relative;
            z-index: 1;
            border: none;
            text-decoration: none;
            color: white;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.4));
            z-index: -1;
            transition: transform 0.6s ease;
        }

        .stat-card:hover {
            transform: var(--hover-transform);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
            text-decoration: none;
            color: white;
        }

        .stat-card:hover::before {
            transform: translateY(-10px);
        }

        .stat-card .icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            padding: 15px;
            border-radius: 50%;
            line-height: 1;
        }

        .stat-card h3 {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-card p {
            font-size: 1rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            margin-bottom: 0;
            text-transform: uppercase;
        }

        .card-lapangan {
            background: linear-gradient(45deg, #e74c3c, #ec7063);
        }

        .card-booking {
            background: linear-gradient(45deg, #3498db, #5dade2);
        }

        .card-pending {
            background: linear-gradient(45deg, #f39c12, #f7dc6f);
        }

        .card-active {
            background: linear-gradient(45deg, #38b27b, #58d68d);
        }

        .card-revenue {
            background: linear-gradient(45deg, #9b59b6, #bb8fce);
        }

        .recent-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .recent-section h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .recent-item {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            background: #f8f9fa;
            border-left: 4px solid var(--info-color);
            transition: all 0.3s ease;
        }

        .recent-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .recent-item h6 {
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: 600;
        }

        .recent-item .details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            font-size: 0.9rem;
            color: #666;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-pending {
            background: #ffeaa7;
            color: #d63031;
        }

        .status-aktif {
            background: #55efc4;
            color: #00b894;
        }

        .status-selesai {
            background: #ddd;
            color: #636e72;
        }

        .menu-section {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
        }

        .menu-section h3 {
            color: var(--primary-color);
            margin-bottom: 25px;
            font-size: 1.4rem;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .menu-item {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 25px;
            border-radius: var(--border-radius);
            text-decoration: none;
            text-align: center;
            transition: all var(--transition-speed) ease;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .menu-item:hover {
            color: white;
            transform: var(--hover-transform);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
            text-decoration: none;
        }

        .menu-item h4 {
            margin-bottom: 10px;
            font-size: 1.2rem;
            font-weight: 600;
        }

        .menu-item p {
            margin: 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .menu-item i {
            font-size: 2rem;
            margin-bottom: 15px;
            display: block;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .welcome-card {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }

            .welcome-card i {
                margin-right: 0;
                margin-bottom: 15px;
            }

            .stat-card {
                margin-bottom: 15px;
            }

            .menu-grid {
                grid-template-columns: 1fr;
            }

            .recent-item .details {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease forwards;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            transform: translateX(100%);
            transition: transform 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .notification.success {
            background: linear-gradient(135deg, #28a745, #20c997);
        }

        .notification.show {
            transform: translateX(0);
        }

        .delay-1 {
            animation-delay: 0.1s;
        }

        .delay-2 {
            animation-delay: 0.2s;
        }

        .delay-3 {
            animation-delay: 0.3s;
        }

        .delay-4 {
            animation-delay: 0.4s;
        }

        .delay-5 {
            animation-delay: 0.5s;
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include 'admin_sidebar.php'; ?>

    <?php
    if (isset($_GET['pesan'])) {
        if ($_GET['pesan'] == "login_berhasil") {
            echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showNotification("Login berhasil! Selamat datang kembali.", "success");
                        });
                    </script>';
        }
    }
    ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard Admin</h1>
            <p class="mb-0">Sistem Manajemen Booking Futsal</p>
        </div>

        <!-- Welcome Card -->
        <div class="welcome-card fade-in">
            <i class="bi bi-person-circle"></i>
            <div class="welcome-message">
                Selamat datang, <span><?php echo htmlspecialchars($admin_username); ?></span>!<br>
                <small class="text-muted">Kelola sistem booking futsal dengan mudah dan efisien.</small>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-section">
            <div class="row">
                <div class="col-lg-2-4 col-md-4 col-sm-6 mb-4 fade-in delay-1">
                    <a href="kelola_lapangan.php" class="text-decoration-none">
                        <div class="stat-card card-lapangan">
                            <div class="icon"><i class="bi bi-building"></i></div>
                            <h3><?php echo $totalLapangan; ?></h3>
                            <p>Total Lapangan</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-2-4 col-md-4 col-sm-6 mb-4 fade-in delay-2">
                    <a href="daftar_booking.php" class="text-decoration-none">
                        <div class="stat-card card-booking">
                            <div class="icon"><i class="bi bi-calendar-check"></i></div>
                            <h3><?php echo $totalBooking; ?></h3>
                            <p>Total Booking</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-2-4 col-md-4 col-sm-6 mb-4 fade-in delay-3">
                    <a href="konfirmasi_booking.php" class="text-decoration-none">
                        <div class="stat-card card-pending">
                            <div class="icon"><i class="bi bi-clock-history"></i></div>
                            <h3><?php echo $pendingCount; ?></h3>
                            <p>Booking Pending</p>
                        </div>
                    </a>
                </div>

                <div class="col-lg-2-4 col-md-4 col-sm-6 mb-4 fade-in delay-4">
                    <div class="stat-card card-active">
                        <div class="icon"><i class="bi bi-check-circle"></i></div>
                        <h3><?php echo $activeCount; ?></h3>
                        <p>Booking Aktif</p>
                    </div>
                </div>

                <div class="col-lg-2-4 col-md-4 col-sm-6 mb-4 fade-in delay-5">
                    <div class="stat-card card-revenue">
                        <div class="icon"><i class="bi bi-cash-coin"></i></div>
                        <h3>Rp <?php echo number_format($revenue / 1000, 0); ?>K</h3>
                        <p>Revenue Bulan Ini</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="recent-section fade-in">
            <h3><i class="bi bi-clock-history me-2"></i>Booking Terbaru</h3>
            <?php if (empty($recentBookings)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-inbox display-4 text-muted"></i>
                    <p class="text-muted mt-2">Belum ada booking</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentBookings as $booking): ?>
                    <div class="recent-item">
                        <h6><?php echo htmlspecialchars($booking['nama_lapangan']); ?></h6>
                        <div class="details">
                            <div>
                                <strong><?php echo htmlspecialchars($booking['full_name']); ?></strong> -
                                <?php echo date('d/m/Y H:i', strtotime($booking['tanggal'] . ' ' . $booking['jam'])); ?>
                            </div>
                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-3">
                    <a href="daftar_booking.php" class="btn btn-primary">
                        <i class="bi bi-list-check me-2"></i>Lihat Semua Booking
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Menu Section -->
        <div class="menu-section fade-in">
            <h3><i class="bi bi-grid-3x3-gap me-2"></i>Menu Administrasi</h3>
            <div class="menu-grid">
                <a href="kelola_lapangan.php" class="menu-item">
                    <i class="bi bi-building"></i>
                    <h4>Kelola Lapangan</h4>
                    <p>Kelola data lapangan futsal</p>
                </a>
                <a href="tambah_lapangan.php" class="menu-item">
                    <i class="bi bi-plus-circle"></i>
                    <h4>Tambah Lapangan</h4>
                    <p>Tambah lapangan futsal baru</p>
                </a>
                <a href="konfirmasi_booking.php" class="menu-item">
                    <i class="bi bi-check-circle"></i>
                    <h4>Konfirmasi Booking</h4>
                    <p>Approve booking pending dari user</p>
                </a>
                <a href="daftar_booking.php" class="menu-item">
                    <i class="bi bi-list-check"></i>
                    <h4>Daftar Booking</h4>
                    <p>Lihat semua riwayat booking</p>
                </a>
            </div>
        </div>
    </div>

    <script>
        // Auto refresh statistics every 60 seconds
        setInterval(function() {
            location.reload();
        }, 60000);

        // Add custom CSS for 5-column layout
        const style = document.createElement('style');
        style.textContent = `
            @media (min-width: 992px) {
                .col-lg-2-4 {
                    flex: 0 0 auto;
                    width: 20%;
                }
            }
        `;
        document.head.appendChild(style);

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
        <i class="bi bi-check-circle me-2"></i>
        <span>${message}</span>
    `;

            document.body.appendChild(notification);

            setTimeout(() => {
                notification.classList.add('show');
            }, 100);

            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>