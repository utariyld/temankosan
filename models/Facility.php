<?php
require_once 'models/BaseModel.php';

/**
 * Facility Model
 * Handles facility-related database operations
 */
class Facility extends BaseModel {
    protected $table = 'facilities';
    protected $fillable = ['name', 'icon', 'category', 'description', 'is_active'];

    /**
     * Get facilities by category
     */
    public function getFacilitiesByCategory($category = null) {
        $conditions = ['is_active' => 1];
        if ($category) {
            $conditions['category'] = $category;
        }
        
        return $this->findAll($conditions, 'category ASC, name ASC');
    }

    /**
     * Get all facility categories
     */
    public function getCategories() {
        $sql = "SELECT DISTINCT category FROM {$this->table} WHERE is_active = 1 ORDER BY category";
        return $this->db->fetchAll($sql);
    }

    /**
     * Search facilities
     */
    public function searchFacilities($query) {
        return $this->search('name', $query);
    }
}
?>
