# Panduan Mengatasi Error HTTP 500 pada search.php

## Masalah
Website temankosan.project2ks2.my.id menampilkan "HTTP ERROR 500" ketika mengakses halaman search.php.

## Penyebab Umum HTTP 500 Error

### 1. **Masalah Koneksi Database**
Error ini paling sering disebabkan oleh masalah koneksi database. Berdasarkan analisis kode, berikut adalah konfigurasi database:

```php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'projec15_temankosan');
define('DB_USER', 'projec15_root');
define('DB_PASS', '@kaesquare123');
```

**Kemungkinan masalah:**
- Database server tidak berjalan
- Kredensial database salah
- Database `projec15_temankosan` tidak ada
- User `projec15_root` tidak memiliki akses

### 2. **File atau Folder Tidak Ada**
search.php memerlukan beberapa file:
- `config/database.php`
- `models/Kos.php`
- `includes/functions.php`
- `models/BaseModel.php`

### 3. **Tabel Database Tidak Ada**
Fungsi `get_search_kos()` mengakses tabel:
- `kos`
- `kos_facilities`
- `facilities`
- `locations`
- `kos_images`

### 4. **Error PHP/Syntax**
Kemungkinan ada syntax error atau masalah PHP di dalam file.

## Langkah Troubleshooting

### Step 1: Jalankan Debug Script
1. Upload file `debug.php` ke root folder website
2. Akses `https://temankosan.project2ks2.my.id/debug.php`
3. Lihat output untuk mengidentifikasi masalah spesifik

### Step 2: Periksa Log Error
```bash
# Cek error log server
tail -f /var/log/apache2/error.log
# atau
tail -f /var/log/nginx/error.log
```

### Step 3: Verifikasi Database
```sql
-- Login ke MySQL dan cek database
SHOW DATABASES;
USE projec15_temankosan;
SHOW TABLES;

-- Cek tabel yang diperlukan
DESCRIBE kos;
DESCRIBE locations;
DESCRIBE facilities;
DESCRIBE kos_facilities;
DESCRIBE kos_images;
```

### Step 4: Perbaiki Koneksi Database
Jika database bermasalah, update file `config/database.php`:

```php
<?php
// Sesuaikan dengan setting hosting Anda
define('DB_HOST', 'localhost'); // atau IP hosting
define('DB_NAME', 'nama_database_yang_benar');
define('DB_USER', 'username_database');
define('DB_PASS', 'password_database');
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');
```

### Step 5: Buat Tabel Database
Jika tabel belum ada, jalankan script setup:

```php
// Akses setup_database.php jika ada
https://temankosan.project2ks2.my.id/setup_database.php
```

## Solusi Cepat - Search.php Minimal

Jika masalah persisten, buat versi minimal search.php:

```php
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test koneksi sederhana
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=projec15_temankosan;charset=utf8mb4",
        "projec15_root", 
        "@kaesquare123",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "Database connection successful";
    
    // Query sederhana
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM kos");
    $result = $stmt->fetch();
    echo "<br>Total kos: " . $result['total'];
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
```

## Periksa Hosting Configuration

### 1. PHP Version
Pastikan hosting mendukung PHP 7.4+ dengan extensions:
- PDO
- mysqli
- mbstring

### 2. File Permissions
Pastikan file permissions benar:
```bash
chmod 644 *.php
chmod 755 config/
chmod 755 models/
chmod 755 includes/
```

### 3. .htaccess
Cek apakah ada .htaccess yang menyebabkan masalah:
```apache
# Matikan untuk test
# RewriteEngine On
```

## Error yang Mungkin Ditemukan

### 1. "Database connection failed"
**Solusi:** Periksa kredensial database di cPanel hosting

### 2. "Table doesn't exist"
**Solusi:** Import database atau jalankan setup script

### 3. "Class not found"
**Solusi:** Periksa autoloader atau require_once path

### 4. "Memory limit exceeded"
**Solusi:** Optimasi query atau increase memory limit

## Testing Checklist

- [ ] Database connection berhasil
- [ ] Semua file required ada
- [ ] Tabel database lengkap
- [ ] PHP error reporting enabled
- [ ] File permissions benar
- [ ] Debug script berjalan tanpa error

## Contact Support
Jika masalah masih berlanjut:
1. Screenshot hasil debug.php
2. Copy paste error message lengkap
3. Informasi hosting provider dan PHP version
4. Kontak support hosting atau developer

## Next Steps
Setelah masalah teridentifikasi:
1. Fix masalah yang ditemukan
2. Test akses search.php
3. Hapus debug.php dari production
4. Monitoring untuk error lainnya

**Note:** Jangan lupa backup database dan files sebelum melakukan perubahan!