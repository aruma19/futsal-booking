<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';
$edit_data = null;

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Ambil data gambar sebelum dihapus
    $query = "SELECT gambar FROM lapangan WHERE id = $id";
    $result = mysqli_query($connection, $query);
    $lapangan = mysqli_fetch_assoc($result);
    
    // Hapus file gambar jika ada
    if ($lapangan && $lapangan['gambar'] && file_exists('../uploads/lapangan/' . $lapangan['gambar'])) {
        unlink('../uploads/lapangan/' . $lapangan['gambar']);
    }
    
    // Hapus data dari database
    $deleteQuery = "DELETE FROM lapangan WHERE id = $id";
    if (mysqli_query($connection, $deleteQuery)) {
        $message = 'Lapangan berhasil dihapus!';
    } else {
        $message = 'Terjadi kesalahan saat menghapus lapangan!';
    }
}

// Handle Edit - Load data
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $query = "SELECT * FROM lapangan WHERE id = $id";
    $result = mysqli_query($connection, $query);
    $edit_data = mysqli_fetch_assoc($result);
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = (int)$_POST['id'];
    $nama = sanitize_input($_POST['nama']);
    $tipe = sanitize_input($_POST['tipe']);
    $harga = (int)$_POST['harga'];
    $status = sanitize_input($_POST['status']);
    $gambar_name = '';
    
    // Handle upload gambar
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $filename = $_FILES['gambar']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($filetype), $allowed)) {
            $gambar_name = time() . '_' . $filename;
            if (move_uploaded_file($_FILES['gambar']['tmp_name'], '../uploads/lapangan/' . $gambar_name)) {
                // Hapus gambar lama jika ada
                $query = "SELECT gambar FROM lapangan WHERE id = $id";
                $result = mysqli_query($connection, $query);
                $old_data = mysqli_fetch_assoc($result);
                if ($old_data && $old_data['gambar'] && file_exists('../uploads/lapangan/' . $old_data['gambar'])) {
                    unlink('../uploads/lapangan/' . $old_data['gambar']);
                }
            } else {
                $message = 'Gagal mengupload gambar!';
                $gambar_name = '';
            }
        } else {
            $message = 'Format gambar tidak didukung! Gunakan JPG, JPEG, atau PNG.';
        }
    }
    
    if (empty($message)) {
        if ($gambar_name) {
            $updateQuery = "UPDATE lapangan SET nama = '$nama', tipe = '$tipe', harga = $harga, status = '$status', gambar = '$gambar_name' WHERE id = $id";
        } else {
            $updateQuery = "UPDATE lapangan SET nama = '$nama', tipe = '$tipe', harga = $harga, status = '$status' WHERE id = $id";
        }
        
        if (mysqli_query($connection, $updateQuery)) {
            $message = 'Lapangan berhasil diupdate!';
            $edit_data = null;
        } else {
            $message = 'Terjadi kesalahan saat mengupdate lapangan: ' . mysqli_error($connection);
        }
    }
}

// Ambil semua lapangan dengan status booking dinamis
$query = "SELECT * FROM lapangan ORDER BY id DESC";
$result = mysqli_query($connection, $query);
$lapangan = [];

while($row = mysqli_fetch_assoc($result)) {
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
    
    // Set status dinamis berdasarkan booking dan status lapangan
    if ($row['status'] == 'habis') {
        // Jika admin set manual ke habis, tetap habis
        $row['dynamic_status'] = 'habis';
        $row['status_text'] = 'Maintenance/Tutup';
        $row['status_class'] = 'maintenance';
    } elseif ($bookingData['active_bookings'] > 0) {
        $row['dynamic_status'] = 'dibooking';
        $row['status_text'] = 'Sedang Dibooking';
        $row['status_class'] = 'dibooking';
    } else {
        $row['dynamic_status'] = 'tersedia';
        $row['status_text'] = 'Tersedia';
        $row['status_class'] = 'tersedia';
    }
    
    // Ambil booking hari ini untuk lapangan ini
    $todayBookingsQuery = "
        SELECT b.jam, b.lama_sewa, b.status, u.full_name, u.username 
        FROM booking b
        JOIN users u ON b.id_user = u.id
        WHERE b.id_lapangan = {$row['id']} 
        AND b.tanggal = '$today' 
        AND b.status IN ('pending', 'aktif')
        ORDER BY b.jam ASC
    ";
    $todayBookingsResult = mysqli_query($connection, $todayBookingsQuery);
    $row['today_bookings'] = [];
    while($booking = mysqli_fetch_assoc($todayBookingsResult)) {
        $row['today_bookings'][] = $booking;
    }
    
    $lapangan[] = $row;
}

// Get statistics dengan status dinamis
$totalLapangan = count($lapangan);
$tersediaCount = count(array_filter($lapangan, function($lap) { return $lap['dynamic_status'] == 'tersedia'; }));
$diBookingCount = count(array_filter($lapangan, function($lap) { return $lap['dynamic_status'] == 'dibooking'; }));
$maintenanceCount = count(array_filter($lapangan, function($lap) { return $lap['dynamic_status'] == 'habis'; }));
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Lapangan - Admin Futsal Booking</title>

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
            --info-color: #3498db;
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .page-header h1 {
            color: #2c3e50;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
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
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
        }

        .stat-item {
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
        }

        .stat-item.total {
            background: linear-gradient(45deg, var(--info-color), #5dade2);
        }

        .stat-item.tersedia {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
        }

        .stat-item.dibooking {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .stat-item.maintenance {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .form-section h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
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
            box-shadow: 0 0 0 0.25rem rgba(231, 76, 60, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
        }

        .btn-warning {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
        }

        .table-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .table-section h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .table {
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .table th {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            font-weight: 500;
            border: none;
            padding: 15px 12px;
        }

        .table td {
            padding: 15px 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status {
            padding: 6px 12px;
            border-radius: 15px;
            color: white;
            font-size: 0.85em;
            font-weight: 500;
            display: inline-block;
        }

        .tersedia {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
        }

        .dibooking {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .maintenance {
            background: linear-gradient(45deg, var(--danger-color), #ec7063);
        }

        .booking-schedule {
            background: #fff3cd;
            padding: 10px;
            border-radius: 8px;
            margin: 5px 0;
            border-left: 4px solid var(--warning-color);
            font-size: 0.8rem;
        }

        .booking-schedule h6 {
            color: var(--warning-color);
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.75rem;
        }

        .schedule-item {
            background: white;
            padding: 5px 8px;
            border-radius: 4px;
            margin: 3px 0;
            font-size: 0.7rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .schedule-user {
            font-weight: 500;
            color: #666;
        }

        .schedule-time {
            font-weight: 600;
            color: var(--primary-color);
        }

        .schedule-status {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 0.6rem;
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

        .img-preview {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .current-img {
            max-width: 200px;
            margin: 10px 0;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .no-image {
            width: 80px;
            height: 60px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            color: #666;
            border-radius: 8px;
            border: 1px dashed #ddd;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .status-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .refresh-indicator {
            color: var(--info-color);
            font-size: 0.8rem;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .form-section, .table-section {
                padding: 20px;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .img-preview {
                width: 60px;
                height: 45px;
            }

            .header-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .booking-schedule {
                font-size: 0.7rem;
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease forwards;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #27ae60;
            border-radius: 50%;
            margin-right: 5px;
            animation: pulse 2s infinite;
        }
    </style>
</head>

<body>
    <!-- Include Sidebar -->
    <?php include 'admin_sidebar.php'; ?>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header fade-in">
            <div class="header-actions">
                <div>
                    <h1><i class="bi bi-building me-2"></i>Kelola Lapangan Futsal</h1>
                    <p class="mb-0">
                        Manajemen data lapangan futsal
                        <br><small class="refresh-indicator">
                            <span class="live-indicator"></span>Status real-time • Auto refresh setiap 60 detik
                        </small>
                    </p>
                </div>
                <div>
                    <a href="tambah_lapangan.php" class="btn btn-success btn-lg">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Lapangan Baru
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert <?php echo (strpos($message, 'berhasil') !== false) ? 'alert-success' : 'alert-danger'; ?> fade-in">
            <i class="bi bi-<?php echo (strpos($message, 'berhasil') !== false) ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-section fade-in">
            <h4 class="mb-4"><i class="bi bi-bar-chart me-2"></i>Statistik Lapangan Real-time</h4>
            <div class="stats-grid">
                <div class="stat-item total">
                    <div class="stat-number"><?php echo $totalLapangan; ?></div>
                    <div class="stat-label">Total Lapangan</div>
                </div>
                <div class="stat-item tersedia">
                    <div class="stat-number"><?php echo $tersediaCount; ?></div>
                    <div class="stat-label">Tersedia</div>
                </div>
                <div class="stat-item dibooking">
                    <div class="stat-number"><?php echo $diBookingCount; ?></div>
                    <div class="stat-label">Sedang Dibooking</div>
                </div>
                <div class="stat-item maintenance">
                    <div class="stat-number"><?php echo $maintenanceCount; ?></div>
                    <div class="stat-label">Maintenance/Tutup</div>
                </div>
            </div>
        </div>

        <!-- Edit Form Section (Only show when editing) -->
        <?php if ($edit_data): ?>
        <div class="form-section fade-in">
            <h3>
                <i class="bi bi-pencil me-2"></i>
                Edit Lapangan: <?php echo htmlspecialchars($edit_data['nama']); ?>
            </h3>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lapangan</label>
                        <input type="text" class="form-control" name="nama" placeholder="Contoh: Lapangan A" 
                               value="<?php echo htmlspecialchars($edit_data['nama']); ?>" required>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tipe Lapangan</label>
                        <select class="form-select" name="tipe" required>
                            <option value="">Pilih Tipe Lapangan</option>
                            <option value="Vinyl" <?php echo ($edit_data['tipe'] == 'Vinyl') ? 'selected' : ''; ?>>Vinyl</option>
                            <option value="Rumput Sintetis" <?php echo ($edit_data['tipe'] == 'Rumput Sintetis') ? 'selected' : ''; ?>>Rumput Sintetis</option>
                            <option value="Parquet" <?php echo ($edit_data['tipe'] == 'Parquet') ? 'selected' : ''; ?>>Parquet</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Harga per Jam (Rp)</label>
                        <input type="number" class="form-control" name="harga" placeholder="Contoh: 100000" 
                               value="<?php echo $edit_data['harga']; ?>" required min="0">
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Status Lapangan (Manual Override)</label>
                        <select class="form-select" name="status" required>
                            <option value="tersedia" <?php echo ($edit_data['status'] == 'tersedia') ? 'selected' : ''; ?>>Tersedia (Normal)</option>
                            <option value="habis" <?php echo ($edit_data['status'] == 'habis') ? 'selected' : ''; ?>>Tutup/Maintenance</option>
                        </select>
                        <small class="text-muted">Catatan: Status akan otomatis berubah jika ada booking aktif</small>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Gambar Lapangan</label>
                    <input type="file" class="form-control" name="gambar" accept=".jpg,.jpeg,.png">
                    <small class="text-muted">Format: JPG, JPEG, PNG. Maksimal 2MB. Kosongkan jika tidak ingin mengubah gambar.</small>
                    
                    <?php if ($edit_data['gambar'] && file_exists('../uploads/lapangan/' . $edit_data['gambar'])): ?>
                        <div class="mt-3">
                            <p class="mb-2"><strong>Gambar saat ini:</strong></p>
                            <img src="../uploads/lapangan/<?php echo htmlspecialchars($edit_data['gambar']); ?>" 
                                 alt="Current" class="current-img">
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-pencil me-2"></i>
                        Update Lapangan
                    </button>
                    <a href="kelola_lapangan.php" class="btn btn-secondary btn-lg ms-2">
                        <i class="bi bi-x-circle me-2"></i>Batal Edit
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Table Section -->
        <div class="table-section fade-in">
            <div class="table-header">
                <h3><i class="bi bi-list me-2"></i>Daftar Lapangan</h3>
                <div>
                    <span class="badge bg-primary">Total: <?php echo $totalLapangan; ?> lapangan</span>
                    <button onclick="location.reload()" class="btn btn-outline-primary btn-sm ms-2">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <?php if (empty($lapangan)): ?>
                <div class="empty-state">
                    <i class="bi bi-building"></i>
                    <h5>Belum ada data lapangan</h5>
                    <p class="text-muted">Mulai dengan menambahkan lapangan futsal pertama Anda.</p>
                    <a href="tambah_lapangan.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Tambah Lapangan
                    </a>
                </div>
                <?php else: ?>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Gambar</th>
                            <th>Nama</th>
                            <th>Tipe</th>
                            <th>Harga</th>
                            <th>Status Real-time</th>
                            <th>Booking Hari Ini</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($lapangan as $lap): ?>
                        <tr>
                            <td><strong><?php echo $lap['id']; ?></strong></td>
                            <td>
                                <?php if ($lap['gambar'] && file_exists('../uploads/lapangan/' . $lap['gambar'])): ?>
                                    <img src="../uploads/lapangan/<?php echo htmlspecialchars($lap['gambar']); ?>" 
                                         alt="<?php echo htmlspecialchars($lap['nama']); ?>" class="img-preview">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="bi bi-image"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo htmlspecialchars($lap['nama']); ?></strong></td>
                            <td><?php echo htmlspecialchars($lap['tipe']); ?></td>
                            <td><strong>Rp <?php echo number_format($lap['harga'], 0, ',', '.'); ?></strong></td>
                            <td>
                                <div class="status-info">
                                    <span class="status <?php echo $lap['status_class']; ?>">
                                        <i class="bi bi-<?php echo $lap['dynamic_status'] == 'tersedia' ? 'check-circle' : ($lap['dynamic_status'] == 'dibooking' ? 'clock' : 'x-circle'); ?> me-1"></i>
                                        <?php echo $lap['status_text']; ?>
                                    </span>
                                    <?php if ($lap['status'] == 'habis'): ?>
                                        <small class="text-muted">(Manual Override)</small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($lap['today_bookings'])): ?>
                                    <div class="booking-schedule">
                                        <h6><i class="bi bi-calendar-check me-1"></i><?php echo count($lap['today_bookings']); ?> Booking</h6>
                                        <?php foreach ($lap['today_bookings'] as $booking): ?>
                                        <div class="schedule-item">
                                            <div>
                                                <div class="schedule-time">
                                                    <?php 
                                                    echo date('H:i', strtotime($booking['jam'])) . ' - ' . 
                                                         date('H:i', strtotime($booking['jam'] . ' + ' . $booking['lama_sewa'] . ' hours')); 
                                                    ?>
                                                </div>
                                                <div class="schedule-user"><?php echo htmlspecialchars($booking['full_name']); ?></div>
                                            </div>
                                            <span class="schedule-status schedule-<?php echo $booking['status']; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <small class="text-muted">Tidak ada booking</small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($lap['created_at'])); ?></td>
                            <td>
                                <a href="?edit=<?php echo $lap['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button onclick="confirmDelete(<?php echo $lap['id']; ?>, '<?php echo htmlspecialchars($lap['nama']); ?>')" 
                                        class="btn btn-danger btn-sm">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function confirmDelete(id, nama) {
            Swal.fire({
                title: 'Konfirmasi Hapus',
                text: `Yakin ingin menghapus lapangan "${nama}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e74c3c',
                cancelButtonColor: '#95a5a6',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `?delete=${id}`;
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

        // Auto refresh halaman setiap 60 detik untuk update status real-time
        setInterval(() => {
            // Only refresh if we're not editing
            if (!window.location.search.includes('edit=')) {
                location.reload();
            }
        }, 60000);

        // Show loading indicator during refresh
        window.addEventListener('beforeunload', function() {
            if (performance.navigation.type === 1) { // reload
                document.body.style.opacity = '0.7';
            }
        });

        // Add real-time timestamp
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('id-ID');
            const refreshIndicator = document.querySelector('.refresh-indicator');
            if (refreshIndicator) {
                refreshIndicator.innerHTML += ` • Diperbarui: ${timeString}`;
            }
        });
    </script>
</body>
</html>