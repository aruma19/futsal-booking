<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';

// Buat folder upload jika belum ada
if (!file_exists('../uploads/lapangan/')) {
    mkdir('../uploads/lapangan/', 0777, true);
}

// Handle Add Lapangan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                // Upload berhasil
            } else {
                $message = 'Gagal mengupload gambar!';
                $gambar_name = '';
            }
        } else {
            $message = 'Format gambar tidak didukung! Gunakan JPG, JPEG, atau PNG.';
        }
    }
    
    if (empty($message)) {
        // Insert
        $insertQuery = "INSERT INTO lapangan (nama, tipe, harga, status, gambar, created_at) 
                       VALUES ('$nama', '$tipe', $harga, '$status', '$gambar_name', NOW())";
        if (mysqli_query($connection, $insertQuery)) {
            $message = 'Lapangan berhasil ditambahkan!';
            // Reset form after success
            $_POST = array();
        } else {
            $message = 'Terjadi kesalahan saat menambah lapangan: ' . mysqli_error($connection);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Lapangan - Admin Futsal Booking</title>

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
            max-width: 900px;
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

        .form-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .form-section h3 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 3px solid var(--primary-color);
            text-align: center;
        }

        .form-control, .form-select {
            padding: 15px 20px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
            font-size: 1rem;
            transition: all 0.3s;
            font-weight: 500;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(231, 76, 60, 0.25);
            transform: translateY(-2px);
        }

        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(149, 165, 166, 0.4);
        }

        .alert {
            border-radius: var(--border-radius);
            padding: 20px 25px;
            font-size: 1rem;
            margin-bottom: 25px;
            border: none;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .alert i {
            margin-right: 12px;
            font-size: 1.2rem;
        }

        .alert-success {
            background: linear-gradient(45deg, rgba(39, 174, 96, 0.15), rgba(46, 204, 113, 0.15));
            color: var(--success-color);
            border-left: 5px solid var(--success-color);
        }

        .alert-danger {
            background: linear-gradient(45deg, rgba(231, 76, 60, 0.15), rgba(236, 112, 99, 0.15));
            color: var(--danger-color);
            border-left: 5px solid var(--danger-color);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1.1rem;
        }

        .input-icon input,
        .input-icon select {
            padding-left: 50px;
        }

        .file-upload-wrapper {
            position: relative;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .file-upload-wrapper:hover {
            border-color: var(--primary-color);
            background: rgba(231, 76, 60, 0.05);
        }

        .file-upload-wrapper input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .action-buttons {
            text-align: center;
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .form-section {
                padding: 25px 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin-bottom: 10px;
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
            <h1><i class="bi bi-plus-circle me-2"></i>Tambah Lapangan Futsal</h1>
            <p class="mb-0">Tambahkan lapangan futsal baru ke dalam sistem</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="alert <?php echo (strpos($message, 'berhasil') !== false) ? 'alert-success' : 'alert-danger'; ?> fade-in">
            <i class="bi bi-<?php echo (strpos($message, 'berhasil') !== false) ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>

        <!-- Form Section -->
        <div class="form-section fade-in">
            <h3>
                <i class="bi bi-plus-circle me-2"></i>
                Form Tambah Lapangan Baru
            </h3>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-building me-2"></i>Nama Lapangan
                            </label>
                            <div class="input-icon">
                                <i class="bi bi-building"></i>
                                <input type="text" class="form-control" name="nama" 
                                       placeholder="Contoh: Lapangan A" 
                                       value="<?php echo isset($_POST['nama']) ? htmlspecialchars($_POST['nama']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-layers me-2"></i>Tipe Lapangan
                            </label>
                            <div class="input-icon">
                                <i class="bi bi-layers"></i>
                                <select class="form-select" name="tipe" required>
                                    <option value="">Pilih Tipe Lapangan</option>
                                    <option value="Vinyl" <?php echo (isset($_POST['tipe']) && $_POST['tipe'] == 'Vinyl') ? 'selected' : ''; ?>>Vinyl</option>
                                    <option value="Rumput Sintetis" <?php echo (isset($_POST['tipe']) && $_POST['tipe'] == 'Rumput Sintetis') ? 'selected' : ''; ?>>Rumput Sintetis</option>
                                    <option value="Parquet" <?php echo (isset($_POST['tipe']) && $_POST['tipe'] == 'Parquet') ? 'selected' : ''; ?>>Parquet</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-cash me-2"></i>Harga per Jam (Rp)
                            </label>
                            <div class="input-icon">
                                <i class="bi bi-cash"></i>
                                <input type="number" class="form-control" name="harga" 
                                       placeholder="Contoh: 100000" 
                                       value="<?php echo isset($_POST['harga']) ? $_POST['harga'] : ''; ?>" required min="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">
                                <i class="bi bi-check-circle me-2"></i>Status Lapangan
                            </label>
                            <div class="input-icon">
                                <i class="bi bi-check-circle"></i>
                                <select class="form-select" name="status" required>
                                    <option value="tersedia" <?php echo (isset($_POST['status']) && $_POST['status'] == 'tersedia') ? 'selected' : ''; ?>>Tersedia</option>
                                    <option value="habis" <?php echo (isset($_POST['status']) && $_POST['status'] == 'habis') ? 'selected' : ''; ?>>Habis</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="bi bi-image me-2"></i>Gambar Lapangan
                    </label>
                    <div class="file-upload-wrapper">
                        <input type="file" name="gambar" accept=".jpg,.jpeg,.png" id="file-input">
                        <div class="upload-content">
                            <i class="bi bi-cloud-upload upload-icon"></i>
                            <h5>Klik untuk upload gambar</h5>
                            <p class="text-muted mb-0">Format: JPG, JPEG, PNG. Maksimal 2MB.</p>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle me-2"></i>
                        Tambah Lapangan
                    </button>
                    <a href="kelola_lapangan.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-arrow-left me-2"></i>Kembali ke Daftar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // File upload preview
        document.getElementById('file-input').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const uploadContent = document.querySelector('.upload-content');
            
            if (file) {
                const fileName = file.name;
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // MB
                
                uploadContent.innerHTML = `
                    <i class="bi bi-file-image upload-icon" style="color: var(--success-color);"></i>
                    <h5 style="color: var(--success-color);">File dipilih!</h5>
                    <p class="text-muted mb-0">${fileName} (${fileSize} MB)</p>
                `;
                
                document.querySelector('.file-upload-wrapper').style.borderColor = 'var(--success-color)';
                document.querySelector('.file-upload-wrapper').style.background = 'rgba(39, 174, 96, 0.05)';
            }
        });

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

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const nama = document.querySelector('input[name="nama"]').value;
            const tipe = document.querySelector('select[name="tipe"]').value;
            const harga = document.querySelector('input[name="harga"]').value;
            const status = document.querySelector('select[name="status"]').value;

            if (!nama || !tipe || !harga || !status) {
                e.preventDefault();
                Swal.fire({
                    title: 'Form Tidak Lengkap',
                    text: 'Mohon lengkapi semua field yang diperlukan!',
                    icon: 'warning',
                    confirmButtonColor: '#e74c3c'
                });
                return;
            }

            if (parseInt(harga) <= 0) {
                e.preventDefault();
                Swal.fire({
                    title: 'Harga Tidak Valid',
                    text: 'Harga harus lebih besar dari 0!',
                    icon: 'warning',
                    confirmButtonColor: '#e74c3c'
                });
                return;
            }
        });
    </script>
</body>
</html>