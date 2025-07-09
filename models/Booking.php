<?php
require_once 'models/BaseModel.php';

/**
 * Booking Model
 * Handles booking-related database operations
 */
class Booking extends BaseModel {
    protected $table = 'bookings';
    protected $fillable = [
        'booking_code', 'user_id', 'kos_id', 'full_name', 'email', 'phone',
        'check_in_date', 'check_out_date', 'duration_months', 'subtotal',
        'admin_fee', 'discount_amount', 'total_amount', 'payment_method',
        'payment_status', 'booking_status', 'notes', 'expires_at'
    ];

    /**
     * Create new booking with auto-generated booking code
     */
    public function createBooking($data) {
        $data['booking_code'] = $this->generateBookingCode();
        $data['expires_at'] = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        return $this->create($data);
    }

    /**
     * Get booking with kos and user details
     */
    public function getBookingWithDetails($id) {
        $sql = "SELECT b.*, 
                       k.name as kos_name, k.address as kos_address, k.price as kos_price,
                       u.name as user_name, u.email as user_email,
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as kos_image
                FROM {$this->table} b
                LEFT JOIN kos k ON b.kos_id = k.id
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.id = ?";
        
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * Get booking by booking code
     */
    public function getBookingByCode($bookingCode) {
        return $this->findAll(['booking_code' => $bookingCode])[0] ?? null;
    }

    /**
     * Get user bookings
     */
    public function getUserBookings($userId, $status = null) {
        $conditions = ['user_id' => $userId];
        if ($status) {
            $conditions['booking_status'] = $status;
        }
        
        $sql = "SELECT b.*, 
                       k.name as kos_name, k.address as kos_address,
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as kos_image
                FROM {$this->table} b
                LEFT JOIN kos k ON b.kos_id = k.id
                WHERE b.user_id = ?";
        
        $params = [$userId];
        
        if ($status) {
            $sql .= " AND b.booking_status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY b.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get kos bookings (for owner)
     */
    public function getKosBookings($kosId, $status = null) {
        $sql = "SELECT b.*, 
                       u.name as user_name, u.email as user_email, u.phone as user_phone
                FROM {$this->table} b
                LEFT JOIN users u ON b.user_id = u.id
                WHERE b.kos_id = ?";
        
        $params = [$kosId];
        
        if ($status) {
            $sql .= " AND b.booking_status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY b.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Update booking status
     */
    public function updateBookingStatus($bookingId, $status, $reason = null) {
        $data = ['booking_status' => $status];
        
        switch ($status) {
            case 'confirmed':
                $data['confirmed_at'] = date('Y-m-d H:i:s');
                break;
            case 'cancelled':
                $data['cancelled_at'] = date('Y-m-d H:i:s');
                if ($reason) {
                    $data['cancelled_reason'] = $reason;
                }
                break;
        }
        
        return $this->update($bookingId, $data);
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($bookingId, $status) {
        return $this->update($bookingId, ['payment_status' => $status]);
    }

    /**
     * Get expired bookings
     */
    public function getExpiredBookings() {
        $sql = "SELECT * FROM {$this->table} 
                WHERE booking_status = 'pending' 
                AND expires_at < NOW()";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Cancel expired bookings
     */
    public function cancelExpiredBookings() {
        $sql = "UPDATE {$this->table} 
                SET booking_status = 'expired', 
                    cancelled_reason = 'Booking expired automatically',
                    cancelled_at = NOW()
                WHERE booking_status = 'pending' 
                AND expires_at < NOW()";
        
        return $this->db->execute($sql);
    }
    /**
     * Get booking by ID
     */
    }

    /**
     * Get booking statistics
     */
    public function getBookingStats() {
    $sql = "SELECT 
                booking_status,
                COUNT(*) as count,
                SUM(total_amount) as total_revenue
            FROM {$this->table}
            GROUP BY booking_status";

    return $this->db->fetchAll($sql);
    }


    /**
     * Generate unique booking code
     */
    private function generateBookingCode() {
        do {
            $code = 'TK' . date('Ymd') . strtoupper(substr(uniqid(), -6));
        } while ($this->getBookingByCode($code));
        
        return $code;
    }

    /**
     * Check room availability
     */
    public function checkAvailability($kosId, $checkInDate, $durationMonths) {
        $checkOutDate = date('Y-m-d', strtotime($checkInDate . " +{$durationMonths} months"));
        
        $sql = "SELECT COUNT(*) as count FROM {$this->table} 
                WHERE kos_id = ? 
                AND booking_status IN ('confirmed', 'pending')
                AND (
                    (check_in_date <= ? AND check_out_date >= ?) OR
                    (check_in_date <= ? AND check_out_date >= ?) OR
                    (check_in_date >= ? AND check_in_date <= ?)
                )";
        
        $params = [$kosId, $checkInDate, $checkInDate, $checkOutDate, $checkOutDate, $checkInDate, $checkOutDate];
        $result = $this->db->fetch($sql, $params);
        
        return $result['count'] == 0;
    }
}
?>
