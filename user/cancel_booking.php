<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

// Fungsi sanitize yang hilang
// Fungsi sanitize dengan check
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        global $connection;
        if (empty($data)) {
            return '';
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return mysqli_real_escape_string($connection, $data);
    }
}

$message = '';
$error = '';
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

if ($booking_id <= 0) {
    header('Location: history.php');
    exit();
}

// Get booking details with improved error handling
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
    WHERE b.id = ? AND b.id_user = ? 
    AND b.status IN ('pending', 'aktif')
";

$stmt = mysqli_prepare($connection, $query);
if (!$stmt) {
    die('Query preparation failed: ' . mysqli_error($connection));
}

mysqli_stmt_bind_param($stmt, "ii", $booking_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    header('Location: history.php?error=booking_not_found');
    exit();
}

// Calculate refund and penalty
$total_harga = $booking['harga'] * $booking['lama_sewa'];
$booking_datetime = strtotime($booking['tanggal'] . ' ' . $booking['jam']);
$now = time();
$time_diff_hours = ($booking_datetime - $now) / 3600;

$penalty_amount = 0;
$refund_amount = 0;
$refund_reason = '';

if ($booking['status'] == 'pending') {
    // No penalty for pending bookings
    $penalty_amount = 0;
    $refund_amount = $booking['total_dibayar'];
    $refund_reason = 'Pembatalan booking dengan status pending - tidak ada penalty';
} elseif ($booking['status'] == 'aktif') {
    if ($time_diff_hours > 24) {
        // No penalty if more than 24 hours
        $penalty_amount = 0;
        $refund_amount = $booking['total_dibayar'];
        $refund_reason = 'Pembatalan lebih dari 24 jam sebelum waktu bermain - tidak ada penalty';
    } else {
        // Penalty applies
        if ($booking['status_pembayaran'] == 'dp') {
            // 0% refund for DP (100% penalty)
            $penalty_amount = $booking['total_dibayar'];
            $refund_amount = 0;
            $refund_reason = 'Pembatalan kurang dari 24 jam dengan status DP - penalty 100%';
        } else {
            // 50% penalty for full payment
            $penalty_amount = $booking['total_dibayar'] * 0.5;
            $refund_amount = $booking['total_dibayar'] - $penalty_amount;
            $refund_reason = 'Pembatalan kurang dari 24 jam dengan status lunas - penalty 50%';
        }
    }
}

// Handle cancellation with improved error handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel'])) {
    
    // Validate inputs
    if (empty($_POST['reason'])) {
        $error = 'Alasan pembatalan wajib diisi';
    } else if (!isset($_POST['confirmUnderstand']) || !isset($_POST['confirmRefund']) || !isset($_POST['confirmFinal'])) {
        $error = 'Semua konfirmasi harus dicentang';
    } else {
        
        $user_reason = sanitize_input($_POST['reason']);
        $combined_reason = sanitize_input($refund_reason . '. Alasan user: ' . $user_reason);
        
        // Start transaction
        mysqli_autocommit($connection, false);
        
        try {
            // Check if booking still exists and can be cancelled
            $checkQuery = "SELECT status FROM booking WHERE id = ? AND id_user = ? AND status IN ('pending', 'aktif')";
            $checkStmt = mysqli_prepare($connection, $checkQuery);
            mysqli_stmt_bind_param($checkStmt, "ii", $booking_id, $user_id);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            
            if (mysqli_num_rows($checkResult) == 0) {
                throw new Exception('Booking tidak ditemukan atau sudah tidak dapat dibatalkan');
            }
            
            // Update booking status using prepared statement
            $updateBooking = "UPDATE booking SET 
                             status = 'batal', 
                             total_pinalti = ?, 
                             cancelled_at = NOW(),
                             cancelled_reason = ?
                             WHERE id = ? AND id_user = ?";
            
            $updateStmt = mysqli_prepare($connection, $updateBooking);
            if (!$updateStmt) {
                throw new Exception('Gagal mempersiapkan query update: ' . mysqli_error($connection));
            }
            
            mysqli_stmt_bind_param($updateStmt, "dsii", $penalty_amount, $combined_reason, $booking_id, $user_id);
            
            if (!mysqli_stmt_execute($updateStmt)) {
                throw new Exception('Gagal mengupdate status booking: ' . mysqli_stmt_error($updateStmt));
            }
            
            if (mysqli_stmt_affected_rows($updateStmt) == 0) {
                throw new Exception('Tidak ada booking yang diupdate');
            }
            
            // Insert refund record if there's refund
            if ($refund_amount > 0) {
                $refundQuery = "INSERT INTO refunds (booking_id, user_id, refund_amount, penalty_amount, reason, status, created_at) 
                               VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
                
                $refundStmt = mysqli_prepare($connection, $refundQuery);
                if (!$refundStmt) {
                    throw new Exception('Gagal mempersiapkan query refund: ' . mysqli_error($connection));
                }
                
                mysqli_stmt_bind_param($refundStmt, "iidds", $booking_id, $user_id, $refund_amount, $penalty_amount, $combined_reason);
                
                if (!mysqli_stmt_execute($refundStmt)) {
                    throw new Exception('Gagal membuat record refund: ' . mysqli_stmt_error($refundStmt));
                }
            }
            
            // Insert notification
            $notif_title = 'Booking Dibatalkan';
            $notif_message = "Booking #" . str_pad($booking_id, 6, '0', STR_PAD_LEFT) . " telah dibatalkan. Refund: Rp " . number_format($refund_amount, 0, ',', '.') . 
                            " | Penalty: Rp " . number_format($penalty_amount, 0, ',', '.');
            
            $notifQuery = "INSERT INTO notifications (user_id, booking_id, title, message, type, created_at) 
                          VALUES (?, ?, ?, ?, 'warning', NOW())";
            
            $notifStmt = mysqli_prepare($connection, $notifQuery);
            if ($notifStmt) {
                mysqli_stmt_bind_param($notifStmt, "iiss", $user_id, $booking_id, $notif_title, $notif_message);
                mysqli_stmt_execute($notifStmt);
                // Don't throw error if notification fails, it's not critical
            }
            
            // Commit transaction
            mysqli_commit($connection);
            mysqli_autocommit($connection, true);
            
            // Add success session message
            $_SESSION['success_message'] = 'Booking berhasil dibatalkan';
            
            // Redirect to success page
            header("Location: cancel_success.php?booking_id=$booking_id&refund=$refund_amount&penalty=$penalty_amount");
            exit();
            
        } catch (Exception $e) {
            mysqli_rollback($connection);
            mysqli_autocommit($connection, true);
            $error = 'Pembatalan gagal: ' . $e->getMessage();
            
            // Log error for debugging
            error_log("Cancel booking error for booking_id $booking_id: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batalkan Booking - Futsal Booking</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            background: linear-gradient(135deg, rgb(255, 235, 235) 0%, rgb(255, 245, 245) 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .cancel-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .warning-header {
            text-align: center;
            margin-bottom: 35px;
            padding: 25px;
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            color: white;
            border-radius: 15px;
        }

        .warning-header h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }

        .warning-header .warning-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .booking-info {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border-left: 5px solid #3498db;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .info-row:last-child {
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

        .refund-calculation {
            background: #fff3cd;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            border-left: 5px solid #f39c12;
        }

        .refund-calculation.no-penalty {
            background: #d4edda;
            border-left-color: #28a745;
        }

        .refund-calculation.high-penalty {
            background: #f8d7da;
            border-left-color: #dc3545;
        }

        .calculation-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .calculation-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1em;
            color: #2c3e50;
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

        .penalty-rules {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            border-left: 5px solid #2196f3;
        }

        .penalty-rules h5 {
            color: #1976d2;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .penalty-rules ul {
            margin-bottom: 0;
        }

        .penalty-rules li {
            padding: 5px 0;
            color: #333;
        }

        .time-info {
            background: #f8d7da;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            border-left: 5px solid #dc3545;
            text-align: center;
        }

        .time-info.safe {
            background: #d4edda;
            border-left-color: #28a745;
        }

        .time-remaining {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 10px 0;
        }

        .form-section {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
        }

        .btn {
            padding: 15px 30px;
            border-radius: 25px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            border: none;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-danger {
            background: linear-gradient(45deg, #e74c3c, #c0392b);
            box-shadow: 0 8px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-danger:hover {
            box-shadow: 0 12px 25px rgba(231, 76, 60, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
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

        .alert-danger {
            background: linear-gradient(45deg, rgba(231, 76, 60, 0.15), rgba(236, 112, 99, 0.15));
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }

        .confirmation-box {
            background: #fff;
            border: 2px solid #e74c3c;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
        }

        .confirmation-box .form-check {
            margin: 15px 0;
        }

        .confirmation-box .form-check-input {
            margin-right: 10px;
        }

        .confirmation-box .form-check-label {
            font-weight: 500;
            color: #333;
        }

        @media (max-width: 768px) {
            .cancel-card {
                padding: 20px;
            }
            
            .info-row, .calculation-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .btn {
                display: block;
                width: 100%;
                margin: 10px 0;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="cancel-card">
            <!-- Warning Header -->
            <div class="warning-header">
                <div class="warning-icon">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h1>Batalkan Booking</h1>
                <p class="mb-0">Perhatian: Proses pembatalan tidak dapat dibatalkan</p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i><?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
            <?php endif; ?>

            <!-- Booking Information -->
            <div class="booking-info">
                <h5><i class="bi bi-info-circle me-2"></i>Informasi Booking</h5>
                <div class="info-row">
                    <span class="info-label">Booking ID:</span>
                    <span class="info-value">#<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Lapangan:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['nama_lapangan']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Tanggal & Waktu:</span>
                    <span class="info-value"><?php echo date('l, d F Y - H:i', strtotime($booking['tanggal'] . ' ' . $booking['jam'])); ?> WIB</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Durasi:</span>
                    <span class="info-value"><?php echo $booking['lama_sewa']; ?> jam</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status Booking:</span>
                    <span class="info-value">
                        <span class="badge bg-<?php echo ($booking['status'] == 'aktif') ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status Pembayaran:</span>
                    <span class="info-value">
                        <span class="badge bg-<?php echo ($booking['status_pembayaran'] == 'lunas') ? 'success' : 'warning'; ?>">
                            <?php echo ($booking['status_pembayaran'] == 'lunas') ? 'Lunas' : 'DP'; ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Total Dibayar:</span>
                    <span class="info-value">Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></span>
                </div>
            </div>

            <!-- Time Information -->
            <div class="time-info <?php echo ($time_diff_hours > 24) ? 'safe' : ''; ?>">
                <h5>
                    <i class="bi bi-clock me-2"></i>
                    <?php if ($time_diff_hours > 24): ?>
                    Pembatalan Aman - Tidak Ada Penalty
                    <?php else: ?>
                    Peringatan: Waktu Bermain Kurang dari 24 Jam
                    <?php endif; ?>
                </h5>
                <div class="time-remaining">
                    <?php if ($time_diff_hours > 0): ?>
                    <?php echo floor($time_diff_hours); ?> jam <?php echo floor(($time_diff_hours - floor($time_diff_hours)) * 60); ?> menit lagi
                    <?php else: ?>
                    <span class="text-danger">Waktu bermain sudah lewat!</span>
                    <?php endif; ?>
                </div>
                <p class="mb-0">
                    <?php if ($time_diff_hours > 24): ?>
                    Anda dapat membatalkan tanpa penalty karena masih lebih dari 24 jam.
                    <?php else: ?>
                    Pembatalan kurang dari 24 jam akan dikenakan penalty sesuai kebijakan.
                    <?php endif; ?>
                </p>
            </div>

            <!-- Penalty Rules -->
            <div class="penalty-rules">
                <h5><i class="bi bi-shield-exclamation me-2"></i>Aturan Penalty Pembatalan</h5>
                <ul>
                    <li><strong>Status Pending:</strong> Tidak ada penalty - refund 100%</li>
                    <li><strong>Status Aktif (>24 jam):</strong> Tidak ada penalty - refund 100%</li>
                    <li><strong>Status Aktif DP (≤24 jam):</strong> Penalty 100% - tidak ada refund</li>
                    <li><strong>Status Aktif Lunas (≤24 jam):</strong> Penalty 50% - refund 50%</li>
                </ul>
            </div>

            <!-- Refund Calculation -->
            <div class="refund-calculation <?php echo ($penalty_amount == 0) ? 'no-penalty' : (($refund_amount == 0) ? 'high-penalty' : ''); ?>">
                <h5><i class="bi bi-calculator me-2"></i>Kalkulasi Refund</h5>
                <div class="calculation-row">
                    <span>Total yang Sudah Dibayar:</span>
                    <span>Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></span>
                </div>
                <div class="calculation-row">
                    <span>Penalty Pembatalan:</span>
                    <span class="<?php echo ($penalty_amount > 0) ? 'amount-negative' : 'amount-zero'; ?>">
                        <?php if ($penalty_amount > 0): ?>
                        -Rp <?php echo number_format($penalty_amount, 0, ',', '.'); ?>
                        <?php else: ?>
                        Rp 0
                        <?php endif; ?>
                    </span>
                </div>
                <div class="calculation-row">
                    <span><strong>Jumlah yang Akan Dikembalikan:</strong></span>
                    <span class="<?php echo ($refund_amount > 0) ? 'amount-positive' : 'amount-zero'; ?>">
                        <strong>Rp <?php echo number_format($refund_amount, 0, ',', '.'); ?></strong>
                    </span>
                </div>
            </div>

            <!-- Cancellation Form -->
            <form method="POST" id="cancelForm">
                <div class="form-section">
                    <h5><i class="bi bi-chat-text me-2"></i>Alasan Pembatalan</h5>
                    <div class="mb-3">
                        <select class="form-select mb-3" id="reasonSelect" name="reason_select">
                            <option value="">-- Pilih Alasan --</option>
                            <option value="Perubahan jadwal mendadak">Perubahan jadwal mendadak</option>
                            <option value="Kondisi cuaca buruk">Kondisi cuaca buruk</option>
                            <option value="Anggota tim tidak bisa hadir">Anggota tim tidak bisa hadir</option>
                            <option value="Masalah kesehatan">Masalah kesehatan</option>
                            <option value="Masalah transportasi">Masalah transportasi</option>
                            <option value="Lainnya">Lainnya (sebutkan)</option>
                        </select>
                        <textarea class="form-control" name="reason" id="reasonText" rows="3" 
                                  placeholder="Masukkan alasan pembatalan..." required></textarea>
                    </div>
                </div>

                <!-- Confirmation -->
                <div class="confirmation-box">
                    <h5><i class="bi bi-check-square me-2"></i>Konfirmasi Pembatalan</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmUnderstand" name="confirmUnderstand" value="1" required>
                        <label class="form-check-label" for="confirmUnderstand">
                            Saya memahami bahwa pembatalan ini akan mengenakan penalty sebesar 
                            <strong>Rp <?php echo number_format($penalty_amount, 0, ',', '.'); ?></strong>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmRefund" name="confirmRefund" value="1" required>
                        <label class="form-check-label" for="confirmRefund">
                            Saya memahami bahwa refund yang akan diterima adalah 
                            <strong>Rp <?php echo number_format($refund_amount, 0, ',', '.'); ?></strong>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmFinal" name="confirmFinal" value="1" required>
                        <label class="form-check-label" for="confirmFinal">
                            Saya memahami bahwa pembatalan ini bersifat final dan tidak dapat dibatalkan
                        </label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="text-center">
                    <button type="submit" name="confirm_cancel" class="btn btn-danger btn-lg me-3" id="cancelBtn" disabled>
                        <i class="bi bi-x-circle me-2"></i>Ya, Batalkan Booking
                    </button>
                    <a href="history.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i>Kembali
                    </a>
                </div>
            </form>

            <!-- Refund Information -->
            <?php if ($refund_amount > 0): ?>
            <div class="alert alert-info mt-4">
                <i class="bi bi-info-circle"></i>
                <strong>Informasi Refund:</strong> Refund akan diproses dalam 1-3 hari kerja setelah pembatalan dikonfirmasi. 
                Anda akan mendapat notifikasi email setelah refund diproses.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle reason selection
        document.getElementById('reasonSelect').addEventListener('change', function() {
            const reasonText = document.getElementById('reasonText');
            if (this.value && this.value !== 'Lainnya') {
                reasonText.value = this.value;
            } else if (this.value === 'Lainnya') {
                reasonText.value = '';
                reasonText.focus();
            }
        });

        // Handle confirmation checkboxes
        const checkboxes = document.querySelectorAll('.confirmation-box input[type="checkbox"]');
        const cancelBtn = document.getElementById('cancelBtn');

        function validateForm() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const reasonFilled = document.getElementById('reasonText').value.trim().length > 0;
            cancelBtn.disabled = !(allChecked && reasonFilled);
        }

        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', validateForm);
        });

        // Handle reason text change
        document.getElementById('reasonText').addEventListener('input', validateForm);

        // Form submission confirmation
        document.getElementById('cancelForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Konfirmasi Pembatalan',
                html: `
                    <div class="text-start">
                        <p><strong>Booking ID:</strong> #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></p>
                        <p><strong>Penalty:</strong> Rp <?php echo number_format($penalty_amount, 0, ',', '.'); ?></p>
                        <p><strong>Refund:</strong> Rp <?php echo number_format($refund_amount, 0, ',', '.'); ?></p>
                        <hr>
                        <p class="text-danger"><strong>Peringatan:</strong> Pembatalan tidak dapat dibatalkan!</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Ya, Batalkan!',
                cancelButtonText: 'Tidak',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading
                    Swal.fire({
                        title: 'Memproses...',
                        text: 'Sedang membatalkan booking Anda',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit form
                    this.submit();
                }
            });
        });

        // Auto-fill form for testing (remove in production)
        <?php if (isset($_GET['debug']) && $_GET['debug'] == 'true'): ?>
        setTimeout(() => {
            document.getElementById('reasonText').value = 'Testing pembatalan booking';
            document.getElementById('confirmUnderstand').checked = true;
            document.getElementById('confirmRefund').checked = true;
            document.getElementById('confirmFinal').checked = true;
            validateForm();
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>