<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include '../config/database.php';

// Check if connection is valid
if (!$connection) {
    die("Connection failed: " . mysqli_connect_error());
}

// Ambil statistik user dengan mysqli
$user_id = $_SESSION['user_id'];

// Total booking
$totalBookingQuery = "SELECT COUNT(*) as total_booking FROM booking WHERE id_user = '$user_id'";
$totalResult = mysqli_query($connection, $totalBookingQuery);
$total_booking = mysqli_fetch_assoc($totalResult)['total_booking'];

// Booking aktif
$activeBookingQuery = "SELECT COUNT(*) as booking_aktif FROM booking WHERE id_user = '$user_id' AND status = 'aktif'";
$activeResult = mysqli_query($connection, $activeBookingQuery);
$booking_aktif = mysqli_fetch_assoc($activeResult)['booking_aktif'];

// Booking pending
$pendingBookingQuery = "SELECT COUNT(*) as booking_pending FROM booking WHERE id_user = '$user_id' AND status = 'pending'";
$pendingResult = mysqli_query($connection, $pendingBookingQuery);
$booking_pending = mysqli_fetch_assoc($pendingResult)['booking_pending'];

// Recent bookings
$recentBookingsQuery = "
    SELECT b.*, l.nama as nama_lapangan 
    FROM booking b 
    JOIN lapangan l ON b.id_lapangan = l.id 
    WHERE b.id_user = '$user_id'
    ORDER BY b.created_at DESC 
    LIMIT 5
";
$recentResult = mysqli_query($connection, $recentBookingsQuery);
$recentBookings = [];
while ($row = mysqli_fetch_assoc($recentResult)) {
    $recentBookings[] = $row;
}

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'User';
$full_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : $username;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard User - Futsal Booking</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-color: #3498db;
            --primary-dark: #2980b9;
            --secondary-color: #5dade2;
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

        .welcome-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            border-left: 5px solid var(--primary-color);
            display: flex;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .welcome-card i {
            font-size: 3rem;
            color: var(--primary-color);
            margin-right: 20px;
        }

        .welcome-message {
            font-size: 1.4rem;
            font-weight: 500;
            color: #333;
        }

        .welcome-message span {
            font-weight: 700;
            color: var(--primary-color);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: #ffffff;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            transition: all var(--transition-speed) ease;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            position: relative;
            z-index: 1;
            border: none;
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

        .stat-number {
            font-size: 2.5rem;
            color: white;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .stat-label {
            font-size: 1rem;
            font-weight: 500;
            letter-spacing: 0.5px;
            margin-bottom: 0;
            text-transform: uppercase;
        }

        .card-total {
            background: linear-gradient(45deg, #3498db, #5dade2);
        }

        .card-active {
            background: linear-gradient(45deg, #38b27b, #58d68d);
        }

        .card-pending {
            background: linear-gradient(45deg, #f39c12, #f7dc6f);
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
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .menu-item:hover {
            color: white;
            transform: var(--hover-transform);
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
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

        @media (max-width: 768px) {
            .welcome-card {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }

            .welcome-card i {
                margin-right: 0;
                margin-bottom: 15px;
            }

            .stats {
                grid-template-columns: 1fr;
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
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include 'user_sidebar.php'; ?>

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
        <!-- Welcome Card -->
        <div class="welcome-card fade-in">
            <i class="bi bi-person-circle"></i>
            <div class="welcome-message">
                Selamat datang, <span><?php echo htmlspecialchars($full_name); ?></span>!<br>
                <small class="text-muted">Dashboard User - Sistem Booking Lapangan Futsal</small>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats fade-in">
            <div class="stat-card card-total">
                <div class="icon"><i class="bi bi-calendar-check"></i></div>
                <div class="stat-number"><?php echo $total_booking; ?></div>
                <div class="stat-label">Total Booking</div>
            </div>
            <div class="stat-card card-active">
                <div class="icon"><i class="bi bi-check-circle"></i></div>
                <div class="stat-number"><?php echo $booking_aktif; ?></div>
                <div class="stat-label">Booking Aktif</div>
            </div>
            <div class="stat-card card-pending">
                <div class="icon"><i class="bi bi-clock-history"></i></div>
                <div class="stat-number"><?php echo $booking_pending; ?></div>
                <div class="stat-label">Booking Pending</div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="recent-section fade-in">
            <h3><i class="bi bi-clock-history me-2"></i>Booking Terbaru Anda</h3>
            <?php if (empty($recentBookings)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-inbox display-4 text-muted"></i>
                    <p class="text-muted mt-2">Belum ada booking</p>
                    <a href="user/lihat_lapangan.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Booking Sekarang
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($recentBookings as $booking): ?>
                    <div class="recent-item">
                        <h6><?php echo htmlspecialchars($booking['nama_lapangan']); ?></h6>
                        <div class="details">
                            <div>
                                <strong>Tanggal:</strong> <?php echo date('d/m/Y', strtotime($booking['tanggal'])); ?> |
                                <strong>Jam:</strong> <?php echo $booking['jam']; ?> |
                                <strong>Durasi:</strong> <?php echo $booking['lama_sewa']; ?> jam
                            </div>
                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="text-center mt-3">
                    <a href="history.php" class="btn btn-primary">
                        <i class="bi bi-list-check me-2"></i>Lihat Semua Booking
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Menu Section -->
        <div class="menu-section fade-in">
            <h3><i class="bi bi-grid-3x3-gap me-2"></i>Menu Utama</h3>
            <div class="menu-grid">
                <a href="lihat_lapangan.php" class="menu-item">
                    <i class="bi bi-building"></i>
                    <h4>Lihat Lapangan</h4>
                    <p>Lihat dan booking lapangan tersedia</p>
                </a>
                <a href="history.php" class="menu-item">
                    <i class="bi bi-list-check"></i>
                    <h4>Daftar Booking</h4>
                    <p>Kelola booking Anda</p>
                </a>
            </div>
        </div>
    </div>

    <script>
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