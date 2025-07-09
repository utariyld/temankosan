<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/admin-functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$success = '';
$error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = sanitize_input($_POST['name'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');

    // Validasi input
    if (empty($name)) {
        $error = 'Nama tidak boleh kosong!';
    } elseif (empty($phone)) {
        $error = 'Nomor telepon tidak boleh kosong!';
    } else {
        // Update ke database
        $pdo = get_db_connection();
        if ($pdo) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");

                if ($stmt->execute([$name, $phone, $user['id']])) {
                    // Perbarui data session
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['phone'] = $phone;
                    $user['name'] = $name;
                    $user['phone'] = $phone;

                    // Log aktivitas
                    log_activity_admin($user['id'], 'profile_updated', 'User updated profile information');

                    $success = 'Profil berhasil diperbarui!';
                } else {
                    $error = 'Gagal memperbarui profil. Silakan coba lagi.';
                }
            } catch (PDOException $e) {
                error_log("Profile update error: " . $e->getMessage());
                $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            }
        } else {
            $error = 'Koneksi database gagal. Silakan coba lagi.';
        }
    }
}

// Get user's booking history from database
try {
    $bookingHistory = get_user_bookings($user['id']);
} catch (Exception $e) {
    $bookingHistory = [];
    $error = 'Gagal memuat data booking: ' . $e->getMessage();
}

// Calculate statistics
$totalBookings = count($bookingHistory);
$activeBookings = count(array_filter($bookingHistory, function($b) { 
    return $b['booking_status'] === 'confirmed' || $b['booking_status'] === 'pending'; 
}));
$completedBookings = count(array_filter($bookingHistory, function($b) { 
    return $b['booking_status'] === 'completed'; 
}));

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Akun Saya - TemanKosan</title>
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
            background-color:rgb(210, 231, 218);;
        }

        html,body {
            overflow-x: hidden;
            max-width: 100vw;
        }

        /* Navigation */
        .navbar {
            background: white;
            box-shadow: var(--shadow);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-weight: 500;
        }

        .btn-outline {
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-300);
        }

        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        /* Main Content */
        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 2rem;
        }

        .account-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        /* Profile Sidebar */
        .profile-sidebar {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            height: fit-content;
            position: sticky;
            top: 120px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 800;
            margin: 0 auto 1.5rem;
            box-shadow: var(--shadow);
        }

        .profile-info {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-name {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .profile-email {
            color: var(--gray-600);
            margin-bottom: 1rem;
            font-size: 0.95rem;
        }

        .profile-role {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .role-admin {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #1976d2;
        }

        .role-member {
            background: linear-gradient(135deg, #e8f5e8, #c8e6c9);
            color: var(--primary-color);
        }

        .role-owner {
            background: linear-gradient(135deg, #fff3e0, #ffcc02);
            color: #f57c00;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--gray-100);
            border-radius: var(--border-radius);
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }

        /* Main Content Area */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .content-card {
            background: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 2rem;
        }

        .section-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-800);
        }

        .profile-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .form-group input,
        .form-group .value {
            padding: 0.875rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 200, 81, 0.1);
        }

        .form-group .value {
            background: var(--gray-100);
            color: var(--gray-600);
            border-color: var(--gray-200);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: fit-content;
            margin-top: 1.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 200, 81, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: fit-content;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 105, 180, 0.3);
        }

        /* Booking History */
        .booking-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .booking-table th,
        .booking-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .booking-table th {
            background: var(--gray-100);
            font-weight: 600;
            color: var(--gray-700);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-active {
            background: #c6f6d5;
            color: #2f855a;
        }

        .status-completed {
            background: #e2e8f0;
            color: #4a5568;
        }

        .status-cancelled {
            background: #fed7d7;
            color: #c53030;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-completed {
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

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .account-layout {
                grid-template-columns: 1fr;
            }

            .profile-form {
                grid-template-columns: 1fr;
            }

            .booking-table {
                font-size: 0.9rem;
            }

            .booking-table th,
            .booking-table td {
                padding: 0.75rem 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-home"></i>
                TemanKosan
            </a>
            <div class="nav-actions">
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Kembali ke Beranda
                </a>
                <a href="logout.php" class="btn btn-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <div class="account-layout">
            <!-- Profile Sidebar -->
            <div class="profile-sidebar">
                <div class="profile-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                    <div class="profile-role role-<?php echo $user['role']; ?>">
                        <i class="fas fa-<?php echo $user['role'] === 'admin' ? 'crown' : ($user['role'] === 'owner' ? 'home' : 'user'); ?>"></i>
                        <?php echo ucfirst($user['role']); ?>
                    </div>
                </div>

                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $totalBookings; ?></div>
                        <div class="stat-label">Total Booking</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $activeBookings; ?></div>
                        <div class="stat-label">Booking Aktif</div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Profile Information -->
                <div class="content-card">
                    <h2 class="section-title">
                        <i class="fas fa-user-edit"></i>
                        Informasi Profil
                    </h2>
                    <form class="profile-form" method="POST">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email</label>
                            <div class="value"><?php echo htmlspecialchars($user['email']); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Nomor Telepon</label>
                            <input type="tel" name="phone" placeholder="Masukkan nomor telepon" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label>Role</label>
                            <div class="value"><?php echo ucfirst($user['role']); ?></div>
                        </div>
                        
                        <div class="form-group full-width">
                            <button type="submit" class="btn-primary">
                                <i class="fas fa-save"></i>
                                Simpan Perubahan
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Booking History -->
                <?php if (isset($user['role']) && $user['role'] === 'member'): ?>
                <div class="content-card">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Riwayat Booking
                    </h2>
                    
                    <?php if (empty($bookingHistory)): ?>
                        <div style="text-align: center; padding: 3rem; color: var(--gray-600);">
                            <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Belum ada riwayat booking</p>
                            <a href="index.php" class="btn-secondary" style="margin-top: 1rem;">
                                <i class="fas fa-search"></i>
                                Cari Kos Sekarang
                            </a>
                        </div>
                    <?php else: ?>
                        <table class="booking-table">
                            <thead>
                                <tr>
                                    <th>Kode Booking</th>
                                    <th>Nama Kos</th>
                                    <th>Check-in</th>
                                    <th>Durasi</th>
                                    <th>Total</th>
                                    <th>Status Booking</th>
                                    <th>Status Bayar</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookingHistory as $booking): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong></td>
                                        <td>

                                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($booking['kos_name']); ?></div>
                                                    <div style="font-size: 0.8rem; color: var(--gray-600);">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                        <?php echo htmlspecialchars($booking['kos_address']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($booking['check_in_date'])); ?></td>
                                        <td><?php echo $booking['duration_months']; ?> bulan</td>
                                        <td><strong>Rp <?php echo number_format($booking['total_price'], 0, ',', '.'); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                                                <?php 
                                                $statusLabels = [
                                                    'pending' => 'Menunggu',
                                                    'confirmed' => 'Dikonfirmasi',
                                                    'completed' => 'Selesai',
                                                    'cancelled' => 'Dibatalkan'
                                                ];
                                                echo $statusLabels[$booking['booking_status']] ?? ucfirst($booking['booking_status']);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $booking['payment_status']; ?>">
                                                <?php 
                                                $paymentLabels = [
                                                    'pending' => 'Belum Bayar',
                                                    'paid' => 'Lunas',
                                                    'failed' => 'Gagal'
                                                ];
                                                echo $paymentLabels[$booking['payment_status']] ?? ucfirst($booking['payment_status']);
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 0.5rem;">
                                                <?php if ($booking['booking_status'] === 'pending' && $booking['payment_status'] === 'pending'): ?>
                                                    <a href="payment.php?booking_id=<?php echo $booking['id']; ?>&code=<?php echo $booking['booking_code']; ?>" 
                                                       class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.8rem; background: var(--secondary-color); color: white; text-decoration: none; border-radius: 4px;">
                                                        <i class="fas fa-credit-card"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                    <!-- Admin Features -->
                    <div class="content-card">
                        <h2 class="section-title">
                            <i class="fas fa-tools"></i>
                            Panel Admin
                        </h2>
                        <p style="margin-bottom: 2rem; color: var(--gray-600);">
                            Sebagai admin, Anda memiliki akses untuk mengelola sistem TemanKosan.
                        </p>
                        
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <a href="add-kos.php" class="btn-primary">
                                <i class="fas fa-tachometer-alt"></i>
                                ➕ Tambah Kos Baru
                            </a>
                            <a href="index.php" class="btn-primary">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard Admin
                            </a>
                            <a href="admin/manage-kos.php" class="btn-secondary">
                                <i class="fas fa-home"></i>
                                Kelola Kos
                            </a>
                            <a href="admin/manage-users.php" class="btn-secondary">
                                <i class="fas fa-users"></i>
                                Kelola User
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($user['role'] === 'owner'): ?>
                    <!-- Owner Features -->
                    <div class="content-card">
                        <h2 class="section-title">
                            <i class="fas fa-building"></i>
                            Panel Pemilik Kos
                        </h2>
                        <p style="margin-bottom: 2rem; color: var(--gray-600);">
                            Kelola kos Anda dan pantau booking dari penyewa.
                        </p>
                        
                        <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                            <a href="owner/add-kos.php" class="btn-primary">
                                <i class="fas fa-plus"></i>
                                Tambah Kos Baru
                            </a>
                            <a href="owner/my-kos.php" class="btn-secondary">
                                <i class="fas fa-list"></i>
                                Kos Saya
                            </a>
                            <a href="owner/bookings.php" class="btn-secondary">
                                <i class="fas fa-calendar-check"></i>
                                Booking Masuk
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
