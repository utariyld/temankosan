<?php
require_once(__DIR__ . '/../config/database.php');


// Admin authentication check
function require_admin() {
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        header('Location: ../kosan/login.php');
        exit;
    }
}

// Get admin dashboard statistics
function get_dashboard_stats() {
    $pdo = get_db_connection();
    if (!$pdo) return [];
    
    try {
        $stats = [];
        
        // Total counts
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM kos) as total_kos,
                    (SELECT COUNT(*) FROM users) as total_users,
                    (SELECT COUNT(*) FROM bookings) as total_bookings,
                    (SELECT COUNT(*) FROM bookings WHERE booking_status = 'pending') as pending_bookings,
                    (SELECT SUM(total_price) FROM bookings WHERE payment_status = 'paid') as total_revenue";
        
        $stmt = $pdo->query($sql);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Monthly new counts
        $monthlySQL = "SELECT 
                          (SELECT COUNT(*) FROM kos WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as new_kos_this_month,
                          (SELECT COUNT(*) FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as new_users_this_month,
                          (SELECT COUNT(*) FROM bookings WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())) as new_bookings_this_month";
        
        $monthlyStmt = $pdo->query($monthlySQL);
        $monthlyStats = $monthlyStmt->fetch(PDO::FETCH_ASSOC);
        
        return array_merge($stats, $monthlyStats);
        
    } catch (PDOException $e) {
        error_log("Error getting admin dashboard stats: " . $e->getMessage());
        return [];
    }
}

// Get recent bookings
function get_recent_bookings($limit = 10) {
    $pdo = get_db_connection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT b.*, u.name as user_name, k.name as kos_name
                FROM bookings b
                LEFT JOIN users u ON b.user_id = u.id
                LEFT JOIN kos k ON b.kos_id = k.id
                ORDER BY b.created_at DESC
                LIMIT ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting recent bookings: " . $e->getMessage());
        return [];
    }
}

// Get recent users
function get_recent_users($limit = 10) {
    $pdo = get_db_connection();
    if (!$pdo) return [];
    
    try {
        $sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting recent users: " . $e->getMessage());
        return [];
    }
}

// Get recent kos
function get_recent_kos($limit = 10) {
    $pdo = get_db_connection();
    if (!$pdo) return [];

    try {
        $sql = "SELECT k.*
                FROM kos k
                ORDER BY k.created_at DESC
                LIMIT ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Error getting recent kos: " . $e->getMessage());
        return [];
    }
}

// Get all kos with pagination
function get_all_kos_admin($page = 1, $limit = 20, $search = '', $status = '') {
    $pdo = getConnection();
    if (!$pdo) return ['data' => [], 'total' => 0];

    try {
        $offset = ($page - 1) * $limit;
        $whereClause = "WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $whereClause .= " AND (k.name LIKE ? OR k.address LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($status)) {
            $whereClause .= " AND k.status = ?";
            $params[] = $status;
        }

        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM kos k $whereClause";
        $countStmt = $pdo->prepare($countSQL);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get data
        // Hapus JOIN ke tabel users dan pemilihan u.name jika owner_id/owner_name tidak ada
        $sql = "SELECT k.*
                FROM kos k
                $whereClause
                ORDER BY k.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['data' => $data, 'total' => $total];

    } catch (PDOException $e) {
        error_log("Error getting all kos admin: " . $e->getMessage());
        return ['data' => [], 'total' => 0];
    }
}

// Get all users with pagination
function get_all_users_admin($page = 1, $limit = 20, $search = '', $role = '') {
    $pdo = get_db_connection();
    if (!$pdo) return ['data' => [], 'total' => 0];

    try {
        $offset = ($page - 1) * $limit;
        $whereClause = "WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $whereClause .= " AND (u.name LIKE ? OR u.email LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        if (!empty($role)) {
            $whereClause .= " AND u.role = ?";
            $params[] = $role;
        }

        // Get total count
        $countSQL = "SELECT COUNT(*) as total FROM users u $whereClause";
        $countStmt = $pdo->prepare($countSQL);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];

        // Get data
        // Hapus LEFT JOIN ke tabel kos dan COUNT(DISTINCT k.id) AS total_kos jika tidak diperlukan
        $sql = "SELECT
                    u.*,
                    COUNT(DISTINCT b.id) AS total_bookings
                    -- Hapus baris di bawah jika Anda tidak ingin menghitung total kos per user
                    -- COUNT(DISTINCT k.id) AS total_kos
                FROM users u
                LEFT JOIN bookings b ON b.user_id = u.id
                -- Hapus baris di bawah jika tidak ada kolom owner_id di tabel kos
                -- LEFT JOIN kos k ON k.owner_id = u.id
                $whereClause
                GROUP BY u.id
                ORDER BY u.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return ['data' => $data, 'total' => $total];

    } catch (PDOException $e) {
        error_log("Error getting all users admin: " . $e->getMessage());
        return ['data' => [], 'total' => 0];
    }
}

function get_all_users() {
    $pdo = get_db_connection();
    if (!$pdo) return [];

    $stmt = $pdo->query("
        SELECT
            u.*,
            COUNT(DISTINCT b.id) AS total_bookings
            -- Hapus JOIN dan COUNT untuk total_kos jika tidak ada owner_id di tabel kos
        FROM users u
        LEFT JOIN bookings b ON b.user_id = u.id
        GROUP BY u.id
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// Get all bookings with pagination
function get_all_bookings_admin($page = 1, $limit = 20, $search = '', $status = '') {
    $pdo = get_db_connection();
    if (!$pdo) return ['data' => [], 'total' => 0];
    
    try {
        $offset = ($page - 1) * $limit;
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($search)) {
            $whereClause .= " AND (b.booking_code LIKE ? OR u.name LIKE ? OR k.nama LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($status)) {
            $whereClause .= " AND b.booking_status = ?";
            $params[] = $status;
        }
        
        // Get total count
        $countSQL = "SELECT COUNT(*) as total 
                     FROM bookings b 
                     JOIN users u ON b.user_id = u.id 
                     JOIN kos k ON b.kos_id = k.id 
                     $whereClause";
        $countStmt = $pdo->prepare($countSQL);
        $countStmt->execute($params);
        $total = $countStmt->fetch()['total'];
        
        // Get data
        $sql = "SELECT 
                    b.*,
                    u.name as user_name,
                    u.email as user_email,
                    k.nama as kos_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN kos k ON b.kos_id = k.id
                $whereClause
                ORDER BY b.created_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['data' => $data, 'total' => $total];
        
    } catch (PDOException $e) {
        error_log("Error getting all bookings admin: " . $e->getMessage());
        return ['data' => [], 'total' => 0];
    }
}



// Update kos status
function update_kos_status($kosId, $status) {
    $pdo = get_db_connection();
    if (!$pdo) return false;
    
    try {
        $sql = "UPDATE kos SET status = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$status, $kosId]);
        
    } catch (PDOException $e) {
        error_log("Error updating kos status: " . $e->getMessage());
        return false;
    }
}

// Update user status
function update_user_status($userId, $isActive) {
    $pdo = get_db_connection();
    if (!$pdo) return false;
    
    try {
        $sql = "UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute([$isActive, $userId]);
        
    } catch (PDOException $e) {
        error_log("Error updating user status: " . $e->getMessage());
        return false;
    }
}

// Delete kos
function delete_kos($kosId) {
    $pdo = get_db_connection();
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        // Check if kos has active bookings
        $checkSQL = "SELECT COUNT(*) as count FROM bookings WHERE kos_id = ? AND booking_status IN ('pending', 'confirmed')";
        $checkStmt = $pdo->prepare($checkSQL);
        $checkStmt->execute([$kosId]);
        $activeBookings = $checkStmt->fetch()['count'];
        
        if ($activeBookings > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Tidak dapat menghapus kos yang memiliki booking aktif'];
        }
        
        // Delete kos
        $sql = "DELETE FROM kos WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$kosId]);
        
        $pdo->commit();
        return ['success' => $result, 'message' => $result ? 'Kos berhasil dihapus' : 'Gagal menghapus kos'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting kos: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
    }
}

// Delete user
function delete_user($userId) {
    $pdo = get_db_connection();
    if (!$pdo) return false;
    
    try {
        $pdo->beginTransaction();
        
        // Check if user has active bookings
        $checkSQL = "SELECT COUNT(*) as count FROM bookings WHERE user_id = ? AND booking_status IN ('pending', 'confirmed')";
        $checkStmt = $pdo->prepare($checkSQL);
        $checkStmt->execute([$userId]);
        $activeBookings = $checkStmt->fetch()['count'];
        
        if ($activeBookings > 0) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Tidak dapat menghapus user yang memiliki booking aktif'];
        }
        
        // Delete user
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([$userId]);
        
        $pdo->commit();
        return ['success' => $result, 'message' => $result ? 'User berhasil dihapus' : 'Gagal menghapus user'];
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error deleting user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Terjadi kesalahan sistem'];
    }
}

// Get database connection helper
function get_db_connection() {
    try {
        $db = Database::getInstance();
        return $db->getConnection();
    } catch (Exception $e) {
        error_log("Database connection failed: " . $e->getMessage());
        return null;
    }
}
?>