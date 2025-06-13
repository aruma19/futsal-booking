<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$user_id = $_SESSION['user_id'];

if ($booking_id <= 0) {
    header('Location: history.php');
    exit();
}

// Get booking details with all related information
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
        p.payment_method,
        p.status as payment_status,
        p.created_at as payment_date,
        r.refund_amount,
        r.penalty_amount,
        r.reason as refund_reason
    FROM booking b 
    JOIN lapangan l ON b.id_lapangan = l.id 
    JOIN users u ON b.id_user = u.id
    LEFT JOIN payments p ON b.id = p.booking_id
    LEFT JOIN refunds r ON b.id = r.booking_id
    WHERE b.id = $booking_id AND b.id_user = $user_id
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

// Set headers for PDF download
header('Content-Type: text/html; charset=utf-8');

// Check if user wants to download as PDF
$download_pdf = isset($_GET['download']) && $_GET['download'] == 'pdf';

if ($download_pdf) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="receipt_booking_' . $booking_id . '.pdf"');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk Pembayaran - Booking #<?php echo $booking_id; ?></title>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
            line-height: 1.6;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #3498db;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #3498db;
            margin: 0;
            font-size: 2em;
            font-weight: 700;
        }

        .header h2 {
            color: #666;
            margin: 5px 0;
            font-size: 1.2em;
            font-weight: 400;
        }

        .company-info {
            text-align: center;
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .receipt-info div {
            flex: 1;
            min-width: 200px;
        }

        .section {
            margin-bottom: 25px;
        }

        .section-title {
            background: #f8f9fa;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
            font-weight: 600;
            color: #3498db;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .info-table th,
        .info-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .info-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #495057;
            width: 40%;
        }

        .amount-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .amount-table th,
        .amount-table td {
            padding: 12px 15px;
            border: 1px solid #ddd;
            text-align: right;
        }

        .amount-table th {
            background: #3498db;
            color: white;
            text-align: left;
        }

        .amount-table .total-row {
            background: #f8f9fa;
            font-weight: 600;
            color: #3498db;
            font-size: 1.1em;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 15px;
            color: white;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-pending { background: #f39c12; }
        .status-aktif { background: #27ae60; }
        .status-selesai { background: #95a5a6; }
        .status-batal { background: #e74c3c; }
        .status-success { background: #27ae60; }
        .status-dp { background: #f39c12; }
        .status-lunas { background: #27ae60; }
        .status-refund { background: #9b59b6; }

        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            color: #666;
            font-size: 0.9em;
        }

        .qr-code {
            float: right;
            width: 100px;
            height: 100px;
            border: 1px solid #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            color: #999;
        }

        .no-print {
            margin-top: 30px;
            text-align: center;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 0 5px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 500;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #2980b9;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #219a52;
        }

        /* Print Styles */
        @media print {
            body {
                padding: 0;
                background: white;
            }
            
            .receipt-container {
                border: none;
                box-shadow: none;
                padding: 20px;
            }
            
            .no-print {
                display: none;
            }
            
            .receipt-info {
                display: block;
            }
            
            .receipt-info div {
                margin-bottom: 15px;
            }
        }

        @media (max-width: 768px) {
            .receipt-container {
                padding: 15px;
            }
            
            .receipt-info {
                flex-direction: column;
            }
            
            .info-table th,
            .info-table td,
            .amount-table th,
            .amount-table td {
                padding: 8px 10px;
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <!-- Header -->
        <div class="header">
            <h1>FUTSAL BOOKING CENTER</h1>
            <h2>STRUK PEMBAYARAN</h2>
            <div class="company-info">
                Jl. Futsal Center No. 123, Depok, Yogyakarta<br>
                Telp: 0274-123456 | Email: admin@futsalbooking.com
            </div>
        </div>

        <!-- Receipt Info -->
        <div class="receipt-info">
            <div>
                <strong>No. Transaksi:</strong> #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?><br>
                <strong>Tanggal Cetak:</strong> <?php echo date('d/m/Y H:i:s'); ?>
            </div>
            <div>
                <strong>Kasir:</strong> System<br>
                <strong>Status:</strong> 
                <span class="status-badge status-<?php echo $booking['status']; ?>">
                    <?php echo ucfirst($booking['status']); ?>
                </span>
            </div>
            <div class="qr-code">
                QR CODE<br>
                #<?php echo $booking_id; ?>
            </div>
        </div>

        <!-- Customer Information -->
        <div class="section">
            <div class="section-title">INFORMASI PELANGGAN</div>
            <table class="info-table">
                <tr>
                    <th>Nama Lengkap</th>
                    <td><?php echo htmlspecialchars($booking['full_name']); ?></td>
                </tr>
                <tr>
                    <th>Email</th>
                    <td><?php echo htmlspecialchars($booking['email']); ?></td>
                </tr>
                <tr>
                    <th>Telepon</th>
                    <td><?php echo htmlspecialchars($booking['phone']); ?></td>
                </tr>
                <tr>
                    <th>Kontak Booking</th>
                    <td><?php echo htmlspecialchars($booking['kontak']); ?></td>
                </tr>
            </table>
        </div>

        <!-- Booking Details -->
        <div class="section">
            <div class="section-title">DETAIL BOOKING</div>
            <table class="info-table">
                <tr>
                    <th>Lapangan</th>
                    <td><?php echo htmlspecialchars($booking['nama_lapangan']); ?></td>
                </tr>
                <tr>
                    <th>Tipe Lapangan</th>
                    <td><?php echo htmlspecialchars($booking['tipe']); ?></td>
                </tr>
                <tr>
                    <th>Tanggal Main</th>
                    <td><?php echo date('l, d F Y', strtotime($booking['tanggal'])); ?></td>
                </tr>
                <tr>
                    <th>Waktu</th>
                    <td><?php echo date('H:i', strtotime($booking['jam'])); ?> - <?php echo date('H:i', strtotime($booking['jam'] . ' + ' . $booking['lama_sewa'] . ' hours')); ?> WIB</td>
                </tr>
                <tr>
                    <th>Durasi</th>
                    <td><?php echo $booking['lama_sewa']; ?> jam</td>
                </tr>
                <tr>
                    <th>Harga per Jam</th>
                    <td>Rp <?php echo number_format($booking['harga'], 0, ',', '.'); ?></td>
                </tr>
                <tr>
                    <th>Tanggal Booking</th>
                    <td><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></td>
                </tr>
            </table>
        </div>

        <!-- Payment Details -->
        <div class="section">
            <div class="section-title">RINCIAN PEMBAYARAN</div>
            <table class="amount-table">
                <thead>
                    <tr>
                        <th>Keterangan</th>
                        <th style="text-align: right;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Total Sewa (<?php echo $booking['lama_sewa']; ?> jam × Rp <?php echo number_format($booking['harga'], 0, ',', '.'); ?>)</td>
                        <td>Rp <?php echo number_format($total_harga, 0, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <td>
                            Pembayaran 
                            <?php if ($booking['status_pembayaran'] == 'dp'): ?>
                                <span class="status-badge status-dp">DP (50%)</span>
                            <?php elseif ($booking['status_pembayaran'] == 'lunas'): ?>
                                <span class="status-badge status-lunas">LUNAS</span>
                            <?php elseif ($booking['status_pembayaran'] == 'refund'): ?>
                                <span class="status-badge status-refund">REFUND</span>
                            <?php endif; ?>
                            <br>
                            <small>
                                <?php if ($booking['payment_method']): ?>
                                Metode: <?php echo ucfirst($booking['payment_method']); ?>
                                <?php endif; ?>
                                <?php if ($booking['payment_date']): ?>
                                <br>Tanggal: <?php echo date('d/m/Y H:i', strtotime($booking['payment_date'])); ?>
                                <?php endif; ?>
                            </small>
                        </td>
                        <td>Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></td>
                    </tr>
                    
                    <?php if ($booking['total_pinalti'] > 0): ?>
                    <tr>
                        <td>Pinalti Pembatalan</td>
                        <td style="color: #e74c3c;">-Rp <?php echo number_format($booking['total_pinalti'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($booking['refund_amount'] > 0): ?>
                    <tr>
                        <td>
                            Jumlah Refund
                            <?php if ($booking['refund_reason']): ?>
                            <br><small>Alasan: <?php echo htmlspecialchars($booking['refund_reason']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="color: #9b59b6;">Rp <?php echo number_format($booking['refund_amount'], 0, ',', '.'); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php if ($sisa_pembayaran > 0): ?>
                    <tr>
                        <td><strong>Sisa Pembayaran</strong></td>
                        <td style="color: #f39c12;"><strong>Rp <?php echo number_format($sisa_pembayaran, 0, ',', '.'); ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    
                    <tr class="total-row">
                        <td><strong>TOTAL YANG TELAH DIBAYAR</strong></td>
                        <td><strong>Rp <?php echo number_format($booking['total_dibayar'], 0, ',', '.'); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Payment Status -->
        <?php if ($booking['payment_status']): ?>
        <div class="section">
            <div class="section-title">STATUS PEMBAYARAN</div>
            <table class="info-table">
                <tr>
                    <th>Status Transaksi</th>
                    <td>
                        <span class="status-badge status-<?php echo $booking['payment_status']; ?>">
                            <?php echo ucfirst($booking['payment_status']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Status Booking</th>
                    <td>
                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </td>
                </tr>
                <tr>
                    <th>Status Pembayaran</th>
                    <td>
                        <span class="status-badge status-<?php echo $booking['status_pembayaran']; ?>">
                            <?php 
                            echo ($booking['status_pembayaran'] == 'dp') ? 'DP (Belum Lunas)' : 
                                 (($booking['status_pembayaran'] == 'lunas') ? 'Lunas' : 
                                 ucfirst($booking['status_pembayaran'])); 
                            ?>
                        </span>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <!-- Terms and Conditions -->
        <div class="section">
            <div class="section-title">SYARAT DAN KETENTUAN</div>
            <div style="font-size: 0.9em; line-height: 1.6;">
                <ol>
                    <li>Struk ini adalah bukti pembayaran yang sah</li>
                    <li>Harap tunjukkan struk ini saat datang ke lapangan</li>
                    <li>Pembatalan booking dapat dilakukan maksimal 24 jam sebelum waktu bermain</li>
                    <li>Pembatalan kurang dari 24 jam akan dikenakan pinalti sesuai kebijakan</li>
                    <li>Waktu toleransi keterlambatan maksimal 15 menit</li>
                    <li>Fasilitas yang rusak akibat kelalaian pengguna akan dikenakan biaya ganti rugi</li>
                    <li>Untuk informasi lebih lanjut hubungi 0274-123456</li>
                </ol>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>TERIMA KASIH TELAH MENGGUNAKAN LAYANAN KAMI</strong></p>
            <p>Futsal Booking Center - Tempat Bermain Futsal Terbaik di Yogyakarta</p>
            <p style="font-size: 0.8em; margin-top: 15px;">
                Struk ini dicetak secara otomatis pada <?php echo date('d/m/Y H:i:s'); ?><br>
                Untuk verifikasi keaslian struk, silakan scan QR Code di atas
            </p>
        </div>

        <!-- Action Buttons (Hidden when printing) -->
        <?php if (!$download_pdf): ?>
        <div class="no-print">
            <a href="javascript:window.print()" class="btn">
                🖨️ Cetak Struk
            </a>
            <a href="history.php" class="btn">
                ← Kembali ke Riwayat
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$download_pdf): ?>
    <script>
        // Auto-focus for printing
        window.addEventListener('load', function() {
            // Add print functionality
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            });
        });
    </script>
    <?php endif; ?>
</body>
</html>