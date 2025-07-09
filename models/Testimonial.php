<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Testimonial Model
 * Handles all testimonial-related database operations
 */
class Testimonial {
    private $db;
    private $table = 'testimonials';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Create new testimonial
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} 
                (name, email, phone, kos_name, rating, comment, is_approved, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $data['name'],
            $data['email'],
            $data['phone'] ?? null,
            $data['kos_name'],
            $data['rating'],
            $data['comment'],
            $data['is_approved'] ?? 0,
            $data['ip_address'] ?? null,
            $data['user_agent'] ?? null
        ];

        return $this->db->insert($sql, $params);
    }

    /**
     * Get testimonial by ID
     */
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = ?";
        return $this->db->fetch($sql, [$id]);
    }

    /**
     * Get all approved testimonials for public display
     */
    public function getApprovedTestimonials($limit = 10, $offset = 0) {
        $sql = "SELECT id, name, kos_name, rating, comment, created_at
                FROM {$this->table}
                WHERE is_approved = 1
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
        
        return $this->db->fetchAll($sql, [$limit, $offset]);
    }

    /**
     * Get featured testimonials (4-5 star ratings)
     */
    public function getFeaturedTestimonials($limit = 6) {
        $sql = "SELECT id, name, kos_name, rating, comment, created_at
                FROM {$this->table}
                WHERE is_approved = 1 AND rating >= 4
                ORDER BY rating DESC, created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Get testimonials by rating
     */
    public function getTestimonialsByRating($rating, $limit = 10) {
        $sql = "SELECT id, name, kos_name, rating, comment, created_at
                FROM {$this->table}
                WHERE is_approved = 1 AND rating = ?
                ORDER BY created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$rating, $limit]);
    }

    /**
     * Get pending testimonials for admin review
     */
    public function getPendingTestimonials() {
        $sql = "SELECT id, name, email, kos_name, rating, comment, created_at, ip_address
                FROM {$this->table}
                WHERE is_approved = 0
                ORDER BY created_at ASC";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Update testimonial
     */
    public function update($id, $data) {
        $fields = [];
        $params = [];

        foreach ($data as $key => $value) {
            $fields[] = "{$key} = ?";
            $params[] = $value;
        }

        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->db->execute($sql, $params);
    }

    /**
     * Delete testimonial
     */
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = ?";
        return $this->db->execute($sql, [$id]);
    }

    /**
     * Approve testimonial
     */
    public function approve($id) {
        return $this->update($id, [
            'is_approved' => 1,
            'approved_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Reject testimonial
     */
    public function reject($id) {
        return $this->update($id, [
            'is_approved' => -1,
            'rejected_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Check if email has submitted testimonial recently
     */
    public function hasRecentTestimonial($email, $hours = 24) {
        $sql = "SELECT COUNT(*) as count
                FROM {$this->table}
                WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? HOUR)";
        
        $result = $this->db->fetch($sql, [$email, $hours]);
        return $result['count'] > 0;
    }

    /**
     * Get testimonial statistics
     */
    public function getStats() {
        $sql = "SELECT * FROM testimonial_stats";
        return $this->db->fetch($sql);
    }

    /**
     * Search testimonials
     */
    public function search($query, $limit = 10) {
        $sql = "SELECT id, name, kos_name, rating, comment, created_at
                FROM {$this->table}
                WHERE is_approved = 1 
                AND (name LIKE ? OR kos_name LIKE ? OR comment LIKE ?)
                ORDER BY created_at DESC
                LIMIT ?";
        
        $searchTerm = "%{$query}%";
        return $this->db->fetchAll($sql, [$searchTerm, $searchTerm, $searchTerm, $limit]);
    }

    /**
     * Get testimonials count by status
     */
    public function getCountByStatus() {
        $sql = "SELECT 
                    is_approved,
                    COUNT(*) as count,
                    CASE 
                        WHEN is_approved = 1 THEN 'Approved'
                        WHEN is_approved = 0 THEN 'Pending'
                        WHEN is_approved = -1 THEN 'Rejected'
                    END as status_name
                FROM {$this->table}
                GROUP BY is_approved";
        
        return $this->db->fetchAll($sql);
    }

    /**
     * Get recent testimonials activity
     */
    public function getRecentActivity($days = 7, $limit = 20) {
        $sql = "SELECT id, name, kos_name, rating, is_approved, created_at
                FROM {$this->table}
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                ORDER BY created_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$days, $limit]);
    }
}
?>
