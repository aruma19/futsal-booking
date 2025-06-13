<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$refund_amount = isset($_GET['refund']) ? (float)$_GET['refund'] : 0;
$penalty_amount = isset($_GET['penalty']) ? (float)$_GET['penalty'] : 0;
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
        u.email,
        r.refund_amount as confirmed_refund,
        r.penalty_amount as confirmed_penalty,
        r.status as refund_status,
        r.created_at as refund_date
    FROM booking b 
    JOIN lapangan l ON b.id_lapangan = l.id 
    JOIN users u ON b.id_user = u.id
    LEFT JOIN refunds r ON b.id = r.booking_id
    WHERE b.id = $booking_id AND b.id_user = $user_id AND b.status = 'batal'
";

$result = mysqli_query($connection, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    header('Location: history.php?error=booking_not_found');
    exit();
}

// Use confirmed amounts if available
$final_refund = $booking['confirmed_refund'] ?? $refund_amount;
$final_penalty = $booking['confirmed_penalty'] ?? $penalty_amount;
$total_paid = $booking['total_dibayar'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembatalan Berhasil - Futsal Booking</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            background: linear-gradient(135deg, rgb(255, 235, 235) 0%, rgb(255, 245, 245) 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .cancel-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cancel-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(231, 76, 60, 0.1) 0%, transparent 70%);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.3; }
            50% { transform: scale(1.1); opacity: 0.6; }
        }

        .cancel-icon {
            font-size: 5rem;
            color: #e74c3c;
            margin-bottom: 20px;
            animation: bounceIn 1s ease-out;
            position: relative;
            z-index: 10;
        }

        @keyframes bounceIn {
            0% { transform: scale(0.3); opacity: 0; }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { transform: scale(1); opacity: 1; }
        }

        .cancel-title {
            color: #2c3e50;
            font-weight: 700;
            font-size: 2.5rem;
            margin-bottom: 15px;
            animation: slideInUp 0.8s ease-out 0.3s both;
            position: relative;
            z-index: 10;
        }

        .cancel-subtitle {
            color: #7f8c8d;
            font-size: 1.2rem;
            margin-bottom: 30px;
            animation: slideInUp 0.8s ease-out 0.5s both;
            position: relative;
            z-index: 10;
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
            border-left: 5px solid #e74c3c;
            animation: slideInUp 0.8s ease-out 0.7s both;
            position: relative;
            z-index: 10;
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
        }

        .refund-summary {
            background: linear-gradient(45deg, #fff3cd, #fef9e7);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 5px solid #f39c12;
            animation: slideInUp 0.8s ease-out 0.9s both;
            position: relative;
            z-index: 10;
        }

        .refund-summary.no-refund {
            background: linear-gradient(45deg, #f8d7da, #fceaea);
            border-left-color: #dc3545;
        }

        .refund-summary.full-refund {
            background: linear-gradient(45deg, #d4edda, #e8f5e8);
            border-left-color: #28a745;
        }

        .refund-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        .refund-row:last-child {
            border-bottom: none;
            font-weight: bold;
            color: #2c3e50;
            font-size: 1.2em;
            background: rgba(255, 255, 255, 0.7);
            margin: 15px -25px -25px -25px;
            padding: 20px 25px;
            border-radius: 0 0 15px 15px;
        }

        .amount-positive {
            color: #28a745;
            font-weight: bold;
        }

        .amount-negative {
            color: #dc3545;
            font-weight: bold;
        }

        .amount-zero {
            color: #6c757d;
            font-weight: bold;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            color: white;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-cancelled {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
        }

        .status-pending {
            background: linear-gradient(45deg, #f39c12, #e67e22);
        }

        .status-completed {
            background: linear-gradient(45deg, #28a745, #20c997);
        }

        .next-steps {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 5px solid #2196f3;
            text-align: left;
            animation: slideInUp 0.8s ease-out 1.1s both;
            position: relative;
            z-index: 10;
        }

        .next-steps h5 {
            color: #1976d2;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .next-steps ul {
            margin-bottom: 0;
        }

        .next-steps li {
            padding: 5px 0;
            color: #333;
        }

        .contact-info {
            background: #fff3cd;
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            border-left: 5px solid #f39c12;
            animation: slideInUp 0.8s ease-out 1.3s both;
            position: relative;
            z-index: 10;
        }

        .action-buttons {
            margin-top: 35px;
            animation: slideInUp 0.8s ease-out 1.5s both;
            position: relative;
            z-index: 10;
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

        .refund-timeline {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            border-left: 5px solid #17a2b8;
            position: relative;
            z-index: 10;
        }

        .timeline-item {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .timeline-item:last-child {
            border-bottom: none;
        }

        .timeline-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 0.8rem;
        }

        .timeline-icon.completed {
            background: #28a745;
            color: white;
        }

        .timeline-icon.pending {
            background: #ffc107;
            color: #212529;
        }

        .timeline-icon.future {
            background: #dee2e6;
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .cancel-card {
                padding: 25px;
            }
            
            .cancel-title {
                font-size: 2rem;
            }
            
            .btn {
                display: block;
                width: 100%;
                margin: 10px 0;
            }
            
            .detail-row, .refund-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="cancel-card">
            <!-- Cancel Icon -->
            <div class="cancel-icon">
                <i class="bi bi-x-circle-fill"></i>
            </div>

            <!-- Cancel Message -->
            <h1 class="cancel-title">Booking Dibatalkan</h1>
            <p class="cancel-subtitle">Booking Anda telah berhasil dibatalkan</p>

            <!-- Booking Details -->
            <div class="booking-details">
                <h5><i class="bi bi-receipt me-2"></i>Detail Booking yang Dibatalkan</h5>
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
                    <span>Status:</span>
                    <span><span class="status-badge status-cancelled">Dibatalkan</span></span>
                </div>
                <div class="detail-row">
                    <span>Waktu Pembatalan:</span>
                    <span><?php echo date('d/m/Y H:i', strtotime($booking['cancelled_at'])); ?></span>
                </div>
            </div>

            <!-- Refund Summary -->
            <div class="refund-summary <?php echo ($final_refund == 0) ? 'no-refund' : (($final_penalty == 0) ? 'full-refund' : ''); ?>">
                <h5><i class="bi bi-calculator me-2"></i>Ringkasan Refund & Penalty</h5>
                <div class="refund-row">
                    <span>Total yang Sudah Dibayar:</span>
                    <span>Rp <?php echo number_format($total_paid, 0, ',', '.'); ?></span>
                </div>
                <div class="refund-row">
                    <span>Penalty Pembatalan:</span>
                    <span class="<?php echo ($final_penalty > 0) ? 'amount-negative' : 'amount-zero'; ?>">
                        <?php if ($final_penalty > 0): ?>
                        -Rp <?php echo number_format($final_penalty, 0, ',', '.'); ?>
                        <?php else: ?>
                        Rp 0 (Tidak ada penalty)
                        <?php endif; ?>
                    </span>
                </div>
                <div class="refund-row">
                    <span><strong>Total Refund:</strong></span>
                    <span class="<?php echo ($final_refund > 0) ? 'amount-positive' : 'amount-zero'; ?>">
                        <strong>Rp <?php echo number_format($final_refund, 0, ',', '.'); ?></strong>
                    </span>
                </div>
            </div>

            <!-- Refund Status & Timeline -->
            <?php if ($final_refund > 0): ?>
            <div class="refund-timeline">
                <h5><i class="bi bi-clock-history me-2"></i>Status Refund</h5>
                <div class="timeline-item">
                    <div class="timeline-icon completed">
                        <i class="bi bi-check"></i>
                    </div>
                    <div>
                        <strong>Pembatalan Dikonfirmasi</strong><br>
                        <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($booking['cancelled_at'])); ?></small>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon <?php echo ($booking['refund_status'] == 'pending') ? 'pending' : 'future'; ?>">
                        <i class="bi bi-hourglass"></i>
                    </div>
                    <div>
                        <strong>Refund Sedang Diproses</strong><br>
                        <small class="text-muted">
                            <?php if ($booking['refund_status'] == 'pending'): ?>
                                Status: <span class="status-badge status-pending">Pending</span>
                            <?php else: ?>
                                Menunggu pemrosesan
                            <?php endif; ?>
                        </small>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon future">
                        <i class="bi bi-bank"></i>
                    </div>
                    <div>
                        <strong>Refund Selesai</strong><br>
                        <small class="text-muted">1-3 hari kerja</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Next Steps -->
            <div class="next-steps">
                <h5><i class="bi bi-list-check me-2"></i>Informasi Penting</h5>
                <ul>
                    <?php if ($final_refund > 0): ?>
                    <li><strong>Refund akan diproses</strong> dalam 1-3 hari kerja</li>
                    <li><strong>Notifikasi email</strong> akan dikirim setelah refund berhasil</li>
                    <li><strong>Refund akan dikembalikan</strong> ke metode pembayaran yang sama</li>
                    <?php else: ?>
                    <li><strong>Tidak ada refund</strong> karena penalty 100%</li>
                    <li><strong>Pembatalan final</strong> - tidak dapat diubah lagi</li>
                    <?php endif; ?>
                    <li><strong>Lapangan tersedia kembali</strong> untuk booking lain</li>
                    <li><strong>Riwayat booking</strong> tetap tersimpan untuk referensi</li>
                    <li><strong>Hubungi customer service</strong> jika ada pertanyaan</li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="contact-info">
                <h6><i class="bi bi-telephone me-2"></i>Butuh Bantuan?</h6>
                <p class="mb-2">
                    <strong>Telepon:</strong> 0274-123456<br>
                    <strong>WhatsApp:</strong> 081234567890<br>
                    <strong>Email:</strong> admin@futsalbooking.com
                </p>
                <small class="text-muted">Layanan pelanggan 24/7 untuk pertanyaan refund</small>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="payment_receipt.php?booking_id=<?php echo $booking_id; ?>" target="_blank" class="btn btn-primary">
                    <i class="bi bi-printer me-2"></i>Cetak Bukti Pembatalan
                </a>
                
                <a href="history.php" class="btn btn-secondary">
                    <i class="bi bi-list me-2"></i>Lihat Riwayat
                </a>
                
                <a href="lihat_lapangan.php" class="btn btn-success">
                    <i class="bi bi-plus-circle me-2"></i>Booking Lagi
                </a>
            </div>

            <!-- Footer -->
            <div class="text-center mt-4 text-muted">
                <p><small>
                    Terima kasih atas pengertian Anda<br>
                    Kami menantikan kunjungan Anda di lain waktu 🏆
                </small></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto redirect to history after 60 seconds
        setTimeout(() => {
            const redirect = confirm('Halaman akan otomatis redirect ke riwayat booking. Lanjutkan?');
            if (redirect) {
                window.location.href = 'history.php';
            }
        }, 60000);

        // Show additional info based on refund amount
        <?php if ($final_refund > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Refund akan diproses: Rp <?php echo number_format($final_refund, 0, ',', '.'); ?>');
        });
        <?php else: ?>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Tidak ada refund - penalty 100%');
        });
        <?php endif; ?>

        // Prevent accidental navigation
        let hasInteracted = false;
        document.addEventListener('click', () => hasInteracted = true);
        document.addEventListener('keydown', () => hasInteracted = true);

        window.addEventListener('beforeunload', function (e) {
            if (!hasInteracted) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    </script>
</body>
</html>