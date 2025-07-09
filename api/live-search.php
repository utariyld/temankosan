<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Include your database connection file
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';

    $pdo = getConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    $query = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 20) : 10;

    if (strlen($query) < 2) {
        echo json_encode(['success' => true, 'data' => [], 'count' => 0]);
        exit;
    }

    // Simple search query - adjust table names according to your database structure
    $sql = "
        SELECT 
            k.id,
            k.name,
            k.address,
            k.price,
            k.type,
            k.room_size,
            COALESCE(l.city, k.address) AS location,
            COALESCE(AVG(r.rating), 4.5) as avg_rating,
            COUNT(DISTINCT r.id) as review_count
        FROM kos k
        LEFT JOIN locations l ON k.location_id = l.id
        LEFT JOIN reviews r ON k.id = r.kos_id
        WHERE k.status = 'published' 
        AND k.is_available = 1
        AND (
            k.name LIKE ? 
            OR k.address LIKE ? 
            OR l.city LIKE ? 
            OR l.district LIKE ?
        )
        GROUP BY k.id, k.name, k.address, k.price, k.type, k.room_size, l.city, l.district
        ORDER BY 
            CASE 
                WHEN k.name LIKE ? THEN 1
                WHEN k.name LIKE ? THEN 2
                ELSE 3
            END,
            k.is_featured DESC,
            avg_rating DESC
        LIMIT ?
    ";

    $stmt = $pdo->prepare($sql);
    $searchTerm = "%{$query}%";
    $exactTerm = $query;
    $startTerm = "{$query}%";

    $stmt->execute([
        $searchTerm, $searchTerm, $searchTerm, $searchTerm, // WHERE conditions
        $exactTerm, $startTerm, // ORDER BY conditions
        $limit
    ]);

    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format results
    $formattedResults = array_map(function($row) {
        return [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'location' => $row['location'],
            'address' => $row['address'],
            'price' => (int)$row['price'],
            'formatted_price' => 'Rp ' . number_format($row['price'], 0, ',', '.'),
            'type' => ucfirst(str_replace('-', '/', $row['type'])),
            'room_size' => $row['room_size'] ?? 'N/A',
            'rating' => round((float)$row['avg_rating'], 1),
            'review_count' => (int)$row['review_count']
        ];
    }, $results);

    echo json_encode([
        'success' => true,
        'data' => $formattedResults,
        'count' => count($formattedResults),
        'query' => $query
    ]);

} catch (Exception $e) {
    error_log("Live search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Search failed',
        'message' => $e->getMessage(),
        'debug' => [
            'query' => $_GET['q'] ?? 'not set',
            'file' => __FILE__,
            'line' => __LINE__
        ]
    ]);
}
?>