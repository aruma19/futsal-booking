<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';
$error = '';
$selected_lapangan = isset($_GET['lapangan']) ? (int)$_GET['lapangan'] : 0;

// Function to get booked time slots for a specific date and field
function getBookedSlots($connection, $lapangan_id, $date) {
    $query = "SELECT jam, lama_sewa FROM booking 
              WHERE id_lapangan = $lapangan_id 
              AND tanggal = '$date' 
              AND status IN ('pending', 'aktif')
              ORDER BY jam ASC";
    $result = mysqli_query($connection, $query);
    $bookedSlots = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $startTime = $row['jam'];
        $duration = $row['lama_sewa'];
        
        // Convert to minutes for easier calculation
        $startMinutes = (int)date('H', strtotime($startTime)) * 60 + (int)date('i', strtotime($startTime));
        $endMinutes = $startMinutes + ($duration * 60);
        
        $bookedSlots[] = [
            'start' => $startMinutes,
            'end' => $endMinutes,
            'start_time' => $startTime,
            'end_time' => date('H:i', strtotime($startTime . ' + ' . $duration . ' hours'))
        ];
    }
    
    return $bookedSlots;
}

// Function to check if a time slot is available
function isTimeSlotAvailable($bookedSlots, $requestedStart, $requestedDuration) {
    $requestedStartMinutes = (int)date('H', strtotime($requestedStart)) * 60 + (int)date('i', strtotime($requestedStart));
    $requestedEndMinutes = $requestedStartMinutes + ($requestedDuration * 60);
    
    foreach ($bookedSlots as $slot) {
        // Check for overlap
        if (($requestedStartMinutes < $slot['end']) && ($requestedEndMinutes > $slot['start'])) {
            return false;
        }
    }
    
    return true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_lapangan = (int)$_POST['id_lapangan'];
    $tanggal = sanitize_input($_POST['tanggal']);
    $jam = sanitize_input($_POST['jam']);
    $lama_sewa = (int)$_POST['lama_sewa'];
    $kontak = sanitize_input($_POST['kontak']);
    $payment_type = sanitize_input($_POST['payment_type']); // dp atau lunas
    $payment_method = sanitize_input($_POST['payment_method']); // tunai, ewallet, transfer
    $id_user = $_SESSION['user_id'];
    
    // Validasi
    if (empty($tanggal) || empty($jam) || empty($kontak) || empty($payment_type) || empty($payment_method)) {
        $error = 'Semua field harus diisi!';
    } elseif ($lama_sewa < 1 || $lama_sewa > 8) {
        $error = 'Lama sewa minimal 1 jam dan maksimal 8 jam!';
    } elseif (strtotime($tanggal) < strtotime(date('Y-m-d'))) {
        $error = 'Tanggal booking tidak boleh di masa lalu!';
    } else {
        // Get lapangan data for price calculation
        $lapanganQuery = "SELECT * FROM lapangan WHERE id = $id_lapangan AND status = 'tersedia'";
        $lapanganResult = mysqli_query($connection, $lapanganQuery);
        $lapanganData = mysqli_fetch_assoc($lapanganResult);
        
        if (!$lapanganData) {
            $error = 'Lapangan tidak ditemukan atau tidak tersedia!';
        } else {
            // Calculate total price
            $total_harga = $lapanganData['harga'] * $lama_sewa;
            
            // Calculate payment amount based on type
            if ($payment_type == 'dp') {
                $jumlah_bayar = $total_harga * 0.5; // DP 50%
                $status_pembayaran = 'dp';
            } else {
                $jumlah_bayar = $total_harga; // Full payment
                $status_pembayaran = 'lunas';
            }
            
            // Validasi jam operasional (06:00 - 23:00)
            $jamStart = (int)date('H', strtotime($jam));
            $jamEnd = $jamStart + $lama_sewa;
            
            if ($jamStart < 6 || $jamEnd > 23) {
                $error = 'Jam operasional lapangan adalah 06:00 - 23:00. Pastikan booking Anda tidak melebihi jam operasional!';
            } else {
                // Cek slot waktu yang sudah dibooking
                $bookedSlots = getBookedSlots($connection, $id_lapangan, $tanggal);
                
                if (!isTimeSlotAvailable($bookedSlots, $jam, $lama_sewa)) {
                    $error = 'Maaf, waktu yang Anda pilih bertabrakan dengan booking lain. Silakan pilih waktu yang tersedia!';
                } else {
                    // Insert booking baru
                    $insertQuery = "INSERT INTO booking (id_user, id_lapangan, tanggal, jam, lama_sewa, kontak, status, status_pembayaran, total_dibayar, total_pinalti, created_at) 
                                   VALUES ($id_user, $id_lapangan, '$tanggal', '$jam', $lama_sewa, '$kontak', 'pending', '$status_pembayaran', $jumlah_bayar, 0, NOW())";
                    
                    if (mysqli_query($connection, $insertQuery)) {
                        $booking_id = mysqli_insert_id($connection);
                        
                        // Insert payment record
                        $paymentQuery = "INSERT INTO payments (booking_id, user_id, amount, payment_type, payment_method, status, created_at) 
                                        VALUES ($booking_id, $id_user, $jumlah_bayar, '$payment_type', '$payment_method', 'pending', NOW())";
                        mysqli_query($connection, $paymentQuery);
                        
                        // Redirect to payment confirmation
                        header("Location: complete_payment.php?booking_id=$booking_id");
                        exit();
                    } else {
                        $error = 'Terjadi kesalahan saat membuat booking: ' . mysqli_error($connection);
                    }
                }
            }
        }
    }
}

// Get available fields
$lapanganQuery = "SELECT * FROM lapangan WHERE status = 'tersedia' ORDER BY nama";
$lapanganResult = mysqli_query($connection, $lapanganQuery);
$lapangan_list = [];
while($row = mysqli_fetch_assoc($lapanganResult)) {
    $lapangan_list[] = $row;
}

// Get selected field data
$selected_lapangan_data = null;
if ($selected_lapangan > 0) {
    $detailQuery = "SELECT * FROM lapangan WHERE id = $selected_lapangan AND status = 'tersedia'";
    $detailResult = mysqli_query($connection, $detailQuery);
    if (mysqli_num_rows($detailResult) > 0) {
        $selected_lapangan_data = mysqli_fetch_assoc($detailResult);
    } else {
        $error = 'Lapangan yang dipilih tidak tersedia atau sedang tutup/maintenance!';
        $selected_lapangan = 0;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Lapangan - Futsal Booking</title>

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

        .booking-form {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .form-section {
            margin-bottom: 25px;
        }

        .form-section h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-color);
        }

        .form-control, .form-select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
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

        .alert-danger {
            background: linear-gradient(45deg, rgba(231, 76, 60, 0.1), rgba(236, 112, 99, 0.1));
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        .alert-warning {
            background: linear-gradient(45deg, rgba(243, 156, 18, 0.1), rgba(230, 126, 34, 0.1));
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }

        .lapangan-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-color);
        }

        .lapangan-info h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .info-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .info-item strong {
            color: var(--primary-color);
        }

        .price-display {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            color: white;
            padding: 15px;
            border-radius: var(--border-radius);
            text-align: center;
            margin: 15px 0;
        }

        .price-display h4 {
            margin: 0;
            font-weight: 700;
        }

        .payment-section {
            background: #e8f5e8;
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 20px 0;
            border-left: 4px solid var(--success-color);
        }

        .payment-section h5 {
            color: var(--success-color);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 15px 0;
        }

        .payment-option {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .payment-option:hover {
            border-color: var(--primary-color);
            background: #f0f8ff;
        }

        .payment-option.selected {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: white;
        }

        .payment-method {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }

        .method-option {
            background: white;
            padding: 12px;
            border-radius: 8px;
            border: 2px solid #ddd;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            font-size: 0.9rem;
        }

        .method-option:hover {
            border-color: var(--success-color);
            background: #f0fff0;
        }

        .method-option.selected {
            border-color: var(--success-color);
            background: var(--success-color);
            color: white;
        }

        .time-slots {
            background: #fff3cd;
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 15px 0;
            border-left: 4px solid var(--warning-color);
        }

        .time-slots h6 {
            color: var(--warning-color);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .slots-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
            gap: 8px;
            margin: 10px 0;
        }

        .time-slot {
            padding: 8px 12px;
            border-radius: 6px;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .slot-available {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .slot-booked {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .booking-summary {
            background: #e3f2fd;
            padding: 20px;
            border-radius: var(--border-radius);
            margin-top: 20px;
            border-left: 4px solid var(--primary-color);
        }

        .booking-summary h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 15px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: 600;
            color: var(--primary-color);
            font-size: 1.1em;
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

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .booking-form {
                padding: 20px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }

            .slots-grid {
                grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
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
    <?php include 'user_sidebar.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <h1><i class="bi bi-calendar-plus me-2"></i>Booking Lapangan Futsal</h1>
            <p class="mb-0">Pilih lapangan, waktu, dan metode pembayaran</p>
            <small class="text-muted">Hanya menampilkan lapangan yang sedang tersedia</small>
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

        <!-- Check if no available fields -->
        <?php if (empty($lapangan_list)): ?>
        <div class="empty-state fade-in">
            <i class="bi bi-geo-alt-fill"></i>
            <h3>Tidak Ada Lapangan Tersedia</h3>
            <p>Maaf, saat ini tidak ada lapangan yang tersedia untuk booking.</p>
            <p>Silakan coba lagi nanti atau hubungi admin untuk informasi lebih lanjut.</p>
            <a href="lihat_lapangan.php" class="btn btn-primary">
                <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar Lapangan
            </a>
        </div>
        <?php else: ?>

        <!-- Booking Form -->
        <div class="booking-form fade-in">
            <form method="POST" action="" id="bookingForm">
                <!-- Pilih Lapangan -->
                <div class="form-section">
                    <h4><i class="bi bi-building me-2"></i>Pilih Lapangan Tersedia</h4>
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle"></i>
                        Hanya lapangan yang sedang tersedia yang dapat dibooking
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lapangan Futsal</label>
                        <select class="form-select" name="id_lapangan" id="lapanganSelect" required onchange="updateLapanganInfo()">
                            <option value="">-- Pilih Lapangan --</option>
                            <?php foreach ($lapangan_list as $lap): ?>
                            <option value="<?php echo $lap['id']; ?>" 
                                    data-nama="<?php echo htmlspecialchars($lap['nama']); ?>"
                                    data-tipe="<?php echo htmlspecialchars($lap['tipe']); ?>"
                                    data-harga="<?php echo $lap['harga']; ?>"
                                    <?php echo ($selected_lapangan == $lap['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($lap['nama']); ?> - <?php echo htmlspecialchars($lap['tipe']); ?> (Tersedia)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Info Lapangan -->
                    <div id="lapanganInfo" class="lapangan-info" style="display: none;">
                        <h5><i class="bi bi-info-circle me-2"></i>Informasi Lapangan</h5>
                        <div class="info-grid">
                            <div class="info-item">
                                <strong><i class="bi bi-geo-alt me-1"></i>Nama:</strong><br>
                                <span id="infoNama">-</span>
                            </div>
                            <div class="info-item">
                                <strong><i class="bi bi-tag me-1"></i>Tipe:</strong><br>
                                <span id="infoTipe">-</span>
                            </div>
                            <div class="info-item">
                                <strong><i class="bi bi-clock me-1"></i>Operasional:</strong><br>
                                06:00 - 23:00 WIB
                            </div>
                            <div class="info-item">
                                <strong><i class="bi bi-star me-1"></i>Fasilitas:</strong><br>
                                Ruang ganti, Parkir, Kantin
                            </div>
                        </div>
                        <div class="price-display">
                            <h4><i class="bi bi-cash me-2"></i>Rp <span id="infoHarga">0</span> / jam</h4>
                        </div>
                    </div>
                </div>

                <!-- Waktu Booking -->
                <div class="form-section">
                    <h4><i class="bi bi-calendar-event me-2"></i>Waktu Booking</h4>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tanggal</label>
                            <input type="date" class="form-control" name="tanggal" id="tanggal" 
                                   min="<?php echo date('Y-m-d'); ?>" required onchange="checkAvailableSlots()">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Jam Mulai</label>
                            <select class="form-select" name="jam" id="jam" required onchange="calculateTotal()">
                                <option value="">-- Pilih Jam --</option>
                                <?php for($i = 6; $i <= 22; $i++): ?>
                                <option value="<?php echo sprintf('%02d:00', $i); ?>">
                                    <?php echo sprintf('%02d:00', $i); ?> WIB
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Lama Sewa (Jam)</label>
                            <select class="form-select" name="lama_sewa" id="lamaSewa" required onchange="calculateTotal()">
                                <option value="">-- Pilih Durasi --</option>
                                <?php for($i = 1; $i <= 8; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> Jam</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Time Slots Availability -->
                    <div id="timeSlots" class="time-slots" style="display: none;">
                        <h6><i class="bi bi-clock-history me-2"></i>Ketersediaan Waktu</h6>
                        <p class="mb-2">
                            <span class="time-slot slot-available me-2">Tersedia</span>
                            <span class="time-slot slot-booked">Dibooking</span>
                        </p>
                        <div id="slotsGrid" class="slots-grid">
                            <!-- Will be populated by JavaScript -->
                        </div>
                    </div>
                </div>

                <!-- Payment Section -->
                <div class="form-section">
                    <h4><i class="bi bi-credit-card me-2"></i>Opsi Pembayaran</h4>
                    
                    <!-- Payment Type -->
                    <div class="payment-section">
                        <h5><i class="bi bi-cash-coin me-2"></i>Jenis Pembayaran</h5>
                        <div class="payment-options">
                            <div class="payment-option" onclick="selectPaymentType('dp')" id="paymentDP">
                                <i class="bi bi-piggy-bank fs-2"></i>
                                <h6 class="mt-2">DP (50%)</h6>
                                <p class="mb-0">Bayar setengah sekarang</p>
                                <small>Sisa dapat dibayar nanti</small>
                            </div>
                            <div class="payment-option" onclick="selectPaymentType('lunas')" id="paymentLunas">
                                <i class="bi bi-cash-stack fs-2"></i>
                                <h6 class="mt-2">Lunas (100%)</h6>
                                <p class="mb-0">Bayar penuh sekarang</p>
                                <small>Tidak ada sisa pembayaran</small>
                            </div>
                        </div>
                        <input type="hidden" name="payment_type" id="paymentType" required>
                    </div>

                    <!-- Payment Method -->
                    <div class="payment-section">
                        <h5><i class="bi bi-wallet2 me-2"></i>Metode Pembayaran</h5>
                        <div class="payment-method">
                            <div class="method-option" onclick="selectPaymentMethod('tunai')" id="methodTunai">
                                <i class="bi bi-cash me-1"></i>Tunai
                            </div>
                            <div class="method-option" onclick="selectPaymentMethod('ewallet')" id="methodEwallet">
                                <i class="bi bi-phone me-1"></i>E-Wallet
                            </div>
                            <div class="method-option" onclick="selectPaymentMethod('transfer')" id="methodTransfer">
                                <i class="bi bi-bank me-1"></i>Transfer Bank
                            </div>
                        </div>
                        <input type="hidden" name="payment_method" id="paymentMethod" required>
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Pembayaran tunai hanya tersedia untuk booking baru. Pelunasan hanya bisa melalui E-Wallet atau Transfer.
                        </small>
                    </div>
                </div>

                <!-- Contact Info -->
                <div class="form-section">
                    <h4><i class="bi bi-telephone me-2"></i>Informasi Kontak</h4>
                    <div class="mb-3">
                        <label class="form-label">Nomor Telepon/WhatsApp</label>
                        <input type="tel" class="form-control" name="kontak" placeholder="Contoh: 08123456789" required>
                        <small class="text-muted">Nomor ini akan digunakan untuk konfirmasi booking dan pembayaran</small>
                    </div>
                </div>

                <!-- Booking Summary -->
                <div id="bookingSummary" class="booking-summary" style="display: none;">
                    <h5><i class="bi bi-receipt me-2"></i>Ringkasan Booking</h5>
                    <div class="summary-item">
                        <span>Lapangan:</span>
                        <span id="summaryLapangan">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Tanggal:</span>
                        <span id="summaryTanggal">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Waktu:</span>
                        <span id="summaryWaktu">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Durasi:</span>
                        <span id="summaryDurasi">-</span>
                    </div>
                    <div class="summary-item">
                        <span>Total Harga:</span>
                        <span>Rp <span id="totalHarga">0</span></span>
                    </div>
                    <div class="summary-item">
                        <span>Jenis Pembayaran:</span>
                        <span id="summaryPaymentType">-</span>
                    </div>
                    <div class="summary-item">
                        <span><strong>Jumlah Dibayar:</strong></span>
                        <span><strong>Rp <span id="jumlahBayar">0</span></strong></span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-credit-card-2-front me-2"></i>Lanjut ke Pembayaran
                    </button>
                    <a href="lihat_lapangan.php" class="btn btn-secondary btn-lg ms-2">
                        <i class="bi bi-arrow-left me-2"></i>Kembali
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let bookedSlots = [];

        function updateLapanganInfo() {
            const select = document.getElementById('lapanganSelect');
            const option = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('lapanganInfo');
            
            if (select.value) {
                document.getElementById('infoNama').textContent = option.dataset.nama;
                document.getElementById('infoTipe').textContent = option.dataset.tipe;
                document.getElementById('infoHarga').textContent = formatNumber(option.dataset.harga);
                infoDiv.style.display = 'block';
                checkAvailableSlots();
            } else {
                infoDiv.style.display = 'none';
                document.getElementById('timeSlots').style.display = 'none';
                document.getElementById('bookingSummary').style.display = 'none';
            }
        }

        async function checkAvailableSlots() {
            const lapanganId = document.getElementById('lapanganSelect').value;
            const tanggal = document.getElementById('tanggal').value;
            
            if (lapanganId && tanggal) {
                try {
                    const response = await fetch(`get_booked_slots.php?lapangan=${lapanganId}&date=${tanggal}`);
                    const data = await response.json();
                    bookedSlots = data.bookedSlots || [];
                    
                    displayTimeSlots();
                    document.getElementById('timeSlots').style.display = 'block';
                } catch (error) {
                    console.error('Error fetching booked slots:', error);
                }
            } else {
                document.getElementById('timeSlots').style.display = 'none';
            }
        }

        function displayTimeSlots() {
            const slotsGrid = document.getElementById('slotsGrid');
            slotsGrid.innerHTML = '';
            
            for (let hour = 6; hour <= 22; hour++) {
                const timeStr = String(hour).padStart(2, '0') + ':00';
                const isBooked = isTimeSlotBooked(hour * 60, 60);
                
                const slotElement = document.createElement('div');
                slotElement.className = `time-slot ${isBooked ? 'slot-booked' : 'slot-available'}`;
                slotElement.textContent = timeStr;
                
                slotsGrid.appendChild(slotElement);
            }
        }

        function isTimeSlotBooked(startMinutes, durationMinutes) {
            const endMinutes = startMinutes + durationMinutes;
            
            return bookedSlots.some(slot => {
                return (startMinutes < slot.end) && (endMinutes > slot.start);
            });
        }

        function selectPaymentType(type) {
            // Reset all payment options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Select clicked option
            document.getElementById('payment' + type.toUpperCase()).classList.add('selected');
            document.getElementById('paymentType').value = type;
            
            calculateTotal();
        }

        function selectPaymentMethod(method) {
            // Reset all method options
            document.querySelectorAll('.method-option').forEach(option => {
                option.classList.remove('selected');
            });
            
            // Select clicked option
            document.getElementById('method' + method.charAt(0).toUpperCase() + method.slice(1)).classList.add('selected');
            document.getElementById('paymentMethod').value = method;
            
            calculateTotal();
        }

        function calculateTotal() {
            const lapanganSelect = document.getElementById('lapanganSelect');
            const tanggal = document.getElementById('tanggal').value;
            const jam = document.getElementById('jam').value;
            const lamaSewa = document.getElementById('lamaSewa').value;
            const paymentType = document.getElementById('paymentType').value;
            
            if (lapanganSelect.value && tanggal && jam && lamaSewa && paymentType) {
                // Check if selected time conflicts with existing bookings
                const jamHour = parseInt(jam.split(':')[0]);
                const jamMinutes = jamHour * 60;
                const duration = parseInt(lamaSewa);
                
                if (isTimeSlotBooked(jamMinutes, duration * 60)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Waktu Tidak Tersedia',
                        text: 'Waktu yang Anda pilih bertabrakan dengan booking lain. Silakan pilih waktu lain.'
                    });
                    return;
                }
                
                // Check operational hours
                if (jamHour < 6 || (jamHour + duration) > 23) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Waktu Operasional',
                        text: 'Jam operasional lapangan adalah 06:00 - 23:00. Pastikan booking tidak melebihi jam operasional.'
                    });
                    return;
                }
                
                const option = lapanganSelect.options[lapanganSelect.selectedIndex];
                const harga = parseInt(option.dataset.harga);
                const total = harga * parseInt(lamaSewa);
                
                let jumlahBayar = total;
                let paymentTypeText = 'Lunas';
                
                if (paymentType === 'dp') {
                    jumlahBayar = Math.round(total * 0.5);
                    paymentTypeText = 'DP (50%)';
                }
                
                // Update summary
                document.getElementById('summaryLapangan').textContent = option.dataset.nama;
                document.getElementById('summaryTanggal').textContent = formatDate(tanggal);
                document.getElementById('summaryWaktu').textContent = jam + ' - ' + addHours(jam, parseInt(lamaSewa)) + ' WIB';
                document.getElementById('summaryDurasi').textContent = lamaSewa + ' jam';
                document.getElementById('totalHarga').textContent = formatNumber(total);
                document.getElementById('summaryPaymentType').textContent = paymentTypeText;
                document.getElementById('jumlahBayar').textContent = formatNumber(jumlahBayar);
                
                document.getElementById('bookingSummary').style.display = 'block';
            } else {
                document.getElementById('bookingSummary').style.display = 'none';
            }
        }

        function addHours(timeStr, hours) {
            const time = new Date('1970-01-01T' + timeStr + ':00');
            time.setHours(time.getHours() + hours);
            return time.toTimeString().slice(0, 5);
        }

        function formatNumber(num) {
            return parseInt(num).toLocaleString('id-ID');
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        // Initialize if lapangan already selected
        <?php if ($selected_lapangan > 0 && $selected_lapangan_data): ?>
        updateLapanganInfo();
        <?php endif; ?>

        // Form validation
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const tanggal = document.getElementById('tanggal').value;
            const jam = document.getElementById('jam').value;
            const lamaSewa = document.getElementById('lamaSewa').value;
            const paymentType = document.getElementById('paymentType').value;
            const paymentMethod = document.getElementById('paymentMethod').value;
            const today = new Date().toISOString().split('T')[0];
            
            if (tanggal < today) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Tanggal Tidak Valid',
                    text: 'Tanggal booking tidak boleh di masa lalu!'
                });
                return false;
            }

            if (!paymentType) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Pilih Jenis Pembayaran',
                    text: 'Silakan pilih jenis pembayaran (DP atau Lunas)!'
                });
                return false;
            }

            if (!paymentMethod) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Pilih Metode Pembayaran',
                    text: 'Silakan pilih metode pembayaran!'
                });
                return false;
            }

            // Final check for time conflicts
            if (jam && lamaSewa) {
                const jamHour = parseInt(jam.split(':')[0]);
                const jamMinutes = jamHour * 60;
                const duration = parseInt(lamaSewa);
                
                if (isTimeSlotBooked(jamMinutes, duration * 60)) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Konflik Waktu',
                        text: 'Waktu yang dipilih bertabrakan dengan booking lain!'
                    });
                    return false;
                }
            }
        });
    </script>
</body>
</html>