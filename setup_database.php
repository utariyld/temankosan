<?php
/**
 * Database Setup Script
 * Jalankan file ini untuk setup database TemanKosan
 */

// Enable error reporting untuk debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>🚀 TemanKosan Database Setup</h1>";

// ========================================
// STEP 1: KONFIGURASI DATABASE
// ========================================
echo "<h2>Step 1: Database Configuration</h2>";

// UBAH KONFIGURASI INI SESUAI SETUP LOCALHOST ANDA
$config = [
    'host' => 'localhost',        // UBAH: Host database Anda
    'username' => 'root',         // UBAH: Username MySQL Anda
    'password' => '',             // UBAH: Password MySQL Anda (kosong untuk XAMPP)
    'database' => 'temankosan',   // UBAH: Nama database yang ingin dibuat
    'port' => 3306               // UBAH: Port MySQL (3306 default, 8889 untuk MAMP)
];

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Setting</th><th>Value</th></tr>";
foreach ($config as $key => $value) {
    echo "<tr><td>{$key}</td><td>" . ($value ?: '(empty)') . "</td></tr>";
}
echo "</table>";

// ========================================
// STEP 2: TEST KONEKSI MYSQL
// ========================================
echo "<h2>Step 2: Testing MySQL Connection</h2>";

try {
    $dsn = "mysql:host={$config['host']};port={$config['port']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<p style='color: green;'>✅ MySQL connection successful!</p>";
    
    // Get MySQL info
    $version = $pdo->query("SELECT VERSION()")->fetchColumn();
    echo "<p>MySQL Version: <strong>{$version}</strong></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ MySQL connection failed!</p>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<h3>🔧 Troubleshooting:</h3>";
    echo "<ul>";
    echo "<li>Pastikan MySQL server sudah running</li>";
    echo "<li>Periksa username dan password MySQL</li>";
    echo "<li>Periksa port MySQL (3306 default, 8889 untuk MAMP)</li>";
    echo "<li>Untuk XAMPP: Start Apache dan MySQL di Control Panel</li>";
    echo "<li>Untuk MAMP: Start servers dan periksa port</li>";
    echo "</ul>";
    exit();
}

// ========================================
// STEP 3: CREATE DATABASE
// ========================================
echo "<h2>Step 3: Creating Database</h2>";

try {
    // Check if database exists
    $databases = $pdo->query("SHOW DATABASES LIKE '{$config['database']}'")->fetchAll();
    
    if (empty($databases)) {
        $pdo->exec("CREATE DATABASE `{$config['database']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<p style='color: green;'>✅ Database '{$config['database']}' created successfully!</p>";
    } else {
        echo "<p style='color: orange;'>⚠️ Database '{$config['database']}' already exists.</p>";
    }
    
    // Connect to the database
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Failed to create database!</p>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    exit();
}

// ========================================
// STEP 4: CREATE TABLES
// ========================================
echo "<h2>Step 4: Creating Tables</h2>";

// SQL untuk membuat tabel testimonials
$testimonialsSql = "
CREATE TABLE IF NOT EXISTS `testimonials` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `kos_name` varchar(200) NOT NULL,
    `rating` tinyint(1) NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
    `comment` text NOT NULL,
    `is_approved` tinyint(1) DEFAULT 0 COMMENT '0=pending, 1=approved, -1=rejected',
    `ip_address` varchar(45) DEFAULT NULL,
    `user_agent` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `idx_approved` (`is_approved`),
    KEY `idx_rating` (`rating`),
    KEY `idx_created` (`created_at`),
    KEY `idx_email_date` (`email`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($testimonialsSql);
    echo "<p style='color: green;'>✅ Testimonials table created successfully!</p>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Failed to create testimonials table!</p>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}

// ========================================
// STEP 5: INSERT SAMPLE DATA
// ========================================
echo "<h2>Step 5: Inserting Sample Data</h2>";

$sampleData = [
    [
        'name' => 'Andi Pratama',
        'email' => 'andi@email.com',
        'phone' => '081234567890',
        'kos_name' => 'Kos Melati Putih',
        'rating' => 5,
        'comment' => 'Kos yang sangat nyaman dan bersih. Pemilik kos juga ramah dan fasilitas lengkap. Sangat recommended untuk mahasiswa!',
        'is_approved' => 1
    ],
    [
        'name' => 'Sari Dewi',
        'email' => 'sari@email.com',
        'phone' => '081234567891',
        'kos_name' => 'Kos Mawar Indah',
        'rating' => 4,
        'comment' => 'Lokasi strategis dekat kampus. Kamar cukup luas dan ada WiFi gratis. Hanya saja parkir agak terbatas.',
        'is_approved' => 1
    ],
    [
        'name' => 'Budi Santoso',
        'email' => 'budi@email.com',
        'phone' => '081234567892',
        'kos_name' => 'Kos Anggrek Residence',
        'rating' => 5,
        'comment' => 'Pelayanan excellent! Kamar bersih, AC dingin, dan ada dapur bersama. Pemilik kos sangat membantu.',
        'is_approved' => 1
    ]
];

$insertSql = "INSERT INTO testimonials (name, email, phone, kos_name, rating, comment, is_approved, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($insertSql);

$inserted = 0;
foreach ($sampleData as $data) {
    try {
        $stmt->execute([
            $data['name'],
            $data['email'],
            $data['phone'],
            $data['kos_name'],
            $data['rating'],
            $data['comment'],
            $data['is_approved'],
            '127.0.0.1'
        ]);
        $inserted++;
    } catch (PDOException $e) {
        // Skip if duplicate
        if ($e->getCode() != 23000) {
            echo "<p style='color: orange;'>⚠️ Failed to insert sample data: " . $e->getMessage() . "</p>";
        }
    }
}

echo "<p style='color: green;'>✅ Inserted {$inserted} sample testimonials!</p>";

// ========================================
// STEP 6: VERIFY SETUP
// ========================================
echo "<h2>Step 6: Verifying Setup</h2>";

try {
    // Count testimonials
    $count = $pdo->query("SELECT COUNT(*) FROM testimonials")->fetchColumn();
    echo "<p style='color: green;'>✅ Total testimonials in database: <strong>{$count}</strong></p>";
    
    // Test approved testimonials
    $approved = $pdo->query("SELECT COUNT(*) FROM testimonials WHERE is_approved = 1")->fetchColumn();
    echo "<p style='color: green;'>✅ Approved testimonials: <strong>{$approved}</strong></p>";
    
    // Show sample data
    $samples = $pdo->query("SELECT name, kos_name, rating, LEFT(comment, 50) as short_comment FROM testimonials WHERE is_approved = 1 LIMIT 3")->fetchAll();
    
    if (!empty($samples)) {
        echo "<h3>Sample Testimonials:</h3>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>Name</th><th>Kos</th><th>Rating</th><th>Comment</th></tr>";
        foreach ($samples as $sample) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sample['name']) . "</td>";
            echo "<td>" . htmlspecialchars($sample['kos_name']) . "</td>";
            echo "<td>" . str_repeat('⭐', $sample['rating']) . "</td>";
            echo "<td>" . htmlspecialchars($sample['short_comment']) . "...</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Verification failed: " . $e->getMessage() . "</p>";
}

// ========================================
// STEP 7: CONFIGURATION FILE
// ========================================
echo "<h2>Step 7: Configuration File Status</h2>";

$configFile = 'config/database.php';
if (file_exists($configFile)) {
    echo "<p style='color: green;'>✅ Configuration file exists: <code>{$configFile}</code></p>";
    echo "<p><strong>⚠️ IMPORTANT:</strong> Pastikan konfigurasi di file tersebut sesuai dengan setup Anda:</p>";
    echo "<ul>";
    echo "<li>DB_HOST = '{$config['host']}'</li>";
    echo "<li>DB_NAME = '{$config['database']}'</li>";
    echo "<li>DB_USER = '{$config['username']}'</li>";
    echo "<li>DB_PASS = '" . ($config['password'] ?: '(empty)') . "'</li>";
    echo "<li>DB_PORT = '{$config['port']}'</li>";
    echo "</ul>";
} else {
    echo "<p style='color: red;'>❌ Configuration file not found: <code>{$configFile}</code></p>";
    echo "<p>Please make sure the config/database.php file exists and is properly configured.</p>";
}

// ========================================
// FINAL STEPS
// ========================================
echo "<h2>🎉 Setup Complete!</h2>";
echo "<p style='color: green; font-size: 18px;'><strong>Database setup berhasil!</strong></p>";

echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Pastikan konfigurasi di <code>config/database.php</code> sudah benar</li>";
echo "<li>Test koneksi dengan menjalankan <code>test_connection.php</code></li>";
echo "<li>Tambahkan testimonial section ke homepage Anda</li>";
echo "<li>Test API endpoints di <code>api/testimonials.php</code></li>";
echo "</ol>";

echo "<h3>Test Links:</h3>";
echo "<ul>";
echo "<li><a href='test_connection.php' target='_blank'>Test Database Connection</a></li>";
echo "<li><a href='api/testimonials.php' target='_blank'>Test API Endpoint</a></li>";
echo "<li><a href='index.php' target='_blank'>View Homepage</a></li>";
echo "</ul>";

echo "<p><em>File ini dapat dihapus setelah setup selesai.</em></p>";
?>
