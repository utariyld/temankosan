<?php
// =============================================
// Database Checker & Fixer
// =============================================

require_once 'config/database.php';

try {
    $db = new Database();
    $pdo = $db->connect();
    
    echo "<h2>🔍 Database Checker TemanKosan</h2>";
    
    // Check database connection
    echo "<h3>✅ Koneksi Database</h3>";
    echo "<p>Status: <strong style='color: green;'>BERHASIL</strong></p>";
    echo "<p>Database: <strong>" . DB_NAME . "</strong></p>";
    echo "<p>Host: <strong>" . DB_HOST . "</strong></p>";
    
    // Check tables
    echo "<h3>📋 Tabel Database</h3>";
    $tables = ['users', 'locations', 'facilities', 'kos', 'kos_facilities', 'kos_images', 'kos_rules', 'testimonials'];
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p>✅ <strong>$table</strong>: $count records</p>";
        } else {
            echo "<p>❌ <strong>$table</strong>: TIDAK DITEMUKAN</p>";
        }
    }
    
    // Check for duplicates in kos_facilities
    echo "<h3>🔍 Check Duplicate Data</h3>";
    $stmt = $pdo->query("
        SELECT kos_id, facility_id, COUNT(*) as count 
        FROM kos_facilities 
        GROUP BY kos_id, facility_id 
        HAVING COUNT(*) > 1
    ");
    
    $duplicates = $stmt->fetchAll();
    if (empty($duplicates)) {
        echo "<p>✅ <strong>Tidak ada data duplikat</strong></p>";
    } else {
        echo "<p>❌ <strong>Ditemukan " . count($duplicates) . " data duplikat:</strong></p>";
        foreach ($duplicates as $dup) {
            echo "<p>- Kos ID: {$dup['kos_id']}, Facility ID: {$dup['facility_id']} ({$dup['count']} kali)</p>";
        }
        
        echo "<h4>🔧 Auto Fix Duplicates</h4>";
        if (isset($_GET['fix'])) {
            // Delete duplicates, keep only one
            $pdo->exec("
                DELETE t1 FROM kos_facilities t1
                INNER JOIN kos_facilities t2 
                WHERE t1.id > t2.id 
                AND t1.kos_id = t2.kos_id 
                AND t1.facility_id = t2.facility_id
            ");
            echo "<p>✅ <strong>Duplikat berhasil dihapus!</strong></p>";
            echo "<script>setTimeout(() => location.reload(), 2000);</script>";
        } else {
            echo "<p><a href='?fix=1' style='background: #ff4444; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🔧 Fix Duplicates</a></p>";
        }
    }
    
    // Show sample data
    echo "<h3>📊 Sample Data</h3>";
    
    // Kos with facilities
    $stmt = $pdo->query("
        SELECT k.name, COUNT(kf.facility_id) as total_facilities
        FROM kos k
        LEFT JOIN kos_facilities kf ON k.id = kf.kos_id
        GROUP BY k.id, k.name
        ORDER BY k.id
    ");
    
    echo "<h4>Kos & Fasilitas:</h4>";
    while ($row = $stmt->fetch()) {
        echo "<p>• <strong>{$row['name']}</strong>: {$row['total_facilities']} fasilitas</p>";
    }
    
    // Testimonials
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(is_approved = 1) as approved FROM testimonials");
    $testimonial_stats = $stmt->fetch();
    echo "<h4>Testimoni:</h4>";
    echo "<p>• Total: {$testimonial_stats['total']}</p>";
    echo "<p>• Disetujui: {$testimonial_stats['approved']}</p>";
    
    echo "<h3>🎉 Database Status: SEHAT!</h3>";
    
} catch (Exception $e) {
    echo "<h2>❌ Error Database</h2>";
    echo "<p style='color: red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
    
    echo "<h3>🔧 Solusi:</h3>";
    echo "<ol>";
    echo "<li>Pastikan MySQL server berjalan</li>";
    echo "<li>Periksa konfigurasi di <code>config/database.php</code></li>";
    echo "<li>Pastikan database 'temankosan' sudah dibuat</li>";
    echo "<li>Import file <code>database/clean_install.sql</code></li>";
    echo "</ol>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2 { color: #333; border-bottom: 2px solid #4CAF50; }
h3 { color: #666; }
p { margin: 5px 0; }
code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
</style>
