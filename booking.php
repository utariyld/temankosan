<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Get kos ID from URL
$kosId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get kos data from database
$kos = get_kos_by_id_summary($kosId);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token keamanan tidak valid';
    } else {
        $fullName = sanitize_input($_POST['full_name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $checkInDate = $_POST['check_in_date'] ?? '';
        $duration = (int)($_POST['duration'] ?? 1);
        $notes = sanitize_input($_POST['notes'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? '';
        
        // Validation
        $errors = [];
        
        if (empty($fullName) || strlen($fullName) < 2) {
            $errors[] = 'Nama lengkap harus diisi minimal 2 karakter';
        }
        
        if (empty($email) || !validate_email($email)) {
            $errors[] = 'Email tidak valid';
        }
        
        if (empty($phone) || !validate_phone($phone)) {
            $errors[] = 'Nomor telepon tidak valid';
        }
        
        if (empty($checkInDate)) {
            $errors[] = 'Tanggal check-in harus diisi';
        } elseif (strtotime($checkInDate) <= time()) {
            $errors[] = 'Tanggal check-in harus minimal besok';
        }
        
        if ($duration < 1 || $duration > 12) {
            $errors[] = 'Durasi sewa harus antara 1-12 bulan';
        }
        
        if (empty($paymentMethod)) {
            $errors[] = 'Metode pembayaran harus dipilih';
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            // Calculate total
            $subtotal = $kos['price'] * $duration;
            $adminFee = 20000;
            $total = $subtotal + $adminFee;
            
            // Prepare booking data
            $bookingData = [
                'user_id' => $_SESSION['user']['id'],
                'kos_id' => $kosId,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $phone,
                'check_in_date' => $checkInDate,
                'duration_months' => $duration,
                'total_price' => $total,
                'admin_fee' => $adminFee,
                'payment_method' => $paymentMethod,
                'notes' => $notes
            ];
            
            // Save booking to database
            $result = create_booking($bookingData);
            
            if ($result['success']) {
                // Log booking activity
                log_activity($_SESSION['user']['id'], 'booking_created',
                    "Booking created: {$result['booking_code']} for kos: {$kos['name']}");
                
                // Store booking data in session for payment page
                $_SESSION['booking_data'] = array_merge($bookingData, [
                    'booking_id' => $result['booking_id'],
                    'booking_code' => $result['booking_code'],
                    'kos_name' => $kos['name'],
                    'kos_location' => $kos['city'] . ', ' . $kos['district'],
                    'subtotal' => $subtotal
                ]);
                
                // Set success message
                $_SESSION['success'] = 'Booking berhasil dibuat! Silakan lanjutkan pembayaran.';
                
                // Redirect to payment page
                header("Location: payment.php?booking_id={$result['booking_id']}&code={$result['booking_code']}");
                exit;
            } else {
                $error = $result['message'] ?? 'Gagal membuat booking. Silakan coba lagi.';
            }
        }
    }
}

$adminFee = 20000;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Kos - TemanKosan</title>
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Main Content */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--gray-800);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray-600);
            font-size: 1.1rem;
        }

        .booking-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
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

        /* Booking Form */
        .booking-form {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
        }

        .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 200, 81, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            background: white;
        }

        .payment-option:hover {
            border-color: var(--primary-color);
            background: rgba(0, 200, 81, 0.05);
        }

        .payment-option input[type="radio"] {
            width: auto;
            margin: 0;
        }

        .payment-option.selected {
            border-color: var(--primary-color);
            background: rgba(0, 200, 81, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 200, 81, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        /* Booking Summary */
        .booking-summary {
            position: sticky;
            top: 120px;
            height: fit-content;
        }

        .summary-card {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            box-shadow: var(--shadow-lg);
        }

        .kos-preview {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .kos-image {
            width: 80px;
            height: 80px;
            border-radius: var(--border-radius);
            overflow: hidden;
            flex-shrink: 0;
        }

        .kos-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .kos-info h3 {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--gray-800);
        }

        .kos-info p {
            color: var(--gray-600);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .kos-price {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 1rem;
        }

        .summary-details {
            margin-bottom: 2rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.5rem 0;
        }

        .summary-row.total {
            font-weight: 700;
            font-size: 1.2rem;
            padding-top: 1rem;
            border-top: 2px solid var(--gray-200);
            color: var(--primary-color);
        }

        .booking-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: var(--border-radius);
            padding: 1rem;
            font-size: 0.9rem;
            color: #856404;
        }

        .booking-note strong {
            display: block;
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .booking-container {
                grid-template-columns: 1fr;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .kos-preview {
                flex-direction: column;
                text-align: center;
            }

            .kos-image {
                align-self: center;
            }

            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali ke Beranda</span>
            </a>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-calendar-check"></i>
                Booking Kos
            </h1>
            <p class="page-subtitle">Lengkapi data booking Anda untuk melanjutkan ke pembayaran</p>
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

        <div class="booking-container">
            <!-- Booking Form -->
            <div class="booking-form">
                <form method="POST" novalidate id="bookingForm">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    
                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-user"></i>
                            Data Penyewa
                        </h2>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="fullName">Nama Lengkap <span class="required">*</span></label>
                                <input type="text" id="fullName" name="full_name"
                                       value="<?php echo htmlspecialchars($_SESSION['user']['name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email <span class="required">*</span></label>
                                <input type="email" id="email" name="email"
                                       value="<?php echo htmlspecialchars($_SESSION['user']['email']); ?>" required>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="phone">Nomor Telepon <span class="required">*</span></label>
                            <input type="tel" id="phone" name="phone" placeholder="Contoh: 08123456789" required>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="checkInDate">Tanggal Masuk <span class="required">*</span></label>
                                <input type="date" id="checkInDate" name="check_in_date"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="duration">Durasi Sewa <span class="required">*</span></label>
                                <select id="duration" name="duration" required onchange="updateSummary()">
                                    <option value="1">1 Bulan</option>
                                    <option value="3">3 Bulan</option>
                                    <option value="6">6 Bulan</option>
                                    <option value="12">12 Bulan</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group full-width">
                            <label for="notes">Catatan Tambahan</label>
                            <textarea id="notes" name="notes"
                                      placeholder="Tambahkan catatan atau permintaan khusus (opsional)..."></textarea>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2 class="section-title">
                            <i class="fas fa-credit-card"></i>
                            Metode Pembayaran
                        </h2>
                        <div class="payment-methods">
                            <label class="payment-option selected">
                                <input type="radio" name="payment_method" value="transfer" checked>
                                <i class="fas fa-university"></i>
                                <span>Transfer Bank</span>
                            </label>
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="ewallet">
                                <i class="fas fa-mobile-alt"></i>
                                <span>E-Wallet (OVO, GoPay, DANA)</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="submitBtn">
                        <i class="fas fa-arrow-right"></i>
                        <span>Lanjutkan ke Pembayaran</span>
                    </button>
                </form>
            </div>

            <!-- Booking Summary -->
            <div class="booking-summary">
                <div class="summary-card">
                    <h2 class="section-title">
                        <i class="fas fa-receipt"></i>
                        Ringkasan Booking
                    </h2>
                    
                    <div class="kos-preview">
                        <div class="kos-image">
                            <img src="<?php echo htmlspecialchars($kos['primary_image'] ?? 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=400&h=300&fit=crop'); ?>" 
                                 alt="<?php echo htmlspecialchars($kos['name']); ?>">
                        </div>
                        <div class="kos-info">
                            <h3><?php echo htmlspecialchars($kos['name']); ?></h3>
                            <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($kos['address'] ?? ($kos['city'] . ', ' . $kos['district'])); ?></p>
                            <div class="kos-price">Rp <?php echo number_format($kos['price'], 0, ',', '.'); ?>/bulan</div>
                        </div>
                    </div>

                    <div class="summary-details">
                        <div class="summary-row">
                            <span>Harga per bulan</span>
                            <span id="monthlyPrice">Rp <?php echo number_format($kos['price'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Durasi sewa</span>
                            <span id="durationText">1 bulan</span>
                        </div>
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <span id="subtotal">Rp <?php echo number_format($kos['price'], 0, ',', '.'); ?></span>
                        </div>
                        <div class="summary-row">
                            <span>Biaya admin</span>
                            <span id="adminFee">Rp <?php echo number_format($adminFee, 0, ',', '.'); ?></span>
                        </div>
                        <div class="summary-row total">
                            <span>Total Pembayaran</span>
                            <span id="totalPrice">Rp <?php echo number_format($kos['price'] + $adminFee, 0, ',', '.'); ?></span>
                        </div>
                    </div>

                    <div class="booking-note">
                        <strong><i class="fas fa-info-circle"></i> Catatan Penting:</strong>
                        Pembayaran harus dilakukan dalam 24 jam setelah booking untuk mengkonfirmasi reservasi Anda. 
                        Setelah pembayaran berhasil, Anda akan menerima konfirmasi via email.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const kosPrice = <?php echo $kos['price']; ?>;
        const adminFee = <?php echo $adminFee; ?>;

        // Update booking summary when duration changes
        function updateSummary() {
            const duration = parseInt(document.getElementById('duration').value);
            const subtotal = kosPrice * duration;
            const total = subtotal + adminFee;

            document.getElementById('durationText').textContent = `${duration} bulan`;
            document.getElementById('subtotal').textContent = `Rp ${subtotal.toLocaleString('id-ID')}`;
            document.getElementById('totalPrice').textContent = `Rp ${total.toLocaleString('id-ID')}`;
        }

        // Setup payment method selection
        document.addEventListener('DOMContentLoaded', function() {
            const paymentOptions = document.querySelectorAll('.payment-option');
            
            paymentOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    paymentOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Check the radio button
                    this.querySelector('input[type="radio"]').checked = true;
                });
            });

            // Form validation and submission
            const form = document.getElementById('bookingForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                const phone = document.getElementById('phone').value.trim();
                const checkInDate = document.getElementById('checkInDate').value;
                
                if (phone && !/^[0-9+\-\s()]{10,}$/.test(phone)) {
                    e.preventDefault();
                    alert('Format nomor telepon tidak valid!');
                    return;
                }
                
                if (checkInDate && new Date(checkInDate) <= new Date()) {
                    e.preventDefault();
                    alert('Tanggal check-in harus minimal besok!');
                    return;
                }

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Memproses...</span>';
            });

            // Set default check-in date to tomorrow
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('checkInDate').value = tomorrow.toISOString().split('T')[0];
        });
    </script>
</body>
</html>
