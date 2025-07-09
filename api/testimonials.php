<?php
// Set headers for API response
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Testimonial.php';

// Initialize
$method = $_SERVER['REQUEST_METHOD'];
$testimonialModel = new Testimonial();

// Helper function to send JSON response
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// Helper function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Helper function to sanitize input
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // Get specific testimonial
                $testimonial = $testimonialModel->find($_GET['id']);
                if ($testimonial) {
                    sendResponse(true, 'Testimonial found', $testimonial);
                } else {
                    sendResponse(false, 'Testimonial not found', null, 404);
                }
            } elseif (isset($_GET['action'])) {
                // Handle special actions
                switch ($_GET['action']) {
                    case 'stats':
                        $stats = $testimonialModel->getStats();
                        sendResponse(true, 'Statistics retrieved', $stats);
                        break;
                    
                    case 'pending':
                        $pending = $testimonialModel->getPendingTestimonials();
                        sendResponse(true, 'Pending testimonials retrieved', $pending);
                        break;
                    
                    case 'featured':
                        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 6;
                        $featured = $testimonialModel->getFeaturedTestimonials($limit);
                        sendResponse(true, 'Featured testimonials retrieved', $featured);
                        break;
                    
                    case 'search':
                        if (empty($_GET['q'])) {
                            sendResponse(false, 'Search query is required', null, 400);
                        }
                        $query = sanitizeInput($_GET['q']);
                        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                        $results = $testimonialModel->search($query, $limit);
                        sendResponse(true, 'Search completed', $results);
                        break;
                    
                    default:
                        sendResponse(false, 'Invalid action', null, 400);
                }
            } else {
                // Get all approved testimonials
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                
                $testimonials = $testimonialModel->getApprovedTestimonials($limit, $offset);
                sendResponse(true, 'Testimonials retrieved successfully', $testimonials);
            }
            break;

        case 'POST':
            // Get input data
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                sendResponse(false, 'Invalid JSON data', null, 400);
            }

            // Validate required fields
            $required = ['name', 'email', 'kos', 'rating', 'comment'];
            $missing = [];
            
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                sendResponse(false, 'Missing required fields: ' . implode(', ', $missing), null, 400);
            }

            // Validate email format
            if (!isValidEmail($input['email'])) {
                sendResponse(false, 'Invalid email format', null, 400);
            }

            // Validate rating
            $rating = (int)$input['rating'];
            if ($rating < 1 || $rating > 5) {
                sendResponse(false, 'Rating must be between 1 and 5', null, 400);
            }

            // Validate comment length
            if (strlen(trim($input['comment'])) < 20) {
                sendResponse(false, 'Comment must be at least 20 characters long', null, 400);
            }

            // Check for recent testimonial from same email
            if ($testimonialModel->hasRecentTestimonial($input['email'], 24)) {
                sendResponse(false, 'You can only submit one testimonial per day', null, 429);
            }

            // Sanitize and prepare data
            $data = [
                'name' => sanitizeInput($input['name']),
                'email' => sanitizeInput($input['email']),
                'phone' => isset($input['phone']) ? sanitizeInput($input['phone']) : null,
                'kos_name' => sanitizeInput($input['kos']),
                'rating' => $rating,
                'comment' => sanitizeInput($input['comment']),
                'is_approved' => 0, // Pending approval
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];

            // Create testimonial
            $testimonialId = $testimonialModel->create($data);
            
            if ($testimonialId) {
                sendResponse(true, 'Testimonial submitted successfully and is pending review', [
                    'id' => $testimonialId
                ], 201);
            } else {
                sendResponse(false, 'Failed to save testimonial', null, 500);
            }
            break;

        case 'PUT':
            // Admin only - approve/reject testimonial
            if (!isset($_GET['id'])) {
                sendResponse(false, 'Testimonial ID is required', null, 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $testimonialId = (int)$_GET['id'];

            if (isset($input['action'])) {
                switch ($input['action']) {
                    case 'approve':
                        $result = $testimonialModel->approve($testimonialId);
                        if ($result) {
                            sendResponse(true, 'Testimonial approved successfully');
                        } else {
                            sendResponse(false, 'Failed to approve testimonial', null, 500);
                        }
                        break;
                    
                    case 'reject':
                        $result = $testimonialModel->reject($testimonialId);
                        if ($result) {
                            sendResponse(true, 'Testimonial rejected successfully');
                        } else {
                            sendResponse(false, 'Failed to reject testimonial', null, 500);
                        }
                        break;
                    
                    default:
                        sendResponse(false, 'Invalid action', null, 400);
                }
            } else {
                sendResponse(false, 'Action is required', null, 400);
            }
            break;

        case 'DELETE':
            // Admin only - delete testimonial
            if (!isset($_GET['id'])) {
                sendResponse(false, 'Testimonial ID is required', null, 400);
            }

            $testimonialId = (int)$_GET['id'];
            $result = $testimonialModel->delete($testimonialId);
            
            if ($result) {
                sendResponse(true, 'Testimonial deleted successfully');
            } else {
                sendResponse(false, 'Failed to delete testimonial', null, 500);
            }
            break;

        default:
            sendResponse(false, 'Method not allowed', null, 405);
            break;
    }

} catch (Exception $e) {
    error_log("Testimonial API Error: " . $e->getMessage());
    sendResponse(false, 'Internal server error: ' . $e->getMessage(), null, 500);
}
?>
