<?php
session_start();
include 'config/database.php';

// Ambil data lapangan - MySQLi version
$query = "SELECT * FROM lapangan";
$result = $connection->query($query);
$lapangan = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $lapangan[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Futsal Booking - Premium Court Booking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0f172a;
            --secondary-color: #1e293b;
            --accent-color: #3b82f6;
            --accent-hover: #2563eb;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg,rgb(233, 236, 249) 0%,rgb(227, 212, 241) 100%);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .hero-section {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(30, 41, 59, 0.9)), 
                        url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="%23ffffff" fill-opacity="0.1" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,138.7C960,139,1056,117,1152,112C1248,107,1344,117,1392,122.7L1440,128L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 4rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #fff, #e2e8f0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInUp 1s ease-out;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            font-weight: 300;
            opacity: 0.9;
            margin-bottom: 2rem;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 1;
        }

        .floating-elements::before,
        .floating-elements::after {
            content: '⚽';
            position: absolute;
            font-size: 2rem;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .floating-elements::before {
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-elements::after {
            top: 60%;
            right: 10%;
            animation-delay: 3s;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .auth-section {
            background: var(--bg-primary);
            padding: 2.5rem;
            margin: -3rem auto 3rem;
            border-radius: 20px;
            text-align: center;
            box-shadow: var(--shadow-xl);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            z-index: 3;
            max-width: 800px;
        }

        .auth-section h2 {
            font-size: 1.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 1.5rem;
        }

        .auth-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-outline {
            background: transparent;
            color: var(--accent-color);
            border: 2px solid var(--accent-color);
        }

        .btn-outline:hover {
            background: var(--accent-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #dc2626);
            color: white;
        }

        .section-title {
            font-size: 2.25rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 3rem;
            color: black;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -0.5rem;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--accent-color), var(--success-color));
            border-radius: 2px;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .field-card {
            background: var(--bg-primary);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            border: 1px solid var(--border-color);
        }

        .field-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-xl);
        }

        .card-image {
            position: relative;
            overflow: hidden;
            height: 220px;
        }

        .card-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .field-card:hover .card-img {
            transform: scale(1.1);
        }

        .no-image {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            color: var(--text-secondary);
            font-style: italic;
            height: 100%;
        }

        .card-overlay {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-tersedia {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border: 2px solid var(--success-color);
        }

        .status-habis {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border: 2px solid var(--danger-color);
        }

        .card-content {
            padding: 2rem;
        }

        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .card-type {
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .price-tag {
            font-size: 1.75rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent-color), var(--success-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-top: 1rem;
        }

        .notification {
            position: fixed;
            top: 2rem;
            right: 2rem;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            transform: translateX(calc(100% + 2rem));
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 400px;
        }

        .notification.success {
            background: linear-gradient(135deg, var(--success-color), #059669);
        }

        .notification.show {
            transform: translateX(0);
        }

        .welcome-card {
            background: linear-gradient(135deg, var(--success-color), #059669);
            color: white;
        }

        .welcome-card h2 {
            color: white;
        }

        @keyframes fadeInUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            .auth-section {
                margin: -2rem auto 2rem;
                padding: 2rem;
            }
            
            .auth-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
            
            .cards-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
        }

        .loading-animation {
            animation: pulse 2s infinite;
        }
    </style>
</head>
<body>
    <?php
        if (isset($_GET['pesan'])) {
            if ($_GET['pesan'] == "logout") {
                echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            showNotification("Anda telah berhasil logout!", "success");
                        });
                    </script>';
            }
        }
    ?>

    <div class="hero-section">
        <div class="floating-elements"></div>
        <div class="hero-content">
            <h1 class="hero-title">Futsal Arena Premium</h1>
            <p class="hero-subtitle">Sistem booking lapangan futsal terdepan dengan pengalaman yang tak terlupakan</p>
        </div>
    </div>
    
    <div class="container">
        <div class="auth-section">
            <h2><i class="fas fa-futbol"></i> Bergabunglah dengan Komunitas Futsal Terbaik</h2>
            <p style="color: var(--text-secondary); margin-bottom: 2rem;">Untuk melakukan booking lapangan, silakan login atau daftar terlebih dahulu</p>
            <div class="auth-buttons">
                <a href="user/login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Login User
                </a>
                <a href="user/register.php" class="btn btn-outline">
                    <i class="fas fa-user-plus"></i>
                    Register User
                </a>
                <a href="admin/login.php" class="btn btn-outline">
                    <i class="fas fa-shield-alt"></i>
                    Login Admin
                </a>
            </div>
        </div>
        <h2 class="section-title">
            <i class="fas fa-map-marker-alt"></i>
            Lapangan Premium Tersedia
        </h2>
        
        <div class="cards-grid">
            <?php if (empty($lapangan)): ?>
                <div class="field-card loading-animation">
                    <div class="card-content" style="text-align: center; padding: 3rem;">
                        <i class="fas fa-search" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                        <h3>Belum ada lapangan tersedia</h3>
                        <p style="color: var(--text-secondary);">Lapangan sedang dalam proses penambahan</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($lapangan as $lap): ?>
                <div class="field-card">
                    <div class="card-image">
                        <?php if ($lap['gambar'] && file_exists('uploads/lapangan/' . $lap['gambar'])): ?>
                            <img src="uploads/lapangan/<?php echo htmlspecialchars($lap['gambar']); ?>" 
                                 alt="<?php echo htmlspecialchars($lap['nama']); ?>" 
                                 class="card-img">
                        <?php else: ?>
                            <div class="no-image">
                                <div style="text-align: center;">
                                    <i class="fas fa-image" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>Foto lapangan akan segera tersedia</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-overlay">
                            <span class="status-badge status-<?php echo $lap['status']; ?>">
                                <?php if ($lap['status'] == 'tersedia'): ?>
                                    <i class="fas fa-check-circle"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle"></i>
                                <?php endif; ?>
                                <?php echo ucfirst($lap['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card-content">
                        <h3 class="card-title"><?php echo htmlspecialchars($lap['nama']); ?></h3>
                        <div class="card-type">
                            <i class="fas fa-tag"></i>
                            <span><?php echo htmlspecialchars($lap['tipe']); ?></span>
                        </div>
                        <div class="price-tag">
                            <i class="fas fa-money-bill-wave"></i>
                            Rp <?php echo number_format($lap['harga'], 0, ',', '.'); ?> /jam
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas fa-check-circle"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 100);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    if (notification.parentNode) {
                        document.body.removeChild(notification);
                    }
                }, 400);
            }, 4000);
        }

        // Add smooth scrolling
        document.addEventListener('DOMContentLoaded', function() {
            // Animate cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                    }
                });
            }, observerOptions);

            document.querySelectorAll('.field-card').forEach(card => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>