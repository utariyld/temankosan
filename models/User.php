<?php
require_once 'models/BaseModel.php';

/**
 * User Model
 * Handles user-related database operations
 */
class User extends BaseModel {
    protected $table = 'users';
    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'avatar', 
        'email_verified_at', 'is_active'
    ];
    protected $hidden = ['password', 'remember_token'];

    /**
     * Create new user with password hashing
     */
    public function createUser($data) {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        return $this->create($data);
    }

    /**
     * Find user by email
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? AND is_active = 1";
        return $this->db->fetch($sql, [$email]);
    }

    /**
     * Verify user login
     */
    public function verifyLogin($email, $password) {
        $user = $this->findByEmail($email);
        
        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $this->update($user['id'], ['last_login' => date('Y-m-d H:i:s')]);
            
            // Remove password from result
            unset($user['password']);
            return $user;
        }
        
        return false;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email, $excludeId = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = $this->db->fetch($sql, $params);
        return $result['count'] > 0;
    }

    /**
     * Update user password
     */
    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        return $this->update($userId, ['password' => $hashedPassword]);
    }

    /**
     * Get users by role
     */
    public function getUsersByRole($role) {
        return $this->findAll(['role' => $role, 'is_active' => 1]);
    }

    /**
     * Deactivate user
     */
    public function deactivateUser($userId) {
        return $this->update($userId, ['is_active' => 0]);
    }

    /**
     * Activate user
     */
    public function activateUser($userId) {
        return $this->update($userId, ['is_active' => 1]);
    }

    /**
     * Get user statistics
     */
    public function getUserStats() {
        $sql = "SELECT 
                    role,
                    COUNT(*) as count,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                FROM {$this->table} 
                GROUP BY role";
        
        return $this->db->fetchAll($sql);
    }
}
?>
