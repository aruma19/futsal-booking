<?php
session_start();
include('../config/database.php');

$error_message = "";
$success_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate password confirmation
    if ($password !== $confirm_password) {
        $error_message = "Password dan konfirmasi password tidak cocok";
    } else {
        // Check if username or email already exists
        $check_query = "SELECT * FROM admin WHERE username = '$username' OR email = '$email'";
        $check_data = mysqli_query($connection, $check_query);
        
        if (mysqli_num_rows($check_data) > 0) {
            $error_message = "Username atau email sudah terdaftar";
        } else {
            // Hash password and insert new admin
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $insert_query = "INSERT INTO admin (username, email, password) VALUES ('$username', '$email', '$hashed_password')";
            
            if (mysqli_query($connection, $insert_query)) {
                $success_message = "Registrasi admin berhasil! Silakan login.";
            } else {
                $error_message = "Terjadi kesalahan saat registrasi";
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
    <title>Register Admin - Futsal Booking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        :root {
            --primary-color: #e74c3c;
            --secondary-color: #c0392b;
            --accent-color: #ec7063;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --error-color: #dc3545;
            --success-color: #28a745;
        }

        body {
            background: url('../public/img/futsal_bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            z-index: 0;
            font-family: 'Poppins', sans-serif;
        }

        body::before {
            content: "";
            position: absolute;
            inset: 0; 
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.85), rgba(192, 57, 43, 0.9));
            z-index: -1; 
        }

        .register-container {
            max-width: 1100px;
            width: 100%;
            z-index: 1;
            overflow: hidden;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .row {
            backdrop-filter: blur(5px);
        }

        .card {
            border: none;
            border-radius: 0 20px 20px 0;
            box-shadow: none;
            background-color: rgba(255, 255, 255, 0.98);
            height: 100%;
        }

        .brand-section {
            color: white;
            padding: 3rem;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .brand-section::before {
            content: "";
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(231, 76, 60, 0.3), transparent 70%);
            z-index: -1;
            animation: pulse 8s infinite alternate;
        }
        
        @keyframes pulse {
            0% { opacity: 0.5; }
            100% { opacity: 0.8; }
        }

        .brand-section h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            letter-spacing: 1px;
        }
        
        .brand-section p {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
            letter-spacing: 1px;
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
            box-shadow: 0 4px 10px rgba(231, 76, 60, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(231, 76, 60, 0.4);
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

        .alert {
            border-radius: 8px;
            padding: 1rem;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border-left-color: var(--error-color);
        }

        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border-left-color: var(--success-color);
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
            box-shadow: 0 0 0 0.25rem rgba(231, 76, 60, 0.25);
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
            background-color: rgba(40, 167, 69, 0.1);
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
                margin-bottom: 0;
                padding: 2rem 1rem;
                border-radius: 20px 20px 0 0;
            }

            .brand-section h1 {
                font-size: 2rem;
            }
            
            .card {
                border-radius: 0 0 20px 20px;
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
            <div class="col-md-6 brand-section">
                <div class="d-flex flex-column h-100">
                    <div class="mb-auto">
                        <a href="../index.php">
                            <button type="button" class="btn btn-back">
                                <i class="bi bi-arrow-left me-1"></i> Back
                            </button>
                        </a>
                    </div>
                    <div class="mb-auto text-center text-md-start">
                        <h1 class="mb-3">⚽ FUTSAL BOOKING</h1>
                        <p style="margin-top: 20px; position: relative;">Admin Registration Portal</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body register-card">
                        <h3 class="mb-4 text-center fw-bold">ADMIN REGISTER</h3>
                        
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
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <div class="input-group">
                                    <input type="text" class="form-control <?php echo !empty($error_message) ? 'is-invalid' : ''; ?>" 
                                           placeholder="Enter admin username" name="username" required 
                                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" />
                                    <span class="input-icon"><i class="bi bi-person-gear"></i></span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <div class="input-group">
                                    <input type="email" class="form-control <?php echo !empty($error_message) ? 'is-invalid' : ''; ?>" 
                                           placeholder="Enter admin email" name="email" required 
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" />
                                    <span class="input-icon"><i class="bi bi-envelope"></i></span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control <?php echo !empty($error_message) ? 'is-invalid' : ''; ?>" 
                                           placeholder="Enter admin password" name="password" required />
                                    <span class="input-icon"><i class="bi bi-shield-lock"></i></span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control <?php echo !empty($error_message) ? 'is-invalid' : ''; ?>" 
                                           placeholder="Confirm admin password" name="confirm_password" required />
                                    <span class="input-icon"><i class="bi bi-shield-check"></i></span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <button class="btn btn-primary w-100" name="register" type="submit">
                                    <i class="bi bi-person-plus me-2"></i>Register as Admin
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <p class="mb-2">Sudah punya akun admin?</p>
                                <a href="login.php" class="text-decoration-none">
                                    <i class="bi bi-box-arrow-in-right me-1"></i>Login Admin
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous">
    </script>
</body>
</html>