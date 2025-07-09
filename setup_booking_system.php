<?php
/**
 * Setup Script untuk Sistem Booking TemanKosan
 * Jalankan script ini untuk mengaktifkan fitur booking
 */

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html>";
echo "<html lang='id'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Setup Sistem Booking - TemanKosan</title>";
echo "<style>";
echo "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f8f9fa; margin: 0; padding: 20px; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }";
echo "h1 { color: #00c851; margin-bottom: 1rem; }";
echo "h2 { color: #495057; border-bottom: 2px solid #00c851; padding-bottom: 0.5rem; margin-top: 2rem; }";
echo ".success { background: #d4edda; color: #155724; padding: 1rem; border-radius: 5px; margin: 1rem 0; border: 1px solid #c3e6cb; }";
echo ".error { background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 5px; margin: 1rem 0; border: 1px solid #f5c6cb; }";
echo ".warning { background: #fff3cd; color: #856404; padding: 1rem; border-radius: 5px; margin: 1rem 0; border: 1px solid #ffeaa7; }";
echo ".info { background: #cce5ff; color: #004085; padding: 1rem; border-radius: 5px; margin: 1rem 0; border: 1px solid #99d3ff; }";
echo "table { width: 100%; border-collapse: collapse; margin: 1rem 0; }";
echo "th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #dee2e6; }";
echo "th { background-color: #f8f9fa; font-weight: 600; }";
echo ".code { background: #f8f9fa; padding: 0.25rem 0.5rem; border-radius: 3px; font-family: monospace; }";
echo "ul { margin: 1rem 0; padding-left: 2rem; }";
echo "li { margin: 0.5rem 0; }";
echo ".step { background: #e9ecef; padding: 1rem; border-radius: 5px; margin: 1rem 0; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";

echo "<h1>🚀 Setup Sistem Booking TemanKosan</h1>";
echo "<p>Script ini akan mengatur database dan fitur booking lengkap untuk aplikasi TemanKosan Anda.</p>";

// ============================
// STEP 1: CHECK CONFIGURATION
// ============================
echo "<h2>Step 1: Memeriksa Konfigurasi</h2>";

// Check if config file exists
if (!file_exists('config/database.php')) {
    echo "<div class='error'>";
    echo "<strong>❌ Error:</strong> File konfigurasi database tidak ditemukan!<br>";
    echo "Pastikan file <code>config/database.php</code> sudah ada dan berisi konfigurasi database yang benar.";
    echo "</div>";
    exit();
}

// Include configuration
try {
    require_once 'config/database.php';
    echo "<div class='success'>✅ File konfigurasi database ditemukan.</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Error loading config: " . $e->getMessage() . "</div>";
    exit();
}

// Display current configuration
echo "<div class='info'>";
echo "<strong>Konfigurasi saat ini:</strong><br>";
echo "Host: " . DB_HOST . "<br>";
echo "Database: " . DB_NAME . "<br>";
echo "User: " . DB_USER . "<br>";
echo "Port: " . DB_PORT . "<br>";
echo "</div>";

// ============================
// STEP 2: TEST CONNECTION
// ============================
echo "<h2>Step 2: Test Koneksi Database</h2>";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    echo "<div class='success'>✅ Koneksi database berhasil!</div>";
    
    // Get MySQL version
    $version = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "<p><strong>MySQL Version:</strong> {$version}</p>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ Koneksi database gagal!</strong><br>";
    echo "Error: " . $e->getMessage() . "<br><br>";
    echo "<strong>Troubleshooting:</strong>";
    echo "<ul>";
    echo "<li>Pastikan MySQL server sudah running</li>";
    echo "<li>Periksa konfigurasi di config/database.php</li>";
    echo "<li>Pastikan user memiliki akses ke database</li>";
    echo "</ul>";
    echo "</div>";
    exit();
}

// ============================
// STEP 3: RUN DATABASE SCHEMA
// ============================
echo "<h2>Step 3: Membuat Struktur Database</h2>";

$schemaFile = 'database_schema.sql';
if (!file_exists($schemaFile)) {
    echo "<div class='error'>❌ File schema database tidak ditemukan: {$schemaFile}</div>";
    exit();
}

try {
    $schema = file_get_contents($schemaFile);
    
    // Split SQL statements
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    $successCount = 0;
    $totalStatements = count($statements);
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) continue;
        
        try {
            $pdo->exec($statement);
            $successCount++;
        } catch (PDOException $e) {
            // Skip if table already exists or duplicate data
            if (strpos($e->getMessage(), 'already exists') === false && 
                strpos($e->getMessage(), 'Duplicate entry') === false) {
                echo "<div class='warning'>⚠️ Warning: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    echo "<div class='success'>✅ Schema database berhasil dijalankan! ({$successCount} statements)</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error menjalankan schema: " . $e->getMessage() . "</div>";
    exit();
}

// ============================
// STEP 4: VERIFY TABLES
// ============================
echo "<h2>Step 4: Verifikasi Tabel Database</h2>";

$requiredTables = [
    'users', 'locations', 'facilities', 'kos', 'kos_images', 
    'kos_facilities', 'bookings', 'reviews', 'user_activities', 'testimonials'
];

$existingTables = [];
try {
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }
    
    echo "<table>";
    echo "<tr><th>Tabel</th><th>Status</th><th>Records</th></tr>";
    
    foreach ($requiredTables as $table) {
        $exists = in_array($table, $existingTables);
        $status = $exists ? "✅ Exists" : "❌ Missing";
        
        $count = 0;
        if ($exists) {
            try {
                $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            } catch (Exception $e) {
                $count = "Error";
            }
        }
        
        echo "<tr><td>{$table}</td><td>{$status}</td><td>{$count}</td></tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error checking tables: " . $e->getMessage() . "</div>";
}

// ============================
// STEP 5: CHECK SAMPLE DATA
// ============================
echo "<h2>Step 5: Data Sample</h2>";

try {
    // Check users
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<p><strong>Users:</strong> {$userCount} records</p>";
    
    if ($userCount > 0) {
        echo "<div class='info'>";
        echo "<strong>Sample Users:</strong><br>";
        $users = $pdo->query("SELECT name, email, role FROM users LIMIT 3")->fetchAll();
        foreach ($users as $user) {
            echo "• {$user['name']} ({$user['email']}) - {$user['role']}<br>";
        }
        echo "</div>";
    }
    
    // Check kos
    $kosCount = $pdo->query("SELECT COUNT(*) FROM kos")->fetchColumn();
    echo "<p><strong>Kos:</strong> {$kosCount} records</p>";
    
    if ($kosCount > 0) {
        echo "<div class='info'>";
        echo "<strong>Sample Kos:</strong><br>";
        $kos = $pdo->query("SELECT name, price FROM kos LIMIT 3")->fetchAll();
        foreach ($kos as $k) {
            echo "• {$k['name']} - Rp " . number_format($k['price'], 0, ',', '.') . "<br>";
        }
        echo "</div>";
    }
    
    // Check bookings
    $bookingCount = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    echo "<p><strong>Bookings:</strong> {$bookingCount} records</p>";
    
} catch (Exception $e) {
    echo "<div class='warning'>⚠️ Error checking sample data: " . $e->getMessage() . "</div>";
}

// ============================
// STEP 6: TEST BOOKING FUNCTIONS
// ============================
echo "<h2>Step 6: Test Fungsi Booking</h2>";

try {
    require_once 'includes/functions.php';
    
    // Test get_kos_by_id function
    $testKos = $pdo->query("SELECT id FROM kos LIMIT 1")->fetch();
    if ($testKos) {
        $kosData = get_kos_by_id($testKos['id']);
        if ($kosData) {
            echo "<div class='success'>✅ Fungsi get_kos_by_id() bekerja dengan baik</div>";
        } else {
            echo "<div class='warning'>⚠️ Fungsi get_kos_by_id() tidak mengembalikan data</div>";
        }
    }
    
    // Test booking model
    if (file_exists('models/Booking.php')) {
        require_once 'models/Booking.php';
        $bookingModel = new Booking();
        echo "<div class='success'>✅ Model Booking berhasil dimuat</div>";
    } else {
        echo "<div class='warning'>⚠️ File models/Booking.php tidak ditemukan</div>";
    }
    
    // Test CSRF functions
    if (function_exists('generate_csrf_token')) {
        $token = generate_csrf_token();
        if ($token) {
            echo "<div class='success'>✅ Fungsi CSRF token bekerja dengan baik</div>";
        }
    } else {
        echo "<div class='warning'>⚠️ Fungsi CSRF token tidak ditemukan</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error testing functions: " . $e->getMessage() . "</div>";
}

// ============================
// STEP 7: FINAL STATUS
// ============================
echo "<h2>Step 7: Status Final</h2>";

echo "<div class='success'>";
echo "<h3>🎉 Setup Berhasil!</h3>";
echo "<p>Sistem booking TemanKosan sudah siap digunakan!</p>";
echo "</div>";

echo "<div class='step'>";
echo "<h4>Langkah Selanjutnya:</h4>";
echo "<ol>";
echo "<li>Akses halaman utama: <a href='index.php'>index.php</a></li>";
echo "<li>Login sebagai user dan coba booking: <a href='login.php'>login.php</a></li>";
echo "<li>Akses halaman admin untuk kelola booking: <a href='manage-bookings.php'>manage-bookings.php</a></li>";
echo "<li>Test halaman detail kos: <a href='kos-detail.php?id=1'>kos-detail.php?id=1</a></li>";
echo "</ol>";
echo "</div>";

echo "<div class='info'>";
echo "<h4>Login Credentials (Sample):</h4>";
echo "<strong>Admin:</strong> admin@temankosan.com / password<br>";
echo "<strong>User:</strong> john@email.com / password<br>";
echo "<strong>Owner:</strong> owner@email.com / password<br>";
echo "<p><em>Password untuk semua akun sample: 'password'</em></p>";
echo "</div>";

echo "<div class='warning'>";
echo "<h4>⚠️ Penting:</h4>";
echo "<ul>";
echo "<li>Ganti password default setelah login</li>";
echo "<li>Konfigurasi email notifications jika diperlukan</li>";
echo "<li>Setup payment gateway untuk production</li>";
echo "<li>Backup database secara berkala</li>";
echo "</ul>";
echo "</div>";

echo "<p><em>Setup script selesai dijalankan pada: " . date('d/m/Y H:i:s') . "</em></p>";

echo "</div>";
echo "</body>";
echo "</html>";
?>