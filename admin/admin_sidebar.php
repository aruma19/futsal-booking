<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div id="sidebar" class="text-white position-fixed h-100" style="width: 250px; top: 0; left: 0; z-index: 1030; background: linear-gradient(180deg, #e74c3c 0%, #c0392b 100%);">
    <div class="p-3">
        <button id="sidebar-toggle" class="btn neumorphic-toggle position-absolute d-flex align-items-center justify-content-center" style="top: 6.5px; right: -50px; z-index: 1031; width: 40px; height: 40px; background-color: #e74c3c;">
            <i class="bi bi-x toggle-icon text-white"></i>
        </button>

        <!-- Logo dan Title -->
        <div class="d-flex align-items-center mb-4" style="margin-left:15px; margin-top: 10px;">
            <i class="bi bi-shield-check me-2" style="font-size: 30px; color: white;"></i>
            <h4 class="text-white mb-0" style="font-family: 'Poppins', sans-serif; font-weight: bold; margin-left: -5px; font-size: 18px;">Futsal Admin</h4>
        </div>
        
        <p style="color: rgba(255,255,255,0.8); font-size: 12px; font-family: 'Poppins', sans-serif; font-weight: 600; margin-left: 15px; margin-top: 20px; text-transform: uppercase; letter-spacing: 1px;">Dashboard</p>
        
        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a href="dashboard.php" class="nav-link text-white <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
        </ul>
        
        <p style="color: rgba(255,255,255,0.8); font-size: 12px; font-weight: 600; margin-left: 15px; margin-top: 25px; text-transform: uppercase; letter-spacing: 1px;">Manajemen</p>

        <ul class="nav flex-column" style="margin-bottom: 50px;">
            <li class="nav-item mb-2">
                <a href="kelola_lapangan.php" class="nav-link text-white <?php echo ($current_page == 'kelola_lapangan.php') ? 'active' : ''; ?>">
                    <i class="bi bi-building me-2"></i> Kelola Lapangan
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="tambah_lapangan.php" class="nav-link text-white <?php echo ($current_page == 'tambah_lapangan.php') ? 'active' : ''; ?>">
                    <i class="bi bi-plus-circle me-2"></i> Tambah Lapangan
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="konfirmasi_booking.php" class="nav-link text-white <?php echo ($current_page == 'konfirmasi_booking.php') ? 'active' : ''; ?>">
                    <i class="bi bi-check-circle me-2"></i> Konfirmasi Booking
                </a>
            </li>
            <li class="nav-item mb-2">
                <a href="daftar_booking.php" class="nav-link text-white <?php echo ($current_page == 'daftar_booking.php') ? 'active' : ''; ?>">
                    <i class="bi bi-list-check me-2"></i> Daftar Booking
                </a>
            </li>
        </ul>

        <p style="color: rgba(255,255,255,0.8); font-size: 12px; font-family: 'Poppins', sans-serif; font-weight: 600; margin-left: 15px; text-transform: uppercase; letter-spacing: 1px;">Akun</p>

        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a href="../logout.php" class="nav-link" onclick="konfirmasiLogout(); return false;">
                    <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const body = document.body;
    let isSidebarOpen = true;

    // Initialize sidebar positioning
    function initializeSidebar() {
        if (window.innerWidth > 768) {
            body.style.marginLeft = '250px';
            sidebar.style.left = '0';
            isSidebarOpen = true;
            sidebarToggle.querySelector('i').classList.replace('bi-list', 'bi-x');
        } else {
            body.style.marginLeft = '0';
            sidebar.style.left = '-250px';
            isSidebarOpen = false;
            sidebarToggle.querySelector('i').classList.replace('bi-x', 'bi-list');
        }
    }

    // Call initialization on page load
    initializeSidebar();

    sidebarToggle.addEventListener('click', function() {
        if (isSidebarOpen) {
            // Close sidebar
            sidebar.style.left = '-250px';
            body.classList.add('sidebar-closed');
            body.style.marginLeft = '0';
            sidebarToggle.querySelector('i').classList.replace('bi-x', 'bi-list');
        } else {
            // Open sidebar
            sidebar.style.left = '0';
            body.classList.remove('sidebar-closed');
            body.style.marginLeft = '250px';
            sidebarToggle.querySelector('i').classList.replace('bi-list', 'bi-x');
        }
        isSidebarOpen = !isSidebarOpen;
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            body.style.marginLeft = '0';
            if (isSidebarOpen) {
                sidebar.style.left = '0';
            } else {
                sidebar.style.left = '-250px';
            }
        } else {
            if (isSidebarOpen) {
                body.style.marginLeft = '250px';
                sidebar.style.left = '0';
            }
        }
    });
});
</script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

    #sidebar {
        transition: left 0.4s ease-in-out;
        will-change: left;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        font-family: 'Poppins', sans-serif;
    }

    body {
        transition: margin-left 0.4s ease-in-out;
        margin-left: 250px;
    }

    body.sidebar-closed {
        margin-left: 0;
    }

    .nav-link {
        padding: 12px 15px;
        border-radius: 10px;
        margin-bottom: 5px;
        transition: all 0.3s ease;
        font-weight: 500;
        font-size: 14px;
        color: rgba(255, 255, 255, 0.9) !important;
    }

    .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: white !important;
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .nav-link.active {
        background: linear-gradient(45deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
        color: white !important;
        font-weight: 600;
        border-left: 4px solid white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    .nav-link.active i {
        color: white;
    }

    .nav-link i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .neumorphic-toggle {
        background: linear-gradient(145deg, #ec7063, #c0392b);
        border: none;
        border-radius: 50%;
        box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.3), -2px -2px 8px rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease-in-out;
        min-width: 40px;
        min-height: 40px;
        width: 40px !important;
        height: 40px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 0 !important;
    }

    .neumorphic-toggle:hover {
        transform: scale(1.1);
        box-shadow: 6px 6px 15px rgba(0, 0, 0, 0.4), -3px -3px 10px rgba(255, 255, 255, 0.2);
        background: linear-gradient(145deg, #e74c3c, #c0392b);
    }

    .neumorphic-toggle:active {
        animation: bounce 0.3s ease-in-out;
        transform: scale(0.95);
    }

    .neumorphic-toggle:focus {
        outline: none;
        box-shadow: 4px 4px 10px rgba(0, 0, 0, 0.3), -2px -2px 8px rgba(255, 255, 255, 0.1), 0 0 0 3px rgba(255, 255, 255, 0.3);
    }

    .neumorphic-toggle i {
        font-size: 16px !important;
        color: white !important;
        transition: transform 0.2s ease;
    }

    @keyframes bounce {
        0%   { transform: scale(1); }
        50%  { transform: scale(0.9); }
        100% { transform: scale(1); }
    }

    .text-danger {
        color: #ff6b6b !important;
    }

    .text-danger:hover {
        color: #ff5252 !important;
        background-color: rgba(255, 107, 107, 0.1) !important;
    }

    /* Responsive */
    @media (max-width: 768px) {
        body {
            margin-left: 0 !important;
        }
        
        #sidebar {
            left: -250px;
        }
        
        .neumorphic-toggle {
            right: -45px;
        }
    }

    /* Scrollbar styling for sidebar */
    #sidebar::-webkit-scrollbar {
        width: 6px;
    }

    #sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 3px;
    }

    #sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }

    #sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }
</style>
<script>
    function konfirmasiLogout() {
        if (confirm("Apakah Anda yakin ingin logout?")) {
            window.location.href = "../logout.php";
        }
    }
</script>