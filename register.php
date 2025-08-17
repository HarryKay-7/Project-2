<?php
session_start();
require_once 'auth.php';

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $terms = isset($_POST['terms']) ? true : false;

    // Validation
    if (empty($firstName)) {
        $errors['firstName'] = "First name is required";
    }
    
    if (empty($lastName)) {
        $errors['lastName'] = "Last name is required";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Valid email is required";
    } elseif (userExists($email)) {
        $errors['email'] = "Email already registered";
    }
    
    if (empty($phone)) {
        $errors['phone'] = "Phone number is required";
    }
    
    if (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters";
    }
    
    if ($password !== $confirmPassword) {
        $errors['confirmPassword'] = "Passwords do not match";
    }
    // Profile picture upload handling
    $profilePicPath = null;
    if (!is_dir('uploads/profile_pics/')) {
        mkdir('uploads/profile_pics/', 0777, true);
    }
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = mime_content_type($_FILES['profile_pic']['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            $errors['profile_pic'] = "Only JPG, PNG, GIF, or WEBP images are allowed.";
        } else {
            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $newName = uniqid('profile_', true) . '.' . $ext;
            $uploadDir = 'uploads/profile_pics/';
            $profilePicPath = $uploadDir . $newName;
            if (!move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profilePicPath)) {
                $errors['profile_pic'] = "Failed to upload profile picture.";
            }
        }
    } elseif (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] !== UPLOAD_ERR_NO_FILE) {
        $errors['profile_pic'] = "Error uploading profile picture.";
    }
    
    if (!$terms) {
        $errors['terms'] = "You must agree to the terms and conditions";
    }

    // If no errors, register user
    if (empty($errors)) {
        $username = $firstName . ' ' . $lastName;
        if (registerUser($username, $email, $password, $profilePicPath)) {
            $success = true;
            $_SESSION['users_acc_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['profile_pic'] = $profilePicPath;
            header('Location: dashboard.php');
            exit();
        } else {
            $errors['general'] = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MemoryChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="password-strength.js" defer></script>
</head>
<body class="bg-gradient-to-br from-blue-50 via-white to-blue-100 min-h-screen flex items-center justify-center p-4">

    <div class="nav-container">
            <div class="nav-logo">
               <a href="modern_homepage.php"><span><img src="left-arrow-direction-navigation-svgrepo-com.svg" width="55px" alt=""></span></a>
            </div>

    <div class="w-full max-w-4xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-blue-900 mb-2">Create Your Account</h1>
            <p class="text-blue-600 text-lg">Join us today and get started</p>
        </div>

        <!-- Registration Form Container -->
        <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
            <div class="grid md:grid-cols-2 gap-0">
                <!-- Left Side - Form -->
                <div class="p-8 md:p-12">
                    <form id="registrationForm" method="POST" action="register.php" enctype="multipart/form-data">
                        <!-- Profile Picture Upload -->
                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-blue-900 mb-2">
                                <i class="fas fa-image mr-2"></i>Profile Picture (optional)
                            </label>
                            <input type="file" id="profile_pic" name="profile_pic" accept="image/*"
                                   class="w-full px-4 py-3 border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200">
                            <?php if (!empty($errors['profile_pic'])): ?>
                                <span class="text-red-500 text-xs"><?php echo htmlspecialchars($errors['profile_pic']); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($errors['general'])): ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <?php echo htmlspecialchars($errors['general']); ?>
                            </div>
                        <?php endif; ?>

                        <!-- Name Fields -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-blue-900 mb-2">
                                    <i class="fas fa-user mr-2"></i>First Name
                                </label>
                                <input type="text" id="firstName" name="firstName" required
                                       class="w-full px-4 py-3 border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="John"
                                       value="<?php echo isset($_POST['firstName']) ? htmlspecialchars($_POST['firstName']) : ''; ?>">
                                <?php if (!empty($errors['firstName'])): ?>
                                    <span class="text-red-500 text-xs"><?php echo htmlspecialchars($errors['firstName']); ?></span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-blue-900 mb-2">
                                    <i class="fas fa-user mr-2"></i>Last Name
                                </label>
                                <input type="text" id="lastName" name="lastName" required
                                       class="w-full px-4 py-3 border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="Doe"
                                       value="<?php echo isset($_POST['lastName']) ? htmlspecialchars($_POST['lastName']) : ''; ?>">
                                <?php if (!empty($errors['lastName'])): ?>
                                    <span class="text-red-500 text-xs"><?php echo htmlspecialchars($errors['lastName']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-blue-900 mb-2">
                                <i class="fas fa-envelope mr-2"></i>Email Address
                            </label>
                            <input type="email" id="email" name="email" required
                                   class="w-full px-4 py-3 border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                   placeholder="john.doe@example.com"
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <?php if (!empty($errors['email'])): ?>
                                <span class="text-red-500 text-xs"><?php echo htmlspecialchars($errors['email']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Phone -->
                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-blue-900 mb-2">
                                <i class="fas fa-phone mr-2"></i>Phone Number
                            </label>
                            <input type="tel" id="phone" name="phone" required
                                   class="w-full px-4 py-3 border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                   placeholder="+1 (555) 123-4567"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            <?php if (!empty($errors['phone'])): ?>
                                <span class="text-red-500 text-xs"><?php echo htmlspecialchars($errors['phone']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Password -->
                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-blue-900 mb-2">
                                <i class="fas fa-lock mr-2"></i>Password
                            </label>
                            <div class="relative">
                                <input type="password" id="password" name="password" required
                                       class="w-full px-4 py-3 pr-12 border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="••••••••">
                                <button type="button" onclick="togglePassword('password')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-blue-500 hover:text-blue-700">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div id="password-strength" class="mt-2"></div>
                            <?php if (!empty($errors['password'])): ?>
                                <span class="text-red-500 text-xs"><?php echo htmlspecialchars($errors['password']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Confirm Password -->
                        <div class="mt-4">
                            <label class="block text-sm font-semibold text-blue-900 mb-2">
                                <i class="fas fa-lock mr-2"></i>Confirm Password
                            </label>
                            <div class="relative">
                                <input type="password" id="confirmPassword" name="confirmPassword" required
                                       class="w-full px-4 py-3 pr-12 border border-blue-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200"
                                       placeholder="••••••••">
                                <button type="button" onclick="togglePassword('confirmPassword')" 
                                        class="absolute right-3 top-1/2 transform -translate-y-1/2 text-blue-500 hover:text-blue-700">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <?php if (!empty($errors['confirmPassword'])): ?>
                                <span class="text-red-500 text-xs"><?php echo htmlspecialchars($errors['confirmPassword']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Terms and Conditions -->
                        <div class="mt-6">
                            <label class="flex items-center">
                                <input type="checkbox" id="terms" name="terms" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>
                                       class="w-4 h-4 text-blue-600 border-blue-300 rounded focus:ring-blue-500">
                                <span class="ml-2 text-sm text-blue-700">
                                    I agree to the <a href="#" class="text-blue-600 hover:text-blue-800 underline">Terms and Conditions</a>
                                </span>
                            </label>
                            <?php if (!empty($errors['terms'])): ?>
                                <span class="text-red-500 text-xs block mt-1"><?php echo htmlspecialchars($errors['terms']); ?></span>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" 
                                class="w-full mt-6 bg-blue-600 text-white py-3 px-4 rounded-xl hover:bg-blue-700 focus:ring-4 focus:ring-blue-300 transition-all duration-200 font-semibold">
                            <i class="fas fa-user-plus mr-2"></i>Create Account
                        </button>

                        <!-- Login Link -->
                        <div class="text-center mt-6">
                            <p class="text-blue-600 text-sm">
                                Already have an account? 
                                <a href="signin.php" class="text-blue-600 hover:text-blue-800 font-semibold underline">Sign in here</a>
                            </p>
                        </div>
                    </form>
                </div>

                <!-- Right Side - Info -->
                <div class="bg-gradient-to-br from-blue-600 to-blue-800 p-8 md:p-12 text-white flex flex-col justify-center">
                    <div class="text-center">
                        <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-brain text-3xl"></i>
                        </div>
                        <h2 class="text-2xl font-bold mb-4">Welcome to MemoryChain</h2>
                        <p class="text-blue-100 mb-6">
                            Join thousands of users who trust us with their memories and data. 
                            Experience seamless registration and get started in minutes.
                        </p>
                        
                        <div class="space-y-4 text-left">
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-300 mr-3 mt-1"></i>
                                <div>
                                    <h4 class="font-semibold">Secure & Private</h4>
                                    <p class="text-blue-100 text-sm">Your data is encrypted and protected</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-300 mr-3 mt-1"></i>
                                <div>
                                    <h4 class="font-semibold">Easy Setup</h4>
                                    <p class="text-blue-100 text-sm">Get started in just a few minutes</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-check-circle text-green-300 mr-3 mt-1"></i>
                                <div>
                                    <h4 class="font-semibold">24/7 Support</h4>
                                    <p class="text-blue-100 text-sm">We're here to help whenever you need</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Form validation enhancement
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            const terms = document.getElementById('terms').checked;
            if (!terms) {
                e.preventDefault();
                alert('Please agree to the terms and conditions');
                return false;
            }
        });
    </script>
</body>
</html>
