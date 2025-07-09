<?php
require_once(__DIR__ . '/../config/database.php');


// Database connection helper
function getConnection() {
    try {
        $db = Database::getInstance();
        return $db->getConnection();
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

// Sanitize input data
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Authenticate user
function authenticate_user($email, $password) {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("
        SELECT id, name, email, password, role
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        // Update last login
        $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
        $update_stmt->execute([$user['id']]);
        
        // Remove password hash from returned data
        unset($user['password']);
        return $user;
    }
    
    return false;
}

function get_search_kos() {
    $pdo = getConnection();
    if (!$pdo) return [];

    $query = "
        SELECT 
            k.id,
            k.name,
            k.address,
            k.price,
            k.type,
            k.is_available,
            k.room_size,
            k.created_at,
            GROUP_CONCAT(DISTINCT f.name) AS facilities,
            CONCAT(l.city, ', ', l.district) AS location,
            ki.image_url
        FROM kos k
        LEFT JOIN kos_facilities kf ON k.id = kf.kos_id
        LEFT JOIN facilities f ON kf.facility_id = f.id
        LEFT JOIN locations l ON k.location_id = l.id
        LEFT JOIN kos_images ki ON k.id = ki.kos_id
        GROUP BY k.id
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'location' => $row['location'],
            'address' => $row['address'],
            'price' => (int)$row['price'],
            'rating' => rand(4, 5), // Dummy rating
            'reviewCount' => rand(50, 200), // Dummy review count
            'image' => $row['image_url'] ?? 'https://via.placeholder.com/400x300',
            'facilities' => explode(',', $row['facilities'] ?? ''),
            'type' => $row['type'],
            'is_available' => (bool)$row['is_available'],
            'room_size' => $row['room_size'],
            'created_at' => $row['created_at']
        ];
    }, $rows);
}

function search_kos($lokasi, $minHarga, $maxHarga, $limit, $offset) {
    // Contoh koneksi (pastikan kamu sudah buat PDO $pdo sebelumnya)
    global $pdo;

    $query = "SELECT * FROM kos WHERE lokasi LIKE :lokasi AND harga BETWEEN :min AND :max LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        'lokasi' => "%$lokasi%",
        'min' => $minHarga,
        'max' => $maxHarga,
        'limit' => $limit,
        'offset' => $offset
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if email exists
function email_exists($email) {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    return $stmt->fetchColumn() > 0;
}

// Create new user
function create_user($name, $email, $phone, $password, $role = 'member') {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    $password = password_hash($password, PASSWORD_DEFAULT);
    $verification_token = bin2hex(random_bytes(32));
    
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, phone, password, role, email_verified_at) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    if ($stmt->execute([$name, $email, $phone, $password, $role, $verification_token])) {
        $user_id = $pdo->lastInsertId();
        
        // For demo purposes, auto-verify the user
        $verify_stmt = $pdo->prepare("UPDATE users SET email_verified_at = 1 WHERE id = ?");
        $verify_stmt->execute([$user_id]);
        
        return $user_id;
    }
    
    return false;
}

// Validate registration data
function validate_registration($name, $email, $phone, $password, $confirmPassword) {
    $errors = [];
    
    if (empty($name) || strlen($name) < 2) {
        $errors[] = 'Nama harus diisi minimal 2 karakter';
    }
    
    if (empty($email) || !validate_email($email)) {
        $errors[] = 'Email tidak valid';
    }
    
    if (empty($phone) || !validate_phone($phone)) {
        $errors[] = 'Nomor telepon tidak valid';
    }
    
    if (empty($password) || strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter';
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = 'Konfirmasi password tidak cocok';
    }
    
    return $errors;
}

// Log user activity
function log_activity($user_id, $action, $description = '') {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "INSERT INTO user_activities (user_id, action, description, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

function log_activity_admin($user_id, $action, $description = '') {
    $pdo = getConnection();
    if (!$pdo) return false;

    try {
        // Ambil nomor telepon dari tabel users
        $phoneStmt = $pdo->prepare("SELECT phone FROM users WHERE id = ?");
        $phoneStmt->execute([$user_id]);
        $user = $phoneStmt->fetch(PDO::FETCH_ASSOC);
        $phone = $user['phone'] ?? '';

        // Simpan ke tabel aktivitas
        $sql = "INSERT INTO user_activities (user_id, action, description, ip_address, user_agent, phone, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $user_id,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $phone
        ]);
    } catch (PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

// Get kos data from database
function get_kos_by_id($kos_id) {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT k.*, 
                       l.city, l.district,
                       u.name as owner_name, u.phone as owner_phone,
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as image,
                       (SELECT COUNT(*) FROM reviews WHERE kos_id = k.id) as review_count,
                       (SELECT AVG(rating) FROM reviews WHERE kos_id = k.id) as avg_rating
                FROM kos k
                LEFT JOIN locations l ON k.location_id = l.id
                LEFT JOIN users u ON k.owner_id = u.id
                WHERE k.id = ? AND k.status = 'published' AND k.is_available = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$kos_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no image found, use default
        if ($result && empty($result['image'])) {
            $result['image'] = 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=400&h=300&fit=crop';
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error getting kos: " . $e->getMessage());
        return false;
    }
}

function get_kos_by_id_summary($kos_id) {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "SELECT k.*, 
                       l.city, l.district,
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as image,
                       (SELECT COUNT(*) FROM reviews WHERE kos_id = k.id) as review_count,
                       (SELECT AVG(rating) FROM reviews WHERE kos_id = k.id) as avg_rating
                FROM kos k
                LEFT JOIN locations l ON k.location_id = l.id
                WHERE k.id = ? AND k.status = 'published' AND k.is_available = 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$kos_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no image found, use default
        if ($result && empty($result['image'])) {
            $result['image'] = 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=400&h=300&fit=crop';
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error getting kos: " . $e->getMessage());
        return false;
    }
}
// Create new booking
function create_booking($bookingData) {
    // Mendapatkan koneksi PDO dari fungsi getConnection()
    $pdo = getConnection();

    // Memastikan koneksi PDO berhasil
    if (!$pdo) {
        error_log("Failed to get PDO connection in create_booking.");
        return ['success' => false, 'message' => 'Sistem bermasalah: Koneksi database tidak tersedia.'];
    }

    // Generate unique booking code
    $bookingCode = 'BK' . strtoupper(uniqid());

    // Query INSERT untuk bookings
    $sql = "INSERT INTO bookings (user_id, kos_id, booking_code, full_name, email, phone, check_in_date, duration_months, total_price, admin_fee, payment_method, notes, booking_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    try {
        // Mempersiapkan statement PDO
        $stmt = $pdo->prepare($sql);

        // Array parameter yang akan di-bind
        $params = [
            $bookingData['user_id'] ?? null, // Tambahkan null coalesce operator untuk keamanan
            $bookingData['kos_id'] ?? null,
            $bookingCode,
            $bookingData['full_name'] ?? '',
            $bookingData['email'] ?? '',
            $bookingData['phone'] ?? '',
            $bookingData['check_in_date'] ?? null,
            $bookingData['duration_months'] ?? 1,
            $bookingData['total_price'] ?? 0.0,
            $bookingData['admin_fee'] ?? 0.0,
            $bookingData['payment_method'] ?? '',
            $bookingData['notes'] ?? ''
        ];

        // Eksekusi statement dengan parameter
        if ($stmt->execute($params)) {
            // Mengambil ID terakhir yang di-insert
            $bookingId = $pdo->lastInsertId();
            return ['success' => true, 'booking_id' => $bookingId, 'booking_code' => $bookingCode];
        } else {
            // Jika eksekusi gagal, ambil informasi error PDO
            $errorInfo = $stmt->errorInfo();
            error_log("Execute failed in create_booking: " . $errorInfo[2]);
            return ['success' => false, 'message' => 'Gagal membuat booking: ' . ($errorInfo[2] ?? 'Terjadi kesalahan tidak diketahui.')];
        }
    } catch (PDOException $e) {
        // Tangani jika ada exception dari PDO (misal: query salah, koneksi putus)
        error_log("PDO Exception in create_booking: " . $e->getMessage());
        return ['success' => false, 'message' => 'Gagal membuat booking: ' . $e->getMessage()];
    }
}

// Get user bookings
function get_user_bookings($user_id) {
    $pdo = getConnection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT b.*, k.name as kos_name, k.address as kos_address,
                       l.city, l.district,
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as kos_image
                FROM bookings b
                JOIN kos k ON b.kos_id = k.id
                LEFT JOIN locations l ON k.location_id = l.id
                WHERE b.user_id = ?
                ORDER BY b.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add default image if not found
        foreach ($bookings as &$booking) {
            if (empty($booking['kos_image'])) {
                $booking['kos_image'] = 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=400&h=300&fit=crop';
            }
        }
        
        return $bookings;
    } catch (PDOException $e) {
        error_log("Error getting user bookings: " . $e->getMessage());
        return [];
    }
}

// Get booking by ID
function get_booking_by_id($bookingId, $userId = null) {
    $pdo = getConnection();
    if (!$pdo) {
        error_log("Failed to get PDO connection in get_booking_by_id.");
        return false;
    }

    $sql = "SELECT
                b.*,                      -- Ambil semua kolom dari tabel bookings (alias 'b')
                u.name AS user_name,      -- Ambil kolom 'name' dari tabel users, beri alias 'user_name'
                u.email AS user_email,    -- Ambil kolom 'email' dari tabel users, beri alias 'user_email'
                k.name AS kos_name,   
                k.address AS kos_address,    -- Ambil kolom 'name' dari tabel kos, beri alias 'kos_name'
                l.city,                   -- Ambil kolom 'city' dari tabel locations
                l.district                -- Ambil kolom 'district' dari tabel locations
            FROM
                bookings b                -- Aliaskan tabel bookings sebagai 'b'
            JOIN
                users u ON b.user_id = u.id -- Gabungkan dengan tabel users (alias 'u') berdasarkan user_id
            JOIN
                kos k ON b.kos_id = k.id  -- Gabungkan dengan tabel kos (alias 'k') berdasarkan kos_id
            LEFT JOIN
                locations l ON k.location_id = l.id -- Gabungkan dengan tabel locations (alias 'l') berdasarkan location_id
            WHERE
                b.id = ?";
    $params = [$bookingId];

    if ($userId !== null) {
        // Jika userId disediakan, tambahkan kondisi untuk memastikan booking adalah milik user tersebut
        $sql .= " AND b.user_id = ?";
        $params[] = $userId;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("PDO Exception in get_booking_by_id: " . $e->getMessage());
        return false;
    }
}

function format_date($dateString, $format = 'd F Y H:i') {
    // Return empty string if date is null, empty, or a zero-date string
    if (empty($dateString) || $dateString === '0000-00-00 00:00:00') {
        return '';
    }
    try {
        // Create a DateTime object from the date string
        $dateTime = new DateTime($dateString);
        // Format the date according to the specified format
        return $dateTime->format($format);
    } catch (Exception $e) {
        // Log the error for debugging purposes
        error_log("Error formatting date '{$dateString}': " . $e->getMessage());
        return ''; // Return empty string or handle error as preferred
    }
}

// Update booking status
function update_booking_status($booking_id, $booking_status, $payment_status = null) {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    try {
        $sql = "UPDATE bookings SET booking_status = ?, updated_at = NOW() WHERE id = ?";
        $params = [$booking_status, $booking_id];
        
        if ($payment_status) {
            $sql .= ", payment_status = ?";
            $params[] = $payment_status;
        }
        
        if ($booking_status === 'confirmed') {
            $sql .= ", confirmed_at = NOW()";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $booking_id;
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating booking status: " . $e->getMessage());
        return false;
    }
}

function update_booking_status_payment($booking_id, $booking_status, $payment_status = null) {
    $pdo = getConnection();
    if (!$pdo) {
        error_log("Failed to get PDO connection in update_booking_status.");
        return false;
    }
    
    try {
        $sql = "UPDATE bookings SET booking_status = :booking_status, updated_at = NOW()";
        $params = [
            ':booking_status' => $booking_status,
            ':id' => $booking_id // Parameter untuk WHERE clause
        ];
        
        if ($payment_status !== null) { // Lebih eksplisit
            $sql .= ", payment_status = :payment_status";
            $params[':payment_status'] = $payment_status;
        }
        
        if ($booking_status === 'confirmed') {
            $sql .= ", confirmed_at = NOW()";
        }
        
        $sql .= " WHERE id = :id"; // Hanya satu WHERE clause di akhir
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    } catch (PDOException $e) {
        error_log("Error updating booking status for ID {$booking_id}: " . $e->getMessage());
        return false;
    }
}

// Get all kos for listing
function get_all_kos($limit = 10, $offset = 0) {
    $pdo = getConnection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT k.*, 
                       l.city, l.district,
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image,
                       (SELECT AVG(rating) FROM reviews WHERE kos_id = k.id) as avg_rating,
                       (SELECT COUNT(*) FROM reviews WHERE kos_id = k.id) as review_count
                FROM kos k
                LEFT JOIN locations l ON k.location_id = l.id
                WHERE k.status = 'published' AND k.is_available = 1
                ORDER BY k.is_featured DESC, k.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting all kos: " . $e->getMessage());
        return [];
    }
}

// Generate unique booking code
function generate_booking_code() {
    return 'TK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Validate email
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate phone number
function validate_phone($phone) {
    // Indonesian phone number validation
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(08|62)[0-9]{8,12}$/', $phone);
}

// Format currency
function format_currency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

// Get booking status badge
function get_status_badge($status) {
    $badges = [
        'pending' => ['class' => 'warning', 'text' => 'Menunggu Pembayaran', 'icon' => 'clock'],
        'paid' => ['class' => 'info', 'text' => 'Sudah Dibayar', 'icon' => 'credit-card'],
        'confirmed' => ['class' => 'success', 'text' => 'Dikonfirmasi', 'icon' => 'check-circle'],
        'active' => ['class' => 'primary', 'text' => 'Aktif', 'icon' => 'home'],
        'completed' => ['class' => 'secondary', 'text' => 'Selesai', 'icon' => 'flag-checkered'],
        'cancelled' => ['class' => 'danger', 'text' => 'Dibatalkan', 'icon' => 'times-circle'],
        'expired' => ['class' => 'dark', 'text' => 'Kedaluwarsa', 'icon' => 'exclamation-triangle']
    ];
    
    return $badges[$status] ?? ['class' => 'secondary', 'text' => ucfirst($status), 'icon' => 'question'];
}

// Check if user can cancel booking
function can_cancel_booking($booking) {
    if (!$booking) return false;
    
    $allowedStatuses = ['pending', 'paid'];
    $checkInDate = strtotime($booking['check_in_date']);
    $now = time();
    $daysDiff = ($checkInDate - $now) / (60 * 60 * 24);
    
    return in_array($booking['booking_status'], $allowedStatuses) && $daysDiff > 1;
}

// Send email notification (placeholder)
function send_email_notification($to, $subject, $message, $type = 'booking') {
    // This is a placeholder for email functionality
    // In production, you would integrate with an email service like PHPMailer, SendGrid, etc.
    
    error_log("Email notification: TO: $to, SUBJECT: $subject, TYPE: $type");
    return true;
}

// Get payment methods
function get_payment_methods() {
    return [
        'transfer' => [
            'name' => 'Transfer Bank',
            'icon' => 'university',
            'description' => 'Transfer ke rekening bank'
        ],
        'ewallet' => [
            'name' => 'E-Wallet',
            'icon' => 'mobile-alt',
            'description' => 'OVO, GoPay, DANA'
        ],
        'credit' => [
            'name' => 'Kartu Kredit/Debit',
            'icon' => 'credit-card',
            'description' => 'Visa, Mastercard'
        ]
    ];
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}


if (!function_exists('get_current_user')) {
    function get_current_user() {
        // isi fungsi di sini
    }
}


// Redirect if not logged in
function require_login($redirect_to = 'login.php') {
    if (!is_logged_in()) {
        header("Location: $redirect_to");
        exit;
    }
}

// Generate CSRF token
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Clean old sessions and expired bookings (can be called via cron)
function cleanup_expired_data() {
    $pdo = getConnection();
    if (!$pdo) return false;
    
    try {
        // Mark expired bookings
        $sql = "UPDATE bookings 
                SET booking_status = 'expired' 
                WHERE booking_status = 'pending' 
                AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $pdo->exec($sql);
        
        // Clean old activity logs (older than 6 months)
        $sql = "DELETE FROM user_activities 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 6 MONTH)";
        $pdo->exec($sql);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error cleaning expired data: " . $e->getMessage());
        return false;
    }
}

?>
