<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$type = isset($_GET['type']) ? sanitize_input($_GET['type']) : 'initial'; // initial or completion
$user_id = $_SESSION['user_id'];

if ($booking_id <= 0) {
    header('Location: history.php');
    exit();
}

// Get booking details
$query = "
    SELECT 
        b.*,
        l.nama as nama_lapangan, 
        l.harga, 
        l.tipe,
        u.full_name, 
        u.email
    FROM booking b 
    JOIN lapangan l ON b.id_lapangan = l.id 
    JOIN users u ON b.id_user = u.id
    WHERE b.id = $booking_id AND b.id_user = $user_id
";

$result = mysqli_query($connection, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    header('Location: history.php?error=booking_not_found');
    exit();
}

$total_harga = $booking['harga'] * $booking['lama_sewa'];

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($type == 'completion') ? 'Pelunasan' : 'Pembayaran'; ?> Berhasil - Futsal Booking</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/confetti-js@0.0.18/dist/index.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            background: linear-gradient(135deg, rgb(46, 204, 113) 0%, rgb(39, 174, 96) 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding: 20px;
            overflow-x: hidden;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .success-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .success-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(46, 204, 113, 0.1) 0%, transparent 70%);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .success-icon {
            font-size: 5rem;
            color: #27ae60;
            margin-bottom: 20px;
            animation: bounceIn 1s ease-out;
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }

        .success-title {
            color: #2c3e50;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 15px;
            animation: slideInUp 0.8s ease-out 0.3s both;
        }

        .success-subtitle {
            color: #7f8c8d;
            font-size: 1.2rem;
            margin-bottom: 30px;
            animation: slideInUp 0.8s ease-out 0.5s both;
        }

        @keyframes slideInUp {
            0% { transform: translateY(30px); opacity: 0; }
            100% { transform: translateY(0); opacity: 1; }
        }

        .booking-details {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 5px solid #27ae60;
            animation: slideInUp 0.8s ease-out 0.7s both;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .detail-row:last-child {
            border-bottom: none;
            background: #e8f5e8;
            margin: 15px -25px -25px -25px;
            padding: 20px 25px;
            border-radius: 0 0 15px 15px;
            font-weight: bold;
            color: #27ae60;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-success {
            background: linear-gradient(45deg, #27ae60, #2ecc71);
        }

        .status-pending {
            background: linear-gradient(45deg, #f39c12, #e67e22);
        }

        .next-steps {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 5px solid #2196f3;
            text-align: left;
            animation: slideInUp 0.8s ease-out 0.9s both;
        }

        .next-steps h5 {
            color: #1976d2;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .next-steps ol {
            margin-bottom: 0;
        }

        .next-steps li {
            padding: 5px 0;
            color: #333;
        }

        .action-buttons {
            margin-top: 35px;
            animation: slideInUp 0.8s ease-out 1.1s both;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
            margin: 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            background: linear-gradient(45deg, #3498db, #2980b9);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 12px 25px rgba(52, 152, 219, 0.4);
        }

        .btn-success {
            background: linear-gradient(45deg, #27ae60, #2ecc71);
            box-shadow: 0 8px 20px rgba(39, 174, 96, 0.3);
        }

        .btn-success:hover {
            box-shadow: 0 12px 25px rgba(39, 174, 96, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
        }

        .celebration-text {
            font-size: 1.5rem;
            color: #e74c3c;
            font-weight: 600;
            margin: 20px 0;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .contact-info {
            background: #fff3cd;
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            border-left: 5px solid #f39c12;
            animation: slideInUp 0.8s ease-out 1.3s both;
        }

        .countdown-container {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
            animation: slideInUp 0.8s ease-out 1.5s both;
        }

        .countdown {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
        }

        @media (max-width: 768px) {
            .success-card {
                padding: 25px;
            }
            
            .success-title {
                font-size: 2rem;
            }
            
            .btn {
                display: block;
                width: 100%;
                margin: 10px 0;
            }
        }

        #confetti-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 1000;
        }
    </style>
</head>

<body>
    <canvas id="confetti-canvas"></canvas>
    
    <div class="container">
        <div class="success-card">
            <!-- Success Icon -->
            <div class="success-icon">
                <i class="bi bi-check-circle-fill"></i>
            </div>

            <!-- Success Message -->
            <h1 class="success-title">
                <?php if ($type == 'completion'): ?>
                Pelunasan Berhasil!
                <?php else: ?>
                Pembayaran Berhasil!
                <?php endif; ?>
            </h1>
            
            <p class="success-subtitle">
                <?php if ($type == 'completion'): ?>
                Booking Anda telah lunas dan siap untuk dimainkan
                <?php else: ?>
                Booking Anda telah dibuat dan menunggu konfirmasi admin
                <?php endif; ?>
            </p>

            <?php if ($type == 'completion'): ?>
            <div class="celebration-text">
                🎉 Selamat! Booking Anda Sudah Lunas! 🎉
            </div>
            <?php endif; ?>

            <!-- Booking Details -->
            <div class="booking-details">
                <h5><i class="bi bi-receipt me-2"></i>Detail Booking</h5>
                <div class="detail-row">
                    <span>Booking ID:</span>
                    <span><strong>#<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></strong></span>
                </div>
                <div class="detail-row">
                    <span>Lapangan:</span>
                    <span><?php echo htmlspecialchars($booking['nama_lapangan']); ?></span>
                </div>
                <div class="detail-row">
                    <span>Tanggal & Waktu:</span>
                    <span><?php echo date('l, d F Y - H:i', strtotime($booking['tanggal'] . ' ' . $booking['jam'])); ?> WIB</span>
                </div>
                <div class="detail-row">
                    <span>Durasi:</span>
                    <span><?php echo $booking['lama_sewa']; ?> jam</span>
                </div>
                <div class="detail-row">
                    <span>Status Booking:</span>
                    <span>
                        <span class="status-badge <?php echo ($booking['status'] == 'aktif') ? 'status-success' : 'status-pending'; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span>Status Pembayaran:</span>
                    <span>
                        <span class="status-badge status-success">
                            <?php echo ($booking['status_pembayaran'] == 'lunas') ? 'Lunas (100%)' : 'DP (50%)'; ?>
                        </span>
                    </span>
                </div>
                <div class="detail-row">
                    <span><strong>Total Dibayar:</strong></span>
                    <span><strong>Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></strong></span>
                </div>
            </div>

            <!-- Countdown to Game Time -->
            <?php 
            $game_time = strtotime($booking['tanggal'] . ' ' . $booking['jam']);
            $now = time();
            $time_diff = $game_time - $now;
            if ($time_diff > 0):
            ?>
            <div class="countdown-container">
                <h5><i class="bi bi-clock me-2"></i>Countdown Waktu Bermain</h5>
                <div class="countdown" id="countdown">
                    <!-- Will be populated by JavaScript -->
                </div>
                <p class="mb-0">Jangan sampai terlambat!</p>
            </div>
            <?php endif; ?>

            <!-- Next Steps -->
            <div class="next-steps">
                <h5><i class="bi bi-list-check me-2"></i>Langkah Selanjutnya</h5>
                <?php if ($type == 'completion'): ?>
                <ol>
                    <li><strong>Booking Anda sudah lunas</strong> - Tidak ada pembayaran lagi</li>
                    <li><strong>Simpan bukti pembayaran</strong> - Screenshot halaman ini</li>
                    <li><strong>Datang tepat waktu</strong> - 15 menit sebelum waktu bermain</li>
                    <li><strong>Bawa identitas</strong> - KTP atau kartu identitas lainnya</li>
                    <li><strong>Siapkan perlengkapan</strong> - Sepatu futsal dan pakaian olahraga</li>
                </ol>
                <?php else: ?>
                <ol>
                    <li><strong>Tunggu konfirmasi admin</strong> - Maksimal 2 jam setelah pembayaran</li>
                    <li><strong>Cek status booking</strong> - Di halaman riwayat booking</li>
                    <li><strong>Lakukan pelunasan</strong> - Jika masih DP, lunasi sebelum waktu bermain</li>
                    <li><strong>Simpan bukti pembayaran</strong> - Screenshot halaman ini</li>
                    <li><strong>Siapkan kedatangan</strong> - Datang 15 menit sebelum waktu bermain</li>
                </ol>
                <?php endif; ?>
            </div>

            <!-- Contact Info -->
            <div class="contact-info">
                <h6><i class="bi bi-telephone me-2"></i>Butuh Bantuan?</h6>
                <p class="mb-2">
                    <strong>Telepon:</strong> 0274-123456<br>
                    <strong>WhatsApp:</strong> 081234567890<br>
                    <strong>Email:</strong> admin@futsalbooking.com
                </p>
                <small class="text-muted">Layanan pelanggan 24/7</small>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="payment_receipt.php?booking_id=<?php echo $booking_id; ?>" target="_blank" class="btn btn-primary">
                    <i class="bi bi-printer me-2"></i>Cetak Struk
                </a>
                
                <?php if ($type != 'completion' && $booking['status_pembayaran'] == 'dp'): ?>
                <a href="complete_payment.php?id=<?php echo $booking_id; ?>" class="btn btn-success">
                    <i class="bi bi-credit-card me-2"></i>Lunasi Sekarang
                </a>
                <?php endif; ?>
                
                <a href="history.php" class="btn btn-secondary">
                    <i class="bi bi-list me-2"></i>Lihat Riwayat
                </a>
                
                <a href="lihat_lapangan.php" class="btn btn-secondary">
                    <i class="bi bi-plus-circle me-2"></i>Booking Lagi
                </a>
            </div>

            <!-- Footer -->
            <div class="text-center mt-4 text-muted">
                <p><small>
                    Terima kasih telah menggunakan layanan Futsal Booking Center<br>
                    Selamat bermain dan semoga menyenangkan! 🏆
                </small></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Confetti Animation
        const confettiSettings = {
            target: 'confetti-canvas',
            max: 150,
            size: 1,
            animate: true,
            props: ['circle', 'square', 'triangle', 'line'],
            colors: [[165,104,246],[230,61,135],[0,199,228],[253,214,126]],
            clock: 25,
            rotate: true,
            width: window.innerWidth,
            height: window.innerHeight
        };

        const confetti = new ConfettiGenerator(confettiSettings);
        confetti.render();

        // Stop confetti after 5 seconds
        setTimeout(() => {
            confetti.clear();
        }, 5000);

        // Countdown Timer
        <?php if ($time_diff > 0): ?>
        function updateCountdown() {
            const gameTime = new Date(<?php echo $game_time * 1000; ?>);
            const now = new Date();
            const timeDiff = gameTime - now;

            if (timeDiff > 0) {
                const days = Math.floor(timeDiff / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeDiff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeDiff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeDiff % (1000 * 60)) / 1000);

                let countdownText = '';
                if (days > 0) countdownText += days + 'd ';
                if (hours > 0) countdownText += hours + 'h ';
                countdownText += minutes + 'm ' + seconds + 's';

                document.getElementById('countdown').textContent = countdownText;
            } else {
                document.getElementById('countdown').textContent = 'Waktu bermain sudah tiba!';
                document.querySelector('.countdown-container').style.background = 'linear-gradient(45deg, #27ae60, #2ecc71)';
            }
        }

        // Update countdown every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
        <?php endif; ?>

        // Auto redirect to history after 30 seconds
        setTimeout(() => {
            const redirect = confirm('Halaman akan otomatis redirect ke riwayat booking. Lanjutkan?');
            if (redirect) {
                window.location.href = 'history.php';
            }
        }, 30000);

        // Prevent accidental page refresh
        window.addEventListener('beforeunload', function (e) {
            e.preventDefault();
            e.returnValue = '';
        });

        // Play success sound (optional)
        <?php if ($type == 'completion'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // You can add a success sound here
            console.log('Pelunasan berhasil!');
        });
        <?php endif; ?>
    </script>
</body>
</html>