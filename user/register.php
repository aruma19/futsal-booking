<?php
session_start();
include("../config/database.php");

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize_input($_POST['full_name']);
    $phone = sanitize_input($_POST['phone']);
    
    // Validasi
    if (strlen($password) < 6) {
        $error_message = "Password minimal 6 karakter";
    } elseif ($password !== $confirm_password) {
        $error_message = "Konfirmasi password tidak sesuai";
    } else {
        // Cek apakah username atau email sudah ada
        $checkQuery = "SELECT * FROM users WHERE username = '$username' OR email = '$email'";
        $checkResult = mysqli_query($connection, $checkQuery);
        
        if (mysqli_num_rows($checkResult) > 0) {
            $error_message = "Username atau email sudah terdaftar";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user baru
            $insertQuery = "INSERT INTO users (username, email, password, full_name, phone, created_at) 
                           VALUES ('$username', '$email', '$hashed_password', '$full_name', '$phone', NOW())";
            
            if (mysqli_query($connection, $insertQuery)) {
                $success_message = "Registrasi berhasil! Silakan login.";
            } else {
                $error_message = "Terjadi kesalahan saat registrasi: " . mysqli_error($connection);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register User - Futsal Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #5dade2;
            --success-color: #27ae60;
            --error-color: #dc3545;
        }

        body {
            background: url('../public/img/futsal_register_bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            font-family: 'Poppins', sans-serif;
        }

        body::before {
            content: "";
            position: absolute;
            inset: 0; 
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.85), rgba(41, 128, 185, 0.9));
            z-index: -1; 
        }

        .register-container {
            max-width: 900px;
            width: 100%;
            z-index: 1;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }

        .card {
            border: none;
            background-color: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
        }

        .brand-section {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        .brand-section h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }

        .register-card {
            padding: 3rem 2.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            padding: 0.7rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(52, 152, 219, 0.4);
        }

        .btn-back {
            background-color: transparent;
            border: 1.5px solid white;
            color: white;
            border-radius: 8px;
            padding: 0.4rem 1.2rem;
            transition: all 0.3s;
            font-weight: 500;
        }

        .btn-back:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateX(-3px);
        }

        .form-control {
            padding: 0.8rem 1rem;
            border-radius: 8px;
            border: 1px solid #ced4da;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
        }

        .form-label {
            font-weight: 500;
            color: #495057;
            margin-bottom: 0.5rem;
        }

        .error-message {
            color: var(--error-color);
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border-left: 4px solid var(--error-color);
            display: flex;
            align-items: center;
        }

        .success-message {
            color: var(--success-color);
            background-color: rgba(39, 174, 96, 0.1);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border-left: 4px solid var(--success-color);
            display: flex;
            align-items: center;
        }

        .error-message i, .success-message i {
            margin-right: 8px;
            font-size: 1.1rem;
        }

        h3.fw-bold {
            position: relative;
            font-weight: 600 !important;
            color: #333;
            margin-bottom: 2rem;
        }

        h3.fw-bold::after {
            content: "";
            position: absolute;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--accent-color));
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 4px;
        }

        .text-decoration-none {
            color: var(--primary-color);
            font-weight: 500;
            transition: all 0.2s;
        }

        .text-decoration-none:hover {
            color: var(--secondary-color);
            text-decoration: underline !important;
        }

        .input-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
        }

        @media (max-width: 768px) {
            .brand-section {
                text-align: center;
                padding: 2rem 1rem;
            }

            .brand-section h1 {
                font-size: 2rem;
            }

            .register-card {
                padding: 2rem 1.5rem;
            }
        }
    </style>
</head>

<body>
    <div class="register-container">
        <div class="row g-0">
            <div class="col-md-5 brand-section">
                <div class="d-flex flex-column h-100">
                    <div class="mb-auto">
                        <a href="../index.php">
                            <button type="button" class="btn btn-back">
                                <i class="bi bi-arrow-left me-1"></i> Back
                            </button>
                        </a>
                    </div>
                    <div class="mb-auto text-center text-md-start">
                        <h1 class="mb-3"><i class="bi bi-person-plus-fill me-2"></i>JOIN US</h1>
                        <p style="margin-top: 20px; font-size: 1.1rem;">Bergabunglah dengan komunitas futsal terbaik!</p>
                        <p style="font-size: 0.9rem; opacity: 0.8;">Dapatkan akses mudah untuk booking lapangan futsal favorit Anda</p>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card">
                    <div class="card-body register-card">
                        <h3 class="mb-4 text-center fw-bold">DAFTAR AKUN BARU</h3>
                        
                        <?php if (!empty($error_message)) : ?>
                            <div class="error-message">
                                <i class="bi bi-exclamation-circle"></i> <?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($success_message)) : ?>
                            <div class="success-message">
                                <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Nama Lengkap</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" 
                                               placeholder="Masukkan nama lengkap" name="full_name" required 
                                               value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" />
                                        <span class="input-icon"><i class="bi bi-person-badge"></i></span>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" 
                                               placeholder="Masukkan username" name="username" required 
                                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" />
                                        <span class="input-icon"><i class="bi bi-person"></i></span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email</label>
                                    <div class="input-group">
                                        <input type="email" class="form-control" 
                                               placeholder="Masukkan email" name="email" required 
                                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
                                        <span class="input-icon"><i class="bi bi-envelope"></i></span>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">No. Telepon</label>
                                    <div class="input-group">
                                        <input type="tel" class="form-control" 
                                               placeholder="Masukkan no. telepon" name="phone" required 
                                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" />
                                        <span class="input-icon"><i class="bi bi-telephone"></i></span>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" 
                                               placeholder="Masukkan password" name="password" required />
                                        <span class="input-icon"><i class="bi bi-lock"></i></span>
                                    </div>
                                    <small class="text-muted">Minimal 6 karakter</small>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label">Konfirmasi Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" 
                                               placeholder="Konfirmasi password" name="confirm_password" required />
                                        <span class="input-icon"><i class="bi bi-lock-fill"></i></span>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <button class="btn btn-primary w-100" type="submit">
                                    <i class="bi bi-person-plus me-2"></i>Daftar Sekarang
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <p class="mb-2">Sudah punya akun?</p>
                                <a href="login.php" class="text-decoration-none">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Login disini
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!empty($success_message)): ?>
    <script>
        setTimeout(function() {
            window.location.href = 'login.php?pesan=signup_berhasil';
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>