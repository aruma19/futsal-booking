<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';
$error = '';
$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
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
        u.phone,
        p.amount as payment_amount,
        p.payment_type,
        p.payment_method as initial_payment_method,
        p.status as payment_status
    FROM booking b 
    JOIN lapangan l ON b.id_lapangan = l.id 
    JOIN users u ON b.id_user = u.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.id = $booking_id AND b.id_user = $user_id 
    AND b.status_pembayaran = 'dp' AND b.status = 'aktif'
";

$result = mysqli_query($connection, $query);
$booking = mysqli_fetch_assoc($result);

if (!$booking) {
    header('Location: history.php?error=booking_not_found');
    exit();
}

// Calculate amounts
$total_harga = $booking['harga'] * $booking['lama_sewa'];
$sisa_pembayaran = $total_harga - $booking['total_dibayar'];

// Handle payment completion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = sanitize_input($_POST['payment_method']);
    
    // Validate payment method (only ewallet and transfer for completion)
    if (!in_array($payment_method, ['ewallet', 'transfer'])) {
        $error = 'Metode pembayaran tidak valid untuk pelunasan!';
    } else {
        // Insert new payment record for completion
        $insertPayment = "INSERT INTO payments (booking_id, user_id, amount, payment_type, payment_method, status, created_at) 
                         VALUES ($booking_id, $user_id, $sisa_pembayaran, 'lunas', '$payment_method', 'pending', NOW())";
        
        if (mysqli_query($connection, $insertPayment)) {
            // Update booking
            $updateBooking = "UPDATE booking SET 
                             status_pembayaran = 'lunas', 
                             total_dibayar = $total_harga 
                             WHERE id = $booking_id";
            
            if (mysqli_query($connection, $updateBooking)) {
                // Update payment status to completed
                $updatePayment = "UPDATE payments SET status = 'completed' WHERE booking_id = $booking_id";
                mysqli_query($connection, $updatePayment);
                
                // Redirect to success page
                header("Location: payment_success.php?booking_id=$booking_id&type=completion");
                exit();
            } else {
                $error = 'Gagal memperbarui status booking: ' . mysqli_error($connection);
            }
        } else {
            $error = 'Gagal menyimpan data pembayaran: ' . mysqli_error($connection);
        }
    }
}

// Check if booking is still valid for completion
$booking_datetime = strtotime($booking['tanggal'] . ' ' . $booking['jam']);
$now = time();
$time_remaining = $booking_datetime - $now;
$hours_remaining = $time_remaining / 3600;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pelunasan Pembayaran - Booking #<?php echo $booking_id; ?></title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            background: linear-gradient(135deg, rgb(235, 245, 255) 0%, rgb(240, 248, 255) 100%);
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .payment-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .header-section {
            text-align: center;
            margin-bottom: 35px;
            padding: 25px;
            background: linear-gradient(45deg, #3498db, #2980b9);
            color: white;
            border-radius: 15px;
        }

        .header-section h1 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }

        .booking-summary {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid #3498db;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            color: #2c3e50;
            font-size: 1.1em;
            background: #e3f2fd;
            margin: 15px -25px -25px -25px;
            padding: 20px 25px;
            border-radius: 0 0 15px 15px;
        }

        .payment-info {
            background: #e8f5e8;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            border-left: 5px solid #27ae60;
        }

        .payment-methods {
            background: #fff3cd;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            border-left: 5px solid #f39c12;
        }

        .method-option {
            background: white;
            border: 2px solid #ddd;
            border-radius: 12px;
            padding: 20px;
            margin: 15px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }

        .method-option:hover {
            border-color: #3498db;
            background: #f0f8ff;
            transform: translateY(-2px);
        }

        .method-option.selected {
            border-color: #3498db;
            background: #3498db;
            color: white;
        }

        .method-option input[type="radio"] {
            display: none;
        }

        .method-icon {
            font-size: 2rem;
            margin-right: 20px;
            width: 60px;
            text-align: center;
        }

        .method-details h6 {
            margin: 0;
            font-weight: 600;
        }

        .method-details p {
            margin: 5px 0 0 0;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .account-details {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            border: 1px solid #e9ecef;
        }

        .time-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }

        .time-warning.urgent {
            background: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }

        .countdown {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
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

        .btn-primary {
            background: linear-gradient(45deg, #3498db, #2980b9);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 12px 25px rgba(52, 152, 219, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
        }

        .instructions {
            background: #e3f2fd;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
            border-left: 5px solid #2196f3;
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

        @media (max-width: 768px) {
            .payment-card {
                padding: 20px;
            }
            
            .method-option {
                flex-direction: column;
                text-align: center;
            }
            
            .method-icon {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }

        @media print {
            .no-print { display: none; }
            body { background: white; }
            .payment-card { box-shadow: none; }
        }
    </style>
</head>

<body>
    <?php include 'user_sidebar.php'; ?>
    
    <div class="container">
        <div class="payment-card">
            <!-- Header -->
            <div class="header-section">
                <h1><i class="bi bi-credit-card-2-front me-2"></i>Pelunasan Pembayaran</h1>
                <p class="mb-0">Booking ID: #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></p>
            </div>

            <!-- Error Message -->
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-circle"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Time Warning -->
            <?php if ($hours_remaining <= 24): ?>
            <div class="time-warning <?php echo ($hours_remaining <= 6) ? 'urgent' : ''; ?>">
                <h5>
                    <i class="bi bi-clock-history me-2"></i>
                    <?php if ($hours_remaining <= 6): ?>
                    Waktu Bermain Segera Dimulai!
                    <?php else: ?>
                    Perhatian: Waktu Bermain Kurang dari 24 Jam
                    <?php endif; ?>
                </h5>
                <div class="countdown">
                    <?php if ($hours_remaining > 0): ?>
                    <?php echo floor($hours_remaining); ?> jam <?php echo floor(($hours_remaining - floor($hours_remaining)) * 60); ?> menit lagi
                    <?php else: ?>
                    <span class="text-danger">Waktu bermain sudah lewat!</span>
                    <?php endif; ?>
                </div>
                <p class="mb-0">Segera lakukan pelunasan untuk memastikan booking Anda tetap aktif.</p>
            </div>
            <?php endif; ?>

            <!-- Booking Summary -->
            <div class="booking-summary">
                <h5><i class="bi bi-receipt me-2"></i>Ringkasan Booking</h5>
                <div class="summary-row">
                    <span>Lapangan:</span>
                    <span><strong><?php echo htmlspecialchars($booking['nama_lapangan']); ?></strong></span>
                </div>
                <div class="summary-row">
                    <span>Tanggal & Waktu:</span>
                    <span><?php echo date('l, d F Y - H:i', strtotime($booking['tanggal'] . ' ' . $booking['jam'])); ?> WIB</span>
                </div>
                <div class="summary-row">
                    <span>Durasi:</span>
                    <span><?php echo $booking['lama_sewa']; ?> jam</span>
                </div>
                <div class="summary-row">
                    <span>Harga per Jam:</span>
                    <span>Rp <?php echo number_format($booking['harga'], 0, ',', '.'); ?></span>
                </div>
                <div class="summary-row">
                    <span>Total Harga:</span>
                    <span>Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></span>
                </div>
                <div class="summary-row">
                    <span>Sudah Dibayar (DP):</span>
                    <span>Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></span>
                </div>
                <div class="summary-row">
                    <span><strong>Sisa yang Harus Dibayar:</strong></span>
                    <span><strong>Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></strong></span>
                </div>
            </div>

            <!-- Payment Info -->
            <div class="payment-info">
                <h5><i class="bi bi-info-circle me-2"></i>Informasi Pembayaran Sebelumnya</h5>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Tipe Pembayaran:</strong> <span class="badge bg-warning">DP (50%)</span></p>
                        <p><strong>Jumlah DP:</strong> Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Metode Awal:</strong> <?php echo ucfirst($booking['initial_payment_method']); ?></p>
                        <p><strong>Status:</strong> <span class="badge bg-success">Confirmed</span></p>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <form method="POST" id="paymentForm">
                <!-- Payment Methods -->
                <div class="payment-methods">
                    <h5><i class="bi bi-wallet2 me-2"></i>Pilih Metode Pelunasan</h5>
                    <p class="text-muted mb-4">Untuk pelunasan, hanya tersedia metode E-Wallet dan Transfer Bank</p>
                    
                    <div class="method-option" onclick="selectMethod('ewallet')">
                        <input type="radio" name="payment_method" value="ewallet" id="ewallet">
                        <div class="method-icon">
                            <i class="bi bi-phone text-primary"></i>
                        </div>
                        <div class="method-details">
                            <h6>E-Wallet</h6>
                            <p>OVO, Dana, GoPay, atau ShopeePay</p>
                            <div class="account-details" id="ewalletDetails" style="display: none;">
                                <strong>Transfer ke nomor:</strong><br>
                                <span class="text-primary">081234567890</span><br>
                                <small>a.n. Futsal Booking Center</small>
                            </div>
                        </div>
                    </div>

                    <div class="method-option" onclick="selectMethod('transfer')">
                        <input type="radio" name="payment_method" value="transfer" id="transfer">
                        <div class="method-icon">
                            <i class="bi bi-bank text-info"></i>
                        </div>
                        <div class="method-details">
                            <h6>Transfer Bank</h6>
                            <p>Transfer melalui ATM, Mobile Banking, atau Internet Banking</p>
                            <div class="account-details" id="transferDetails" style="display: none;">
                                <strong>BCA:</strong> 1234567890<br>
                                <strong>Mandiri:</strong> 9876543210<br>
                                <small>a.n. Futsal Booking Center</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Instructions -->
                <div class="instructions">
                    <h6><i class="bi bi-list-check me-2"></i>Petunjuk Pelunasan</h6>
                    <ol class="mb-0">
                        <li>Pilih metode pembayaran yang diinginkan</li>
                        <li>Lakukan transfer sesuai nominal yang tertera</li>
                        <li>Klik tombol "Konfirmasi Pelunasan" di bawah</li>
                        <li>Simpan bukti transfer untuk referensi</li>
                        <li>Datang ke lapangan sesuai jadwal</li>
                    </ol>
                </div>

                <!-- Submit Button -->
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg me-3" id="submitBtn" disabled>
                        <i class="bi bi-check-circle me-2"></i>Konfirmasi Pelunasan
                        <br><small>Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></small>
                    </button>
                    <a href="history.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i>Kembali
                    </a>
                </div>
            </form>

            <!-- Footer -->
            <div class="text-center mt-4 text-muted">
                <p><small>
                    <i class="bi bi-telephone me-1"></i>Bantuan: 0274-123456 | 
                    <i class="bi bi-envelope me-1"></i>admin@futsalbooking.com
                </small></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function selectMethod(method) {
            // Remove selected class from all options
            document.querySelectorAll('.method-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Hide all details
            document.querySelectorAll('.account-details').forEach(detail => {
                detail.style.display = 'none';
            });
            
            // Select current method
            const selectedOption = document.querySelector(`input[value="${method}"]`).closest('.method-option');
            selectedOption.classList.add('selected');
            
            // Show details for selected method
            document.getElementById(method + 'Details').style.display = 'block';
            
            // Check radio button
            document.getElementById(method).checked = true;
            
            // Enable submit button
            document.getElementById('submitBtn').disabled = false;
        }

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!selectedMethod) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Pilih Metode Pembayaran',
                    text: 'Silakan pilih metode pembayaran untuk melanjutkan pelunasan.'
                });
                return false;
            }
            
            // Show confirmation
            e.preventDefault();
            Swal.fire({
                title: 'Konfirmasi Pelunasan',
                html: `
                    <p>Anda akan melakukan pelunasan sebesar:</p>
                    <h4 class="text-primary">Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></h4>
                    <p>Metode: <strong>${selectedMethod.value.toUpperCase()}</strong></p>
                    <hr>
                    <p class="text-muted">Lakukan transfer terlebih dahulu sebelum mengkonfirmasi.</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3498db',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Ya, Sudah Transfer!',
                cancelButtonText: 'Belum Transfer'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Submit form
                    this.submit();
                }
            });
        });

        // Update countdown every minute
        setInterval(function() {
            location.reload();
        }, 60000);

        // Disable form if time is up
        <?php if ($hours_remaining <= 0): ?>
        document.getElementById('paymentForm').style.display = 'none';
        Swal.fire({
            icon: 'error',
            title: 'Waktu Habis',
            text: 'Maaf, waktu untuk melakukan pelunasan sudah habis. Silakan hubungi admin.',
            allowOutsideClick: false
        });
        <?php endif; ?>
    </script>
</body>
</html>