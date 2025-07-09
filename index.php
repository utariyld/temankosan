<?php
session_start();

// Database connection
require_once 'config/database.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get featured kos with complete data
function getFeaturedKos($pdo, $limit = 8) {
    $sql = "SELECT k.*, 
                   l.city, l.district,
                   (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT COUNT(*) FROM reviews WHERE kos_id = k.id) as review_count,
                   (SELECT AVG(rating) FROM reviews WHERE kos_id = k.id) as avg_rating,
                   GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR ', ') as facilities
            FROM kos k
            LEFT JOIN locations l ON k.location_id = l.id
            LEFT JOIN kos_facilities kf ON k.id = kf.kos_id AND kf.is_available = 1
            LEFT JOIN facilities f ON kf.facility_id = f.id
            WHERE k.status = 'published' AND k.is_available = 1
            GROUP BY k.id
            ORDER BY k.is_featured DESC, k.view_count DESC, k.created_at DESC
            LIMIT ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get testimonials
function getTestimonials(PDO $pdo, int $limit = 6): array {
    $sql = "SELECT 
                t.*, 
                k.name AS kos_name
            FROM testimonials t
            LEFT JOIN kos k ON t.kos_id = k.id
            ORDER BY t.created_at DESC
            LIMIT ?";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle search
$searchLocation = isset($_GET['location']) ? trim($_GET['location']) : '';
$featuredKos = getFeaturedKos($pdo, 8);
$testimonials = getTestimonials($pdo, 6);
$total_kos = 150;
$total_users = 500;
$total_bookings = 300;
$total_cities = 25;

// Filter kos if search is performed
if (!empty($searchLocation)) {
    $sql = "SELECT k.*, 
                   l.city, l.district,
                   (SELECT image_url FROM kos_images WHERE kos_id = k.id AND is_primary = 1 LIMIT 1) as primary_image,
                   (SELECT AVG(rating) FROM reviews WHERE kos_id = k.id) as avg_rating,
                   GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR ', ') as facilities
            FROM kos k
            LEFT JOIN locations l ON k.location_id = l.id
            LEFT JOIN kos_facilities kf ON k.id = kf.kos_id AND kf.is_available = 1
            LEFT JOIN facilities f ON kf.facility_id = f.id
            WHERE k.status = 'published' AND k.is_available = 1
              AND (k.name LIKE ? OR k.address LIKE ? OR l.city LIKE ? OR l.district LIKE ?)
            GROUP BY k.id
            ORDER BY k.is_featured DESC, k.view_count DESC";
    
    $searchTerm = "%{$searchLocation}%";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $featuredKos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TemanKosan - Platform Booking Kos Terpercaya</title>
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
            overflow-x: hidden;
            scroll-behavior: smooth;
            background-color:rgb(188, 239, 208);
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--gray-100);
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
        }

        /* Loading Animation */
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--white);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease;
        }

        .loading.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--gray-200);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Navigation */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }

        .navbar.scrolled {
            background: rgba(255, 255, 255, 0.98);
            box-shadow: var(--shadow-lg);
        }

        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            transition: var(--transition);
        }

        .logo:hover {
            transform: scale(1.05);
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .nav-links a {
            color: var(--gray-700);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.9rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-outline {
            background: transparent;
            color: var(--gray-700);
            border: 2px solid var(--gray-300);
        }

        .btn-outline:hover {
            background: var(--primary-color);
            color: var(--white);
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: var(--white);
            border: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 105, 180, 0.4);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, var(--primary-color) 0%, #00a844 30%, var(--secondary-color) 70%, var(--accent-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: float 20s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .hero-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            text-align: center;
            position: relative;
            z-index: 2;
            width: 100%;
        }

        .hero h1 {
            font-size: 4rem;
            color: var(--white);
            margin-bottom: 1.5rem;
            font-weight: 900;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            animation: fadeInUp 1s ease-out;
        }

        .hero p {
            font-size: 1.3rem;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 3rem;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Search Form */
        .search-form {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            padding: 2.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            max-width: 900px;
            margin: 0 auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .search-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 1.5rem;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 0.8rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group input {
            padding: 1rem 1.5rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            transition: var(--transition);
            background: var(--white);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(0, 200, 81, 0.1);
            transform: translateY(-2px);
        }

        .btn-search {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
            padding: 1rem 2.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-search:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 200, 81, 0.4);
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Section styling */
        .section {
            padding: 6rem 0;
            position: relative;
        }

        .section-title {
            font-size: 3rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 1rem;
            color: var(--gray-800);
            position: relative;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: vvar(--gray-750);
            text-align: center;
            margin-bottom: 4rem;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 2px;
        }

        /* Stats Section */
        .stats-section {
            background: rgb(106, 173, 133);
            position: relative;
        }

        .stats-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 100px;
            background: linear-gradient(135deg, var(--primary-color) 0%, #00a844 30%, var(--secondary-color) 70%, var(--accent-color) 100%);
            clip-path: polygon(0 0, 100% 0, 100% 50%, 0 100%);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 2.5rem 2rem;
            border-radius: var(--border-radius-lg);
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            transition: left 0.6s;
        }

        .stat-card:hover::before {
            left: 100%;
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }

        .stat-label {
            color: var(--gray-600);
            font-weight: 500;
            font-size: 1.1rem;
        }

        /* Features Section */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2.5rem;
        }

        .feature-card {
            background: var(--white);
            padding: 3rem 2rem;
            border-radius: var(--border-radius-lg);
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-200);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(0, 200, 81, 0.05), transparent);
            transition: left 0.6s;
        }

        .feature-card:hover::before {
            left: 100%;
        }

        .feature-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--white);
            animation: bounce 2s infinite;
            box-shadow: 0 10px 30px rgba(0, 200, 81, 0.3);
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-10px);
            }
            60% {
                transform: translateY(-5px);
            }
        }

        .feature-card:hover .feature-icon {
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .feature-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--gray-800);
        }

        .feature-description {
            color: var(--gray-600);
            line-height: 1.8;
            font-size: 1.1rem;
        }

        /* Testimonials Section */
        .testimonials-section {
            background: linear-gradient(135deg, var(--gray-100) 0%, var(--white) 100%);
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2.5rem;
        }

        .testimonial-card {
            background: var(--white);
            padding: 2.5rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--gray-200);
        }

        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-lg);
        }

        .testimonial-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .testimonial-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            margin-right: 1rem;
            font-size: 1.5rem;
            box-shadow: var(--shadow);
        }

        .testimonial-info h4 {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--gray-800);
            font-size: 1.1rem;
        }

        .testimonial-info p {
            color: var(--gray-600);
            font-size: 0.9rem;
        }

        .testimonial-rating {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stars {
            color: #ffc107;
            font-size: 1.2rem;
        }

        .rating-text {
            color: var(--gray-600);
            font-size: 0.9rem;
            margin-left: 0.5rem;
        }

        .testimonial-comment {
            font-style: italic;
            color: var(--gray-700);
            line-height: 1.8;
            font-size: 1rem;
            position: relative;
        }

        .testimonial-comment::before {
            content: '"';
            font-size: 4rem;
            color: var(--gray-300);
            position: absolute;
            top: -1rem;
            left: -1rem;
            font-family: serif;
        }

        /* Kos Listings */
        .kos-section {
            background-color:rgb(173, 225, 194);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4rem;
        }

        .btn-view-all {
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: var(--white);
            padding: 1rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 700;
            font-size: 1.1rem;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-view-all:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 200, 81, 0.4);
        }

        .kos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2.5rem;
        }

        .kos-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
            position: relative;
            border: 1px solid var(--gray-200);
        }

        .kos-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 200, 81, 0.05), rgba(255, 105, 180, 0.05));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }

        .kos-card:hover::before {
            opacity: 1;
        }

        .kos-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .kos-image {
            position: relative;
            height: 250px;
            overflow: hidden;
        }

        .kos-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .kos-card:hover .kos-image img {
            transform: scale(1.1);
        }

        .kos-badge {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            z-index: 2;
            box-shadow: var(--shadow);
        }

        .favorite-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            cursor: pointer;
            transition: var(--transition);
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: var(--shadow);
        }

        .favorite-btn:hover {
            background: var(--white);
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .favorite-btn.active {
            color: var(--accent-color);
        }

        .kos-content {
            padding: 2rem;
            position: relative;
            z-index: 2;
        }

        .kos-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.8rem;
            color: var(--gray-800);
            line-height: 1.3;
        }

        .kos-location {
            color: var(--gray-600);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .kos-rating {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 1.5rem;
        }

        .kos-rating .stars {
            color: #ffc107;
            font-size: 1.1rem;
        }

        .kos-rating span {
            font-weight: 600;
            color: var(--gray-700);
        }

        .kos-facilities {
            display: flex;
            flex-wrap: wrap;
            gap: 0.8rem;
            margin-bottom: 2rem;
        }

        .facility-tag {
            background: linear-gradient(135deg, var(--gray-100), var(--gray-200));
            color: var(--gray-700);
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 500;
            border: 1px solid var(--gray-300);
            transition: var(--transition);
        }

        .facility-tag:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: var(--white);
        }

        .kos-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
        }

        .kos-price {
            font-size: 1.4rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .kos-price span {
            font-size: 0.9rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        /* PERBAIKAN UTAMA: Tombol booking dengan z-index tinggi dan event handling yang benar */
        .btn-booking {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: var(--white);
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            z-index: 100; /* Z-index tinggi untuk memastikan tombol dapat diklik */
        }

        .btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 105, 180, 0.4);
            color: var(--white); /* Pastikan warna tetap putih saat hover */
        }

        /* Search Results */
        .search-results {
            text-align: center;
            margin-bottom: 3rem;
            padding: 2rem;
            background: linear-gradient(135deg, rgba(0, 200, 81, 0.1), rgba(255, 105, 180, 0.1));
            border-radius: var(--border-radius-lg);
            color: var(--gray-800);
            font-weight: 600;
            font-size: 1.1rem;
            border: 1px solid rgba(0, 200, 81, 0.2);
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, var(--dark-color), #34495e);
            color: var(--white);
            padding: 4rem 0 2rem;
            margin-top: 6rem;
            position: relative;
        }

        .footer-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .footer-section h3 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .footer-section h4 {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #ecf0f1;
        }

        .footer-description {
            color: #bdc3c7;
            line-height: 1.8;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        .social-links {
            display: flex;
            gap: 1rem;
        }

        .social-link {
            display: inline-block;
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 50px;
            text-decoration: none;
            font-size: 1.3rem;
            transition: var(--transition);
            backdrop-filter: blur(10px);
        }

        .social-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 8px 25px rgba(0, 200, 81, 0.3);
        }

        .footer-links {
            list-style: none;
        }

        .footer-links li {
            margin-bottom: 0.8rem;
        }

        .footer-link {
            color: #bdc3c7;
            text-decoration: none;
            transition: var(--transition);
            font-size: 1rem;
        }

        .footer-link:hover {
            color: var(--primary-color);
            padding-left: 5px;
        }

        .footer-contact {
            color: #bdc3c7;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.8rem;
            font-size: 1rem;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 2rem 0;
        }

        .footer-bottom-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #bdc3c7;
        }

        .footer-bottom-links {
            display: flex;
            gap: 2rem;
        }

        .footer-bottom-link {
            color: #bdc3c7;
            text-decoration: none;
            font-size: 0.95rem;
            transition: color 0.3s ease;
        }

        .footer-bottom-link:hover {
            color: var(--primary-color);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .nav-links {
                display: none;
            }

            .search-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .section-header {
                flex-direction: column;
                gap: 1.5rem;
                text-align: center;
            }

            .footer-content {
                grid-template-columns: 1fr;
                text-align: center;
                gap: 2rem;
            }

            .footer-bottom-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .footer-bottom-links {
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .section-title {
                font-size: 2.2rem;
            }

            .kos-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="index.php" class="logo">
                <i class="fas fa-home"></i> TemanKosan
            </a>
            <ul class="nav-links">
                <li><a href="index.php"><i class="fas fa-home"></i> Beranda</a></li>
                <li><a href="search.php"><i class="fas fa-search"></i> Cari Kos</a></li>
                <li><a href="about.php"><i class="fas fa-info-circle"></i> Tentang</a></li>
            </ul>
            <div class="nav-buttons">
                <?php if (isset($_SESSION['user'])): ?>
                    <a href="account.php" class="btn btn-outline">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user']['name']); ?>
                    </a>
                    <a href="logout.php" class="btn btn-primary">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="account.php" class="btn btn-outline">
                        <i class="fas fa-user"></i> Akun
                    </a>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> Masuk
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
            <h1><i class="fas fa-home"></i> TemanKosan</h1>
            <p>Platform booking kos terpercaya dengan ribuan pilihan di seluruh Indonesia. Temukan tempat tinggal impianmu dengan cepat, mudah, dan penuh rasa nyaman. Karena rumah bukan soal tempat, tapi tentang rasa.</p>
            
            <form class="search-form" method="GET" action="search.php">
                <div class="search-grid">
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Cari berdasarkan lokasi</label>
                        <input type="text" name="location" value="<?php echo htmlspecialchars($searchLocation); ?>" placeholder="Masukkan kota atau daerah...">
                    </div>
                    <button type="submit" class="btn-search">
                        <i class="fas fa-search"></i> Cari Kos
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="section stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-home"></i></div>
                    <div class="stat-number"><?php echo number_format($total_kos); ?>+</div>
                    <div class="stat-label">Kos Tersedia</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-number"><?php echo number_format($total_users); ?>+</div>
                    <div class="stat-label">Pengguna Aktif</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-number"><?php echo number_format($total_bookings); ?>+</div>
                    <div class="stat-label">Booking Sukses</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-map-marker-alt"></i></div>
                    <div class="stat-number"><?php echo number_format($total_cities); ?>+</div>
                    <div class="stat-label">Kota Tersedia</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Kos Listings -->
    <section class="section kos-section">
        <div class="container">
            <div class="section-header">
                <div>
                    <h2 class="section-title">
                        <?php echo !empty($searchLocation) ? 'Hasil Pencarian' : 'Kos Terpopuler'; ?>
                    </h2>
                    <?php if (!empty($searchLocation)): ?>
                        <p class="section-subtitle">Menampilkan hasil untuk "<?= htmlspecialchars($searchLocation) ?>"</p>
                    <?php else: ?>
                        <p class="section-subtitle">Kos pilihan terbaik dengan fasilitas lengkap dan lokasi strategis</p>
                    <?php endif; ?>
                </div>
                <a href="search.php" class="btn-view-all">
                    <i class="fas fa-th-large"></i> Lihat Semua
                </a>
            </div>
            
            <?php if (!empty($searchLocation)): ?>
                <div class="search-results">
                    <?php if (count($featuredKos) > 0): ?>
                        <p><i class="fas fa-check-circle"></i> Ditemukan <?php echo count($featuredKos); ?> kos di lokasi "<?php echo htmlspecialchars($searchLocation); ?>"</p>
                    <?php else: ?>
                        <p><i class="fas fa-exclamation-circle"></i> Tidak ditemukan kos di lokasi "<?php echo htmlspecialchars($searchLocation); ?>". Coba kata kunci lain.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="kos-grid">
                <?php foreach ($featuredKos as $index => $kos): ?>
                    <div class="kos-card">
                        <div class="kos-image">
                            <?php if (!empty($kos['primary_image'])): ?>
                                <img src="uploads/<?= htmlspecialchars($kos['primary_image']) ?>" alt="<?= htmlspecialchars($kos['name']) ?>">
                            <?php else: ?>
                                <img src="https://via.placeholder.com/400x250?text=No+Image" alt="No Image">
                            <?php endif; ?>
                        </div>
                        
                        <div class="kos-content">
                            <h3 class="kos-title"><?php echo htmlspecialchars($kos['name']); ?></h3>
                            
                            <p class="kos-location">
                                <i class="fas fa-map-marker-alt"></i> 
                                <?php echo htmlspecialchars($kos['city'] . ', ' . $kos['district']); ?>
                            </p>
                            
                            <div class="kos-rating">
                                <span><?php echo $kos['type'] ?: 0; ?></span>
                                <span>(<?php echo $kos['room_size'] ?: '3x4 meter'; ?> meter)</span>
                            </div>
                            
                            <div class="kos-facilities">
                                <?php 
                                $facilities = explode(', ', $kos['facilities'] ?: 'WiFi, AC, Kamar Mandi');
                                $displayFacilities = array_slice($facilities, 0, 4);
                                foreach ($displayFacilities as $facility): 
                                ?>
                                    <span class="facility-tag"><?php echo htmlspecialchars($facility); ?></span>
                                <?php endforeach; ?>
                                <?php if (count($facilities) > 4): ?>
                                    <span class="facility-tag">+<?php echo count($facilities) - 4; ?> lagi</span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="kos-footer">
                                <div class="kos-price">
                                    Rp <?php echo number_format($kos['price'], 0, ',', '.'); ?>
                                    <span>/bulan</span>
                                </div>
                                <!-- PERBAIKAN: Tombol booking dengan event handler yang benar -->
                                <a href="booking.php?id=<?php echo $kos['id']; ?>" 
                                   class="btn-booking" 
                                   onclick="handleBookingClick(event, <?php echo $kos['id']; ?>)">
                                    <i class="fas fa-calendar-plus"></i> Booking
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer" id="contact">
        <div class="footer-container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3><i class="fas fa-home"></i> TemanKosan</h3>
                    <p class="footer-description">Platform terpercaya untuk mencari kos nyaman dan terjangkau di seluruh Indonesia. Temukan hunian impian Anda dengan mudah dan dapatkan pengalaman terbaik bersama kami!</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>Layanan</h4>
                    <ul class="footer-links">
                        <li><a href="search.php" class="footer-link">Cari Kos</a></li>
                        <li><a href="#" class="footer-link">Bantuan</a></li>
                        <li><a href="#" class="footer-link">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Perusahaan</h4>
                    <ul class="footer-links">
                        <li><a href="about.php" class="footer-link">Tentang Kami</a></li>
                        <li><a href="#" class="footer-link">Karir</a></li>
                        <li><a href="#" class="footer-link">Blog</a></li>
                        <li><a href="#" class="footer-link">Press</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Kontak</h4>
                    <div class="footer-contact">
                        <i class="fas fa-envelope"></i>
                        <span>info@temankosan.com</span>
                    </div>
                    <div class="footer-contact">
                        <i class="fas fa-phone"></i>
                        <span>0800-1234-5678</span>
                    </div>
                    <div class="footer-contact">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Jakarta, Indonesia</span>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <div class="footer-bottom-content">
                    <p>&copy; 2025 TemanKosan. Semua hak dilindungi.</p>
                    <div class="footer-bottom-links">
                        <a href="#" class="footer-bottom-link">Syarat & Ketentuan</a>
                        <a href="#" class="footer-bottom-link">Kebijakan Privasi</a>
                        <a href="#" class="footer-bottom-link">Cookie Policy</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // PERBAIKAN: Function untuk handle booking click dengan benar
        function handleBookingClick(event, kosId) {
            // Cegah event bubbling ke parent elements
            event.stopPropagation();
            
            // Check if user is logged in
            <?php if (isset($_SESSION['user'])): ?>
                // User sudah login, lanjutkan ke booking
                console.log('Redirecting to booking page for kos ID:', kosId);
                window.location.href = 'booking.php?id=' + kosId;
            <?php else: ?>
                // User belum login, redirect ke login page
                console.log('User not logged in, redirecting to login');
                alert('Silakan login terlebih dahulu untuk melakukan booking');
                window.location.href = 'login.php?redirect=' + encodeURIComponent('booking.php?id=' + kosId);
            <?php endif; ?>
            
            return false;
        }

        // Navbar Scroll Effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Toggle Favorite Function
        function toggleFavorite(kosId) {
            <?php if (isset($_SESSION['user'])): ?>
                fetch('api/favorites.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        kos_id: kosId,
                        action: 'toggle'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const btn = event.target.closest('.favorite-btn');
                        btn.classList.toggle('active');
                        
                        // Show notification
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message || 'Gagal mengubah favorit', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Terjadi kesalahan sistem', 'error');
                });
            <?php else: ?>
                showNotification('Silakan login terlebih dahulu', 'warning');
                setTimeout(() => {
                    window.location.href = 'login.php';
                }, 2000);
            <?php endif; ?>
        }

        // Show Notification
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                    <span>${message}</span>
                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            // Add notification styles if not exists
            if (!document.getElementById('notificationStyles')) {
                const notificationStyles = `
                    <style id="notificationStyles">
                        .notification {
                            position: fixed;
                            top: 2rem;
                            right: 2rem;
                            z-index: 10001;
                            max-width: 400px;
                            border-radius: var(--border-radius);
                            box-shadow: var(--shadow-lg);
                            animation: slideInRight 0.3s ease;
                        }
                        
                        .notification-success {
                            background: linear-gradient(135deg, #d4edda, #c3e6cb);
                            border: 1px solid #c3e6cb;
                            color: #155724;
                        }
                        
                        .notification-error {
                            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
                            border: 1px solid #f5c6cb;
                            color: #721c24;
                        }
                        
                        .notification-warning {
                            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
                            border: 1px solid #ffeaa7;
                            color: #856404;
                        }
                        
                        .notification-content {
                            display: flex;
                            align-items: center;
                            gap: 1rem;
                            padding: 1rem 1.5rem;
                        }
                        
                        .notification-close {
                            background: none;
                            border: none;
                            cursor: pointer;
                            opacity: 0.7;
                            transition: opacity 0.3s ease;
                            margin-left: auto;
                        }
                        
                        .notification-close:hover {
                            opacity: 1;
                        }
                        
                        @keyframes slideInRight {
                            from {
                                opacity: 0;
                                transform: translateX(100%);
                            }
                            to {
                                opacity: 1;
                                transform: translateX(0);
                            }
                        }
                    </style>
                `;
                document.head.insertAdjacentHTML('beforeend', notificationStyles);
            }
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            console.log('TemanKosan loaded successfully!');
            
            // Add loading animation to images
            const images = document.querySelectorAll('img');
            images.forEach(img => {
                img.addEventListener('load', function() {
                    this.style.opacity = '1';
                });
                
                img.addEventListener('error', function() {
                    this.src = 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?w=400&h=300&fit=crop';
                });
            });
        });
    </script>
</body>
</html>
