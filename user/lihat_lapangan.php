<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';

// Ambil data lapangan dengan status booking dinamis
$lapanganQuery = "SELECT * FROM lapangan ORDER BY nama ASC";
$lapanganResult = mysqli_query($connection, $lapanganQuery);
$lapangan_list = [];

if ($lapanganResult && mysqli_num_rows($lapanganResult) > 0) {
    while($row = mysqli_fetch_assoc($lapanganResult)) {
        // Cek apakah ada booking aktif untuk lapangan ini hari ini
        $today = date('Y-m-d');
        $current_time = date('H:i:s');
        
        $checkBookingQuery = "
            SELECT COUNT(*) as active_bookings 
            FROM booking 
            WHERE id_lapangan = {$row['id']} 
            AND tanggal = '$today' 
            AND status IN ('pending', 'aktif')
            AND (
                (jam <= '$current_time' AND ADDTIME(jam, SEC_TO_TIME(lama_sewa * 3600)) > '$current_time')
                OR jam > '$current_time'
            )
        ";
        
        $checkResult = mysqli_query($connection, $checkBookingQuery);
        $bookingData = mysqli_fetch_assoc($checkResult);
        
        // Set status dinamis berdasarkan booking
        if ($bookingData['active_bookings'] > 0) {
            $row['dynamic_status'] = 'habis';
            $row['status_text'] = 'Sedang Dibooking';
        } else {
            $row['dynamic_status'] = 'tersedia';
            $row['status_text'] = 'Tersedia';
        }
        
        // Ambil booking hari ini untuk lapangan ini
        $todayBookingsQuery = "
            SELECT jam, lama_sewa, status 
            FROM booking 
            WHERE id_lapangan = {$row['id']} 
            AND tanggal = '$today' 
            AND status IN ('pending', 'aktif')
            ORDER BY jam ASC
        ";
        $todayBookingsResult = mysqli_query($connection, $todayBookingsQuery);
        $row['today_bookings'] = [];
        while($booking = mysqli_fetch_assoc($todayBookingsResult)) {
            $row['today_bookings'][] = $booking;
        }
        
        $lapangan_list[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lihat Lapangan - Futsal Booking</title>

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
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
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

        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 25px;
            margin: 20px 0;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: all var(--transition-speed) ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .card:hover {
            transform: var(--hover-transform);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }

        .card-img {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #eee;
        }

        .card-content {
            padding: 25px;
        }

        .card h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 600;
            font-size: 1.3rem;
        }

        .card-info {
            margin-bottom: 15px;
        }

        .card-info p {
            margin: 8px 0;
            display: flex;
            align-items: center;
            font-size: 0.95rem;
        }

        .card-info i {
            margin-right: 8px;
            color: var(--primary-color);
            width: 16px;
        }

        .price {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            text-align: center;
            margin: 15px 0 20px 0;
            font-size: 1.3rem;
            font-weight: 700;
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 15px;
        }

        .status-tersedia {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .status-habis {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
            font-style: italic;
            background: linear-gradient(45deg, #ecf0f1, #bdc3c7);
            font-size: 1rem;
        }

        .card-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
            flex: 1;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
            background: linear-gradient(45deg, var(--secondary-color), var(--primary-color));
        }

        .btn-primary:disabled {
            background: #95a5a6;
            transform: none;
            box-shadow: none;
            cursor: not-allowed;
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            background: transparent;
            padding: 8px 18px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .facilities {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .facilities h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .facilities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .facility-tag {
            background: var(--primary-color);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .booking-schedule {
            background: #fff3cd;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid var(--warning-color);
        }

        .booking-schedule h6 {
            color: var(--warning-color);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .schedule-item {
            background: white;
            padding: 8px 12px;
            border-radius: 5px;
            margin: 5px 0;
            font-size: 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .schedule-status {
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .schedule-pending {
            background: #fff3cd;
            color: #856404;
        }

        .schedule-aktif {
            background: #d1ecf1;
            color: #0c5460;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .empty-state i {
            font-size: 4rem;
            color: #bdc3c7;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: var(--text-color);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            font-size: 0.95rem;
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

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .cards-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .card-content {
                padding: 20px;
            }
            
            .card-actions {
                flex-direction: column;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease forwards;
        }

        .fade-in:nth-child(1) { animation-delay: 0.1s; }
        .fade-in:nth-child(2) { animation-delay: 0.2s; }
        .fade-in:nth-child(3) { animation-delay: 0.3s; }
        .fade-in:nth-child(4) { animation-delay: 0.4s; }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include 'user_sidebar.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1><i class="bi bi-geo-alt me-2"></i>Daftar Lapangan Futsal</h1>
            <p class="mb-0">Pilih lapangan yang sesuai dengan kebutuhan Anda</p>
            <small class="text-muted">Status diperbarui secara real-time berdasarkan booking aktif</small>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert alert-success fade-in">
            <i class="bi bi-check-circle"></i><?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Lapangan Cards -->
        <?php if (empty($lapangan_list)): ?>
        <div class="empty-state fade-in">
            <i class="bi bi-geo-alt-fill"></i>
            <h3>Belum Ada Lapangan Tersedia</h3>
            <p>Maaf, saat ini belum ada lapangan yang tersedia untuk booking.</p>
        </div>
        <?php else: ?>
        <div class="cards-container">
            <?php foreach ($lapangan_list as $index => $lap): ?>
            <div class="card fade-in" style="animation-delay: <?php echo ($index * 0.1); ?>s;">
                <!-- Gambar Lapangan -->
                <?php if ($lap['gambar'] && file_exists('../uploads/lapangan/' . $lap['gambar'])): ?>
                    <img src="../uploads/lapangan/<?php echo htmlspecialchars($lap['gambar']); ?>" 
                         alt="<?php echo htmlspecialchars($lap['nama']); ?>" 
                         class="card-img">
                <?php else: ?>
                    <div class="card-img no-image">
                        <i class="bi bi-image me-2"></i>Tidak ada gambar
                    </div>
                <?php endif; ?>
                
                <div class="card-content">
                    <!-- Status Badge -->
                    <span class="status-badge status-<?php echo $lap['dynamic_status']; ?>">
                        <i class="bi bi-<?php echo ($lap['dynamic_status'] == 'tersedia') ? 'check-circle' : 'clock'; ?> me-1"></i>
                        <?php echo $lap['status_text']; ?>
                    </span>

                    <!-- Nama Lapangan -->
                    <h3><i class="bi bi-geo-alt me-2"></i><?php echo htmlspecialchars($lap['nama']); ?></h3>
                    
                    <!-- Info Lapangan -->
                    <div class="card-info">
                        <p><i class="bi bi-tag"></i><strong>Tipe:</strong> <?php echo htmlspecialchars($lap['tipe']); ?></p>
                        <p><i class="bi bi-clock"></i><strong>Operasional:</strong> 06:00 - 23:00 WIB</p>
                        <p><i class="bi bi-people"></i><strong>Kapasitas:</strong> 10-22 Pemain</p>
                        <p><i class="bi bi-car-front"></i><strong>Parkir:</strong> Tersedia</p>
                    </div>

                    <!-- Jadwal Booking Hari Ini -->
                    <?php if (!empty($lap['today_bookings'])): ?>
                    <div class="booking-schedule">
                        <h6><i class="bi bi-calendar-check me-1"></i>Booking Hari Ini</h6>
                        <?php foreach ($lap['today_bookings'] as $booking): ?>
                        <div class="schedule-item">
                            <span>
                                <?php 
                                echo date('H:i', strtotime($booking['jam'])) . ' - ' . 
                                     date('H:i', strtotime($booking['jam'] . ' + ' . $booking['lama_sewa'] . ' hours')); 
                                ?>
                            </span>
                            <span class="schedule-status schedule-<?php echo $booking['status']; ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Fasilitas -->
                    <div class="facilities">
                        <h6><i class="bi bi-star me-1"></i>Fasilitas</h6>
                        <div class="facilities-list">
                            <span class="facility-tag">Ruang Ganti</span>
                            <span class="facility-tag">Toilet</span>
                            <span class="facility-tag">Parkir</span>
                            <span class="facility-tag">Kantin</span>
                            <span class="facility-tag">Lampu</span>
                        </div>
                    </div>

                    <!-- Harga -->
                    <div class="price">
                        <i class="bi bi-cash me-2"></i>Rp <?php echo number_format($lap['harga'], 0, ',', '.'); ?> / jam
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="card-actions">
                        <?php if ($lap['dynamic_status'] == 'tersedia'): ?>
                        <a href="booking.php?lapangan=<?php echo $lap['id']; ?>" class="btn btn-primary">
                            <i class="bi bi-calendar-plus me-1"></i>Booking Sekarang
                        </a>
                        <?php else: ?>
                        <button class="btn btn-primary" disabled>
                            <i class="bi bi-clock me-1"></i>Sedang Dibooking
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-primary" onclick="showDetail(<?php echo $lap['id']; ?>)">
                            <i class="bi bi-info-circle me-1"></i>Detail
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function showDetail(lapanganId) {
            // Data lapangan untuk detail
            const lapanganData = <?php echo json_encode($lapangan_list); ?>;
            const lapangan = lapanganData.find(l => l.id == lapanganId);
            
            if (lapangan) {
                let scheduleHtml = '';
                if (lapangan.today_bookings && lapangan.today_bookings.length > 0) {
                    scheduleHtml = '<hr><p><strong><i class="bi bi-calendar-check me-2"></i>Jadwal Hari Ini:</strong></p><ul style="margin-left: 20px;">';
                    lapangan.today_bookings.forEach(booking => {
                        const startTime = booking.jam;
                        const endTime = new Date(new Date('1970-01-01T' + booking.jam + 'Z').getTime() + booking.lama_sewa * 60 * 60 * 1000).toISOString().substr(11, 5);
                        scheduleHtml += `<li>${startTime} - ${endTime} (${booking.status})</li>`;
                    });
                    scheduleHtml += '</ul>';
                }

                Swal.fire({
                    title: lapangan.nama,
                    html: `
                        <div style="text-align: left;">
                            <p><strong><i class="bi bi-tag me-2"></i>Tipe:</strong> ${lapangan.tipe}</p>
                            <p><strong><i class="bi bi-cash me-2"></i>Harga:</strong> Rp ${parseInt(lapangan.harga).toLocaleString('id-ID')} / jam</p>
                            <p><strong><i class="bi bi-check-circle me-2"></i>Status:</strong> <span class="badge ${lapangan.dynamic_status == 'tersedia' ? 'bg-success' : 'bg-danger'}">${lapangan.status_text}</span></p>
                            <p><strong><i class="bi bi-clock me-2"></i>Jam Operasional:</strong> 06:00 - 23:00 WIB</p>
                            <p><strong><i class="bi bi-people me-2"></i>Kapasitas:</strong> 10-22 Pemain</p>
                            ${scheduleHtml}
                            <hr>
                            <p><strong><i class="bi bi-star me-2"></i>Fasilitas:</strong></p>
                            <ul style="margin-left: 20px;">
                                <li>Ruang ganti lengkap</li>
                                <li>Toilet bersih</li>
                                <li>Area parkir luas</li>
                                <li>Kantin</li>
                                <li>Pencahayaan LED</li>
                                <li>Rumput sintetis berkualitas</li>
                            </ul>
                        </div>
                    `,
                    imageUrl: lapangan.gambar ? `../uploads/lapangan/${lapangan.gambar}` : null,
                    imageWidth: 400,
                    imageHeight: 200,
                    imageAlt: lapangan.nama,
                    showCancelButton: true,
                    confirmButtonText: lapangan.dynamic_status == 'tersedia' ? '<i class="bi bi-calendar-plus me-1"></i> Booking Sekarang' : '<i class="bi bi-clock me-1"></i> Sedang Dibooking',
                    cancelButtonText: 'Tutup',
                    confirmButtonColor: lapangan.dynamic_status == 'tersedia' ? '#3498db' : '#95a5a6',
                    cancelButtonColor: '#95a5a6'
                }).then((result) => {
                    if (result.isConfirmed && lapangan.dynamic_status == 'tersedia') {
                        window.location.href = `booking.php?lapangan=${lapanganId}`;
                    }
                });
            }
        }

        // Auto refresh halaman setiap 60 detik untuk update status
        setInterval(() => {
            location.reload();
        }, 60000);

        // Animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all cards
        document.querySelectorAll('.card').forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
            observer.observe(card);
        });
    </script>
</body>
</html>