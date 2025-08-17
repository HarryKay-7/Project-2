<?php
require_once 'admin_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

// Include authentication
require_once 'auth.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: signin.php");
    exit();
}

// Available time zones
$time_zones = DateTimeZone::listIdentifiers();

// Available languages
$languages = [
    'en' => 'English',
    'es' => 'Spanish',
    'fr' => 'French',
    'de' => 'German'
];

// Privacy levels
$privacy_levels = [
    'private' => 'Only Me',
    'friends' => 'Friends Only',
    'public' => 'Public'
];

// Fetch current user data with additional settings
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT 
        id, username, email, profile_pic, created_at, 
        last_password_change
    FROM users_acc 
    WHERE id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get notification preferences from cookies or set defaults
$notification_prefs = [
    'email' => isset($_COOKIE['notification_email']) ? $_COOKIE['notification_email'] === 'true' : true,
    'browser' => isset($_COOKIE['notification_browser']) ? $_COOKIE['notification_browser'] === 'true' : true
];

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            try {
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $time_zone = $_POST['time_zone'] ?? 'UTC';
                $language = $_POST['language'] ?? 'en';
                $privacy = $_POST['privacy_level'] ?? 'private';
                
                // Handle theme selection
                if (isset($_POST['theme'])) {
                    $theme = $_POST['theme'];
                    setcookie('darkMode', $theme === 'dark' ? 'true' : 'false', time() + 31536000, '/');
                }
                
                // Update notification preferences using cookies
                $email_notif = isset($_POST['notification_email']) ? 'true' : 'false';
                $browser_notif = isset($_POST['notification_browser']) ? 'true' : 'false';
                setcookie('notification_email', $email_notif, time() + 31536000, '/');
                setcookie('notification_browser', $browser_notif, time() + 31536000, '/');
                
                // Basic validation
                if (empty($username) || empty($email)) {
                    throw new Exception("Username and email are required fields.");
                }
                
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Please enter a valid email address.");
                }
                
                // Handle profile picture upload
                $profile_pic = $user['profile_pic'];
                if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                    $file_type = mime_content_type($_FILES['profile_pic']['tmp_name']);
                    
                    if (in_array($file_type, $allowed_types)) {
                        $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                        $new_name = uniqid('profile_') . '.' . $ext;
                        $upload_dir = 'uploads/profile_pics/';
                        
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $new_path = $upload_dir . $new_name;
                        
                        // Delete old profile picture if exists
                        if ($profile_pic && file_exists($profile_pic)) {
                            unlink($profile_pic);
                        }
                        
                        if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $new_path)) {
                            $profile_pic = $new_path;
                        }
                    }
                }
                
                // Update user data
                $stmt = $pdo->prepare("
                    UPDATE users_acc 
                    SET 
                        username = ?,
                        email = ?,
                        profile_pic = ?
                    WHERE id = ?
                ");
                
                $stmt->execute([
                    $username,
                    $email,
                    $profile_pic,
                    $user_id
                ]);
                
                $message = "Settings updated successfully!";
                $message_type = "success";
                
                // Refresh user data
                $_SESSION['username'] = $username;
                
            } catch (Exception $e) {
                $message = $e->getMessage();
                $message_type = "error";
            }
            break;
            
        case 'change_vault_pin':
            try {
                $current_pin = $_POST['current_vault_pin'];
                $new_pin = $_POST['new_vault_pin'];
                $confirm_pin = $_POST['confirm_vault_pin'];

                if (!password_verify($current_pin, $user['vault_pin'] ?? '')) {
                    throw new Exception('Current vault PIN is incorrect.');
                }
                
                if ($new_pin !== $confirm_pin) {
                    throw new Exception('New vault PINs do not match.');
                }
                
                if (!preg_match('/^\d{4,6}$/', $new_pin)) {
                    throw new Exception('Vault PIN must be 4-6 digits.');
                }
                
                $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users_acc SET vault_pin = ? WHERE id = ?");
                if (!$stmt->execute([$hashed_pin, $user_id])) {
                    throw new Exception('Failed to update vault PIN.');
                }
                
                $message = 'Vault PIN updated successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = $e->getMessage();
                    // Redirect to dashboard to reflect changes
                    header('Location: dashboard.php?success=1&msg=' . urlencode($message));
                    exit();
                $message_type = 'error';
            }
            break;

        case 'update_security_questions':
            try {
                $security_question1 = $_POST['security_question1'];
                $security_answer1 = $_POST['security_answer1'];
                $security_question2 = $_POST['security_question2'];
                $security_answer2 = $_POST['security_answer2'];

                if (empty($security_question1) || empty($security_answer1) || 
                    empty($security_question2) || empty($security_answer2)) {
                    throw new Exception('Please fill all security questions and answers.');
                }

                $hashed_answer1 = password_hash(strtolower($security_answer1), PASSWORD_DEFAULT);
                $hashed_answer2 = password_hash(strtolower($security_answer2), PASSWORD_DEFAULT);

                $stmt = $pdo->prepare("UPDATE users_acc SET 
                    security_question1 = ?, 
                    security_answer1 = ?, 
                    security_question2 = ?, 
                    security_answer2 = ? 
                    WHERE id = ?");

                if (!$stmt->execute([
                    $security_question1, 
                    $hashed_answer1, 
                    $security_question2, 
                    $hashed_answer2, 
                    $user_id
                ])) {
                    throw new Exception('Failed to update security questions.');
                }

                $message = 'Security questions updated successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = $e->getMessage();
                $message_type = 'error';
            }
            break;

        case 'change_password':
            try {
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];

                if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                    throw new Exception("All password fields are required.");
                }

                if ($new_password !== $confirm_password) {
                    throw new Exception("New passwords do not match.");
                }

                if (strlen($new_password) < 8) {
                    throw new Exception("Password must be at least 8 characters long.");
                }

                // Verify current password
                $stmt = $pdo->prepare("SELECT password FROM users_acc WHERE id = ?");
                $stmt->execute([$user_id]);
                $stored_hash = $stmt->fetchColumn();

                if (!password_verify($current_password, $stored_hash)) {
                    throw new Exception("Current password is incorrect.");
                }

                // Update password
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users_acc SET password = ?, last_password_change = NOW() WHERE id = ?");
                $stmt->execute([$new_hash, $user_id]);

                $message = "Password changed successfully!";
                $message_type = "success";
                // Redirect to dashboard to reflect changes
                header('Location: settings.php?success=1&msg=' . urlencode($message));
                exit();
            } catch (Exception $e) {
                $message = $e->getMessage();
                $message_type = "error";
            }
            break;
            
        case 'toggle_2fa':
            try {
                $new_state = isset($_POST['enable_2fa']);
                $stmt = $pdo->prepare("UPDATE users_acc SET two_factor_enabled = ? WHERE id = ?");
                $stmt->execute([$new_state, $user_id]);
                if ($new_state) {
                    // Redirect to 2FA setup page if enabling
                    header('Location: setup_2fa.php');
                    exit();
                } else {
                    $message = "Two-factor authentication disabled successfully!";
                    $message_type = "success";
                }
            } catch (Exception $e) {
                $message = $e->getMessage();
                $message_type = "error";
            }
            break;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - MemoryChain</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {"50":"#eff6ff","100":"#dbeafe","200":"#bfdbfe","300":"#93c5fd","400":"#60a5fa","500":"#3b82f6","600":"#2563eb","700":"#1d4ed8","800":"#1e40af","900":"#1e3a8a","950":"#172554"}
                    }
                }
            }
        }
    </script>
    <style>
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .setting-card {
            transition: transform 0.2s ease-in-out;
        }
        .setting-card:hover {
            transform: translateY(-2px);
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #3b82f6;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        .dark .prose {
            color: #e5e7eb;
        }
        .dark .prose h2 {
            color: #f3f4f6;
        }
    </style>
</head>
<body class="min-h-screen bg-blue-50">
    <div class="min-h-screen">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center">
                        <a href="dashboard.php" class="text-gray-800 hover:text-gray-600">
                            <i class="fas fa-arrow-left mr-2"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-100' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-100'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Tab Navigation -->
                    <div class="w-full md:w-64 shrink-0">
                    <div class="bg-slate-100 shadow rounded-lg p-4">
                        <nav class="space-y-2">
                            <button class="tab-button w-full text-left px-4 py-2 rounded-lg transition-colors hover:bg-slate-200 active" data-tab="profile">
                                <i class="fas fa-user mr-2"></i> Profile Settings
                            </button>
                            <button class="tab-button w-full text-left px-4 py-2 rounded-lg transition-colors hover:bg-gray-100" data-tab="security">
                                <i class="fas fa-shield-alt mr-2"></i> Security
                            </button>
                        </nav>
                    </div>                    <!-- Account Status Card -->
                    <div class="mt-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                        <div class="text-sm text-gray-600 dark:text-gray-300">
                            <h3 class="font-semibold mb-2">Account Status</h3>
                            <div class="space-y-1">
                                <p>Member since: <?php echo date('M j, Y', strtotime($user['created_at'])); ?></p>
                                <?php if ($user['last_password_change']): ?>
                                    <p>Password last changed: <?php echo date('M j, Y', strtotime($user['last_password_change'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Content -->
                <div class="flex-1">
                    <!-- Profile Settings Tab -->
                    <div id="profile" class="tab-content active">
                        <div class="bg-slate-100 shadow rounded-lg p-6">
                            <h2 class="text-xl font-semibold mb-6 text-gray-900">Profile Information</h2>
                            <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="flex items-center space-x-6">
                                    <div class="relative">
                                        <div class="w-24 h-24 rounded-full overflow-hidden bg-gray-100">
                                            <img id="profile-preview" src="<?php echo htmlspecialchars($user['profile_pic'] ?? 'assets/default-avatar.png'); ?>" 
                                                alt="Profile Picture" class="w-full h-full object-cover">
                                        </div>
                                        <label class="absolute bottom-0 right-0 bg-primary-600 rounded-full p-2 cursor-pointer hover:bg-primary-700">
                                            <i class="fas fa-camera text-white"></i>
                                            <input type="file" name="profile_pic" class="hidden" accept="image/*" onchange="previewImage(this)">
                                        </label>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold text-indigo-600"><?php echo htmlspecialchars($user['username']); ?></h3>
                                        <p class="text-sm text-gray-500">Upload a new profile picture</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-base font-semibold text-gray-700">Username</label>
                                        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                                               class="mt-2 block w-full text-lg py-3 px-4 rounded-md border-gray-300 bg-blue-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 hover:bg-blue-100 transition-colors">
                                    </div>

                                    <div>
                                        <label class="block text-base font-semibold text-gray-700">Email Address</label>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               class="mt-2 block w-full text-lg py-3 px-4 rounded-md border-gray-300 bg-blue-50 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 hover:bg-blue-100 transition-colors">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Language</label>
                                        <select name="language" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <?php foreach ($languages as $code => $name): ?>
                                                <option value="<?php echo $code; ?>" <?php echo ($user['language_preference'] ?? 'en') === $code ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Time Zone</label>
                                        <select name="time_zone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <?php foreach ($time_zones as $zone): ?>
                                                <option value="<?php echo $zone; ?>" <?php echo ($user['time_zone'] ?? 'UTC') === $zone ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($zone); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                        Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div id="security" class="tab-content">
                        <div class="space-y-6">
                            <!-- Password Change -->
                            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                                <h2 class="text-xl font-semibold mb-6 dark:text-white">Change Password</h2>
                                <form action="" method="POST" class="space-y-6">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</label>
                                        <input type="password" name="current_password" 
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
                                            <input type="password" name="new_password" 
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>

                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
                                            <input type="password" name="confirm_password" 
                                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                            Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Two-Factor Authentication -->
                            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6 mb-6">
                                <form action="" method="POST">
                                    <input type="hidden" name="action" value="toggle_2fa">
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h3 class="text-lg font-medium dark:text-white">Two-Factor Authentication</h3>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Add an extra layer of security to your account</p>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="enable_2fa" <?php echo ($user['two_factor_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>

                                    <div class="mt-6 flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                            Update 2FA Settings
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Vault PIN -->
                            <div class="bg-slate-100 shadow rounded-lg p-6 mb-6">
                                <h3 class="text-lg font-medium mb-4">Change Vault PIN</h3>
                                <form action="" method="POST" class="space-y-4">
                                    <input type="hidden" name="action" value="change_vault_pin">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Current Vault PIN</label>
                                        <input type="password" name="current_vault_pin" maxlength="6" pattern="\d{4,6}"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <p class="mt-1 text-sm text-gray-500">Enter your current vault PIN</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">New Vault PIN</label>
                                        <input type="password" name="new_vault_pin" maxlength="6" pattern="\d{4,6}"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <p class="mt-1 text-sm text-gray-500">Must be 4-6 digits</p>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Vault PIN</label>
                                        <input type="password" name="confirm_vault_pin" maxlength="6" pattern="\d{4,6}"
                                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                            Update Vault PIN
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Security Questions -->
                            <div class="bg-slate-100 shadow rounded-lg p-6">
                                <h3 class="text-lg font-medium mb-4">Security Questions</h3>
                                <form action="" method="POST" class="space-y-6">
                                    <input type="hidden" name="action" value="update_security_questions">
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Security Question 1</label>
                                        <select name="security_question1" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">Select a question...</option>
                                            <option value="What was your first pet's name?" <?php echo (($user['security_question1'] ?? '') === "What was your first pet's name?") ? 'selected' : ''; ?>>What was your first pet's name?</option>
                                            <option value="What city were you born in?" <?php echo (($user['security_question1'] ?? '') === "What city were you born in?") ? 'selected' : ''; ?>>What city were you born in?</option>
                                            <option value="What was your mother's maiden name?" <?php echo (($user['security_question1'] ?? '') === "What was your mother's maiden name?") ? 'selected' : ''; ?>>What was your mother's maiden name?</option>
                                            <option value="What high school did you attend?" <?php echo (($user['security_question1'] ?? '') === "What high school did you attend?") ? 'selected' : ''; ?>>What high school did you attend?</option>
                                        </select>
                             <input type="text" name="security_answer1" placeholder="Your answer" value="<?php echo htmlspecialchars($user['security_answer1'] ?? ''); ?>"
                                 class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Security Question 2</label>
                                        <select name="security_question2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="">Select a question...</option>
                                            <option value="What was the name of your first school?" <?php echo (($user['security_question2'] ?? '') === "What was the name of your first school?") ? 'selected' : ''; ?>>What was the name of your first school?</option>
                                            <option value="What is your favorite book?" <?php echo (($user['security_question2'] ?? '') === "What is your favorite book?") ? 'selected' : ''; ?>>What is your favorite book?</option>
                                            <option value="What is the name of the town where you were born?" <?php echo (($user['security_question2'] ?? '') === "What is the name of the town where you were born?") ? 'selected' : ''; ?>>What is the name of the town where you were born?</option>
                                            <option value="What was the make of your first car?" <?php echo (($user['security_question2'] ?? '') === "What was the make of your first car?") ? 'selected' : ''; ?>>What was the make of your first car?</option>
                                        </select>
                             <input type="text" name="security_answer2" placeholder="Your answer" value="<?php echo htmlspecialchars($user['security_answer2'] ?? ''); ?>"
                                 class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>

                                    <div class="flex justify-end">
                                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2">
                                            Save Security Questions
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </div>
    </div>

    <script>


        // Profile picture preview
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profile-preview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Tab switching
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => {
                // Remove active class from all buttons and content
                document.querySelectorAll('.tab-button').forEach(b => b.classList.remove('bg-gray-100', 'dark:bg-gray-700'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                button.classList.add('bg-gray-100', 'dark:bg-gray-700');
                document.getElementById(button.dataset.tab).classList.add('active');
            });
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const passwordFields = form.querySelectorAll('input[type="password"]');
                if (passwordFields.length > 1) {
                    const newPassword = form.querySelector('input[name="new_password"]')?.value;
                    const confirmPassword = form.querySelector('input[name="confirm_password"]')?.value;
                    
                    if (newPassword && confirmPassword && newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New passwords do not match!');
                    }
                }
            });
        });

        // Theme selection visual feedback
        document.querySelectorAll('input[name="theme"]').forEach(input => {
            input.addEventListener('change', function() {
                document.querySelectorAll('label').forEach(label => {
                    label.classList.remove('border-primary-500');
                });
                this.closest('label').classList.add('border-primary-500');
            });
        });
    </script>
</body>
</html>
  
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - MemoryChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-button.active {
            background: linear-gradient(to right, #3b82f6, #8b5cf6);
            color: white;
        }
    </style>
</head>

                   

            

            <!-- Security Tab -->
            <div id="security" class="tab-content p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current Password</label>
                            <input type="password" name="current_password" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">New Password</label>
                            <input type="password" name="new_password" required minlength="8"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                            <input type="password" name="confirm_password" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 transition">
                            <i class="fas fa-key mr-2"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Vault PIN Tab -->
            <div id="vault" class="tab-content p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="update_vault_pin">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Current Vault PIN</label>
                            <input type="password" name="current_vault_pin" required pattern="\d{4,6}" maxlength="6"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">New Vault PIN (4-6 digits)</label>
                            <input type="password" name="new_vault_pin" required pattern="\d{4,6}" maxlength="6"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Confirm New Vault PIN</label>
                            <input type="password" name="confirm_vault_pin" required pattern="\d{4,6}" maxlength="6"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        
                        <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition">
                            <i class="fas fa-lock mr-2"></i> Update Vault PIN
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security Questions Tab -->
            <div id="questions" class="tab-content p-6">
                <form method="POST">
                    <input type="hidden" name="action" value="update_security_questions">
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Security Question 1</label>
                            <select name="security_question1" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select a question</option>
                                <option value="pet_name">What was your first pet's name?</option>
                                <option value="birth_city">In what city were you born?</option>
                                <option value="mother_maiden">What is your mother's maiden name?</option>
                                <option value="first_school">What was the name of your first school?</option>
                                <option value="best_friend">What was your childhood best friend's name?</option>
                                <option value="favorite_book">What is your favorite book?</option>
                                <option value="first_car">What was your first car?</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Answer 1</label>
                            <input type="text" name="security_answer1" required
                                   class="mt-1Since the database schema files are empty or unavailable, I will assume the users_acc table has at least these fields based on auth.php: id, username, email, password, profile_pic.

For the settings feature, we need to allow users to update:
- Profile details (username, email, profile picture)
- Password
- Vault PIN (assuming stored in users_acc or a related table)
- Security questions (assuming stored in a related table or users_acc)

Plan:
- Create a settings.php page with forms/tabs for each update category.
- Implement backend PHP logic in settings.php to handle updates securely:
  - Validate inputs
  - Hash passwords and PINs
  - Update database accordingly
- Add file upload handling for profile picture update.
- Add security question update form and logic.
- Add password change form with current password verification.
- Add vault PIN change form with verification.
- Use session user_id to identify the user.

Dependent files:
- settings.php (main file to create/update)
- Possibly add helper functions in auth.php or a new file for user updates.

Followup steps:
- Implement the settings.php page with forms and backend logic.
- Test each update feature.
- Ensure security best practices (e.g., password hashing, input validation).

Please confirm if I can proceed with this plan or provide any additional requirements. 

<ask_followup_question>
<question>Do you want me to create the full settings.php page with all these update features and backend logic now?</question>
</ask_followup_question>
