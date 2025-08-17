<?php
session_start();
require_once 'auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // In a real application, this would:
        // 1. Check if email exists in database
        // 2. Generate a secure token
        // 3. Send password reset email
        // 4. Store token with expiration
        
        // For demo purposes, we'll show success
        $success = "We've sent a password reset link to your email. Please check your inbox and follow the instructions.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MemoryChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .floating-animation {
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        
        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.455, 0.03, 0.515, 0.955) infinite;
        }
        
        @keyframes pulse-ring {
            0% { transform: scale(0.33); }
            80%, 100% { opacity: 0; }
        }
        
        .input-focus:focus {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        @media (max-width: 1024px) {
            .max-w-md {
                max-width: 95vw !important;
            }
            .glass-effect {
                padding: 1.5rem !important;
            }
        }
        
        @media (max-width: 768px) {
            .max-w-md {
                max-width: 99vw !important;
            }
            .glass-effect {
                padding: 1rem !important;
            }
            .text-3xl {
                font-size: 1.5rem !important;
            }
            .text-2xl {
                font-size: 1.2rem !important;
            }
        }
        
        @media (max-width: 480px) {
            .max-w-md {
                max-width: 100vw !important;
            }
            .glass-effect {
                padding: 0.5rem !important;
            }
            .text-3xl, .text-2xl {
                font-size: 1rem !important;
            }
            .p-8 {
                padding: 0.5rem !important;
            }
            .back-home-text { display: none !important; }
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4">
    <!-- Back Arrow Top Left -->
    <div class="absolute top-4 left-4 z-20">
        <a href="modern_homepage.php" class="text-white hover:text-indigo-200 text-lg flex items-center">
            <i class="fas fa-arrow-left mr-2"></i>
            <span class="back-home-text">Back to Home</span>
        </a>
    </div>
    <!-- Background Elements -->
    <div class="absolute inset-0 overflow-hidden">
        <div class="absolute top-0 left-0 w-72 h-72 bg-purple-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob"></div>
        <div class="absolute top-0 right-0 w-72 h-72 bg-yellow-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-2000"></div>
        <div class="absolute -bottom-8 left-20 w-72 h-72 bg-pink-300 rounded-full mix-blend-multiply filter blur-xl opacity-70 animate-blob animation-delay-4000"></div>
    </div>

    <div class="relative z-10 w-full max-w-md">
        <!-- Logo Section -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center space-x-2 mb-4">
                <i class="fas fa-link text-4xl text-white floating-animation"></i>
                <span class="text-3xl font-bold text-white">MemoryChain</span>
            </div>
            <h1 class="text-2xl font-light text-white/90">Reset your password</h1>
            <p class="text-white/70 mt-2">We'll help you get back into your account</p>
        </div>

        <!-- Main Card -->
        <div class="glass-effect rounded-2xl p-8 shadow-2xl">
            <?php if (!empty($error)): ?>
                <div class="bg-red-500/20 border border-red-500/30 text-red-100 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="bg-green-500/20 border border-green-500/30 text-green-100 px-4 py-3 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="forgot_password.php" class="space-y-6">
                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-sm font-medium text-white/90 mb-2">
                        Email address
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-envelope text-white/50"></i>
                        </div>
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            required
                            class="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-white/50 focus:border-transparent input-focus transition-all duration-300"
                            placeholder="Enter your email address"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                        >
                    </div>
                    <p class="text-xs text-white/60 mt-2">
                        We'll send a password reset link to this email
                    </p>
                </div>

                <!-- Submit Button -->
                <button 
                    type="submit" 
                    class="w-full bg-white text-indigo-600 font-semibold py-3 px-4 rounded-lg hover:bg-gray-50 transition-all duration-300 btn-hover focus:outline-none focus:ring-2 focus:ring-white/50"
                >
                    <span class="flex items-center justify-center">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Send reset link
                    </span>
                </button>
            </form>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-white/20"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-transparent text-white/60">or</span>
                </div>
            </div>

            <!-- Back to Sign In -->
            <div class="text-center">
                <a href="signin.php" class="text-white/80 hover:text-white transition-colors duration-300 flex items-center justify-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to sign in
                </a>
            </div>

            <!-- Security Note -->
            <div class="mt-6 p-4 bg-white/5 rounded-lg border border-white/10">
                <div class="flex items-start">
                    <i class="fas fa-shield-alt text-white/70 mt-1 mr-3"></i>
                    <div>
                        <h4 class="text-sm font-medium text-white/90 mb-1">Security Notice</h4>
                        <p class="text-xs text-white/70">
                            For your security, password reset links expire after 24 hours and can only be used once.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Links -->
        <div class="text-center mt-8 space-x-6">
            <a href="register.php" class="text-white/80 hover:text-white transition-colors duration-300">
                <i class="fas fa-user-plus"></i> Sign Up
            </a>
            <a href="signin.php" class="text-white/80 hover:text-white transition-colors duration-300">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </a>
        </div>
    </div>
</body>
</html>
