<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$message = '';
$error = '';
$user_id = $_SESSION['user_id'];

// Get current user data
$userQuery = "SELECT * FROM users WHERE id = $user_id";
$userResult = mysqli_query($connection, $userQuery);
$user = mysqli_fetch_assoc($userResult);

if (!$user) {
    session_destroy();
    header('Location: login.php?pesan=user_not_found');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        // Update profile information
        $full_name = sanitize_input($_POST['full_name']);
        $email = sanitize_input($_POST['email']);
        $phone = sanitize_input($_POST['phone']);
        
        // Check if email is already used by another user
        $emailCheckQuery = "SELECT id FROM users WHERE email = '$email' AND id != $user_id";
        $emailCheckResult = mysqli_query($connection, $emailCheckQuery);
        
        if (mysqli_num_rows($emailCheckResult) > 0) {
            $error = 'Email sudah digunakan oleh user lain!';
        } else {
            $updateQuery = "UPDATE users SET full_name = '$full_name', email = '$email', phone = '$phone', updated_at = NOW() WHERE id = $user_id";
            
            if (mysqli_query($connection, $updateQuery)) {
                $message = 'Profil berhasil diperbarui!';
                // Refresh user data
                $userResult = mysqli_query($connection, $userQuery);
                $user = mysqli_fetch_assoc($userResult);
            } else {
                $error = 'Terjadi kesalahan saat memperbarui profil: ' . mysqli_error($connection);
            }
        }
    } elseif (isset($_POST['change_password'])) {
        // Change password
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (!password_verify($current_password, $user['password'])) {
            $error = 'Password lama tidak sesuai!';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password baru minimal 6 karakter!';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Konfirmasi password baru tidak sesuai!';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updatePasswordQuery = "UPDATE users SET password = '$hashed_password', updated_at = NOW() WHERE id = $user_id";
            
            if (mysqli_query($connection, $updatePasswordQuery)) {
                $message = 'Password berhasil diubah!';
            } else {
                $error = 'Terjadi kesalahan saat mengubah password: ' . mysqli_error($connection);
            }
        }
    }
}

// Get user booking statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total_booking,
        SUM(CASE WHEN status = 'aktif' THEN 1 ELSE 0 END) as booking_aktif,
        SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as booking_selesai,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as booking_pending
    FROM booking 
    WHERE id_user = $user_id
";
$statsResult = mysqli_query($connection, $statsQuery);
$stats = mysqli_fetch_assoc($statsResult);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil User - Futsal Booking</title>

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

        .profile-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary-color);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 3rem;
            color: white;
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        .profile-info h3 {
            color: #2c3e50;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-info p {
            color: #666;
            margin: 0;
        }

        .stats-section {
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .stat-item {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-3px);
        }

        .stat-item.aktif {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
        }

        .stat-item.selesai {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
        }

        .stat-item.pending {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
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

        .form-section h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-color);
        }

        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #ced4da;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 8px;
        }

        .btn {
            padding: 12px 25px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(231, 76, 60, 0.4);
        }

        .alert {
            border-radius: var(--border-radius);
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

        .member-since {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            text-align: center;
        }

        .member-since i {
            color: var(--primary-color);
            margin-right: 8px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .profile-card, .form-section {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
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
            <h1><i class="bi bi-person-circle me-2"></i>Profil User</h1>
            <p class="mb-0">Kelola informasi akun dan keamanan Anda</p>
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

        <!-- Profile Card -->
        <div class="profile-card fade-in">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <p>@<?php echo htmlspecialchars($user['username']); ?></p>
                </div>
                <div class="member-since">
                    <i class="bi bi-calendar-plus"></i>
                    Member sejak <?php echo date('d F Y', strtotime($user['created_at'])); ?>
                </div>
            </div>

            <h4><i class="bi bi-person-gear me-2"></i>Update Informasi Profil</h4>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" name="full_name" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                        <small class="text-muted">Username tidak dapat diubah</small>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" 
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">No. Telepon</label>
                        <input type="tel" class="form-control" name="phone" 
                               value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="update_profile" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Update Profil
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password Form -->
        <div class="form-section fade-in">
            <h4><i class="bi bi-shield-lock me-2"></i>Ubah Password</h4>
            <form method="POST" action="">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Password Lama</label>
                        <input type="password" class="form-control" name="current_password" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Password Baru</label>
                        <input type="password" class="form-control" name="new_password" required>
                        <small class="text-muted">Minimal 6 karakter</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" name="confirm_password" required>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="change_password" class="btn btn-danger">
                        <i class="bi bi-shield-check me-2"></i>Ubah Password
                    </button>
                </div>
            </form>
        </div>

        <!-- Account Info -->
        <div class="form-section fade-in">
            <h4><i class="bi bi-info-circle me-2"></i>Informasi Akun</h4>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>Akun dibuat:</strong> <?php echo date('d F Y, H:i', strtotime($user['created_at'])); ?> WIB</p>
                    <p><strong>Terakhir diperbarui:</strong> <?php echo date('d F Y, H:i', strtotime($user['updated_at'])); ?> WIB</p>
                </div>
                <div class="col-md-6">
                    <p><strong>Status akun:</strong> <span class="text-success">Aktif</span></p>
                    <p><strong>ID User:</strong> #<?php echo $user['id']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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

        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.querySelector('input[name="new_password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (newPassword && confirmPassword) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Password Tidak Sesuai',
                        text: 'Konfirmasi password baru tidak sesuai!'
                    });
                    return false;
                }
            }
        });
    </script>
</body>
</html>