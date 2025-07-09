<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';

// Check if user is admin (simple check for demo)
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$db = getConnection();
$message = "";
$messageType = "";

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking'])) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    $booking_status = filter_input(INPUT_POST, 'booking_status', FILTER_SANITIZE_STRING);
    $payment_status = filter_input(INPUT_POST, 'payment_status', FILTER_SANITIZE_STRING);

    if ($booking_id && $booking_status) {
        try {
            $sql = "UPDATE bookings SET booking_status = :booking_status";
            $params = [':booking_status' => $booking_status, ':id' => $booking_id];

            if ($payment_status) {
                $sql .= ", payment_status = :payment_status";
                $params[':payment_status'] = $payment_status;
            }

            $sql .= ", updated_at = NOW() WHERE id = :id";

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $message = "Status booking berhasil diperbarui.";
            $messageType = "success";

            // Log activity
            log_activity($_SESSION['user']['id'], 'update_booking_status', "Updated booking #{$booking_id} to {$booking_status}");

        } catch (Exception $e) {
            $message = "Gagal memperbarui status: " . $e->getMessage();
            $messageType = "error";
        }
    } else {
        $message = "Data tidak valid. Gagal memperbarui status.";
        $messageType = "error";
    }
}

// Handle booking deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_booking'])) {
    $booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

    if ($booking_id) {
        try {
            $stmt = $db->prepare("DELETE FROM bookings WHERE id = :id");
            $stmt->execute([':id' => $booking_id]);

            $message = "Booking berhasil dihapus.";
            $messageType = "success";

            log_activity($_SESSION['user']['id'], 'delete_booking', "Deleted booking #{$booking_id}");

        } catch (Exception $e) {
            $message = "Gagal menghapus booking: " . $e->getMessage();
            $messageType = "error";
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query with filters
$sql = "SELECT 
            b.id, 
            b.user_id, 
            b.kos_id, 
            b.booking_code,
            b.full_name,
            b.email,
            b.phone,
            b.check_in_date,
            b.duration_months,
            b.total_price,
            b.admin_fee,
            b.payment_method,
            b.booking_status,
            b.payment_status,
            b.notes,
            b.created_at,
            b.updated_at,
            COALESCE(u.name, 'User tidak ditemukan') AS user_name,
            COALESCE(k.name, 'Kos tidak ditemukan') AS kos_name,
            COALESCE(k.address, '') AS kos_address,
            COALESCE(l.city, '') AS kos_city
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN kos k ON b.kos_id = k.id
        LEFT JOIN locations l ON k.location_id = l.id
        WHERE 1=1";

$params = [];

if ($status_filter) {
    $sql .= " AND b.booking_status = :status";
    $params[':status'] = $status_filter;
}

if ($date_from) {
    $sql .= " AND DATE(b.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(b.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($search_term) {
    $sql .= " AND (b.booking_code LIKE :search OR b.full_name LIKE :search OR b.email LIKE :search OR k.name LIKE :search)";
    $params[':search'] = "%{$search_term}%";
}

$sql .= " ORDER BY b.created_at DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = "Error fetching bookings: " . $e->getMessage();
    $messageType = "error";
    $bookings = [];
}

// Get booking statistics
try {
    $stats_sql = "SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN booking_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN booking_status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_count,
                    SUM(CASE WHEN booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count,
                    SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as total_revenue
                  FROM bookings";
    $stats = $db->query($stats_sql)->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = [
        'total_bookings' => 0,
        'pending_count' => 0,
        'confirmed_count' => 0,
        'cancelled_count' => 0,
        'total_revenue' => 0
    ];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking - Admin TemanKosan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #00c851;
            --secondary-color: #ff69b4;
            --accent-color: #ff1493;
            --dark-color: #2c3e50;
            --light-color: #f8f9fa;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-500: #adb5bd;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
            --shadow-sm: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            --shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
            --border-radius: 0.75rem;
            --border-radius-lg: 1rem;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--gray-800);
            background-color:rgb(225, 162, 202);
        }

        /* Admin Layout */
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .admin-sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--dark-color), #34495e);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin-bottom: 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.5rem;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: var(--transition);
            border-left: 3px solid transparent;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-left-color: var(--primary-color);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .admin-main {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .admin-header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 2px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .stat-card.total { border-color: rgba(0, 200, 81, 0.2); }
        .stat-card.pending { border-color: rgba(255, 193, 7, 0.2); }
        .stat-card.confirmed { border-color: rgba(0, 200, 81, 0.2); }
        .stat-card.cancelled { border-color: rgba(220, 53, 69, 0.2); }
        .stat-card.revenue { border-color: rgba(255, 105, 180, 0.2); }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .stat-label {
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Message Alert */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .alert.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Filters */
        .filters {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 200px 200px 200px auto;
            gap: 1rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-control {
            padding: 0.75rem 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 200, 81, 0.1);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-weight: 600;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 200, 81, 0.3);
        }

        .btn-secondary {
            background: var(--gray-200);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-300);
        }

        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #e83e8c);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        /* Table */
        .table-container {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .table th {
            background: var(--gray-50);
            font-weight: 700;
            color: var(--gray-800);
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr:hover {
            background: var(--gray-50);
        }

        /* Status Badges */
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-unpaid {
            background: #f8d7da;
            color: #721c24;
        }

        .booking-code {
            font-family: monospace;
            background: var(--gray-100);
            padding: 0.2rem 0.5rem;
            border-radius: 5px;
            font-weight: 600;
        }

        .price {
            font-weight: 700;
            color: var(--primary-color);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray-600);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--gray-800);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-main {
                margin-left: 0;
                padding: 1rem;
            }

            .filter-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-header">
                <a href="dashboard.php" class="sidebar-logo">
                    <i class="fas fa-home"></i>
                    TemanKosan Admin
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="manage-kos.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        <span>Kelola Kos</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="manage-users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        <span>Kelola User</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="manage-bookings.php" class="nav-link active">
                        <i class="fas fa-calendar-check"></i>
                        <span>Kelola Booking</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="account.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        <span>Profil Saya</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="admin-header">
                <h1 class="page-title">
                    <i class="fas fa-calendar-check"></i>
                    Kelola Booking
                </h1>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card total">
                    <div class="stat-icon">📊</div>
                    <div class="stat-number"><?php echo number_format($stats['total_bookings']); ?></div>
                    <div class="stat-label">Total Booking</div>
                </div>

                <div class="stat-card pending">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-number"><?php echo number_format($stats['pending_count']); ?></div>
                    <div class="stat-label">Pending</div>
                </div>

                <div class="stat-card confirmed">
                    <div class="stat-icon">✅</div>
                    <div class="stat-number"><?php echo number_format($stats['confirmed_count']); ?></div>
                    <div class="stat-label">Dikonfirmasi</div>
                </div>

                <div class="stat-card cancelled">
                    <div class="stat-icon">❌</div>
                    <div class="stat-number"><?php echo number_format($stats['cancelled_count']); ?></div>
                    <div class="stat-label">Dibatalkan</div>
                </div>

                <div class="stat-card revenue">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number">Rp <?php echo number_format($stats['total_revenue'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Total Pendapatan</div>
                </div>
            </div>

            <!-- Message Alert -->
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $messageType; ?>">
                    <span><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?></span>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="filter-row">
                    <div class="form-group">
                        <label class="form-label">Cari Booking</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Kode booking, nama, email, kos..." 
                               value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Semua Status</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Dikonfirmasi</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Dibatalkan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Dari Tanggal</label>
                        <input type="date" name="date_from" class="form-control" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Sampai Tanggal</label>
                        <input type="date" name="date_to" class="form-control" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Booking Table -->
            <div class="table-container">
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-check"></i>
                        <h3>Tidak ada booking ditemukan</h3>
                        <p>Belum ada booking yang sesuai dengan filter yang dipilih.</p>
                    </div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Kode Booking</th>
                                <th>Pelanggan</th>
                                <th>Kos</th>
                                <th>Check-in</th>
                                <th>Durasi</th>
                                <th>Total Harga</th>
                                <th>Status Booking</th>
                                <th>Status Pembayaran</th>
                                <th>Tanggal Booking</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>
                                    <span class="booking-code"><?php echo htmlspecialchars($booking['booking_code']); ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['full_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['email']); ?></small><br>
                                    <small><?php echo htmlspecialchars($booking['phone']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($booking['kos_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($booking['kos_address']); ?></small>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($booking['check_in_date'])); ?></td>
                                <td><?php echo $booking['duration_months']; ?> bulan</td>
                                <td>
                                    <span class="price">Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                                        <?php echo ucfirst($booking['booking_status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $booking['payment_status'] ?? 'unpaid'; ?>">
                                        <?php echo ucfirst($booking['payment_status'] ?? 'unpaid'); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y H:i', strtotime($booking['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <button type="button" class="btn btn-warning btn-sm" 
                                                onclick="openUpdateModal(<?php echo $booking['id']; ?>, '<?php echo $booking['booking_status']; ?>', '<?php echo $booking['payment_status'] ?? 'unpaid'; ?>')">
                                            <i class="fas fa-edit"></i>
                                            Edit
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" 
                                                onclick="openDeleteModal(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['booking_code']); ?>')">
                                            <i class="fas fa-trash"></i>
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Update Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-edit"></i>
                    Update Status Booking
                </h3>
            </div>
            <form method="POST" id="updateForm">
                <input type="hidden" name="booking_id" id="updateBookingId">

                <div class="form-group">
                    <label for="updateBookingStatus" class="form-label">Status Booking</label>
                    <select name="booking_status" id="updateBookingStatus" class="form-control" required>
                        <option value="pending">Pending</option>
                        <option value="confirmed">Dikonfirmasi</option>
                        <option value="cancelled">Dibatalkan</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="updatePaymentStatus" class="form-label">Status Pembayaran</label>
                    <select name="payment_status" id="updatePaymentStatus" class="form-control">
                        <option value="unpaid">Belum Dibayar</option>
                        <option value="paid">Sudah Dibayar</option>
                        <option value="refunded">Refund</option>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('updateModal')">
                        <i class="fas fa-times"></i>
                        Batal
                    </button>
                    <button type="submit" name="update_booking" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-trash"></i>
                    Hapus Booking
                </h3>
            </div>
            <p>Apakah Anda yakin ingin menghapus booking <strong id="deleteBookingCode"></strong>?</p>
            <p><small>Tindakan ini tidak dapat dibatalkan.</small></p>

            <form method="POST" id="deleteForm">
                <input type="hidden" name="booking_id" id="deleteBookingId">

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">
                        <i class="fas fa-times"></i>
                        Batal
                    </button>
                    <button type="submit" name="delete_booking" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Hapus Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUpdateModal(bookingId, bookingStatus, paymentStatus) {
            document.getElementById('updateBookingId').value = bookingId;
            document.getElementById('updateBookingStatus').value = bookingStatus;
            document.getElementById('updatePaymentStatus').value = paymentStatus;
            document.getElementById('updateModal').classList.add('show');
        }

        function openDeleteModal(bookingId, bookingCode) {
            document.getElementById('deleteBookingId').value = bookingId;
            document.getElementById('deleteBookingCode').textContent = bookingCode;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('show');
            }
        });

        // Escape key to close modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const openModal = document.querySelector('.modal.show');
                if (openModal) {
                    openModal.classList.remove('show');
                }
            }
        });
    </script>
</body>
</html>