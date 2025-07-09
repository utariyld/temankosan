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

$kos = null;
if (isset($_GET['id'])) {
    $kos_id = $_GET['id'];

    $sql = "SELECT k.*, 
                   l.city, l.district, l.province, 
                   (SELECT GROUP_CONCAT(image_url) FROM kos_images WHERE kos_id = k.id ORDER BY is_primary DESC) AS images,
                   (SELECT COUNT(*) FROM reviews WHERE kos_id = k.id) AS review_count,
                   (SELECT AVG(rating) FROM reviews WHERE kos_id = k.id) AS avg_rating,
                   GROUP_CONCAT(DISTINCT f.name ORDER BY f.name SEPARATOR ', ') AS facilities
            FROM kos k
            LEFT JOIN locations l ON k.location_id = l.id
            LEFT JOIN kos_facilities kf ON k.id = kf.kos_id AND kf.is_available = 1
            LEFT JOIN facilities f ON kf.facility_id = f.id
            WHERE k.id = ? AND k.status = 'published' AND k.is_available = 1
            GROUP BY k.id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$kos_id]);
    $kos = $stmt->fetch(PDO::FETCH_ASSOC);

    // If kos not found, redirect or show error
    if (!$kos) {
        header("Location: index.php");
        exit();
    }

    // Convert comma-separated images string to an array
    if ($kos['images']) {
        $kos['images_array'] = explode(',', $kos['images']);
    } else {
        $kos['images_array'] = [];
    }

} else {
    // If no ID is provided, redirect to index.php
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Kos - <?php echo htmlspecialchars($kos['name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* General Reset & Variables */
        :root {
            --primary-color: #4CAF50; /* Hijau */
            --secondary-color: #FFA726; /* Oranye */
            --text-color: #333;
            --light-gray: #f4f4f4;
            --gray-100: #f7fafc;
            --gray-200: #edf2f7;
            --gray-300: #e2e8f0;
            --gray-400: #cbd5e0;
            --gray-500: #a0aec0;
            --gray-600: #718096;
            --gray-700: #4a5568;
            --gray-800: #2d3748;
            --gray-900: #1a202c;
            --border-radius: 0.5rem;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition-ease: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            color: var(--text-color);
            line-height: 1.6;
            background-color:rgb(210, 231, 218);
        }

        a {
            text-decoration: none;
            color: var(--primary-color);
        }

        ul {
            list-style: none;
        }

        img {
            max-width: 100%;
            height: auto;
            display: block;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Navigation */
        .navbar {
            background: white;
            box-shadow: var(--shadow);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--gray-600);
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            padding: 2rem 0;
        }

        .kos-detail-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            background-color: #fff;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .kos-images {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .kos-images img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .kos-images .main-image {
            grid-column: span 2; 
            height: 400px; 
        }

        .kos-info h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--gray-800);
        }

        .kos-info .location, .kos-info .price, .kos-info .owner {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-size: 1.1rem;
            color: var(--gray-600);
        }

        .kos-info .price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .kos-info .description {
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--gray-700);
        }

        .kos-info .features {
            margin-bottom: 1.5rem;
        }

        .kos-info .features h3 {
            margin-bottom: 0.75rem;
            color: var(--gray-800);
        }

        .kos-info .feature-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .kos-info .feature-list span {
            background-color: var(--gray-100);
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            color: var(--gray-700);
        }

        .contact-owner {
            background-color: var(--gray-100);
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            text-align: center;
        }

        .contact-owner h3 {
            margin-bottom: 1rem;
            color: var(--gray-800);
        }

        .contact-owner .owner-info {
            margin-bottom: 1rem;
            color: var(--gray-700);
        }

        .contact-owner .btn-contact {
            width: 100%;
            padding: 1rem;
            font-size: 1.1rem;
        }

        .rating-reviews {
            margin-top: 2rem;
            background-color: #fff;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .rating-reviews h3 {
            margin-bottom: 1rem;
            color: var(--gray-800);
        }

        .rating-summary {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .rating-summary .avg-rating {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--secondary-color);
        }

        .rating-summary .stars i {
            color: var(--secondary-color);
            font-size: 1.5rem;
        }

        .rating-summary .review-count {
            color: var(--gray-600);
            font-size: 1rem;
        }

        .review-item {
            border-top: 1px solid var(--gray-200);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .review-item .reviewer-name {
            font-weight: 600;
            color: var(--gray-800);
        }

        .review-item .review-rating i {
            color: var(--secondary-color);
            font-size: 1rem;
        }

        .review-item .review-date {
            font-size: 0.9rem;
            color: var(--gray-500);
            margin-left: 0.5rem;
        }

        .review-item .review-comment {
            margin-top: 0.5rem;
            color: var(--gray-700);
        }

        .form-booking {
            background-color: white; /* fallback ke biru */
            padding: 1.5rem 2rem;
            border-radius: var(--border-radius, 8px);
            box-shadow: white;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            max-width: 500px;
            margin: 2rem auto;
        }

        .form-booking h3 {
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            color: #fff;
        }

        .form-booking .btn-booking {
            background-color:rgb(91, 181, 127);
            color: var(--white, #fff);
            padding: 1rem 2.5rem;
            border: none;
            border-radius: var(--border-radius, 8px);
            font-weight: 700;
            font-size: 1.1rem;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            display: inline-block;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        .form-booking .btn-booking:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        /* Footer */
        .footer {
            background-color: var(--gray-800);
            color: #fff;
            padding: 2rem 0;
            text-align: center;
            margin-top: 2rem;
        }

        .footer .container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .footer p {
            font-size: 0.9rem;
            color: var(--gray-400);
        }

        .footer-links a {
            color: var(--gray-400);
            margin: 0 0.75rem;
            font-size: 0.9rem;
            transition: var(--transition-ease);
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 1rem;
            }

            .nav-links {
                flex-direction: column;
                gap: 0.5rem;
            }

            .kos-detail-grid {
                grid-template-columns: 1fr;
            }

            .kos-images {
                grid-template-columns: 1fr;
            }

            .kos-images .main-image {
                grid-column: span 1;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali ke Beranda</span>
            </a>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 1)); ?></div>
                <span><?php echo htmlspecialchars($_SESSION['user']['name']); ?></span>
            </div>
        </div>
    </nav>

    <main class="main-content container">
        <?php if ($kos): ?>
            <div class="kos-detail-grid">
                <div class="kos-main-details">
                    <div class="kos-images">
                        <?php if (!empty($kos['images_array'])): ?>
                            <img src="uploads/<?php echo htmlspecialchars($kos['images_array'][0]); ?>" alt="<?php echo htmlspecialchars($kos['name']); ?>" class="main-image">
                            <?php for ($i = 1; $i < count($kos['images_array']) && $i < 3; $i++): ?>
                                <img src="uploads/<?php echo htmlspecialchars($kos['images_array'][$i]); ?>" alt="<?php echo htmlspecialchars($kos['name']); ?>">
                            <?php endfor; ?>
                        <?php else: ?>
                            <img src="https://via.placeholder.com/600x400?text=No+Image" alt="No Image" class="main-image">
                            <img src="https://via.placeholder.com/300x200?text=No+Image" alt="No Image">
                            <img src="https://via.placeholder.com/300x200?text=No+Image" alt="No Image">
                        <?php endif; ?>
                    </div>

                    <div class="kos-info">
                        <h1><?php echo htmlspecialchars($kos['name']); ?></h1>
                        <div class="price">Rp <?php echo number_format($kos['price'], 0, ',', '.'); ?> / bulan</div>
                        <div class="location">
                            <i class="fas fa-map-marker-alt"></i>
                            <span> <?php echo htmlspecialchars($kos['address'] . ', ' . $kos['district'] . ', ' . $kos['city'] . ', ' . $kos['province']); ?></span>
                        </div>
                        <div class="description">
                            <h3>Deskripsi</h3>
                            <p><?php echo nl2br(htmlspecialchars($kos['description'])); ?></p>
                        </div>
                        <div class="features">
                            <h3>Fasilitas</h3>
                            <div class="feature-list">
                                <?php 
                                    $facilities = explode(', ', $kos['facilities']);
                                    foreach ($facilities as $facility):
                                ?>
                                    <span><?php echo htmlspecialchars(trim($facility)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        </div>
                </div>

                <div class="sidebar">
                    <div class="form-booking">
                        <a href="booking.php?id=<?php echo $kos['id']; ?>" class="btn-booking">
                            <h3>Booking Sekarang</h3>
                        </a>
                    </div>
                </div>
            </div>
                
                <?php
                // Fetch reviews for this kos
                $reviews_sql = "SELECT r.*, u.name as reviewer_name 
                                FROM reviews r
                                LEFT JOIN users u ON r.user_id = u.id
                                WHERE r.kos_id = ?
                                ORDER BY r.created_at DESC";
                $reviews_stmt = $pdo->prepare($reviews_sql);
                $reviews_stmt->execute([$kos_id]);
                $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($reviews)):
                    foreach ($reviews as $review):
                ?>
                    <div class="review-item">
                        <div class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></div>
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= $review['rating']): ?>
                                    <i class="fas fa-star"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; ?>
                            <?php endfor; ?>
                            <span class="review-date"><?php echo date('d M Y', strtotime($review['created_at'])); ?></span>
                        </div>
                        <div class="review-comment">
                            <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                        </div>
                    </div>
                <?php
                    endforeach;
                else:
                ?>
                
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p>Kos tidak ditemukan.</p>
            <a href="index.php" class="btn btn-primary">Kembali ke Beranda</a>
        <?php endif; ?>
    </main>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> TemanKosan. All rights reserved.</p>
            <div class="footer-links">
                <a href="#">Kebijakan Privasi</a> |
                <a href="#">Syarat dan Ketentuan</a> |
                <a href="#">Bantuan</a>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fadeOutStyles = `
                <style>
                    @keyframes fadeOut {
                        from { opacity: 1; }
                        to { opacity: 0; }
                    }
                    
                    @keyframes slideOutRight {
                        from {
                            opacity: 1;
                            transform: translateX(0);
                        }
                        to {
                            opacity: 0;
                            transform: translateX(100%);
                        }
                    }
                    
                    .tooltip {
                        position: absolute;
                        background: var(--gray-800);
                        color: white;
                        padding: 0.5rem 1rem;
                        border-radius: var(--border-radius);
                        font-size: 0.9rem;
                        z-index: 10000;
                        pointer-events: none;
                        animation: fadeIn 0.2s ease;
                    }
                    @keyframes fadeIn { /* Ensure fadeIn is defined if used by tooltip */
                        from { opacity: 0; }
                        to { opacity: 1; }
                    }
                </style>
            `;
            document.head.insertAdjacentHTML('beforeend', fadeOutStyles);
        });
    </script>
</body>
</html>