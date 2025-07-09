<?php
session_start();
require_once 'config/database.php';
require_once 'models/Kos.php';
require_once 'includes/functions.php';

$kosModel = new Kos();

// Get all available facilities for filter options
$allFacilities = [
    'WiFi', 'AC', 'Kamar Mandi Dalam', 'Kamar Mandi Luar', 'Parkir Motor', 'Parkir Mobil',
    'Dapur', 'Kulkas', 'Laundry', 'Security 24 Jam', 'CCTV', 'Akses 24 Jam',
    'Dekat Kampus', 'Dekat Mall', 'Dekat Stasiun', 'Dekat Halte', 'Warung Makan',
    'Minimarket', 'ATM', 'Apotek', 'Rumah Sakit', 'Masjid', 'Gereja'
];

// Get search parameters
$searchLocation = isset($_GET['location']) ? trim($_GET['location']) : '';
$selectedFacilities = isset($_GET['facilities']) ? $_GET['facilities'] : [];
$minPrice = isset($_GET['min_price']) ? (int)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) ? (int)$_GET['max_price'] : 10000000;
$kosType = isset($_GET['type']) ? $_GET['type'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Sample data with more detailed facilities
$kosData = get_search_kos();

// Filter the data based on search criteria
$filteredKos = $kosData;

// Filter by location
if (!empty($searchLocation)) {
    $filteredKos = array_filter($filteredKos, function($kos) use ($searchLocation) {
        return stripos($kos['location'], $searchLocation) !== false || 
               stripos($kos['name'], $searchLocation) !== false ||
               stripos($kos['address'], $searchLocation) !== false;
    });
}

// Filter by facilities
if (!empty($selectedFacilities)) {
    $filteredKos = array_filter($filteredKos, function($kos) use ($selectedFacilities) {
        foreach ($selectedFacilities as $facility) {
            if (!in_array($facility, $kos['facilities'])) {
                return false;
            }
        }
        return true;
    });
}

// Filter by price range
$filteredKos = array_filter($filteredKos, function($kos) use ($minPrice, $maxPrice) {
    return $kos['price'] >= $minPrice && $kos['price'] <= $maxPrice;
});

// Filter by type
if (!empty($kosType)) {
    $filteredKos = array_filter($filteredKos, function($kos) use ($kosType) {
        return $kos['type'] === $kosType;
    });
}

// Sort the results
switch ($sortBy) {
    case 'price_low':
        usort($filteredKos, function($a, $b) { return $a['price'] - $b['price']; });
        break;
    case 'price_high':
        usort($filteredKos, function($a, $b) { return $b['price'] - $a['price']; });
        break;
    case 'rating':
        usort($filteredKos, function($a, $b) { return $b['rating'] <=> $a['rating']; });
        break;
    case 'newest':
    default:
        usort($filteredKos, function($a, $b) { return strtotime($b['created_at']) - strtotime($a['created_at']); });
        break;
}

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
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pencarian Kos - TemanKosan</title>
    <!-- Load CSS files -->
    <link rel="stylesheet" href="assets/css/live-search.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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

        /* Main Content */
        .main-content {
            margin-top: 100px;
            padding: 2rem 0;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 800;
            color: #333;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #00c851, #ff69b4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: #666;
            max-width: 600px;
            margin: 0 auto;
        }

        /* Search Layout */
        .search-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 3rem;
            align-items: start;
        }

        /* Search Filters */
        .search-filters {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            position: sticky;
            top: 120px;
            max-height: calc(100vh - 140px);
            overflow-y: auto;
        }

        .filter-section {
            margin-bottom: 2rem;
        }

        .filter-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00c851;
            box-shadow: 0 0 0 3px rgba(0, 200, 81, 0.1);
        }

        .price-range {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* Facilities Checkboxes */
        .facilities-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 0.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
        }

        .facility-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: 0.3s ease;
        }

        .facility-item:hover {
            background: rgba(0, 200, 81, 0.05);
        }

        .facility-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .facility-item label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }

        /* Filter Buttons */
        .filter-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-filter {
            flex: 1;
            padding: 0.75rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-search-filter {
            background: linear-gradient(135deg, #00c851, #00a844);
            color: white;
        }

        .btn-search-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 200, 81, 0.3);
        }

        .btn-reset {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #e0e0e0;
        }

        .btn-reset:hover {
            background: #e9ecef;
        }

        /* Search Results */
        .search-results {
            flex: 1;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.05);
        }

        .results-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
        }

        .sort-dropdown {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .sort-dropdown select {
            padding: 0.5rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-weight: 500;
        }

        /* Kos Grid */
        .kos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .kos-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            transition: all 0.4s ease;
            cursor: pointer;
            position: relative;
        }

        .kos-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .kos-image {
            position: relative;
            height: 220px;
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
            top: 1rem;
            left: 1rem;
            background: linear-gradient(135deg, #00c851, #00a844);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 700;
            z-index: 2;
        }

        .favorite-btn {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.95);
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .favorite-btn:hover {
            background: white;
            transform: scale(1.1);
        }

        .kos-content {
            padding: 1.5rem;
        }

        .kos-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .kos-location {
            color: #666;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.95rem;
        }

        .kos-address {
            color: #888;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .kos-rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .stars {
            color: #ffc107;
        }

        .kos-facilities {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .facility-tag {
            background: linear-gradient(135deg, #e9ecef, #f8f9fa);
            color: #495057;
            padding: 0.3rem 0.6rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .kos-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 1px solid #f0f0f0;
        }

        .kos-price {
            font-size: 1.3rem;
            font-weight: 800;
            background: linear-gradient(135deg, #00c851, #00a844);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .kos-price span {
            font-size: 0.8rem;
            color: #666;
        }

        .btn-booking {
            background: linear-gradient(135deg, #ff69b4, #ff1493);
            color: white;
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 105, 180, 0.4);
        }

        /* No Results */
        .no-results {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
        }

        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .no-results h3 {
            font-size: 1.5rem;
            color: #333;
            margin-bottom: 1rem;
        }

        .no-results p {
            color: #666;
            margin-bottom: 2rem;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .search-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .search-filters {
                position: static;
                max-height: none;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .page-title {
                font-size: 2.2rem;
            }

            .results-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .kos-grid {
                grid-template-columns: 1fr;
            }

            .price-range {
                grid-template-columns: 1fr;
            }

            .filter-buttons {
                flex-direction: column;
            }
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

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h1 class="page-title">🔍 Pencarian Kos</h1>
                <p class="page-subtitle">Temukan kos impian Anda dengan filter fasilitas yang lengkap dan detail</p>
            </div>

            <!-- Search Layout -->
            <div class="search-layout">
                <!-- Search Filters -->
                <div class="search-filters">
                    <form method="GET" action="search.php" id="searchForm">
                        <!-- Location Filter with Live Search -->
                        <div class="filter-section">
                            <h3 class="filter-title">📍 Lokasi</h3>
                            <div class="form-group">
                                <div class="live-search-wrapper">
                                    <input type="text" 
                                           name="location" 
                                           id="locationSearch"
                                           value="<?php echo htmlspecialchars($searchLocation); ?>" 
                                           placeholder="Masukkan kota atau daerah..." 
                                           autocomplete="off">
                                    <div id="liveSearchResults" class="live-search-results"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Range Filter -->
                        <div class="filter-section">
                            <h3 class="filter-title">💰 Rentang Harga</h3>
                            <div class="price-range">
                                <div class="form-group">
                                    <label>Harga Minimum</label>
                                    <input type="number" name="min_price" value="<?php echo $minPrice; ?>" placeholder="0" min="0" step="100000">
                                </div>
                                <div class="form-group">
                                    <label>Harga Maksimum</label>
                                    <input type="number" name="max_price" value="<?php echo $maxPrice; ?>" placeholder="10000000" min="0" step="100000">
                                </div>
                            </div>
                        </div>

                        <!-- Type Filter -->
                        <div class="filter-section">
                            <h3 class="filter-title">👥 Tipe Kos</h3>
                            <div class="form-group">
                                <select name="type">
                                    <option value="">Semua Tipe</option>
                                    <option value="putra" <?php echo $kosType === 'putra' ? 'selected' : ''; ?>>Putra</option>
                                    <option value="putri" <?php echo $kosType === 'putri' ? 'selected' : ''; ?>>Putri</option>
                                    <option value="putra-putri" <?php echo $kosType === 'putra-putri' ? 'selected' : ''; ?>>Putra/Putri</option>
                                </select>
                            </div>
                        </div>

                        <!-- Facilities Filter -->
                        <div class="filter-section">
                            <h3 class="filter-title">🏠 Fasilitas</h3>
                            <div class="facilities-grid">
                                <?php foreach ($allFacilities as $facility): ?>
                                    <div class="facility-item">
                                        <input type="checkbox" 
                                               name="facilities[]" 
                                               value="<?php echo htmlspecialchars($facility); ?>" 
                                               id="facility_<?php echo str_replace(' ', '_', $facility); ?>"
                                               <?php echo in_array($facility, $selectedFacilities) ? 'checked' : ''; ?>>
                                        <label for="facility_<?php echo str_replace(' ', '_', $facility); ?>">
                                            <?php echo htmlspecialchars($facility); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="filter-buttons">
                            <button type="submit" class="btn-filter btn-search-filter">🔍 Cari Kos</button>
                            <button type="button" class="btn-filter btn-reset" onclick="resetFilters()">🔄 Reset</button>
                        </div>
                    </form>
                </div>

                <!-- Search Results -->
                <div class="search-results">
                    <!-- Results Header -->
                    <div class="results-header">
                        <div class="results-count">
                            <?php if (count($filteredKos) > 0): ?>
                                🎉 Ditemukan <?php echo count($filteredKos); ?> kos sesuai kriteria Anda
                            <?php else: ?>
                                😔 Tidak ada kos yang sesuai dengan kriteria pencarian
                            <?php endif; ?>
                        </div>
                        
                        <?php if (count($filteredKos) > 0): ?>
                            <div class="sort-dropdown">
                                <label>Urutkan:</label>
                                <select name="sort" onchange="updateSort(this.value)">
                                    <option value="newest" <?php echo $sortBy === 'newest' ? 'selected' : ''; ?>>Terbaru</option>
                                    <option value="price_low" <?php echo $sortBy === 'price_low' ? 'selected' : ''; ?>>Harga Terendah</option>
                                    <option value="price_high" <?php echo $sortBy === 'price_high' ? 'selected' : ''; ?>>Harga Tertinggi</option>
                                    <option value="rating" <?php echo $sortBy === 'rating' ? 'selected' : ''; ?>>Rating Tertinggi</option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Kos Grid -->
                    <?php if (count($filteredKos) > 0): ?>
                        <div class="kos-grid">
                            <?php foreach ($filteredKos as $kos): ?>
                                <div class="kos-card" onclick="window.location.href='kos-detail.php?id=<?php echo $kos['id']; ?>'">
                                    <div class="kos-image">
                                        <?php if (!empty($kos['image'])): ?>
                                            <img src="uploads/<?= htmlspecialchars($kos['image']) ?>" alt="<?= htmlspecialchars($kos['name']) ?>">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/400x250?text=No+Image" alt="No Image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="kos-content">
                                        <h3 class="kos-title"><?php echo htmlspecialchars($kos['name']); ?></h3>
                                        <div class="kos-location">
                                            📍 <?php echo htmlspecialchars($kos['location']); ?>
                                        </div>
                                        <div class="kos-address">
                                            <?php echo htmlspecialchars($kos['address']); ?>
                                        </div>
                                        <div class="kos-rating">
                                            <span class="stars">⭐</span>
                                            <span><?php echo $kos['rating']; ?></span>
                                            <span>(<?php echo $kos['reviewCount']; ?> ulasan)</span>
                                            <span>• <?php echo $kos['room_size']; ?></span>
                                        </div>
                                        <div class="kos-facilities">
                                            <?php 
                                            $displayFacilities = array_slice($kos['facilities'], 0, 4);
                                            foreach ($displayFacilities as $facility): 
                                            ?>
                                                <span class="facility-tag"><?php echo htmlspecialchars($facility); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($kos['facilities']) > 4): ?>
                                                <span class="facility-tag">+<?php echo count($kos['facilities']) - 4; ?> lagi</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="kos-footer">
                                            <div class="kos-price">
                                                Rp <?php echo number_format($kos['price'], 0, ',', '.'); ?>
                                                <span>/bulan</span>
                                            </div>
                                            <a href="booking.php?id=<?php echo $kos['id']; ?>" class="btn-booking" onclick="event.stopPropagation();">
                                                Booking
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <!-- No Results -->
                        <div class="no-results">
                            <div class="no-results-icon">🏠</div>
                            <h3>Tidak Ada Kos Ditemukan</h3>
                            <p>Coba ubah kriteria pencarian Anda atau hapus beberapa filter untuk melihat lebih banyak hasil.</p>
                            <button class="btn-filter btn-search-filter" onclick="resetFilters()">Reset Pencarian</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

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
                    <p>&copy; 2024 TemanKosan. Semua hak dilindungi.</p>
                    <div class="footer-bottom-links">
                        <a href="#" class="footer-bottom-link">Syarat & Ketentuan</a>
                        <a href="#" class="footer-bottom-link">Kebijakan Privasi</a>
                        <a href="#" class="footer-bottom-link">Cookie Policy</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Load JavaScript files -->
    <script src="assets/js/live-search.js"></script>

    <script>
        // Initialize live search when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize live search with custom options
            const liveSearch = new LiveSearch('#locationSearch', '#liveSearchResults', {
                minLength: 2,
                debounceTime: 300,
                maxResults: 8,
                apiUrl: 'api/live-search.php'
            });

            // Handle form submission when selecting from live search results
            document.addEventListener('click', function(e) {
                if (e.target.closest('.search-result-item')) {
                    const resultItem = e.target.closest('.search-result-item');
                    const locationInput = document.getElementById('locationSearch');
                    const title = resultItem.querySelector('.result-title').textContent;
                    const location = resultItem.querySelector('.result-location').textContent.replace('📍 ', '');
                    
                    // Set the input value to the selected location
                    locationInput.value = location;
                    
                    // Hide the results
                    document.getElementById('liveSearchResults').style.display = 'none';
                }
            });
        });

        // Toggle favorite function
        function toggleFavorite(kosId) {
            <?php if (isset($_SESSION['user'])): ?>
                // Add logic to add/remove favorite
                console.log('Toggle favorite for kos:', kosId);
                // You can implement AJAX call here to update favorites
            <?php else: ?>
                alert('Silakan login terlebih dahulu untuk menambah favorit');
                window.location.href = 'login.php';
            <?php endif; ?>
        }

        // Reset filter function
        function resetFilters() {
            window.location.href = 'search.php';
        }

        // Update sorting function
        function updateSort(sortValue) {
            const url = new URL(window.location);
            url.searchParams.set('sort', sortValue);
            window.location.href = url.toString();
        }

        // Auto-submit form when checkbox facilities are changed (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('input[name="facilities[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    // Uncomment the line below if you want auto-submit on facility change
                    // document.getElementById('searchForm').submit();
                });
            });

            // Add animation to kos cards
            const cards = document.querySelectorAll('.kos-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.style.animation = 'fadeInUp 0.6s ease-out forwards';
            });
        });

        // Add fadeInUp animation CSS
        const style = document.createElement('style');
        style.textContent = `
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
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>