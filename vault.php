<?php
ob_start();
session_start();
require_once 'auth.php';

// Security check - redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

$message = '';
$errors = [];
$security_verified = false;

// Handle all POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Handle Download Action
    if ($action === 'download') {
        $item_id = $_POST['item_id'] ?? '';
        if (!empty($item_id)) {
            $stmt = $pdo->prepare("SELECT * FROM vault_items WHERE id = ? AND user_id = ?");
            $stmt->execute([$item_id, $user_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item && file_exists($item['file_path'])) {
                $file_path = $item['file_path'];
                // Force browser download, prevent IDM interception
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
                header('Content-Length: ' . filesize($file_path));
                readfile($file_path);
                exit();
            } else {
                $errors[] = "File not found or access denied.";
            }
        }
    }
    
    // Handle Email Action
    if ($action === 'email') {
        $item_id = $_POST['item_id'] ?? '';
        if (!empty($item_id)) {
            // Get user email and memory details
            $stmt = $pdo->prepare("SELECT u.email, v.* FROM vault_items v 
                                 JOIN users_acc u ON u.id = v.user_id 
                                 WHERE v.id = ? AND v.user_id = ?");
            $stmt->execute([$item_id, $user_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item) {
                // Prepare email
                $to = $item['email'];
                $subject = "Your Memory from MemoryChain: " . $item['title'];
                
                // Create email message
                $message = "Hello,\n\n";
                $message .= "Here's your memory from MemoryChain:\n\n";
                $message .= "Title: " . $item['title'] . "\n";
                $message .= "Description: " . $item['description'] . "\n";
                $message .= "Created: " . date('F j, Y g:i A', strtotime($item['created_at'])) . "\n\n";
                $message .= "Your memory file is attached to this email.\n\n";
                $message .= "Best regards,\nMemoryChain Team";
                
                // Prepare attachment if file exists
                $file_content = '';
                if (!empty($item['file_path']) && file_exists($item['file_path'])) {
                    $file_content = chunk_split(base64_encode(file_get_contents($item['file_path'])));
                }
                
                // Create email headers for attachment
                $boundary = md5(time());
                $headers = "From: MemoryChain <noreply@memorychain.com>\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";
                
                // Create email body with attachment
                $body = "--" . $boundary . "\r\n";
                $body .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($message));
                
                if ($file_content) {
                    $body .= "--" . $boundary . "\r\n";
                    $body .= "Content-Type: " . $item['file_type'] . "; name=\"" . basename($item['file_name']) . "\"\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"" . basename($item['file_name']) . "\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                } elseif ($action === 'update_timeline') {
                    $timeline_date = $_POST['timeline_date'] ?? '';
                    $timeline_time = $_POST['timeline_time'] ?? '';
                    $timeline_note = $_POST['timeline_note'] ?? '';
                    $item_id = $_POST['vault_item_id'] ?? 0;
                    if (!empty($timeline_date) && !empty($timeline_time)) {
                        $timestamp = date('Y-m-d H:i:s', strtotime("$timeline_date $timeline_time"));
                        $stmt = $pdo->prepare("UPDATE vault_items SET timeline_date = ?, timeline_note = ? WHERE user_id = ? AND id = ?");
                        $stmt->execute([$timestamp, $timeline_note, $user_id, $item_id]);
                        $message = "Timeline updated successfully!";
                        header('Location: vault.php?success=1&msg=' . urlencode($message));
                        exit();
                    } else {
                        $errors[] = "Date and time are required.";
                    }
                    $body .= $file_content;
                }
                
                $body .= "\r\n--" . $boundary . "--\r\n";
                
                // Send email
                if (mail($to, $subject, $body, $headers)) {
                    $message = "Memory sent successfully to your email!";
                } else {
                    $errors[] = "Failed to send memory to email.";
                }
            } else {
                $errors[] = "Memory not found or access denied.";
            }
        }
    }
    
    // Handle security check
    if (isset($_POST['security_check'])) {
        $security_type = $_POST['security_type'] ?? '';
        $security_answer = $_POST['security_answer'] ?? '';
        // Rest of your security verification logic...
    }
}

// Check if user has vault access
if (!isset($_SESSION['vault_access']) || $_SESSION['vault_access'] !== true) {
    $security_verified = false;
} else {
    $security_verified = true;
}

// Handle vault operations after security verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'download') {
        $item_id = $_POST['item_id'] ?? '';
        if (!empty($item_id)) {
            $stmt = $pdo->prepare("SELECT * FROM vault_items WHERE id = ? AND user_id = ?");
            $stmt->execute([$item_id, $user_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item && file_exists($item['file_path'])) {
                header('Content-Type: ' . $item['file_type']);
                header('Content-Disposition: attachment; filename="' . basename($item['file_name']) . '"');
                header('Content-Length: ' . filesize($item['file_path']));
                readfile($item['file_path']);
                exit();
            } else {
                $errors[] = "File not found or access denied.";
            }
        }
    } elseif ($action === 'email') {
        $item_id = $_POST['item_id'] ?? '';
        if (!empty($item_id)) {
            // Get user email and memory details
            $stmt = $pdo->prepare("SELECT u.email, v.* FROM vault_items v 
                                 JOIN users_acc u ON u.id = v.user_id 
                                 WHERE v.id = ? AND v.user_id = ?");
            $stmt->execute([$item_id, $user_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($item) {
                $to = $item['email'];
                $subject = "Your Memory from MemoryChain: " . $item['title'];
                
                // Create email message
                $message = "Hello,\n\n";
                $message .= "Here's your memory from MemoryChain:\n\n";
                $message .= "Title: " . $item['title'] . "\n";
                $message .= "Description: " . $item['description'] . "\n";
                $message .= "Created: " . date('F j, Y g:i A', strtotime($item['created_at'])) . "\n\n";
                $message .= "Your memory file is attached to this email.\n\n";
                $message .= "Best regards,\nMemoryChain Team";
                
                // Prepare attachment if file exists
                $file_content = '';
                if (!empty($item['file_path']) && file_exists($item['file_path'])) {
                    $file_content = chunk_split(base64_encode(file_get_contents($item['file_path'])));
                }
                
                // Create email headers for attachment
                $boundary = md5(time());
                $headers = "From: MemoryChain <noreply@memorychain.com>\r\n";
                $headers .= "MIME-Version: 1.0\r\n";
                $headers .= "Content-Type: multipart/mixed; boundary=\"" . $boundary . "\"\r\n";
                
                // Create email body
                $body = "--" . $boundary . "\r\n";
                $body .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($message));
                
                if ($file_content) {
                    $body .= "--" . $boundary . "\r\n";
                    $body .= "Content-Type: " . $item['file_type'] . "; name=\"" . basename($item['file_name']) . "\"\r\n";
                    $body .= "Content-Disposition: attachment; filename=\"" . basename($item['file_name']) . "\"\r\n";
                    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                    $body .= $file_content;
                }
                
                $body .= "\r\n--" . $boundary . "--\r\n";
                
                // Send email
                if (mail($to, $subject, $body, $headers)) {
                    $message = "Memory sent successfully to your email!";
                } else {
                    $errors[] = "Failed to send memory to email.";
                }
            } else {
                $errors[] = "Memory not found or access denied.";
            }
        }
    } elseif ($action === 'upload') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $unlock_date = $_POST['unlock_date'] ?? '';
        $unlock_time = $_POST['unlock_time'] ?? '';
        $security_level = $_POST['security_level'] ?? 'standard';
        
        if (empty($title)) {
            $errors['title'] = 'Title is required';
        }
        if (empty($unlock_date)) {
            $errors['unlock_date'] = 'Unlock date is required';
        }
        
        $unlock_datetime = $unlock_date . ' ' . ($unlock_time ?: '00:00:00');
        $now = new DateTime();
        $unlock_dt = DateTime::createFromFormat('Y-m-d H:i:s', $unlock_datetime);
        if (!$unlock_dt || $unlock_dt <= $now) {
            $errors['unlock_date'] = 'Unlock date and time must be in the future';
        }
        
        if (empty($errors)) {
            $file_path = NULL;
            $file_name = NULL;
            $file_type = NULL;
            $file_size = 0;
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/' . $user_id . '/';
                if (!is_dir($upload_dir)) {
                    if (!mkdir($upload_dir, 0755, true)) {
                        $upload_dir = 'uploads/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                    }
                }
                $file_tmp = $_FILES['file']['tmp_name'];
                $file_name = basename($_FILES['file']['name']);
                $file_size = $_FILES['file']['size'];
                $file_type = $_FILES['file']['type'];
                $file_path = $upload_dir . $file_name;
                if (!move_uploaded_file($file_tmp, $file_path)) {
                    $errors['file'] = 'Failed to secure your memory';
                }
            }
            if (empty($errors)) {
                try {
                    global $pdo;
                    $stmt = $pdo->prepare("INSERT INTO vault_items (user_id, title, description, file_path, file_name, file_type, file_size, unlock_date, security_level, is_locked) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $title, $description, $file_path, $file_name, $file_type, $file_size, $unlock_datetime, $security_level, true]);
                    $message = 'Memory secured successfully and locked until ' . $unlock_datetime;
                    header('Location: dashboard.php?success=1&msg=' . urlencode($message));
                    exit();
                } catch (PDOException $e) {
                    $errors['db'] = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'update_unlock_date') {
        $vault_item_id = $_POST['vault_item_id'] ?? 0;
        $new_unlock_date = $_POST['new_unlock_date'] ?? '';
        $new_unlock_time = $_POST['new_unlock_time'] ?? '';
        $change_reason = trim($_POST['change_reason'] ?? '');
        $new_unlock_datetime = $new_unlock_date . ' ' . ($new_unlock_time ?: '00:00:00');
        $now = new DateTime();
        $new_unlock_dt = DateTime::createFromFormat('Y-m-d H:i:s', $new_unlock_datetime);
        if (!$new_unlock_dt || $new_unlock_dt <= $now) {
            $errors['new_unlock_date'] = 'New unlock date and time must be in the future';
        }
        if (empty($change_reason)) {
            $errors['change_reason'] = 'Please provide a reason for the change.';
        }
        if (empty($errors)) {
            global $pdo;
            $stmt = $pdo->prepare("SELECT unlock_date FROM vault_items WHERE id = ? AND user_id = ?");
            $stmt->execute([$vault_item_id, $user_id]);
            $old_unlock_date = $stmt->fetchColumn();
            if ($old_unlock_date) {
                $stmt = $pdo->prepare("UPDATE vault_items SET unlock_date = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                $stmt->execute([$new_unlock_datetime, $vault_item_id, $user_id]);
                $stmt = $pdo->prepare("INSERT INTO vault_updates (vault_item_id, user_id, old_unlock_date, new_unlock_date, change_reason) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$vault_item_id, $user_id, $old_unlock_date, $new_unlock_datetime, $change_reason]);
                $message = 'Memory timeline updated successfully';
                header('Location: vault.php?success=1&msg=' . urlencode($message));
                exit();
            } else {
                $errors['db'] = 'Memory not found.';
            }
        }
    }
}

// Fetch user's vault items
global $pdo;
$stmt = $pdo->prepare("SELECT * FROM vault_items WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$vault_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle per-memory unlock
$unlocked_memories = $_SESSION['unlocked_memories'] ?? [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlock_memory'])) {
    $memory_id = (int)($_POST['memory_id'] ?? 0);
    $pin = trim($_POST['pin'] ?? '');
    $answer = trim($_POST['security_answer'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM vault_items WHERE id = ? AND user_id = ?");
    $stmt->execute([$memory_id, $user_id]);
    $memory = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($memory) {
        $pin_ok = true;
        $answer_ok = true;
        // If you add pin_code_hash and security_answer_hash columns, use password_verify here
        if (!empty($memory['pin_code_hash'])) {
            $pin_ok = password_verify($pin, $memory['pin_code_hash']);
        }
        if (!empty($memory['security_answer_hash'])) {
            $answer_ok = password_verify($answer, $memory['security_answer_hash']);
        }
        if ($pin_ok && $answer_ok) {
            $unlocked_memories[] = $memory_id;
            $_SESSION['unlocked_memories'] = $unlocked_memories;
        } else {
            $errors['unlock_' . $memory_id] = 'Invalid PIN or security answer.';
        }
    }
}

function isUnlocked($unlock_date) {
    $now = new DateTime();
    $unlock_dt = new DateTime($unlock_date);
    return $now >= $unlock_dt;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vault - MemoryChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom animations */
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
        
        .animate-fadeInUp {
            animation: fadeInUp 0.6s ease-out;
        }
        
        .security-glow {
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.5);
        }
        
        .memory-card {
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .memory-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .locked-overlay {
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }
        
        .countdown-timer {
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-purple-900 to-slate-900 min-h-screen">
    <!-- Vault Interface: always show, only per-memory unlock required -->
    <!-- Main Vault Interface -->
    <div class="flex h-screen">
        <!-- Responsive Sidebar -->
        <aside class="fixed inset-y-0 left-0 z-30 w-64 bg-gradient-to-b from-slate-800 to-slate-900 shadow-2xl transform -translate-x-full md:translate-x-0 md:static md:inset-auto transition-transform duration-300 ease-in-out" id="sidebar">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-12 h-12 bg-gradient-to-r from-purple-500 to-pink-500 rounded-xl flex items-center justify-center">
                        <i class="fas fa-vault text-white text-xl"></i>
                    </div>
                    <span class="text-xl font-bold text-white">MemoryChain</span>
                </div>
                
                <nav class="space-y-1">
                    <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="vault_access.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gradient-to-r from-purple-500 to-pink-500 text-white font-medium shadow-lg">
                        <i class="fas fa-vault"></i>
                        <span>My Vault</span>
                    </a>
                    <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                    <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </nav>
                
                <!-- User Profile -->
                <div class="mt-auto p-4">
                    <div class="glass rounded-lg p-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-white"><?php echo htmlspecialchars($username); ?></p>
                                <p class="text-xs text-gray-400">Vault Access Granted</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <!-- Sidebar Toggle Button (visible on small screens) -->
        <button id="sidebarToggle" class="md:hidden fixed top-4 right-4 z-40 bg-transparent text-slate-900 p-2 rounded-lg focus:outline-none">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <script>
        // Sidebar toggle logic
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        sidebarToggle.addEventListener('click', () => {
            if (sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });
        // Close sidebar when clicking outside (mobile only)
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 768 && !sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Hide toggle button on scroll down, show on scroll up
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            let st = window.pageYOffset || document.documentElement.scrollTop;
            if (st > lastScrollTop) {
                // Scrolling down
                sidebarToggle.style.display = 'none';
            } else {
                // Scrolling up
                sidebarToggle.style.display = '';
            }
            lastScrollTop = st <= 0 ? 0 : st;
        }, false);
        </script>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto">
            <!-- Enhanced Header -->
            <header class="bg-white/10 backdrop-blur-sm border-b border-white/20 px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-white mb-1">
                            Your Digital Vault
                        </h1>
                        <p class="text-gray-300">Secure your memories with time-locked encryption</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="bg-green-500/20 px-4 py-2 rounded-full">
                            <span class="text-green-400 text-sm font-medium">
                                <i class="fas fa-shield-alt mr-1"></i>Vault Secured
                            </span>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Vault Content -->
            <div class="p-6">
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl p-4 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-blue-200 text-sm">Total Memories</p>
                                <p class="text-2xl font-bold"><?php echo count($vault_items); ?></p>
                            </div>
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-archive text-white text-lg"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-200 text-sm">Unlocked</p>
                                <p class="text-3xl font-bold">
                                    <?php echo count(array_filter($vault_items, function($item) { return isUnlocked($item['unlock_date']); })); ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-unlock text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-orange-500 to-red-600 rounded-2xl p-6 text-white">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-orange-200 text-sm">Locked</p>
                                <p class="text-3xl font-bold">
                                    <?php echo count(array_filter($vault_items, function($item) { return !isUnlocked($item['unlock_date']); })); ?>
                                </p>
                            </div>
                            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-lock text-white text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if (isset($_GET['success']) && $_GET['success'] == '1' && !empty($_GET['msg'])): ?>
                    <div class="mb-6 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg text-center font-semibold">
                        <?php echo htmlspecialchars($_GET['msg']); ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="mb-6 p-4 bg-red-100 border border-red-300 text-red-800 rounded-lg text-center font-semibold">
                        <?php foreach ($errors as $err): ?>
                            <div><?php echo htmlspecialchars($err); ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <!-- Upload Form -->
                <!-- Secure New Memory section removed -->

                <!-- Memories Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($vault_items as $item): ?>
                    <div class="memory-card rounded-xl p-4 text-white relative overflow-hidden">
                        <div class="absolute inset-0 bg-black/20"></div>
                        <div class="relative z-10">
                            <div class="flex justify-between items-start mb-3">
                                <h3 class="text-base font-bold truncate flex-1 mr-2"><?php echo htmlspecialchars($item['title']); ?></h3>
                                <div class="flex-shrink-0">
                                    <span class="px-2 py-1 bg-white/20 rounded-full text-xs">
                                        <?php echo htmlspecialchars($item['security_level'] ?? ''); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <p class="text-gray-200 text-xs mb-3"><?php echo htmlspecialchars($item['description']); ?></p>
                            
                            <div class="space-y-1.5 text-xs">
                                <div class="flex items-center text-gray-300">
                                    <i class="fas fa-calendar mr-1.5 text-purple-300 w-4"></i>
                                    <span><?php echo date('M j, Y', strtotime($item['created_at'])); ?></span>
                                </div>
                                <div class="flex items-center text-gray-300">
                                    <i class="fas fa-clock mr-1.5 text-purple-300 w-4"></i>
                                    <span><?php echo date('M j, Y \a\t g:i A', strtotime($item['unlock_date'])); ?></span>
                                </div>
                                <div class="flex items-center text-gray-300">
                                    <i class="fas fa-file mr-1.5 text-purple-300 w-4"></i>
                                    <span><?php echo htmlspecialchars($item['file_type'] ?? ''); ?> - <?php echo formatFileSize($item['file_size'] ?? 0); ?></span>
                                </div>
                            </div>
                            
                            <div class="mt-3 pt-3 border-t border-white/20 flex items-center justify-between">
                                <?php if (isUnlocked($item['unlock_date'])): ?>
                                    <span class="text-green-300 text-sm font-medium">Unlocked</span>
                                    <?php if (!empty($item['file_path'])): ?>
                                        <form method="POST" action="vault.php" class="inline">
                                            <input type="hidden" name="action" value="download">
                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                            <button type="submit" title="Download Memory" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg flex items-center gap-2 shadow-md transition-all duration-200 group" style="outline:none;" aria-label="Download Memory">
                                                <i class="fas fa-download text-lg group-hover:animate-bounce"></i>
                                                <span class="hidden md:inline">Download</span>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-xs">No file attached</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="relative">
                                        <div class="locked-overlay absolute inset-0 rounded-lg flex items-center justify-center">
                                            <div class="text-center">
                                                <i class="fas fa-lock text-2xl mb-2"></i>
                                                <p class="text-sm">Locked</p>
                                            </div>
                                        </div>
                                        <div class="countdown-timer text-center">
                                            <div class="text-xs text-purple-300">Time Remaining</div>
                                            <div class="text-lg font-bold" data-unlock="<?php echo $item['unlock_date']; ?>"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Update Form -->
                            <details class="mt-3">
                                <summary class="cursor-pointer text-xs text-purple-300 hover:text-white inline-flex items-center">
                                    <i class="fas fa-edit mr-1 text-xs"></i>Modify Timeline
                                </summary>
                                <form method="POST" class="mt-2 space-y-1.5">
                                    <input type="hidden" name="action" value="update_unlock_date">
                                    <input type="hidden" name="vault_item_id" value="<?php echo $item['id']; ?>">
                                    
                                    <input type="date" name="new_unlock_date" required 
                                           class="w-full px-2 py-1 bg-white/10 border border-white/20 rounded text-white text-xs">
                                    <input type="time" name="new_unlock_time" 
                                           class="w-full px-2 py-1 bg-white/10 border border-white/20 rounded text-white text-xs">
                                    <textarea name="change_reason" placeholder="Reason for change..." 
                                              class="w-full px-2 py-1 bg-white/10 border border-white/20 rounded text-white text-xs resize-none" rows="2"></textarea>
                                    <button type="submit" 
                                            class="w-full bg-purple-500 text-white py-1 px-2 rounded text-xs font-medium hover:bg-purple-600 transition-colors">
                                        Update Timeline
                                    </button>
                                </form>
                            </details>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($vault_items)): ?>
                    <div class="col-span-full text-center py-12">
                        <div class="w-24 h-24 bg-gradient-to-r from-purple-500 to-pink-500 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-inbox text-white text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-white mb-2">No memories yet</h3>
                        <p class="text-gray-300 mb-4">Start your journey by securing your first memory</p>
                        <button class="bg-gradient-to-r from-purple-500 to-pink-500 text-white px-6 py-3 rounded-xl hover:from-purple-600 hover:to-pink-600 transition-all">
                            <i class="fas fa-plus mr-2"></i>Add Memory
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Countdown Timer Script -->
    <script>
        function updateCountdowns() {
            document.querySelectorAll('[data-unlock]').forEach(element => {
                const unlockDate = new Date(element.dataset.unlock);
                const now = new Date();
                const diff = unlockDate - now;
                
                if (diff > 0) {
                    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    element.innerHTML = `${days}d ${hours}h ${minutes}m ${seconds}s`;
                } else {
                    element.innerHTML = 'Ready to unlock!';
                }
            });
        }
        
        // Update countdowns every second
        setInterval(updateCountdowns, 1000);
        updateCountdowns();
        
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fadeInUp');
                    }
                });
            });
            
            document.querySelectorAll('.memory-card').forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>
</html>
