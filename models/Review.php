<?php
require_once 'models/BaseModel.php';

/**
 * Review Model
 * Handles review-related database operations
 */
class Review extends BaseModel {
    protected $table = 'reviews';
    protected $fillable = [
        'user_id', 'kos_id', 'booking_id', 'rating', 'title', 'comment',
        'pros', 'cons', 'is_anonymous', 'is_approved'
    ];

    /**
     * Create review and update kos rating
     */
    public function createReview($data) {
        try {
            $this->beginTransaction();
            
            $reviewId = $this->create($data);
            
            // Update kos rating
            $kosModel = new Kos();
            $kosModel->updateRating($data['kos_id']);
            
            $this->commit();
            return $reviewId;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    /**
     * Get kos reviews
     */
    public function getKosReviews($kosId, $approved = true) {
        $sql = "SELECT r.*, u.name as user_name, u.avatar as user_avatar
                FROM {$this->table} r
                LEFT JOIN users u ON r.user_id = u.id
                WHERE r.kos_id = ?";
        
        $params = [$kosId];
        
        if ($approved) {
            $sql .= " AND r.is_approved = 1";
        }
        
        $sql .= " ORDER BY r.created_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Get user reviews
     */
    public function getUserReviews($userId) {
        $sql = "SELECT r.*, k.name as kos_name
                FROM {$this->table} r
                LEFT JOIN kos k ON r.kos_id = k.id
                WHERE r.user_id = ?
                ORDER BY r.created_at DESC";
        
        return $this->db->fetchAll($sql, [$userId]);
    }

    /**
     * Check if user can review kos
     */
    public function canUserReview($userId, $kosId) {
        // Check if user has confirmed booking for this kos
        $sql = "SELECT COUNT(*) as count FROM bookings 
                WHERE user_id = ? AND kos_id = ? AND booking_status = 'confirmed'";
        
        $result = $this->db->fetch($sql, [$userId, $kosId]);
        $hasBooking = $result['count'] > 0;
        
        // Check if user already reviewed this kos
        $existingReview = $this->findAll(['user_id' => $userId, 'kos_id' => $kosId]);
        $hasReviewed = !empty($existingReview);
        
        return $hasBooking && !$hasReviewed;
    }

    /**
     * Approve review
     */
    public function approveReview($reviewId) {
        return $this->update($reviewId, ['is_approved' => 1]);
    }

    /**
     * Reject review
     */
    public function rejectReview($reviewId) {
        return $this->update($reviewId, ['is_approved' => 0]);
    }
}
?>
