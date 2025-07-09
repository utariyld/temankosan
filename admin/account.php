<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/admin-functions.php';

// Check if user is admin
require_admin();

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


// Get admin statistics for profile
$stats = get_dashboard_stats();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Admin - TemanKosan</title>
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

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 200, 81, 0.3);
        }

        .btn-outline {
            background: white;
            color: var(--gray-600);
            border: 2px solid var(--gray-300);
        }

        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
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

        /* Profile Layout */
        .profile-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
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
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 800;
            margin: 0 auto 1.5rem;
            box-shadow: var(--shadow);
        }

        .profile-info {
            text-align: center;
            margin-bottom: 2rem;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .profile-email {
            color: var(--gray-600);
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .profile-role {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            color: #1976d2;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .admin-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem 1rem;
            background: var(--gray-100);
            border-radius: var(--border-radius);
            border: 2px solid transparent;
            transition: var(--transition);
        }

        .stat-item:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        .stat-number {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--gray-600);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            padding: 2.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--gray-800);
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--gray-200);
        }

        .profile-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group .value {
            padding: 1rem;
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
            font-weight: 600;
        }

        .btn-save {
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: white;
            padding: 1rem 2.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: fit-content;
            margin-top: 1.5rem;
            font-size: 1rem;
        }

        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 200, 81, 0.3);
        }

        /* Admin Actions */
        .admin-actions {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            margin-top: 2rem;
        }

        .actions-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 1rem 1.5rem;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            justify-content: center;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .profile-layout {
                grid-template-columns: 1fr;
            }
            
            .profile-sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .admin-sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .admin-main {
                margin-left: 0;
                padding: 1rem;
            }

            .profile-form {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
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
                    <a href="manage-bookings.php" class="nav-link">
                        <i class="fas fa-calendar-check"></i>
                        <span>Kelola Booking</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="account.php" class="nav-link active">
                        <i class="fas fa-user-cog"></i>
                        <span>Profil Admin</span>
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
                    <i class="fas fa-user-cog"></i>
                    Profil Admin
                </h1>
                <div class="header-actions">
                    <span>Selamat datang, <?php echo htmlspecialchars($_SESSION['user']['name']); ?>!</span>
                    <a href="dashboard.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Dashboard
                    </a>
                </div>
            </div>

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

            <div class="profile-layout">
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <div class="profile-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                    <div class="profile-info">
                        <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                        <div class="profile-email"><?php echo htmlspecialchars($user['email']); ?></div>
                        <div class="profile-role">
                            <i class="fas fa-crown"></i>
                            Administrator
                        </div>
                    </div>

                    <div class="admin-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($stats['total_kos'] ?? 0); ?></div>
                            <div class="stat-label">Total Kos</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($stats['total_users'] ?? 0); ?></div>
                            <div class="stat-label">Total User</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($stats['total_bookings'] ?? 0); ?></div>
                            <div class="stat-label">Total Booking</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($stats['pending_bookings'] ?? 0); ?></div>
                            <div class="stat-label">Pending</div>
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
                                <div class="value">
                                    <i class="fas fa-crown"></i>
                                    <?php echo ucfirst($user['role']); ?>
                                </div>
                            </div>
                            
                            <div class="form-group full-width">
                                <button type="submit" class="btn-save">
                                    <i class="fas fa-save"></i>
                                    Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Admin Actions -->
                    <div class="admin-actions">
                        <h3 class="actions-title">
                            <i class="fas fa-tools"></i>
                            Panel Administrasi
                        </h3>
                        <p style="opacity: 0.9; margin-bottom: 0;">
                            Sebagai administrator, Anda memiliki akses penuh untuk mengelola sistem TemanKosan.
                        </p>
                        
                        <div class="actions-grid">
                            <a href="dashboard.php" class="action-btn">
                                <i class="fas fa-tachometer-alt"></i>
                                Dashboard
                            </a>
                            <a href="manage-kos.php" class="action-btn">
                                <i class="fas fa-home"></i>
                                Kelola Kos
                            </a>
                            <a href="manage-users.php" class="action-btn">
                                <i class="fas fa-users"></i>
                                Kelola User
                            </a>
                            <a href="manage-bookings.php" class="action-btn">
                                <i class="fas fa-calendar-check"></i>
                                Kelola Booking
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
