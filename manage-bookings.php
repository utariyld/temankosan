<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$message = "";
$error = "";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();

    // Handle booking status update
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
                
                // Add timestamp for confirmed bookings
                if ($booking_status === 'confirmed') {
                    $sql .= ", confirmed_at = NOW()";
                } elseif ($booking_status === 'cancelled') {
                    $sql .= ", cancelled_at = NOW()";
                }
                
                $sql .= ", updated_at = NOW() WHERE id = :id";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                
                $message = "Status booking berhasil diperbarui.";
                
                // Log activity
                log_activity($_SESSION['user']['id'], 'booking_status_updated', 
                    "Updated booking #{$booking_id} status to {$booking_status}");
                
            } catch (PDOException $e) {
                $error = "Gagal memperbarui status: " . $e->getMessage();
            }
        } else {
            $error = "Data tidak valid. Gagal memperbarui status.";
        }
    }

    // Get all bookings with detailed information
    $sql = "SELECT 
                b.id, 
                b.booking_code,
                b.user_id, 
                b.kos_id, 
                b.full_name,
                b.email,
                b.phone,
                b.check_in_date,
                b.duration_months,
                b.total_amount,
                b.payment_method,
                b.payment_status,
                b.booking_status, 
                b.created_at,
                u.name AS user_name,
                u.email AS user_email,
                k.name AS kos_name,
                k.address AS kos_address,
                CONCAT(l.city, ', ', l.district) AS kos_location
            FROM bookings b
            LEFT JOIN users u ON b.user_id = u.id
            LEFT JOIN kos k ON b.kos_id = k.id
            LEFT JOIN locations l ON k.location_id = l.id
            ORDER BY b.created_at DESC";
    
    $bookings = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
    $bookings = [];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking - Admin TemanKosan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: #f8f9fa;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: linear-gradient(135deg, #00c851, #00a844);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .table-header {
            background: #f8f9fa;
            padding: 1.5rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table-header h2 {
            color: #495057;
            font-size: 1.5rem;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse;
        }
        
        th, td { 
            padding: 12px 15px; 
            text-align: left; 
            border-bottom: 1px solid #dee2e6;
        }
        
        th { 
            background-color: #f8f9fa;
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        td {
            vertical-align: middle;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-active { background: #cce5ff; color: #004085; }
        .status-completed { background: #e2e3e5; color: #383d41; }
        .status-expired { background: #343a40; color: #fff; }
        
        .payment-pending { background: #ffeaa7; color: #6c5700; }
        .payment-paid { background: #55a3ff; color: #fff; }
        .payment-failed { background: #ff6b6b; color: #fff; }
        
        .form-inline { 
            display: flex; 
            align-items: center; 
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .form-inline select, .form-inline button {
            padding: 6px 12px;
            font-size: 0.9rem;
            border-radius: 6px;
            border: 1px solid #ced4da;
        }
        
        .form-inline select {
            background: white;
            min-width: 120px;
        }
        
        .form-inline button {
            background: #00c851;
            color: white;
            border: 1px solid #00c851;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .form-inline button:hover {
            background: #00a844;
            transform: translateY(-1px);
        }
        
        .booking-code {
            font-family: 'Courier New', monospace;
            background: #f8f9fa;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-weight: bold;
            color: #495057;
        }
        
        .user-info {
            line-height: 1.4;
        }
        
        .user-name {
            font-weight: 600;
            color: #495057;
        }
        
        .user-email {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .kos-info {
            line-height: 1.4;
        }
        
        .kos-name {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.25rem;
        }
        
        .kos-location {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .amount {
            font-weight: 600;
            color: #00c851;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header { padding: 1rem; }
            .header h1 { font-size: 1.5rem; }
            th, td { padding: 8px 10px; font-size: 0.9rem; }
            .form-inline { flex-direction: column; align-items: stretch; }
            .form-inline select, .form-inline button { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-clipboard-list"></i> Kelola Booking</h1>
            <p>Pantau dan kelola semua booking yang masuk ke sistem</p>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <div class="table-header">
                <h2><i class="fas fa-list"></i> Daftar Booking</h2>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Kode Booking</th>
                            <th>Penyewa</th>
                            <th>Kos</th>
                            <th>Check-in</th>
                            <th>Durasi</th>
                            <th>Total</th>
                            <th>Pembayaran</th>
                            <th>Status Booking</th>
                            <th>Tanggal Booking</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($bookings) === 0): ?>
                            <tr>
                                <td colspan="10" class="no-data">
                                    <i class="fas fa-inbox"></i>
                                    <div>Belum ada data booking.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <span class="booking-code"><?= htmlspecialchars($booking['booking_code']) ?></span>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-name"><?= htmlspecialchars($booking['full_name']) ?></div>
                                            <div class="user-email"><?= htmlspecialchars($booking['email']) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="kos-info">
                                            <div class="kos-name"><?= htmlspecialchars($booking['kos_name'] ?? 'Kos tidak ditemukan') ?></div>
                                            <div class="kos-location"><?= htmlspecialchars($booking['kos_location'] ?? '-') ?></div>
                                        </div>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($booking['check_in_date'])) ?></td>
                                    <td><?= $booking['duration_months'] ?> bulan</td>
                                    <td>
                                        <span class="amount"><?= format_currency($booking['total_amount']) ?></span>
                                    </td>
                                    <td>
                                        <span class="status-badge payment-<?= $booking['payment_status'] ?>">
                                            <?= ucfirst($booking['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $booking['booking_status'] ?>">
                                            <i class="fas fa-circle"></i>
                                            <?= ucfirst($booking['booking_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($booking['created_at'])) ?></td>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="booking_id" value="<?= $booking['id'] ?>">
                                            
                                            <select name="booking_status" required>
                                                <option value="pending" <?= $booking['booking_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="confirmed" <?= $booking['booking_status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                                <option value="active" <?= $booking['booking_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="completed" <?= $booking['booking_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="cancelled" <?= $booking['booking_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                <option value="expired" <?= $booking['booking_status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                                            </select>
                                            
                                            <select name="payment_status">
                                                <option value="">-Pembayaran-</option>
                                                <option value="pending" <?= $booking['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                <option value="paid" <?= $booking['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                                <option value="failed" <?= $booking['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                                                <option value="refunded" <?= $booking['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                                            </select>
                                            
                                            <button type="submit" name="update_booking">
                                                <i class="fas fa-save"></i> Update
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
