<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - Futsal Booking</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f4f4f4; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .welcome-card { background: white; padding: 40px; border-radius: 10px; text-align: center; margin-top: 50px; }
        .btn { padding: 15px 25px; margin: 10px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; display: inline-block; }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-card">
            <h1>Selamat Datang di Sistem Booking Futsal!</h1>
            <p style="margin: 20px 0;">Anda berhasil login sebagai user. Silakan pilih menu yang tersedia:</p>
            
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="index.php" class="btn">Lihat Lapangan</a>
            <a href="logout.php" class="btn">Logout</a>
        </div>
    </div>
</body>
</html>