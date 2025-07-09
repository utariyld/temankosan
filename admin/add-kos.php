<?php
session_start();
require_once '../config/database.php';

// Get database connection
try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Get locations for dropdown
$locationsQuery = "SELECT * FROM locations ORDER BY province, city, district";
$locationsStmt = $pdo->prepare($locationsQuery);
$locationsStmt->execute();
$locations = $locationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get facilities for checkboxes
$facilitiesQuery = "SELECT * FROM facilities WHERE is_active = 1 ORDER BY category, name";
$facilitiesStmt = $pdo->prepare($facilitiesQuery);
$facilitiesStmt->execute();
$facilities = $facilitiesStmt->fetchAll(PDO::FETCH_ASSOC);

// Group facilities by category
$facilitiesByCategory = [];
foreach ($facilities as $facility) {
    $facilitiesByCategory[$facility['category']][] = $facility;
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize input
        $name = trim($_POST['name'] ?? '');
        $locationId = (int)($_POST['location_id'] ?? 0);
        $address = trim($_POST['address'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $type = $_POST['type'] ?? '';
        $roomSize = trim($_POST['room_size'] ?? '');
        $totalRooms = (int)($_POST['total_rooms'] ?? 0);
        $availableRooms = (int)($_POST['available_rooms'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $selectedFacilities = $_POST['facilities'] ?? [];
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Nama kos harus diisi';
        }
        
        if ($locationId <= 0) {
            $errors[] = 'Lokasi harus dipilih';
        }
        
        if (empty($address)) {
            $errors[] = 'Alamat harus diisi';
        }
        
        if ($price <= 0) {
            $errors[] = 'Harga harus lebih dari 0';
        }
        
        if (empty($type)) {
            $errors[] = 'Tipe kos harus dipilih';
        }
        
        if ($totalRooms <= 0) {
            $errors[] = 'Jumlah kamar harus lebih dari 0';
        }
        
        if ($availableRooms < 0 || $availableRooms > $totalRooms) {
            $errors[] = 'Kamar tersedia tidak valid';
        }
        
        // Validate images
        $uploadedImages = [];
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $maxFileSize = 5 * 1024 * 1024; // 5MB
            $maxImages = 10;
            
            if (count($_FILES['images']['name']) > $maxImages) {
                $errors[] = "Maksimal $maxImages gambar yang dapat diupload";
            }
            
            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                    $fileType = $_FILES['images']['type'][$i];
                    $fileSize = $_FILES['images']['size'][$i];
                    
                    if (!in_array($fileType, $allowedTypes)) {
                        $errors[] = "File ke-" . ($i + 1) . " harus berformat JPG, PNG, atau WebP";
                    }
                    
                    if ($fileSize > $maxFileSize) {
                        $errors[] = "File ke-" . ($i + 1) . " maksimal 5MB";
                    }
                } elseif ($_FILES['images']['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    $errors[] = "Error upload file ke-" . ($i + 1);
                }
            }
        }
        
        if (!empty($errors)) {
            $error = implode('<br>', $errors);
        } else {
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Generate slug
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
                
                // Check if slug exists, if so add number
                $slugCheck = "SELECT COUNT(*) FROM kos WHERE slug = ?";
                $slugStmt = $pdo->prepare($slugCheck);
                $slugStmt->execute([$slug]);
                if ($slugStmt->fetchColumn() > 0) {
                    $slug .= '-' . time();
                }
                
                // Insert kos
                $kosQuery = "INSERT INTO kos (
                    location_id, name, slug, description, address, price, type, 
                    room_size, total_rooms, available_rooms, is_available, 
                    is_featured, rating, review_count, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, 0, 0, NOW(), NOW())";
                
                $kosStmt = $pdo->prepare($kosQuery);
                $kosStmt->execute([
                    $locationId, $name, $slug, $description, $address, $price, 
                    $type, $roomSize, $totalRooms, $availableRooms
                ]);
                
                $kosId = $pdo->lastInsertId();
                
                // Handle image uploads
                if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
                    $uploadDir = '../uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                        if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
                            $originalName = $_FILES['images']['name'][$i];
                            $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                            $fileName = 'kos' . $kosId . '_' . ($i + 1) . '_' . time() . '.' . $extension;
                            $filePath = $uploadDir . $fileName;
                            
                            if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $filePath)) {
                                // Insert image record
                                $imageQuery = "INSERT INTO kos_images (
                                    kos_id, image_url, alt_text, is_primary, sort_order, created_at
                                ) VALUES (?, ?, ?, ?, ?, NOW())";
                                
                                $isPrimary = ($i === 0) ? 1 : 0; // First image is primary
                                $altText = $name . ' - Gambar ' . ($i + 1);
                                
                                $imageStmt = $pdo->prepare($imageQuery);
                                $imageStmt->execute([$kosId, $fileName, $altText, $isPrimary, $i + 1]);
                            }
                        }
                    }
                }
                
                // Insert facilities
                if (!empty($selectedFacilities)) {
                    $facilityQuery = "INSERT INTO kos_facilities (kos_id, facility_id, is_available, created_at) VALUES (?, ?, 1, NOW())";
                    $facilityStmt = $pdo->prepare($facilityQuery);
                    
                    foreach ($selectedFacilities as $facilityId) {
                        $facilityStmt->execute([$kosId, (int)$facilityId]);
                    }
                }
                
                // Commit transaction
                $pdo->commit();
                
                $success = 'Kos berhasil ditambahkan!';
                
                // Clear form data
                $_POST = [];
                
            } catch (Exception $e) {
                $pdo->rollback();
                throw $e;
            }
        }
        
    } catch (Exception $e) {
        $error = 'Terjadi kesalahan: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kos - TemanKosan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4CAF50;
            --secondary-color: #FFA726;
            --accent-color: #FF5722;
            --text-color: #333;
            --light-gray: #f8f9fa;
            --border-radius: 0.75rem;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background-color:rgb(225, 162, 202);
        }

        /* Navigation */
        .navbar {
            background: white;
            box-shadow: var(--box-shadow);
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
            color: #666;
            text-decoration: none;
            transition: var(--transition);
            font-weight: 500;
        }

        .back-link:hover {
            color: var(--primary-color);
        }

        /* Main Content */
        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-error {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #feb2b2;
        }

        .alert-success {
            background: #c6f6d5;
            color: #2f855a;
            border: 1px solid #9ae6b4;
        }

        /* Form Sections */
        .form-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--box-shadow);
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-color);
        }

        .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 0.875rem;
            border: 2px solid #e0e0e0;
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* Image Upload */
        .image-upload {
            border: 2px dashed #ddd;
            border-radius: var(--border-radius);
            padding: 2rem;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }

        .image-upload:hover {
            border-color: var(--primary-color);
            background: rgba(76, 175, 80, 0.05);
        }

        .image-upload.dragover {
            border-color: var(--primary-color);
            background: rgba(76, 175, 80, 0.1);
        }

        .upload-icon {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }

        .upload-text {
            color: #666;
            margin-bottom: 1rem;
        }

        .file-input {
            display: none;
        }

        .image-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .preview-item {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            aspect-ratio: 1;
        }

        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .remove-image {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(255, 0, 0, 0.8);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .primary-badge {
            position: absolute;
            bottom: 0.5rem;
            left: 0.5rem;
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }

        /* Facilities */
        .facilities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .facility-category {
            background: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 1rem;
        }

        .category-title {
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--text-color);
            text-transform: capitalize;
        }

        .facility-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .facility-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-color);
        }

        .facility-item label {
            cursor: pointer;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .facility-icon {
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
            font-size: 1rem;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .btn-submit {
            background: var(--primary-color);
            color: white;
        }

        .btn-submit:hover {
            background: #45a049;
            transform: translateY(-2px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .facilities-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i>
                <span>Kembali ke Dashboard</span>
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-plus-circle"></i>
                Tambah Kos Baru
            </h1>
            <p class="page-subtitle">Lengkapi informasi kos yang akan Anda tambahkan ke platform TemanKosan</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo $error; ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" novalidate>
            <!-- Basic Information -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-info-circle"></i>
                    Informasi Dasar
                </h2>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="name">Nama Kos <span class="required">*</span></label>
                        <input type="text" id="name" name="name" required 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                               placeholder="Contoh: Kos Melati Residence">
                    </div>
                    <div class="form-group">
                        <label for="location_id">Lokasi <span class="required">*</span></label>
                        <select id="location_id" name="location_id" required>
                            <option value="">Pilih lokasi</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?php echo $location['id']; ?>"
                                        <?php echo (($_POST['location_id'] ?? '') == $location['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($location['district'] . ', ' . $location['city'] . ', ' . $location['province']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="price">Harga per Bulan (Rp) <span class="required">*</span></label>
                        <input type="number" id="price" name="price" required min="0"
                               value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>"
                               placeholder="1500000">
                    </div>
                    <div class="form-group">
                        <label for="type">Tipe Kos <span class="required">*</span></label>
                        <select id="type" name="type" required>
                            <option value="">Pilih tipe kos</option>
                            <option value="putra" <?php echo (($_POST['type'] ?? '') == 'putra') ? 'selected' : ''; ?>>Putra</option>
                            <option value="putri" <?php echo (($_POST['type'] ?? '') == 'putri') ? 'selected' : ''; ?>>Putri</option>
                            <option value="putra-putri" <?php echo (($_POST['type'] ?? '') == 'putra-putri') ? 'selected' : ''; ?>>Putra/Putri</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="room_size">Ukuran Kamar</label>
                        <input type="text" id="room_size" name="room_size"
                               value="<?php echo htmlspecialchars($_POST['room_size'] ?? ''); ?>"
                               placeholder="Contoh: 3x4 meter">
                    </div>
                    <div class="form-group">
                        <label for="total_rooms">Total Kamar <span class="required">*</span></label>
                        <input type="number" id="total_rooms" name="total_rooms" required min="1"
                               value="<?php echo htmlspecialchars($_POST['total_rooms'] ?? ''); ?>"
                               placeholder="20">
                    </div>
                    <div class="form-group">
                        <label for="available_rooms">Kamar Tersedia <span class="required">*</span></label>
                        <input type="number" id="available_rooms" name="available_rooms" required min="0"
                               value="<?php echo htmlspecialchars($_POST['available_rooms'] ?? ''); ?>"
                               placeholder="15">
                    </div>
                    <div class="form-group full-width">
                        <label for="address">Alamat Lengkap <span class="required">*</span></label>
                        <textarea id="address" name="address" required
                                  placeholder="Masukkan alamat lengkap kos termasuk nomor, jalan, RT/RW"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group full-width">
                        <label for="description">Deskripsi Kos</label>
                        <textarea id="description" name="description"
                                  placeholder="Deskripsikan kos Anda secara detail, termasuk keunggulan dan aturan kos"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Image Upload -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-images"></i>
                    Foto Kos
                </h2>
                <div class="image-upload" onclick="document.getElementById('images').click()">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="upload-text">
                        <strong>Klik untuk upload foto</strong> atau drag & drop di sini
                    </div>
                    <small>Format: JPG, PNG, WebP. Maksimal 5MB per file. Maksimal 10 foto.</small>
                    <input type="file" id="images" name="images[]" multiple accept="image/*" class="file-input">
                </div>
                <div id="imagePreview" class="image-preview"></div>
            </div>

            <!-- Facilities -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="fas fa-home"></i>
                    Fasilitas
                </h2>
                <div class="facilities-grid">
                    <?php foreach ($facilitiesByCategory as $category => $categoryFacilities): ?>
                        <div class="facility-category">
                            <h3 class="category-title"><?php echo htmlspecialchars($category); ?></h3>
                            <?php foreach ($categoryFacilities as $facility): ?>
                                <div class="facility-item">
                                    <input type="checkbox" 
                                           id="facility-<?php echo $facility['id']; ?>" 
                                           name="facilities[]" 
                                           value="<?php echo $facility['id']; ?>"
                                           <?php echo in_array($facility['id'], $_POST['facilities'] ?? []) ? 'checked' : ''; ?>>
                                    <label for="facility-<?php echo $facility['id']; ?>">
                                        <i class="facility-icon <?php echo htmlspecialchars($facility['icon']); ?>"></i>
                                        <?php echo htmlspecialchars($facility['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <a href="dashboard.php" class="btn btn-cancel">
                    <i class="fas fa-times"></i>
                    Batal
                </a>
                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-save"></i>
                    Tambah Kos
                </button>
            </div>
        </form>
    </div>

    <script>
        // Image upload handling
        const imageInput = document.getElementById('images');
        const imagePreview = document.getElementById('imagePreview');
        const uploadArea = document.querySelector('.image-upload');
        let selectedFiles = [];

        // Handle file selection
        imageInput.addEventListener('change', function(e) {
            handleFiles(e.target.files);
        });

        // Handle drag and drop
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        function handleFiles(files) {
            selectedFiles = Array.from(files);
            updateImagePreview();
        }

        function updateImagePreview() {
            imagePreview.innerHTML = '';
            
            selectedFiles.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'preview-item';
                        previewItem.innerHTML = `
                            <img src="${e.target.result}" alt="Preview ${index + 1}">
                            <button type="button" class="remove-image" onclick="removeImage(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                            ${index === 0 ? '<div class="primary-badge">Utama</div>' : ''}
                        `;
                        imagePreview.appendChild(previewItem);
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Update file input
            const dt = new DataTransfer();
            selectedFiles.forEach(file => dt.items.add(file));
            imageInput.files = dt.files;
        }

        function removeImage(index) {
            selectedFiles.splice(index, 1);
            updateImagePreview();
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const totalRooms = parseInt(document.getElementById('total_rooms').value) || 0;
            const availableRooms = parseInt(document.getElementById('available_rooms').value) || 0;
            
            if (availableRooms > totalRooms) {
                e.preventDefault();
                alert('Kamar tersedia tidak boleh lebih dari total kamar!');
                return false;
            }
        });

        // Auto-update available rooms when total rooms changes
        document.getElementById('total_rooms').addEventListener('input', function() {
            const totalRooms = parseInt(this.value) || 0;
            const availableRoomsInput = document.getElementById('available_rooms');
            const currentAvailable = parseInt(availableRoomsInput.value) || 0;
            
            if (currentAvailable > totalRooms) {
                availableRoomsInput.value = totalRooms;
            }
            
            availableRoomsInput.max = totalRooms;
        });
    </script>
</body>
</html>