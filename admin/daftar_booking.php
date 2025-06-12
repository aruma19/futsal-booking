<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php?pesan=belum_login');
    exit();
}

include('../config/database.php');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Filter
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$date_filter = isset($_GET['date']) ? sanitize_input($_GET['date']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Build query with filters
$whereConditions = [];

if ($status_filter && $status_filter != 'all') {
    $whereConditions[] = "b.status = '$status_filter'";
}

if ($date_filter) {
    $whereConditions[] = "b.tanggal = '$date_filter'";
}

if ($search) {
    $whereConditions[] = "(u.username LIKE '%$search%' OR u.full_name LIKE '%$search%' OR l.nama LIKE '%$search%')";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total records for pagination
$countQuery = "
    SELECT COUNT(*) as total 
    FROM booking b 
    JOIN users u ON b.id_user = u.id 
    JOIN lapangan l ON b.id_lapangan = l.id 
    $whereClause
";
$countResult = mysqli_query($connection, $countQuery);
$totalRecords = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRecords / $limit);

// Get booking data with pagination
$bookingQuery = "
    SELECT b.*, u.username, u.full_name, u.email, u.phone, l.nama as nama_lapangan, l.harga, l.tipe
    FROM booking b 
    JOIN users u ON b.id_user = u.id 
    JOIN lapangan l ON b.id_lapangan = l.id 
    $whereClause
    ORDER BY b.created_at DESC
    LIMIT $limit OFFSET $offset
";

$bookingResult = mysqli_query($connection, $bookingQuery);
$all_booking = [];
while($row = mysqli_fetch_assoc($bookingResult)) {
    $all_booking[] = $row;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Booking - Admin Futsal Booking</title>

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

        .stat-item.pending {
            background: linear-gradient(45deg, var(--warning-color), #e67e22);
        }

        .stat-item.aktif {
            background: linear-gradient(45deg, var(--success-color), #2ecc71);
        }

        .stat-item.selesai {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
        }

        .stat-item.batal {
            background: linear-gradient(45deg, var(--danger-color), #c0392b);
        }

        .stat-item.revenue {
            background: linear-gradient(45deg, #9b59b6, #8e44ad);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .filter-section {
            background: rgba(255, 255, 255, 0.95);
            padding: 25px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            box-shadow: var(--card-shadow);
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
            font-size: 0.9rem;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
            font-size: 0.9rem;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .status {
            padding: 6px 12px;
            border-radius: 15px;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
            display: inline-block;
        }

        .pending { background: linear-gradient(45deg, var(--warning-color), #e67e22); }
        .aktif { background: linear-gradient(45deg, var(--success-color), #2ecc71); }
        .batal { background: linear-gradient(45deg, var(--danger-color), #c0392b); }
        .selesai { background: linear-gradient(45deg, #95a5a6, #7f8c8d); }

        .btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--info-color), #5dade2);
        }

        .btn-secondary {
            background: linear-gradient(45deg, #95a5a6, #7f8c8d);
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .pagination {
            justify-content: center;
            margin-top: 30px;
        }

        .page-link {
            color: var(--primary-color);
            border-radius: 8px;
            margin: 0 2px;
            border: none;
        }

        .page-link:hover {
            background-color: var(--primary-color);
            color: white;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .export-section {
            text-align: right;
            margin-bottom: 20px;
        }

        .user-info {
            font-size: 0.8rem;
            color: #666;
        }

        .revenue-highlight {
            font-weight: bold;
            color: var(--success-color);
        }

        /* Print Styles */
        @media print {
            body * {
                visibility: hidden;
            }
            
            #printable-content,
            #printable-content * {
                visibility: visible;
            }
            
            #printable-content {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                background: white;
                padding: 20px;
            }
            
            .print-header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #333;
                padding-bottom: 15px;
            }
            
            .print-header h2 {
                color: #333;
                margin: 0;
                font-size: 24px;
            }
            
            .print-info {
                font-size: 14px;
                color: #666;
                margin-top: 10px;
            }
            
            .table {
                border-collapse: collapse;
                width: 100%;
                margin-top: 20px;
            }
            
            .table th,
            .table td {
                border: 1px solid #333;
                padding: 8px;
                text-align: left;
                font-size: 12px;
            }
            
            .table th {
                background-color: #f5f5f5;
                font-weight: bold;
            }
            
            .status {
                background: none !important;
                color: #333 !important;
                border: 1px solid #333;
                padding: 2px 6px;
                border-radius: 3px;
            }
            
            .user-info {
                font-size: 10px;
            }
            
            .revenue-highlight {
                color: #333;
            }
            
            .btn {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .table th, .table td {
                padding: 8px 6px;
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
            <h1><i class="bi bi-list-check me-2"></i>Daftar Semua Booking</h1>
            <p class="mb-0">Monitor dan kelola semua booking lapangan futsal</p>
        </div>

        <!-- Filter Section -->
        <div class="filter-section fade-in">
            <h4 class="mb-3"><i class="bi bi-funnel me-2"></i>Filter & Pencarian</h4>
            <form method="GET" action="">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="all" <?php echo ($status_filter == 'all' || $status_filter == '') ? 'selected' : ''; ?>>Semua Status</option>
                            <option value="pending" <?php echo ($status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="aktif" <?php echo ($status_filter == 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="selesai" <?php echo ($status_filter == 'selesai') ? 'selected' : ''; ?>>Selesai</option>
                            <option value="batal" <?php echo ($status_filter == 'batal') ? 'selected' : ''; ?>>Dibatal</option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Tanggal</label>
                        <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Cari</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Nama user, lapangan..." value="<?php echo $search; ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-search me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <a href="daftar_booking.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Table Section -->
        <div class="table-section fade-in">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3>
                    <i class="bi bi-table me-2"></i>Daftar Booking 
                    <small class="text-muted">(<?php echo $totalRecords; ?> total)</small>
                </h3>
                <div class="export-section">
                    <button onclick="printBookingList()" class="btn btn-secondary">
                        <i class="bi bi-printer me-1"></i>Print
                    </button>
                </div>
            </div>
            
            <div class="table-responsive">
                <?php if (empty($all_booking)): ?>
                <div class="no-data">
                    <i class="bi bi-inbox"></i>
                    <h5>Tidak ada data booking</h5>
                    <p>Belum ada booking dengan kriteria yang dipilih.</p>
                </div>
                <?php else: ?>
                <table class="table table-hover" id="booking-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Lapangan</th>
                            <th>Tanggal</th>
                            <th>Waktu</th>
                            <th>Durasi</th>
                            <th>Kontak</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Dibuat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_booking as $booking): ?>
                        <tr>
                            <td><strong>#<?php echo $booking['id']; ?></strong></td>
                            <td>
                                <div><strong><?php echo htmlspecialchars($booking['full_name']); ?></strong></div>
                                <div class="user-info">@<?php echo htmlspecialchars($booking['username']); ?></div>
                                <div class="user-info"><?php echo htmlspecialchars($booking['email']); ?></div>
                            </td>
                            <td>
                                <div><strong><?php echo htmlspecialchars($booking['nama_lapangan']); ?></strong></div>
                                <div class="user-info"><?php echo htmlspecialchars($booking['tipe']); ?></div>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($booking['tanggal'])); ?></td>
                            <td><?php echo date('H:i', strtotime($booking['jam'])); ?> WIB</td>
                            <td><?php echo $booking['lama_sewa']; ?> jam</td>
                            <td><?php echo htmlspecialchars($booking['kontak']); ?></td>
                            <td>
                                <span class="revenue-highlight">
                                    Rp <?php echo number_format($booking['harga'] * $booking['lama_sewa'], 0, ',', '.'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status <?php echo $booking['status']; ?>">
                                    <?php 
                                    $status_icons = [
                                        'pending' => 'clock',
                                        'aktif' => 'check-circle',
                                        'batal' => 'x-circle',
                                        'selesai' => 'check2-circle'
                                    ];
                                    $icon = $status_icons[$booking['status']] ?? 'circle';
                                    echo '<i class="bi bi-' . $icon . ' me-1"></i>' . ucfirst($booking['status']); 
                                    ?>
                                </span>
                            </td>
                            <td>
                                <div><?php echo date('d/m/Y', strtotime($booking['created_at'])); ?></div>
                                <div class="user-info"><?php echo date('H:i', strtotime($booking['created_at'])); ?></div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo $search; ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        for ($i = $start; $i <= $end; $i++): 
                        ?>
                        <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo $search; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo $search; ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hidden Printable Content -->
    <div id="printable-content" style="display: none;">
        <div class="print-header">
            <h2>Laporan Daftar Booking Futsal</h2>
            <div class="print-info">
                <p>Tanggal Cetak: <?php echo date('d/m/Y H:i:s'); ?></p>
                <?php if ($status_filter && $status_filter != 'all'): ?>
                <p>Filter Status: <?php echo ucfirst($status_filter); ?></p>
                <?php endif; ?>
                <?php if ($date_filter): ?>
                <p>Filter Tanggal: <?php echo date('d/m/Y', strtotime($date_filter)); ?></p>
                <?php endif; ?>
                <?php if ($search): ?>
                <p>Pencarian: "<?php echo htmlspecialchars($search); ?>"</p>
                <?php endif; ?>
                <p>Total Data: <?php echo $totalRecords; ?> booking</p>
            </div>
        </div>
        
        <?php if (!empty($all_booking)): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Lapangan</th>
                    <th>Tanggal</th>
                    <th>Waktu</th>
                    <th>Durasi</th>
                    <th>Kontak</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Dibuat</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_booking as $booking): ?>
                <tr>
                    <td>#<?php echo $booking['id']; ?></td>
                    <td>
                        <div><?php echo htmlspecialchars($booking['full_name']); ?></div>
                        <div class="user-info">@<?php echo htmlspecialchars($booking['username']); ?></div>
                        <div class="user-info"><?php echo htmlspecialchars($booking['email']); ?></div>
                    </td>
                    <td>
                        <div><?php echo htmlspecialchars($booking['nama_lapangan']); ?></div>
                        <div class="user-info"><?php echo htmlspecialchars($booking['tipe']); ?></div>
                    </td>
                    <td><?php echo date('d/m/Y', strtotime($booking['tanggal'])); ?></td>
                    <td><?php echo date('H:i', strtotime($booking['jam'])); ?> WIB</td>
                    <td><?php echo $booking['lama_sewa']; ?> jam</td>
                    <td><?php echo htmlspecialchars($booking['kontak']); ?></td>
                    <td>
                        <span class="revenue-highlight">
                            Rp <?php echo number_format($booking['harga'] * $booking['lama_sewa'], 0, ',', '.'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="status <?php echo $booking['status']; ?>">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div><?php echo date('d/m/Y', strtotime($booking['created_at'])); ?></div>
                        <div class="user-info"><?php echo date('H:i', strtotime($booking['created_at'])); ?></div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; margin-top: 40px;">Tidak ada data booking untuk dicetak.</p>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Enhanced Print Function - Only prints booking list
        function printBookingList() {
            // Show the printable content
            const printContent = document.getElementById('printable-content');
            printContent.style.display = 'block';
            
            // Print the page
            window.print();
            
            // Hide the printable content again after printing
            setTimeout(() => {
                printContent.style.display = 'none';
            }, 1000);
        }

        // Auto refresh data every 60 seconds
        setInterval(function() {
            // Only refresh if we're on the first page and no filters are applied
            const urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.has('page') && !urlParams.has('status') && !urlParams.has('date') && !urlParams.has('search')) {
                location.reload();
            }
        }, 60000);

        // Enhance table with better responsive behavior
        window.addEventListener('resize', function() {
            const table = document.querySelector('.table-responsive');
            if (window.innerWidth < 768) {
                table.style.fontSize = '0.8rem';
            } else {
                table.style.fontSize = '0.9rem';
            }
        });
    </script>
</body>
</html>