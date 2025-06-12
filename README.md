# 🏟️ Sistem Booking Lapangan Futsal

Sistem manajemen booking lapangan futsal berbasis web yang dibuat menggunakan PHP, MySQL, dan Bootstrap. Sistem ini memungkinkan pengguna untuk melakukan booking lapangan futsal secara online dan admin untuk mengelola lapangan serta konfirmasi booking.

## ✨ Fitur Utama

### 👥 User Features
- Registrasi dan Login User
- Dashboard User dengan statistik booking
- Booking lapangan futsal dengan pemilihan tanggal dan waktu
- Riwayat booking dengan filter dan pencarian
- Manajemen profil user
- Pembatalan booking (jika masih pending)

### 🛠️ Admin Features
- Login Admin
- Dashboard Admin dengan statistik lengkap
- Kelola data lapangan (CRUD)
- Konfirmasi booking pending
- Daftar semua booking dengan filter
- Upload gambar lapangan

### 🎨 Design Features
- Responsive design dengan Bootstrap 5
- Modern UI dengan gradients dan animations
- Interactive components dengan SweetAlert2
- Mobile-friendly interface

## 🔧 Teknologi yang Digunakan

- **Backend**: PHP 7.4+ dengan MySQLi
- **Database**: MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework CSS**: Bootstrap 5.3.2
- **Icons**: Bootstrap Icons
- **Alerts**: SweetAlert2
- **Server**: Apache (XAMPP/WAMP/LAMP)

## 📋 Requirements

- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau MariaDB 10.3+
- Apache Web Server
- Extension PHP yang diperlukan:
  - mysqli
  - gd (untuk upload gambar)
  - session

## 🚀 Instalasi

### 1. Persiapan Environment

#### Menggunakan XAMPP (Windows/Mac/Linux)
1. Download dan install [XAMPP](https://www.apachefriends.org/)
2. Start Apache dan MySQL dari XAMPP Control Panel

#### Menggunakan WAMP (Windows)
1. Download dan install [WAMP](https://www.wampserver.com/)
2. Start semua services

#### Menggunakan LAMP (Linux)
```bash
sudo apt update
sudo apt install apache2 mysql-server php php-mysqli php-gd
sudo systemctl start apache2
sudo systemctl start mysql
```

### 2. Download dan Setup Project

1. **Clone atau Download project**
   ```bash
   cd /path/to/your/webserver/htdocs  # untuk XAMPP: C:\xampp\htdocs
   git clone [repository-url] futsal_booking
   # atau extract file zip ke folder futsal_booking
   ```

2. **Set permissions (Linux/Mac)**
   ```bash
   chmod -R 755 futsal_booking/
   chmod -R 777 futsal_booking/uploads/
   ```

### 3. Setup Database

1. **Buka phpMyAdmin** di browser: `http://localhost/phpmyadmin`

2. **Buat database baru** bernama `futsal_booking`

3. **Import database schema**:
   - Pilih database `futsal_booking`
   - Klik tab "SQL"
   - Copy dan paste isi file `database/futsal_booking.sql`
   - Klik "Go" untuk execute

   Atau jalankan script SQL berikut:
   ```sql
   CREATE DATABASE futsal_booking;
   USE futsal_booking;
   
   -- [Copy semua script dari database schema yang sudah dibuat]
   ```

### 4. Konfigurasi Database

Edit file `config/database.php` sesuai dengan konfigurasi database Anda:

```php
<?php
$hostname = "localhost";     // Host database
$username = "root";          // Username database
$password = "";              // Password database (kosong untuk XAMPP default)
$database = "futsal_booking"; // Nama database

$connection = new mysqli($hostname, $username, $password, $database);

if($connection->connect_error) {
    die('Connection error: '. $connection->connect_error);
}

$connection->set_charset("utf8mb4");
?>
```

### 5. Setup Folder Upload

Buat folder untuk upload gambar lapangan:
```bash
mkdir uploads
mkdir uploads/lapangan
chmod -R 777 uploads/  # Linux/Mac only
```

### 6. Akses Website

Buka browser dan akses: `http://localhost/futsal_booking`

## 👤 Akun Default

### Admin
- **Username**: admin
- **Password**: password
- **Email**: admin@futsal.com

### User Test
- **Username**: user1
- **Password**: 123456
- **Email**: user1@gmail.com

## 📁 Struktur Project

```
futsal_booking/
├── admin/                    # Admin panel
│   ├── dashboard.php        # Dashboard admin
│   ├── login.php           # Login admin
│   ├── register.php        # Register admin
│   ├── kelola_lapangan.php # Kelola lapangan
│   ├── konfirmasi_booking.php # Konfirmasi booking
│   └── daftar_booking.php  # Daftar semua booking
├── user/                    # User panel
│   ├── dashboard.php       # Dashboard user
│   ├── login.php          # Login user
│   ├── register.php       # Register user
│   ├── booking.php        # Form booking
│   ├── history.php        # Riwayat booking
│   └── profile.php        # Profil user
├── config/
│   └── database.php       # Konfigurasi database
├── uploads/
│   └── lapangan/         # Upload gambar lapangan
├── database/
│   └── futsal_booking.sql # Database schema
├── index.php             # Landing page
├── logout.php            # Logout handler
└── README.md             # Dokumentasi
```

## 🎯 Cara Penggunaan

### Untuk User:
1. Daftar akun baru atau login
2. Pilih lapangan di halaman utama
3. Klik "Booking Sekarang"
4. Isi form booking (tanggal, jam, durasi)
5. Submit dan tunggu konfirmasi admin
6. Cek status di dashboard atau riwayat

### Untuk Admin:
1. Login sebagai admin
2. Kelola lapangan (tambah/edit/hapus)
3. Konfirmasi booking pending
4. Monitor semua booking di daftar booking

## 🔧 Kustomisasi

### Mengubah Warna Theme
Edit CSS variables di setiap file:
```css
:root {
    --primary-color: #3498db;    /* Warna utama */
    --secondary-color: #2980b9;  /* Warna sekunder */
    --success-color: #27ae60;    /* Warna sukses */
    --danger-color: #e74c3c;     /* Warna bahaya */
}
```

### Menambah Tipe Lapangan
Edit dropdown di `admin/kelola_lapangan.php`:
```html
<option value="Tipe Baru">Tipe Baru</option>
```

### Mengubah Jam Operasional
Edit loop jam di `user/booking.php`:
```php
<?php for($i = 6; $i <= 22; $i++): ?> <!-- 06:00 - 22:00 -->
```

## 🐛 Troubleshooting

### Error: "Connection error"
- Pastikan MySQL service berjalan
- Cek konfigurasi database di `config/database.php`
- Pastikan database `futsal_booking` sudah dibuat

### Error: "Unknown column 'user_id'"
- Pastikan menggunakan nama kolom `id_user` (bukan `user_id`)
- Import ulang database schema

### Upload gambar gagal
- Pastikan folder `uploads/lapangan/` ada dan writable
- Cek ukuran file (max 2MB)
- Pastikan format JPG/JPEG/PNG

### Session tidak bekerja
- Pastikan `session_start()` dipanggil di setiap file
- Cek konfigurasi PHP session

## 📝 Development Notes

### Database Schema
- Menggunakan MySQLi untuk koneksi database
- Foreign keys untuk relasi antar tabel
- Index untuk optimasi query
- Views untuk laporan

### Security Features
- Password hashing dengan `password_hash()`
- Input sanitization dengan `mysqli_real_escape_string()`
- Prepared statements untuk query sensitive
- Session management

### Performance
- CSS dan JS minified
- Image optimization untuk upload
- Database indexing
- Lazy loading untuk gambar

## 🤝 Contributing

1. Fork repository
2. Buat branch baru (`git checkout -b feature/AmazingFeature`)
3. Commit perubahan (`git commit -m 'Add some AmazingFeature'`)
4. Push ke branch (`git push origin feature/AmazingFeature`)
5. Buat Pull Request

## 📄 License

Project ini dibuat untuk keperluan edukasi dan pembelajaran.

## 📞 Support

Jika mengalami masalah atau butuh bantuan:
1. Cek troubleshooting guide di atas
2. Pastikan semua requirements terpenuhi
3. Cek log error di browser console dan server error log

---

**Happy Coding! 🚀**