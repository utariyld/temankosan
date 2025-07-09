<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
require_login();

// Get booking data
$bookingId = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$bookingCode = isset($_GET['code']) ? sanitize_input($_GET['code']) : '';

if (!$bookingId || !$bookingCode) {
    $_SESSION['error'] = 'Data booking tidak valid';
    header('Location: account.php');
    exit;
}

// Get booking details from database
$booking = get_booking_by_id($bookingId, $_SESSION['user']['id']);

if (!$booking || $booking['booking_code'] !== $bookingCode) {
    $_SESSION['error'] = 'Booking tidak ditemukan atau tidak dapat diakses';
    header('Location: account.php');
    exit;
}

$success = '';
$error = '';

// Handle payment confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'confirm_payment') {
            // In real application, integrate with payment gateway
            // For demo, we'll just mark as paid
            
            if ($booking['booking_status'] === 'pending') {
                if (update_booking_status_payment($bookingId, 'confirmed', 'paid')) {
                    log_activity($_SESSION['user']['id'], 'payment_completed', 
                        "Payment completed for booking: {$booking['booking_code']}");
                    /*
                    // Send confirmation email (placeholder)
                    send_email_notification(
                        $booking['email'],
                        'Konfirmasi Pembayaran - TemanKosan',
                        "Pembayaran untuk booking {$booking['booking_code']} telah dikonfirmasi.",
                        'payment_confirmation'
                    );
                    */
                    
                    $success = 'Pembayaran berhasil dikonfirmasi! Booking Anda telah aktif.';
                    
                    // Refresh booking data
                    $booking = get_booking_by_id($bookingId, $_SESSION['user']['id']);
                } else {
                    $error = 'Gagal mengkonfirmasi pembayaran. Silakan coba lagi.';
                }
            } else {
                $error = 'Booking ini sudah diproses sebelumnya.';
            }
        } elseif ($action === 'cancel_booking') {
            if (can_cancel_booking($booking)) {
                if (update_booking_status_payment($bookingId, 'cancelled')) {
                    log_activity($_SESSION['user']['id'], 'booking_cancelled', 
                        "Booking cancelled: {$booking['booking_code']}");
                    
                    $success = 'Booking berhasil dibatalkan.';
                    
                    // Refresh booking data
                    $booking = get_booking_by_id($bookingId, $_SESSION['user']['id']);
                } else {
                    $error = 'Gagal membatalkan booking. Silakan coba lagi.';
                }
            } else {
                $error = 'Booking tidak dapat dibatalkan.';
            }
        }
    }
}

// Get payment methods
$paymentMethods = get_payment_methods();
$currentMethod = $paymentMethods[$booking['payment_method']] ?? null;
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran - TemanKosan</title>
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
            background-color:rgb(210, 231, 218);
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
            align-items: center;
            justify-content: space-between;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        /* Main Content */
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .payment-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .booking-info {
            background: var(--gray-100);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .info-row.total {
            font-weight: 700;
            font-size: 1.2rem;
            color: var(--primary-color);
            border-top: 1px solid var(--gray-300);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .payment-methods {
            margin-bottom: 2rem;
        }

        .method-card {
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: white;
        }

        .method-title {
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bank-details {
            background: var(--gray-100);
            padding: 1rem;
            border-radius: var(--border-radius);
            font-family: monospace;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
            justify-content: center;
            font-size: 1.1rem;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 200, 81, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
        }

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

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-paid {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .payment-success {
            text-align: center;
            padding: 3rem 2rem;
        }

        .success-icon {
            font-size: 4rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .countdown {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: var(--border-radius);
            padding: 1rem;
            margin-bottom: 2rem;
            text-align: center;
            font-weight: 600;
            color: #856404;
        }

        .countdown-timer {
            font-size: 1.2rem;
            color: #dc3545;
            font-weight: 700;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .action-buttons .btn-primary {
            flex: 1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .page-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="account.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali ke Akun</span>
            </a>
            <div>
                <span style="color: var(--gray-600);">Booking: </span>
                <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong>
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

        <div class="payment-card">
            <h1 class="page-title">
                <i class="fas fa-credit-card"></i>
                Pembayaran Booking
            </h1>

            <div class="booking-info">
                <h3 style="margin-bottom: 1rem; color: var(--gray-800);">Detail Booking</h3>
                <div class="info-row">
                    <span>Kode Booking:</span>
                    <strong><?php echo htmlspecialchars($booking['booking_code']); ?></strong>
                </div>
                <div class="info-row">
                    <span>Nama Kos:</span>
                    <span><?php echo htmlspecialchars($booking['kos_name']); ?></span>
                </div>
                <div class="info-row">
                    <span>Lokasi:</span>
                    <span><?php echo htmlspecialchars(($booking['kos_address'] ?? '') . ', ' . ($booking['city'] ?? '')); ?></span>
                </div>
                <div class="info-row">
                    <span>Tanggal Check-in:</span>
                    <span><?php echo format_date($booking['check_in_date']); ?></span>
                </div>
                <div class="info-row">
                    <span>Durasi:</span>
                    <span><?php echo $booking['duration_months']; ?> bulan</span>
                </div>
                <div class="info-row">
                    <span>Status Booking:</span>
                    <?php $statusBadge = get_status_badge($booking['booking_status']); ?>
                    <span class="status-badge status-<?php echo $booking['booking_status']; ?>">
                        <i class="fas fa-<?php echo $statusBadge['icon']; ?>"></i>
                        <?php echo $statusBadge['text']; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span>Status Pembayaran:</span>
                    <?php $paymentBadge = get_status_badge($booking['payment_status'] ?? 'pending'); ?>
                    <span class="status-badge status-<?php echo $booking['payment_status'] ?? 'pending'; ?>">
                        <i class="fas fa-<?php echo $paymentBadge['icon']; ?>"></i>
                        <?php echo $paymentBadge['text']; ?>
                    </span>
                </div>
                <div class="info-row total">
                    <span>Total Pembayaran:</span>
                    <span><?php echo format_currency($booking['total_price']); ?></span>
                </div>
            </div>

            <?php if ($booking['booking_status'] === 'pending'): ?>
                <!-- Countdown Timer -->
                <div class="countdown">
                    <i class="fas fa-clock"></i>
                    <strong>Batas Waktu Pembayaran:</strong><br>
                    <span class="countdown-timer" id="countdown">24:00:00</span>
                    <br><small>Booking akan otomatis dibatalkan jika tidak dibayar dalam 24 jam</small>
                </div>

                <div class="payment-methods">
                    <h3 style="margin-bottom: 1.5rem;">Metode Pembayaran</h3>
                    
                    <?php if ($booking['payment_method'] === 'transfer'): ?>
                        <div class="method-card">
                            <div class="method-title">
                                <i class="fas fa-university"></i>
                                Transfer Bank
                            </div>
                            <p style="margin-bottom: 1rem; color: var(--gray-600);">
                                Silakan transfer ke rekening berikut:
                            </p>
                            <div class="bank-details">
                                <div><strong>Bank BCA</strong></div>
                                <div>No. Rekening: 1234567890</div>
                                <div>Atas Nama: PT TemanKosan Indonesia</div>
                                <div style="margin-top: 0.5rem;">
                                    <strong>Jumlah: <?php echo format_currency($booking['total_price']); ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($booking['payment_method'] === 'ewallet'): ?>
                        <div class="method-card">
                            <div class="method-title">
                                <i class="fas fa-mobile-alt"></i>
                                E-Wallet
                            </div>
                            <div class="bank-details">
                                <div><strong>OVO/GoPay/DANA</strong></div>
                                <div>Nomor: 081234567890</div>
                                <div>Atas Nama: TemanKosan</div>
                                <div style="margin-top: 0.5rem;">
                                    <strong>Jumlah: <?php echo format_currency($booking['total_price']); ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="method-card">
                            <div class="method-title">
                                <i class="fas fa-credit-card"></i>
                                Kartu Kredit/Debit
                            </div>
                            <p style="color: var(--gray-600);">
                                Pembayaran dengan kartu kredit/debit akan segera tersedia.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="action-buttons">
                    <form method="POST" style="flex: 1;">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="confirm_payment">
                        <button type="submit" class="btn-primary" onclick="return confirm('Apakah Anda yakin sudah melakukan pembayaran?')">
                            <i class="fas fa-check"></i>
                            Konfirmasi Pembayaran
                        </button>
                    </form>
                    
                    <?php if (can_cancel_booking($booking)): ?>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="action" value="cancel_booking">
                            <button type="submit" class="btn-danger" onclick="return confirm('Apakah Anda yakin ingin membatalkan booking ini?')">
                                <i class="fas fa-times"></i>
                                Batalkan
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div style="margin-top: 1rem; padding: 1rem; background: #fff3cd; border-radius: var(--border-radius); font-size: 0.9rem; color: #856404;">
                    <strong><i class="fas fa-info-circle"></i> Catatan:</strong>
                    Setelah melakukan pembayaran, klik tombol "Konfirmasi Pembayaran" di atas. 
                    Tim kami akan memverifikasi pembayaran Anda dalam 1x24 jam.
                </div>
            <?php else: ?>
                <div class="payment-success">
                    <?php if ($booking['booking_status'] === 'confirmed'): ?>
                        <i class="fas fa-check-circle success-icon"></i>
                        <h3 style="color: var(--primary-color); margin-bottom: 0.5rem;">Pembayaran Berhasil!</h3>
                        <p style="color: var(--gray-600); margin-bottom: 2rem;">Booking Anda telah dikonfirmasi dan siap digunakan.</p>
                    <?php elseif ($booking['booking_status'] === 'cancelled'): ?>
                        <i class="fas fa-times-circle" style="font-size: 4rem; color: #dc3545; margin-bottom: 1rem;"></i>
                        <h3 style="color: #dc3545; margin-bottom: 0.5rem;">Booking Dibatalkan</h3>
                        <p style="color: var(--gray-600); margin-bottom: 2rem;">Booking ini telah dibatalkan.</p>
                    <?php endif; ?>
                    
                    <a href="account.php" class="btn-primary" style="width: auto; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i>
                        Kembali ke Akun
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Countdown timer
        <?php if ($booking['booking_status'] === 'pending'): ?>
        function startCountdown() {
            const createdAt = new Date('<?php echo $booking['created_at']; ?>');
            const expiryTime = new Date(createdAt.getTime() + 24 * 60 * 60 * 1000); // 24 hours
            
            function updateCountdown() {
                const now = new Date();
                const timeLeft = expiryTime - now;
                
                if (timeLeft <= 0) {
                    document.getElementById('countdown').textContent = '00:00:00';
                    document.querySelector('.countdown').innerHTML = '<i class="fas fa-exclamation-triangle"></i> <strong>Waktu pembayaran telah habis!</strong><br><small>Silakan buat booking baru.</small>';
                    return;
                }
                
                const hours = Math.floor(timeLeft / (1000 * 60 * 60));
                const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
                
                document.getElementById('countdown').textContent = 
                    `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }
            
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
        
        document.addEventListener('DOMContentLoaded', startCountdown);
        <?php endif; ?>
    </script>
</body>
</html>
