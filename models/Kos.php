<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/BaseModel.php';
/**
 * Kos Model
 * Handles kos-related database operations
 */
class Kos extends BaseModel {
    protected $db;
    protected $table = 'kos';
    protected $fillable = [
        'owner_id', 'location_id', 'name', 'slug', 'description', 'address',
        'price', 'type', 'room_size', 'total_rooms', 'available_rooms',
        'is_available', 'is_featured', 'status'
    ];

    public function __construct() {
        $this->db = Database::getInstance(); 

    }

    // Get all kos with search
    public function getAllKos($search = '') {
        if (!empty($search)) {
            $this->db->query('SELECT k.*, u.name as owner_name, u.phone as owner_phone,
                             (SELECT image_path FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image
                             FROM kos k 
                             LEFT JOIN users u ON k.user_id = u.id 
                             WHERE k.name LIKE :search OR k.location LIKE :search 
                             ORDER BY k.created_at DESC');
            $this->db->bind(':search', '%' . $search . '%');
        } else {
            $this->db->query('SELECT k.*, u.name as owner_name, u.phone as owner_phone,
                             (SELECT image_path FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image
                             FROM kos k 
                             LEFT JOIN users u ON k.user_id = u.id 
                             ORDER BY k.created_at DESC');
        }
        
        return $this->db->resultSet();
    }

    // Get kos by ID with details
    public function getKosById($id) {
        $this->db->query('SELECT k.*, u.name as owner_name, u.phone as owner_phone, u.email as owner_email
                         FROM kos k 
                         LEFT JOIN users u ON k.user_id = u.id 
                         WHERE k.id = :id');
        $this->db->bind(':id', $id);
        
        $kos = $this->db->single();
        
        if ($kos) {
            // Get images
            $kos['images'] = $this->getKosImages($id);
            
            // Get facilities
            $kos['facilities'] = $this->getKosFacilities($id);
            
            // Get rules
            $kos['rules'] = $this->getKosRules($id);
        }
        
        return $kos;
    }

    /**
     * Get kos with owner and location details
     */
    public function getKosWithDetails($id) {
        $sql = "SELECT k.*, 
                       u.name as owner_name, u.phone as owner_phone, u.email as owner_email,
                       l.city, l.district, l.subdistrict, l.latitude, l.longitude
                FROM {$this->table} k
                LEFT JOIN users u ON k.owner_id = u.id
                LEFT JOIN locations l ON k.location_id = l.id
                WHERE k.id = ? AND k.status = 'published'";
        
        $kos = $this->db->fetch($sql, [$id]);
        
        if ($kos) {
            // Get images
            $kos['images'] = $this->getKosImages($id);
            
            // Get facilities
            $kos['facilities'] = $this->getKosFacilities($id);
            
            // Get rules
            $kos['rules'] = $this->getKosRules($id);
            
            // Increment view count
            $this->incrementViewCount($id);
        }
        
        return $kos;
    }

    /**
     * Search kos with filters
     */
    public function searchKos($filters = []) {
        $sql = "SELECT k.*, 
                       u.name as owner_name,
                       l.city, l.district,
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM {$this->table} k
                LEFT JOIN users u ON k.owner_id = u.id
                LEFT JOIN locations l ON k.location_id = l.id
                WHERE k.status = 'published' AND k.is_available = 1";
        
        $params = [];
        
        // Location filter
        if (!empty($filters['location'])) {
            $sql .= " AND (k.name LIKE ? OR k.address LIKE ? OR l.city LIKE ? OR l.district LIKE ?)";
            $locationParam = "%{$filters['location']}%";
            $params = array_merge($params, [$locationParam, $locationParam, $locationParam, $locationParam]);
        }
        
        // Price range filter
        if (!empty($filters['min_price'])) {
            $sql .= " AND k.price >= ?";
            $params[] = $filters['min_price'];
        }
        
        if (!empty($filters['max_price'])) {
            $sql .= " AND k.price <= ?";
            $params[] = $filters['max_price'];
        }
        
        // Type filter
        if (!empty($filters['type'])) {
            $sql .= " AND k.type = ?";
            $params[] = $filters['type'];
        }
        
        // Facilities filter
        if (!empty($filters['facilities'])) {
            $facilityIds = $this->getFacilityIdsByNames($filters['facilities']);
            if (!empty($facilityIds)) {
                $placeholders = str_repeat('?,', count($facilityIds) - 1) . '?';
                $sql .= " AND k.id IN (
                    SELECT kf.kos_id 
                    FROM kos_facilities kf 
                    WHERE kf.facility_id IN ({$placeholders}) 
                    AND kf.is_available = 1
                    GROUP BY kf.kos_id 
                    HAVING COUNT(DISTINCT kf.facility_id) = ?
                )";
                $params = array_merge($params, $facilityIds, [count($facilityIds)]);
            }
        }
        
        // Sorting
        $orderBy = 'k.created_at DESC';
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'price_low':
                    $orderBy = 'k.price ASC';
                    break;
                case 'price_high':
                    $orderBy = 'k.price DESC';
                    break;
                case 'rating':
                    $orderBy = 'k.rating DESC, k.review_count DESC';
                    break;
                case 'newest':
                default:
                    $orderBy = 'k.created_at DESC';
                    break;
            }
        }
        
        $sql .= " ORDER BY k.is_featured DESC, {$orderBy}";
        
        // Limit
        if (!empty($filters['limit'])) {
            $sql .= " LIMIT ?";
            $params[] = $filters['limit'];
        }
        
        return $this->db->fetchAll($sql, $params);
    }

    // Get kos images
    public function getKosImages($kosId) {
        $this->db->query('SELECT * FROM kos_images WHERE kos_id = :kos_id ORDER BY is_primary DESC, id ASC');
        $this->db->bind(':kos_id', $kosId);
        
        return $this->db->resultSet();
    }


    // Get kos facilities
    public function getKosFacilities($kosId) {
    $this->db->query('
        SELECT f.facility_name 
        FROM kos_facilities kf 
        JOIN facilities f ON kf.facility_id = f.id 
        WHERE kf.kos_id = :kos_id 
        ORDER BY f.facility_name
    ');
    $this->db->bind(':kos_id', $kosId);
    return $this->db->resultSet();
}


    

    /**
     * Get kos rules
     */
    public function getKosRules($kosId) {
        $sql = "SELECT * FROM kos_rules WHERE kos_id = ? ORDER BY sort_order ASC, id ASC";
        return $this->db->fetchAll($sql, [$kosId]);
    }

    // Add new kos
    public function addKos($data) {
        try {
            $this->db->beginTransaction();
            
            // Insert kos
            $this->db->query('INSERT INTO kos (user_id, name, location, address, price, type, room_size, description) 
                             VALUES (:user_id, :name, :location, :address, :price, :type, :room_size, :description)');
            
            $this->db->bind(':user_id', $data['user_id']);
            $this->db->bind(':name', $data['name']);
            $this->db->bind(':location', $data['location']);
            $this->db->bind(':address', $data['address']);
            $this->db->bind(':price', $data['price']);
            $this->db->bind(':type', $data['type']);
            $this->db->bind(':room_size', $data['room_size']);
            $this->db->bind(':description', $data['description']);
            
            $this->db->execute();
            $kosId = $this->db->lastInsertId();
            
            // Insert facilities
            if (!empty($data['facilities'])) {
                foreach ($data['facilities'] as $facility) {
                    $this->db->query('INSERT INTO kos_facilities (kos_id, facility_name) VALUES (:kos_id, :facility_name)');
                    $this->db->bind(':kos_id', $kosId);
                    $this->db->bind(':facility_name', $facility);
                    $this->db->execute();
                }
            }
            
            // Insert rules
            if (!empty($data['rules'])) {
                foreach ($data['rules'] as $rule) {
                    if (!empty(trim($rule))) {
                        $this->db->query('INSERT INTO kos_rules (kos_id, rule_text) VALUES (:kos_id, :rule_text)');
                        $this->db->bind(':kos_id', $kosId);
                        $this->db->bind(':rule_text', trim($rule));
                        $this->db->execute();
                    }
                }
            }
            
            $this->db->endTransaction();
            return $kosId;
            
        } catch (Exception $e) {
            $this->db->cancelTransaction();
            return false;
        }
    }

    /**
     * Create kos with related data
     */
    public function createKos($kosData, $facilities = [], $rules = [], $images = []) {
        try {
            $this->beginTransaction();
            
            // Generate slug
            $kosData['slug'] = $this->generateSlug($kosData['name']);
            
            // Create kos
            $kosId = $this->create($kosData);
            
            // Add facilities
            if (!empty($facilities)) {
                $this->addKosFacilities($kosId, $facilities);
            }
            
            // Add rules
            if (!empty($rules)) {
                $this->addKosRules($kosId, $rules);
            }
            
            // Add images
            if (!empty($images)) {
                $this->addKosImages($kosId, $images);
            }
            
            $this->commit();
            return $kosId;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }

    // Get kos by user ID
    public function getKosByUserId($userId) {
        $this->db->query('SELECT k.*, 
                         (SELECT image_path FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image,
                         (SELECT COUNT(*) FROM bookings WHERE kos_id = k.id AND status = "confirmed") as booking_count
                         FROM kos k 
                         WHERE k.user_id = :user_id 
                         ORDER BY k.created_at DESC');
        $this->db->bind(':user_id', $userId);
        
        return $this->db->resultSet();
    }

    /**
     * Get kos by owner
     */
    public function getKosByOwner($ownerId) {
        $sql = "SELECT k.*, 
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image,
                       (SELECT COUNT(*) FROM bookings WHERE kos_id = k.id AND booking_status = 'confirmed') as booking_count
                FROM {$this->table} k
                WHERE k.owner_id = ?
                ORDER BY k.created_at DESC";
        
        return $this->db->fetchAll($sql, [$ownerId]);
    }

    // Delete kos
    public function deleteKos($id, $userId) {
        $this->db->query('DELETE FROM kos WHERE id = :id AND user_id = :user_id');
        $this->db->bind(':id', $id);
        $this->db->bind(':user_id', $userId);
        
        return $this->db->execute();
    }


    /**
     * Get featured kos
     */
    public function getFeaturedKos($limit = 6) {
        return $this->searchKos(['limit' => $limit, 'featured' => true]);
    }

    /**
     * Get popular kos based on views and bookings
     */
    public function getPopularKos($limit = 10) {
        $sql = "SELECT k.*, 
                       u.name as owner_name,
                       l.city, l.district,
                       (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM {$this->table} k
                LEFT JOIN users u ON k.owner_id = u.id
                LEFT JOIN locations l ON k.location_id = l.id
                WHERE k.status = 'published' AND k.is_available = 1
                ORDER BY (k.view_count * 0.3 + k.review_count * 0.7) DESC, k.rating DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }

    /**
     * Update kos rating
     */
    public function updateRating($kosId) {
        $sql = "UPDATE {$this->table} SET 
                    rating = (SELECT AVG(rating) FROM reviews WHERE kos_id = ? AND is_approved = 1),
                    review_count = (SELECT COUNT(*) FROM reviews WHERE kos_id = ? AND is_approved = 1)
                WHERE id = ?";
        
        return $this->db->execute($sql, [$kosId, $kosId, $kosId]);
    }

    /**
     * Increment view count
     */
    public function incrementViewCount($kosId) {
        $sql = "UPDATE {$this->table} SET view_count = view_count + 1 WHERE id = ?";
        return $this->db->execute($sql, [$kosId]);
    }

    /**
     * Generate unique slug
     */
    private function generateSlug($name, $excludeId = null) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }

    /**
     * Check if slug exists
     */
    private function slugExists($slug, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE slug = ?";
        $params = [$slug];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result['count'] > 0;
    }

    /**
     * Get facility IDs by names
     */
    private function getFacilityIdsByNames($facilityNames) {
        if (empty($facilityNames)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($facilityNames) - 1) . '?';
        $sql = "SELECT id FROM facilities WHERE name IN ({$placeholders})";
        
        $results = $this->db->fetchAll($sql, $facilityNames);
        return array_column($results, 'id');
    }

    /**
     * Add kos facilities
     */
    private function addKosFacilities($kosId, $facilities) {
        foreach ($facilities as $facilityId) {
            $sql = "INSERT INTO kos_facilities (kos_id, facility_id) VALUES (?, ?)";
            $this->db->execute($sql, [$kosId, $facilityId]);
        }
    }

    /**
     * Add kos rules
     */
    private function addKosRules($kosId, $rules) {
        foreach ($rules as $index => $rule) {
            if (!empty(trim($rule))) {
                $sql = "INSERT INTO kos_rules (kos_id, rule_text, sort_order) VALUES (?, ?, ?)";
                $this->db->execute($sql, [$kosId, trim($rule), $index + 1]);
            }
        }
    }

    /**
     * Add kos images
     */
    private function addKosImages($kosId, $images) {
        foreach ($images as $index => $image) {
            $sql = "INSERT INTO kos_images (kos_id, image_url, is_primary, sort_order) VALUES (?, ?, ?, ?)";
            $isPrimary = $index === 0 ? 1 : 0;
            $this->db->execute($sql, [$kosId, $image, $isPrimary, $index + 1]);
        }
    }

    /**
     * Delete kos facilities
     */
    private function deleteKosFacilities($kosId) {
        $sql = "DELETE FROM kos_facilities WHERE kos_id = ?";
        return $this->db->execute($sql, [$kosId]);
    }

    /**
     * Delete kos rules
     */
    private function deleteKosRules($kosId) {
        $sql = "DELETE FROM kos_rules WHERE kos_id = ?";
        return $this->db->execute($sql, [$kosId]);
    }

    /**
     * Delete kos images
     */
    private function deleteKosImages($kosId) {
        $sql = "DELETE FROM kos_images WHERE kos_id = ?";
        return $this->db->execute($sql, [$kosId]);
    }
}
?>
