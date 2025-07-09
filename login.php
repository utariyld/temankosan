<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            $email = sanitize_input($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                $error = 'Email dan password harus diisi!';
            } else {
                $user = authenticate_user($email, $password);
                if ($user) {
                    $_SESSION['user'] = $user;
                    $_SESSION['last_activity'] = time();
                    
                    log_activity($user['id'], 'login', 'User logged in successfully');
                    
                    $redirect = $user['role'] === 'admin' ? 'admin/dashboard.php' : 'index.php';
                    header('Location: ' . $redirect);
                    exit;
                } else {
                    $error = 'Email atau password salah!';
                    log_activity(null, 'login_failed', 'Failed login attempt for email: ' . $email);
                }
            }
        } elseif ($_POST['action'] === 'register') {
            $name = sanitize_input($_POST['name'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');
            $phone = sanitize_input($_POST['phone'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $role = sanitize_input($_POST['role'] ?? 'member');
            
            $validation_errors = validate_registration($name, $email, $phone, $password, $confirmPassword);
            
            if (!empty($validation_errors)) {
                $error = implode('<br>', $validation_errors);
            } else {
                if (email_exists($email)) {
                    $error = 'Email sudah terdaftar!';
                } else {
                    $user_id = create_user($name, $email, $phone, $password, $role);
                    if ($user_id) {
                        $success = 'Registrasi berhasil! Silakan login dengan akun Anda.';
                        log_activity($user_id, 'register', 'New user registered');
                    } else {
                        $error = 'Terjadi kesalahan saat mendaftar. Silakan coba lagi.';
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TemanKosan</title>
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
            background: linear-gradient(135deg, var(--primary-color) 0%, #00a844 30%, var(--secondary-color) 70%, var(--accent-color) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Background decorative elements */
        body::before,
        body::after {
            content: "";
            position: fixed;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 0;
        }

        body::before {
            top: 10%;
            left: 10%;
            width: 200px;
            height: 200px;
            animation: float 6s ease-in-out infinite;
        }

        body::after {
            bottom: 10%;
            right: 10%;
            width: 150px;
            height: 150px;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Back button */
        .back-link {
            position: fixed;
            top: 2rem;
            left: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.2);
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            transition: var(--transition);
            z-index: 1000;
            font-weight: 500;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-2px);
        }

        /* Modal styles - always visible */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: slideInUp 0.3s ease;
            position: relative;
        }

        .modal-content::-webkit-scrollbar {
            width: 6px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 3px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: var(--gray-400);
            border-radius: 3px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .modal-header h3 {
            margin: 0;
            color: var(--gray-800);
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-600);
            transition: var(--transition);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--gray-800);
            background: var(--gray-100);
        }

        /* Alert messages */
        .alert {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
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

        /* Tabs */
        .tabs {
            display: flex;
            margin-bottom: 2rem;
            background: var(--gray-100);
            border-radius: var(--border-radius);
            padding: 0.25rem;
        }

        .tab {
            flex: 1;
            padding: 0.875rem 1rem;
            text-align: center;
            background: transparent;
            border: none;
            border-radius: calc(var(--border-radius) - 0.25rem);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--gray-600);
        }

        .tab.active {
            background: white;
            color: var(--primary-color);
            box-shadow: var(--shadow-sm);
        }

        /* Forms */
        .auth-form {
            display: none;
        }

        .auth-form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.9rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            color: var(--gray-400);
            z-index: 1;
        }

        .input-wrapper input,
        .input-wrapper select {
            width: 100%;
            padding: 0.875rem 1rem 0.875rem 2.75rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            transition: var(--transition);
            background: var(--gray-50);
        }

        .input-wrapper input:focus,
        .input-wrapper select:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(0, 200, 81, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--primary-color);
        }

        /* Radio buttons for user type */
        .radio-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .radio-wrapper {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 1rem;
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            transition: var(--transition);
            position: relative;
        }

        .radio-wrapper:hover {
            border-color: var(--primary-color);
            background: rgba(0, 200, 81, 0.05);
        }

        .radio-wrapper input[type="radio"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .radio-mark {
            width: 20px;
            height: 20px;
            border: 2px solid var(--gray-300);
            border-radius: 50%;
            margin-right: 0.75rem;
            position: relative;
            transition: var(--transition);
        }

        .radio-wrapper input[type="radio"]:checked ~ .radio-mark {
            border-color: var(--primary-color);
            background: var(--primary-color);
        }

        .radio-wrapper input[type="radio"]:checked ~ .radio-mark::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 8px;
            height: 8px;
            background: white;
            border-radius: 50%;
        }

        .radio-content {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .radio-content i {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        /* Checkbox */
        .checkbox-wrapper {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
            gap: 0.75rem;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .checkbox-wrapper input[type="checkbox"] {
            position: absolute;
            opacity: 0;
            cursor: pointer;
        }

        .checkmark {
            width: 20px;
            height: 20px;
            border: 2px solid var(--gray-300);
            border-radius: 4px;
            position: relative;
            transition: var(--transition);
            flex-shrink: 0;
            margin-top: 2px;
        }

        .checkbox-wrapper input[type="checkbox"]:checked ~ .checkmark {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkbox-wrapper input[type="checkbox"]:checked ~ .checkmark::after {
            content: "";
            position: absolute;
            top: 2px;
            left: 6px;
            width: 6px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        /* Buttons */
        .auth-btn {
            width: 100%;
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .auth-btn.primary {
            background: linear-gradient(135deg, var(--primary-color), #00a844);
            color: white;
        }

        .auth-btn.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 200, 81, 0.3);
        }

        .auth-btn.secondary {
            background: linear-gradient(135deg, var(--secondary-color), var(--accent-color));
            color: white;
        }

        .auth-btn.secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(255, 105, 180, 0.3);
        }

        
            .modal-content {
                margin: 1rem;
                padding: 1.5rem;
            }

            .radio-group {
                grid-template-columns: 1fr;
            }
        

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Loading state */
        .auth-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .auth-btn.loading::after {
            content: "";
            width: 16px;
            height: 16px;
            border: 2px solid transparent;
            border-top: 2px solid currentColor;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Back link -->
    <a href="index.php" class="back-link">
        <i class="fas fa-arrow-left"></i>
        <span>Kembali ke Beranda</span>
    </a>

    <!-- Auth Modal - Always visible -->
    <div class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-user-circle"></i>
                    <span id="modalTitle">Masuk ke Akun</span>
                </h3>
                <button class="modal-close" onclick="window.location.href='index.php'">
                    <i class="fas fa-times"></i>
                </button>
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

            <div class="tabs">
                <button class="tab active" data-tab="login">Masuk</button>
                <button class="tab" data-tab="register">Daftar</button>
            </div>

            <!-- Login Form -->
            <form class="auth-form active" id="loginForm" method="POST" novalidate>
                <input type="hidden" name="action" value="login">
                
                <div class="form-group">
                    <label for="loginEmail">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="loginEmail" name="email" placeholder="Masukkan email Anda" required 
                               value="<?php echo isset($_POST['email']) && $_POST['action'] === 'login' ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="loginPassword">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="loginPassword" name="password" placeholder="Masukkan password Anda" required>
                        <button type="button" class="toggle-password" data-target="loginPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="auth-btn primary">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Masuk</span>
                </button>
            </form>

            <!-- Register Form -->
            <form class="auth-form" id="registerForm" method="POST" novalidate>
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="registerName">Nama Lengkap</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="registerName" name="name" placeholder="Masukkan nama lengkap" required
                               value="<?php echo isset($_POST['name']) && $_POST['action'] === 'register' ? htmlspecialchars($_POST['name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="registerEmail">Email</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="registerEmail" name="email" placeholder="Masukkan email Anda" required
                               value="<?php echo isset($_POST['email']) && $_POST['action'] === 'register' ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="registerPhone">Nomor Telepon</label>
                    <div class="input-wrapper">
                        <i class="fas fa-phone"></i>
                        <input type="tel" id="registerPhone" name="phone" placeholder="Masukkan nomor telepon" required
                               value="<?php echo isset($_POST['phone']) && $_POST['action'] === 'register' ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="registerPassword">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="registerPassword" name="password" placeholder="Buat password" required>
                        <button type="button" class="toggle-password" data-target="registerPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirmPassword">Konfirmasi Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="confirmPassword" name="confirm_password" placeholder="Konfirmasi password" required>
                        <button type="button" class="toggle-password" data-target="confirmPassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="auth-btn secondary">
                    <i class="fas fa-user-plus"></i>
                    <span>Buat Akun</span>
                </button>
            </form>
        </div>
    </div>

    <script>
        const modalTitle = document.getElementById('modalTitle');

        // Tab functionality
        const tabs = document.querySelectorAll('.tab');
        const forms = document.querySelectorAll('.auth-form');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabType = tab.dataset.tab;
                
                // Remove active class from all tabs and forms
                tabs.forEach(t => t.classList.remove('active'));
                forms.forEach(f => f.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding form
                tab.classList.add('active');
                document.getElementById(tabType + 'Form').classList.add('active');
                
                // Update modal title
                if (tabType === 'login') {
                    modalTitle.textContent = 'Masuk ke Akun';
                } else {
                    modalTitle.textContent = 'Buat Akun Baru';
                }
                
                // Clear any existing errors
                clearAllErrors();
            });
        });

        // Password toggle functionality
        const toggleButtons = document.querySelectorAll('.toggle-password');
        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.dataset.target;
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });

        // Form validation
        const authForms = document.querySelectorAll('.auth-form');
        authForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!validateForm(this)) {
                    e.preventDefault();
                } else {
                    showLoadingState(this);
                }
            });
        });

        function validateForm(form) {
            let isValid = true;
            const inputs = form.querySelectorAll('input[required]');
            
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });
            
            // Special validation for password confirmation
            const password = form.querySelector('#registerPassword');
            const confirmPassword = form.querySelector('#confirmPassword');
            
            if (password && confirmPassword && password.value !== confirmPassword.value) {
                showFieldError(confirmPassword, 'Password tidak cocok');
                isValid = false;
            }
            
            return isValid;
        }

        function validateField(input) {
            const value = input.value.trim();
            const type = input.type;
            const name = input.name;
            
            clearFieldError(input);
            
            if (!value && input.required) {
                showFieldError(input, 'Field ini wajib diisi');
                return false;
            }
            
            if (type === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    showFieldError(input, 'Format email tidak valid');
                    return false;
                }
            }
            
            if (name === 'phone' && value) {
                const phoneRegex = /^[0-9+\-\s()]{10,}$/;
                if (!phoneRegex.test(value)) {
                    showFieldError(input, 'Format nomor telepon tidak valid');
                    return false;
                }
            }
            
            if (name === 'password' && value) {
                if (value.length < 8) {
                    showFieldError(input, 'Password minimal 8 karakter');
                    return false;
                }
            }
            
            return true;
        }

        function showFieldError(input, message) {
            const formGroup = input.closest('.form-group');
            formGroup.classList.add('error');
            
            const existingError = formGroup.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }
            
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.style.color = '#e53e3e';
            errorDiv.style.fontSize = '0.8rem';
            errorDiv.style.marginTop = '0.25rem';
            errorDiv.textContent = message;
            
            const inputWrapper = input.closest('.input-wrapper') || input;
            inputWrapper.parentElement.appendChild(errorDiv);
        }

        function clearFieldError(input) {
            const formGroup = input.closest('.form-group');
            formGroup.classList.remove('error');
            
            const errorMessage = formGroup.querySelector('.error-message');
            if (errorMessage) {
                errorMessage.remove();
            }
        }

        function clearAllErrors() {
            const errorMessages = document.querySelectorAll('.error-message');
            const errorGroups = document.querySelectorAll('.form-group.error');
            
            errorMessages.forEach(msg => msg.remove());
            errorGroups.forEach(group => group.classList.remove('error'));
        }

        function showLoadingState(form) {
            const submitButton = form.querySelector('button[type="submit"]');
            const originalHTML = submitButton.innerHTML;
            
            submitButton.disabled = true;
            submitButton.classList.add('loading');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Memproses...</span>';
            
            submitButton.dataset.originalHtml = originalHTML;
        }

        // Auto-switch to register tab if registration error
        <?php if (!empty($error) && isset($_POST['action']) && $_POST['action'] === 'register'): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.querySelector('[data-tab="register"]').click();
            });
        <?php endif; ?>

        // Keyboard navigation
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = 'index.php';
            }
        });

        // Focus on first input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('#loginEmail');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>
