<?php
require_once 'admin_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getUpcomingUnlocks($pdo, $userId) {
    // Get memories that will unlock within the next hour
    $stmt = $pdo->prepare("
        SELECT 
            id,
            title,
            unlock_date,
            TIMESTAMPDIFF(MINUTE, NOW(), unlock_date) as minutes_remaining
        FROM vault_items 
        WHERE user_id = ? 
            AND is_locked = 1 
            AND unlock_date > NOW() 
            AND unlock_date <= DATE_ADD(NOW(), INTERVAL 1 HOUR)
        ORDER BY unlock_date ASC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$vault_count = 0;
$unlocked_count = 0;
$total_size = 0;
$active_days = 0;
if (isset($_SESSION['user_id'])) {
    global $pdo;
    try {
        // Get today's date for comparison
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // Get total memories for today
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vault_items WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $vault_count = (int)$stmt->fetchColumn();
        
        // Get total memories from yesterday (created_at before today)
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM vault_items WHERE user_id = ? AND DATE(created_at) < ?');
        $stmt->execute([$_SESSION['user_id'], $today]);
        $previous_count = (int)$stmt->fetchColumn();
        
        // Calculate percentage change
        $percentage_change = 0;
        if ($previous_count > 0) {
            $percentage_change = (($vault_count - $previous_count) / $previous_count) * 100;
        } elseif ($vault_count > 0) {
            $percentage_change = 100; // If previous was 0 and now we have items
        }
        
        // Update active days for first visit of the day
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            UPDATE users_acc 
            SET last_login = ?,
                active_days = IF(active_days IS NULL OR active_days = 0, 1,
                    active_days + IF(DATE(COALESCE(last_login, '1900-01-01')) < ?, 1, 0)
                )
            WHERE id = ?");
        $stmt->execute([$today, $today, $_SESSION['user_id']]);
        
        // Get updated active days count
        $stmt = $pdo->prepare('SELECT COALESCE(active_days, 0) as active_days FROM users_acc WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get upcoming unlocks
        $upcoming_unlocks = getUpcomingUnlocks($pdo, $_SESSION['user_id']);
        $active_days = (int)$result['active_days'];
        
        if ($active_days === 0) {
            // Force initialize active_days if it's still 0
            $stmt = $pdo->prepare("UPDATE users_acc SET active_days = 1 WHERE id = ? AND (active_days IS NULL OR active_days = 0)");
            $stmt->execute([$_SESSION['user_id']]);
            $active_days = 1;
        }
    } catch (PDOException $e) {
        error_log("Dashboard error: " . $e->getMessage());
    }

    // Unlocked memories (assuming unlocked = is_locked = 0)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM vault_items WHERE user_id = ? AND is_locked = 0');
    $stmt->execute([$_SESSION['user_id']]);
    $unlocked_count = (int)$stmt->fetchColumn();

    // Total size (sum of file_size column)
    $stmt = $pdo->prepare('SELECT SUM(file_size) FROM vault_items WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $total_size = (int)$stmt->fetchColumn();
}

// Success message from add_memory.php
$success_message = '';
if ((isset($_GET['success']) && $_GET['success'] === '1') || (isset($_GET['status']) && $_GET['status'] === 'success')) {
    $success_message = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : 'Memory added successfully!';
}
?>
<?php
if (!isset($total_size)) $total_size = 0;
// Storage quota in bytes (2GB)
$storage_limit = 2 * 1024 * 1024 * 1024;
$storage_percent = $storage_limit > 0 ? round(($total_size / $storage_limit) * 100, 2) : 0;
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
    $pow = min($pow, count($units) - 1);
    $val = $bytes / pow(1024, $pow);
    return round($val, 2) . ' ' . $units[$pow];
}
?>
<?php
if (!isset($unlocked_count)) $unlocked_count = 0;
?>
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Dashboard - MemoryChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Custom gradient animations */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0px); }
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        /* Glass morphism effect */
        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Hover effects */
        .hover-lift {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .hover-lift:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 3px;
        }
        @media (max-width: 640px) {
    #memory-search {
        width: 100% !important;
        font-size: 1rem !important;
        padding: 0.75rem 2.5rem 0.75rem 2.5rem !important;
    }
    #search-results {
        left: 0 !important;
        width: 100vw !important;
        min-width: 0 !important;
        font-size: 0.95rem !important;
        padding: 0.5rem !important;
    }
    #notificationBell {
        padding: 0.5rem !important;
        right: 1vw !important;
        top: 1vw !important;
        font-size: 1.5rem !important;
    }
    #notificationCount {
        width: 1.5rem !important;
        height: 1.5rem !important;
        font-size: 0.8rem !important;
        top: -0.5rem !important;
        right: -0.5rem !important;
    }
}
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 font-sans">
    <!-- Professional Dashboard Layout -->
    <div class="flex h-screen overflow-hidden">
        <!-- Responsive Sidebar -->
        <aside class="fixed inset-y-0 left-0 z-30 w-64 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl transform -translate-x-full md:translate-x-0 md:static md:inset-auto transition-transform duration-300 ease-in-out" id="sidebar">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 rounded-xl flex items-center justify-center animate-float">
                        <i class="fas fa-brain text-white text-lg"></i>
                    </div>
                    <span class="text-xl font-bold text-white">MemoryChain</span>
                </div>
                
                <nav class="space-y-1">
                    <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gradient-to-r from-blue-500 to-purple-600 text-white font-medium shadow-lg">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="vault_access.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
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
            </div>
            
            <!-- User Profile Section -->
            <div class="mt-auto p-4">
                <div class="glass rounded-lg p-4">
                    <div class="flex items-center space-x-3">
                        <?php
                        $profilePicPath = null;
                        $profilePicsDir = __DIR__ . '/uploads/profile_pics/';
                        if (isset($_SESSION['user_id'])) {
                            $userId = $_SESSION['user_id'];
                            // Search for a profile picture matching the user id
                            $picFiles = glob($profilePicsDir . 'profile_' . $userId . '*');
                            if (!empty($picFiles)) {
                                // Use the first match
                                $profilePicPath = 'uploads/profile_pics/' . basename($picFiles[0]);
                            }
                        }
                        ?>
                        <div class="w-10 h-10 rounded-full flex items-center justify-center overflow-hidden bg-gradient-to-r from-green-400 to-blue-500">
                            <?php if ($profilePicPath): ?>
                                <img src="<?php echo $profilePicPath; ?>" alt="Profile Picture" class="object-cover w-full h-full" />
                            <?php else: ?>
                                <i class="fas fa-user text-white text-sm"></i>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-white"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></p>
                            <p class="text-xs text-gray-400">Premium Member</p>
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
        </script>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto">
            <!-- Enhanced Header -->
            <header class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50 px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 mb-1">
                            Welcome back, <span class="gradient-text"><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                        </h1>
                        <p class="text-gray-600">Manage your memories and explore your digital vault</p>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <div class="relative">
                            <input type="search" 
                                   id="memory-search" 
                                   placeholder="Search memories..." 
                                   autocomplete="off"
                                   class="pl-12 pr-4 py-3 w-80 bg-gray-50 border border-gray-200 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all">
                            <i class="fas fa-search absolute left-4 top-4 text-gray-400"></i>
                            
                            <!-- Search Results Dropdown -->
                            <div id="search-results" 
                                 class="absolute mt-2 w-full bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden hidden">
                                <div class="max-h-96 overflow-y-auto">
                                    <!-- Results will be populated here -->
                                </div>
                                <div id="search-loading" class="hidden p-4 text-center text-gray-500">
                                    <i class="fas fa-circle-notch fa-spin mr-2"></i>Searching...
                                </div>
                                <div id="no-results" class="hidden p-4 text-center text-gray-500">
                                    No memories found
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notification Bell -->
                        <button class="relative p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all" id="notificationBell">
                            <i class="fas fa-bell text-gray-600"></i>
                            <span id="notificationCount" class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center" style="display: none;">0</span>
                        </button>
                    </div>
                </div>
            </header>

            <?php if (!empty($upcoming_unlocks)): ?>
            <!-- Notification Area -->
            <div class="mx-8 mt-4">
                <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-4" id="notifications-container">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-bell text-blue-400"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800">Upcoming Memory Unlocks</h3>
                            <div class="mt-2 text-sm text-blue-700">
                                <ul class="list-disc pl-5 space-y-1">
                                    <?php foreach ($upcoming_unlocks as $memory): ?>
                                        <li>
                                            "<?php echo htmlspecialchars($memory['title']); ?>" will unlock in 
                                            <?php 
                                            $mins = $memory['minutes_remaining'];
                                            if ($mins < 1) {
                                                echo "less than a minute";
                                            } elseif ($mins == 1) {
                                                echo "1 minute";
                                            } else {
                                                echo "$mins minutes";
                                            }
                                            ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Dashboard Content -->
            <div class="p-8">
                <!-- Enhanced Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Memories Card -->
                    <div class="bg-white rounded-2xl shadow-lg hover-lift p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Total Memories</p>
                                <div class="flex items-center space-x-2">
                                    <p class="text-3xl font-bold text-gray-900"><?php echo number_format($vault_count); ?></p>
                                    <?php if (isset($percentage_change)): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-sm <?php echo $percentage_change >= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <i class="fas fa-<?php echo $percentage_change >= 0 ? 'arrow-up' : 'arrow-down'; ?> mr-1"></i>
                                            <?php echo abs(round($percentage_change, 1)); ?>%
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="w-14 h-14 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-archive text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="flex justify-between mb-1">
                                <div class="text-xs text-gray-500">Previous: <?php echo number_format($previous_count); ?></div>
                                <div class="text-xs text-gray-500">Current: <?php echo number_format($vault_count); ?></div>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-blue-500 to-cyan-500 h-2 rounded-full transition-all duration-500" 
                                     style="width: <?php echo min(100, ($vault_count/max($previous_count, 1))*100) ?>%">
                                </div>
                            </div>
                            <div class="flex items-center justify-between mt-2">
                                <p class="text-xs text-gray-500">
                                    <?php if ($percentage_change > 0): ?>
                                        <span class="text-green-600">Added <?php echo $vault_count - $previous_count; ?> new memories</span>
                                    <?php elseif ($percentage_change < 0): ?>
                                        <span class="text-red-600">Removed <?php echo $previous_count - $vault_count; ?> memories</span>
                                    <?php else: ?>
                                        <span class="text-gray-600">No change in memories</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Unlocked Memories Card -->
                    <div class="bg-white rounded-2xl shadow-lg hover-lift p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Unlocked</p>
                                <p class="text-3xl font-bold text-green-600"><?php echo number_format($unlocked_count); ?></p>
                            </div>
                            <div class="w-14 h-14 bg-gradient-to-r from-green-500 to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-unlock text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-green-500 to-emerald-500 h-2 rounded-full transition-all duration-500" 
                                     style="width: <?php echo $vault_count > 0 ? min(100, ($unlocked_count/$vault_count)*100) : 0 ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                <?php echo $vault_count > 0 ? round(($unlocked_count/$vault_count)*100) : 0 ?>% unlocked
                            </p>
                        </div>
                    </div>

                    <!-- Storage Used Card -->
                    <div class="bg-white rounded-2xl shadow-lg hover-lift p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Storage Used</p>
                                <p class="text-3xl font-bold text-purple-600"><?php echo number_format($total_size/1024/1024, 1); ?> MB</p>
                            </div>
                            <div class="w-14 h-14 bg-gradient-to-r from-purple-500 to-pink-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-hdd text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-gradient-to-r from-purple-500 to-pink-500 h-2 rounded-full transition-all duration-500" 
                                     style="width: <?php echo $storage_percent; ?>%"></div>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                <?php echo $storage_percent; ?>% of <?php echo formatBytes($storage_limit); ?> (<?php echo formatBytes($total_size); ?> used)
                            </p>
                        </div>
                    </div>

                    <!-- Active Days Card -->
                    <div class="bg-white rounded-2xl shadow-lg hover-lift p-6 border border-gray-100">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600 mb-1">Active Days</p>
                                <p class="text-3xl font-bold text-orange-600"><?php echo number_format($active_days); ?></p>
                            </div>
                            <div class="w-14 h-14 bg-gradient-to-r from-orange-500 to-red-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-calendar text-white text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <?php 
                                    $monthlyGoal = 30;
                                    $progress = min(100, ($active_days/$monthlyGoal)*100);
                                ?>
                                <div class="bg-gradient-to-r from-orange-500 to-red-500 h-2 rounded-full transition-all duration-500" 
                                     style="width: <?php echo $progress; ?>%">
                                </div>
                            </div>
                            <div class="flex justify-between mt-2">
                                <p class="text-xs text-gray-500">
                                    <?php echo $active_days; ?> days active
                                </p>
                                <p class="text-xs text-gray-500">
                                    Goal: <?php echo $monthlyGoal; ?> days
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions Section -->
                <div class="bg-white rounded-2xl shadow-lg p-6 mb-8">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6">Quick Actions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="add_memory.php" class="group flex items-center justify-center px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl hover:from-blue-600 hover:to-blue-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i class="fas fa-plus mr-3 text-lg"></i>
                            <span class="font-medium">Add New Memory</span>
                        </a>
                        
                        <a href="vault_access.php" class="group flex items-center justify-center px-6 py-4 bg-gradient-to-r from-green-500 to-green-600 text-white rounded-xl hover:from-green-600 hover:to-green-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i class="fas fa-folder-open mr-3 text-lg"></i>
                            <span class="font-medium">View Vault</span>
                        </a>
                        
                        <a href="analytics.php" class="group flex items-center justify-center px-6 py-4 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded-xl hover:from-purple-600 hover:to-purple-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                            <i class="fas fa-chart-bar mr-3 text-lg"></i>
                            <span class="font-medium">Analytics</span>
                        </a>
                    </div>
                </div>

                <!-- Recent Activity Section -->
                <div class="bg-white rounded-2xl shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-semibold text-gray-800">Recent Activity</h3>
                        <button class="text-blue-600 hover:text-blue-700 font-medium">View All</button>
                    </div>
                    
                    <div class="space-y-4">
                        <?php if ($vault_count > 0): ?>
                            <?php
                            global $pdo;
                            $now = new DateTime();
                            
                            // First, get recently unlocked memories (where current time > unlock_date)
                            $stmt = $pdo->prepare("
                                SELECT 
                                    v.id, 
                                    v.title, 
                                    v.created_at, 
                                    v.file_type, 
                                    v.file_size, 
                                    v.is_locked,
                                    v.unlock_date,
                                    CASE 
                                        WHEN v.unlock_date <= NOW() AND v.unlock_date > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'recently_unlocked'
                                        WHEN v.unlock_date <= NOW() THEN 'unlocked'
                                        ELSE 'locked'
                                    END as status
                                FROM vault_items v
                                WHERE v.user_id = ? 
                                    AND (
                                        (v.unlock_date <= NOW() AND v.unlock_date > DATE_SUB(NOW(), INTERVAL 7 DAY))  -- Recently unlocked (within last 7 days)
                                        OR v.unlock_date > NOW()  -- Soon to be unlocked
                                    )
                                ORDER BY 
                                    CASE 
                                        WHEN v.unlock_date <= NOW() THEN v.unlock_date
                                        ELSE v.created_at 
                                    END DESC
                                LIMIT 5
                            ");
                            $stmt->execute([$_SESSION['user_id']]);
                            $recent_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Group items by their unlock status
                            $grouped_items = [
                                'recently_unlocked' => [],
                                'unlocked' => [],
                                'locked' => []
                            ];
                            
                            foreach ($recent_items as $item) {
                                $grouped_items[$item['status']][] = $item;
                            }
                            ?>
                            <?php if (!empty($grouped_items['recently_unlocked'])): ?>
                                <div class="mb-4">
                                    <h4 class="text-sm font-medium text-green-600 mb-2">Recently Unlocked</h4>
                                    <?php foreach ($grouped_items['recently_unlocked'] as $item): ?>
                                        <a href="vault.php?item_id=<?php echo urlencode($item['id']); ?>" 
                                           class="group flex items-center space-x-4 p-3 bg-green-50 rounded-xl hover:bg-green-100 transition-all duration-200 cursor-pointer mb-2">
                                            <div class="w-10 h-10 bg-gradient-to-r from-green-500 to-emerald-500 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                                                <i class="fas fa-unlock-alt text-white text-sm"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium text-gray-800 truncate group-hover:text-green-600 transition-colors">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </p>
                                                <p class="text-xs text-gray-600">
                                                    Unlocked <?php echo date('M j, g:i A', strtotime($item['unlock_date'])); ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0 flex items-center gap-2">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-check-circle mr-1"></i>Unlocked
                                                </span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                                <?php if (!empty($grouped_items['unlocked'])): ?>
                                    <div class="mb-4">
                                        <h4 class="text-sm font-medium text-blue-600 mb-2">Unlocked Memories</h4>
                                        <?php foreach ($grouped_items['unlocked'] as $item): ?>
                                            <a href="vault.php?item_id=<?php echo urlencode($item['id']); ?>" 
                                               class="group flex items-center space-x-4 p-3 bg-blue-50 rounded-xl hover:bg-blue-100 transition-all duration-200 cursor-pointer mb-2">
                                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-cyan-500 rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform">
                                                    <i class="fas fa-unlock text-white text-sm"></i>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <p class="font-medium text-gray-800 truncate group-hover:text-blue-600 transition-colors">
                                                        <?php echo htmlspecialchars($item['title']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        Unlocked <?php echo date('M j, g:i A', strtotime($item['unlock_date'])); ?>
                                                    </p>
                                                </div>
                                                <div class="flex-shrink-0 flex items-center gap-2">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        <i class="fas fa-unlock mr-1"></i>Unlocked
                                                    </span>
                                                </div>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                            <?php if (!empty($grouped_items['locked'])): ?>
                                <div>
                                    <h4 class="text-sm font-medium text-purple-600 mb-2">Coming Up</h4>
                                    <?php foreach ($grouped_items['locked'] as $item): ?>
                                        <div class="group flex items-center space-x-4 p-3 bg-gray-50 rounded-xl hover:bg-gray-100 transition-all duration-200 mb-2">
                                            <div class="w-10 h-10 bg-gradient-to-r from-purple-500 to-pink-500 rounded-xl flex items-center justify-center">
                                                <?php
                                                $iconClass = 'fa-file';
                                                if (strpos($item['file_type'], 'image/') === 0) {
                                                    $iconClass = 'fa-image';
                                                } elseif (strpos($item['file_type'], 'video/') === 0) {
                                                    $iconClass = 'fa-video';
                                                } elseif (strpos($item['file_type'], 'audio/') === 0) {
                                                    $iconClass = 'fa-music';
                                                }
                                                ?>
                                                <i class="fas <?php echo $iconClass; ?> text-white text-sm"></i>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <p class="font-medium text-gray-800 truncate">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </p>
                                                <p class="text-xs text-gray-600">
                                                    Unlocks <?php echo date('M j, g:i A', strtotime($item['unlock_date'])); ?>
                                                </p>
                                            </div>
                                            <div class="flex-shrink-0">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                                    <i class="fas fa-lock mr-1"></i>Locked
                                                </span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-inbox text-3xl text-gray-400"></i>
                                </div>
                                <h4 class="text-lg font-medium text-gray-900 mb-2">No memories yet</h4>
                                <p class="text-gray-600 mb-4">Start your journey by adding your first memory</p>
                                <a href="add_memory.php" class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 text-white rounded-lg hover:from-blue-600 hover:to-purple-700 transition-all">
                                    <i class="fas fa-plus mr-2"></i>
                                    Add Memory
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Enhanced Floating Action Button -->
    <div class="fixed bottom-8 right-8 group">
        <a href="add_memory.php" 
           class="w-16 h-16 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 rounded-full flex items-center justify-center shadow-2xl hover:shadow-3xl transition-all duration-300 hover:scale-110 group-hover:rotate-12">
            <i class="fas fa-plus text-white text-2xl"></i>
        </a>
        <div class="absolute bottom-full right-0 mb-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
            <div class="bg-gray-900 text-white text-sm rounded-lg py-2 px-3 whitespace-nowrap">
                Add Memory
            </div>
        </div>
    </div>

    <!-- Enhanced JavaScript -->
    <script>
        // Memory Search Functionality
        // Function to refresh notifications
        function refreshNotifications() {
            // Get notification content
            fetch('get_notifications.php')
                .then(response => response.text())
                .then(html => {
                    const container = document.getElementById('notifications-container');
                    if (container) {
                        if (html.trim()) {
                            container.innerHTML = html;
                        } else {
                            container.closest('.mx-8')?.remove();
                        }
                    }
                });

            // Get notification count
            fetch('get_notifications.php?count_only=1')
                .then(response => response.json())
                .then(data => {
                    const countElement = document.getElementById('notificationCount');
                    if (data.count > 0) {
                        countElement.textContent = data.count;
                        countElement.style.display = 'flex';
                    } else {
                        countElement.style.display = 'none';
                    }
                });
        }

        // Mark notifications as read when clicking the bell
        document.getElementById('notificationBell').addEventListener('click', function() {
            fetch('get_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_read'
            })
            .then(() => {
                document.getElementById('notificationCount').style.display = 'none';
                refreshNotifications();
                // Show dialog box with notifications
                const container = document.getElementById('notifications-container');
                if (container) {
                    container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    container.classList.add('ring-2', 'ring-blue-400', 'rounded-xl');
                    setTimeout(() => container.classList.remove('ring-2', 'ring-blue-400', 'rounded-xl'), 2000);
                } else {
                    // If no notifications, show a dialog
                    alert('No messages or notifications.');
                }
            });
        });

        // Initial notification check and refresh every minute
        refreshNotifications();
        setInterval(refreshNotifications, 60000);

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('memory-search');
            const searchResults = document.getElementById('search-results');
            const searchLoading = document.getElementById('search-loading');
            const noResults = document.getElementById('no-results');
            let searchTimeout;
            
            // Function to escape HTML
            function escapeHtml(unsafe) {
                return unsafe
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }
            
            // Function to format the result item
            function formatSearchResult(item) {
                const statusClass = item.status === 'unlocked' ? 
                    'bg-green-100 text-green-800' : 
                    'bg-gray-100 text-gray-800';
                const statusIcon = item.status === 'unlocked' ? 
                    'fa-unlock' : 
                    'fa-lock';
                    
                return `
                    <a href="vault.php?item_id=${item.id}" 
                       class="block hover:bg-gray-50 transition-colors duration-150">
                        <div class="p-4 border-b border-gray-100 last:border-0">
                            <div class="flex items-start space-x-3">
                                <div class="w-10 h-10 flex-shrink-0 bg-gradient-to-r from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
                                    <i class="fas ${item.icon_class} text-white text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium text-gray-900 truncate">
                                        ${escapeHtml(item.title)}
                                    </p>
                                    <p class="text-sm text-gray-500 mt-0.5 line-clamp-1">
                                        ${escapeHtml(item.description || 'No description')}
                                    </p>
                                    <div class="flex items-center space-x-2 mt-1">
                                        <span class="text-xs text-gray-500">
                                            <i class="far fa-calendar-alt mr-1"></i>${item.created_at}
                                        </span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${statusClass}">
                                            <i class="fas ${statusIcon} mr-1"></i>${item.status}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                `;
            }
            
            // Function to perform search
            async function performSearch(query) {
                console.log('Performing search for:', query);
                searchLoading.classList.remove('hidden');
                noResults.classList.add('hidden');
                
                try {
                    const response = await fetch(`search_memories.php?q=${encodeURIComponent(query)}`);
                    console.log('Search response status:', response.status);
                    
                    const text = await response.text();
                    console.log('Raw response:', text);
                    
                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        throw new Error('Invalid JSON response');
                    }
                    
                    const resultsContainer = searchResults.querySelector('div');
                    console.log('Search results:', data);
                    
                    if (data.results && data.results.length > 0) {
                        const html = data.results.map(formatSearchResult).join('');
                        console.log('Generated HTML:', html);
                        resultsContainer.innerHTML = html;
                        searchResults.classList.remove('hidden');
                        noResults.classList.add('hidden');
                    } else {
                        resultsContainer.innerHTML = '';
                        noResults.classList.remove('hidden');
                    }
                } catch (error) {
                    console.error('Search error:', error);
                    searchResults.querySelector('div').innerHTML = 
                        `<div class="p-4 text-center text-red-500">Error: ${error.message}</div>`;
                } finally {
                    searchLoading.classList.add('hidden');
                }
            }
            
            // Search input event handler
            searchInput.addEventListener('input', (e) => {
                const query = e.target.value.trim();
                
                clearTimeout(searchTimeout);
                
                if (query.length === 0) {
                    searchResults.classList.add('hidden');
                    return;
                }
                
                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => performSearch(query), 300);
                }
            });
            
            // Close search results when clicking outside
            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                    searchResults.classList.add('hidden');
                }
            });
            
            // Show results again when focusing on input
            searchInput.addEventListener('focus', () => {
                if (searchInput.value.trim().length >= 2) {
                    searchResults.classList.remove('hidden');
                }
            });
            
            // Enhanced animations and interactions
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '0';
                        entry.target.style.transform = 'translateY(20px)';
                        entry.target.style.transition = 'all 0.6s ease-out';
                        
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                        }, 100);
                    }
                });
            }, observerOptions);

            // Observe all cards
            document.querySelectorAll('.hover-lift').forEach(card => {
                observer.observe(card);
            });


            document.querySelectorAll(".info-icon").forEach(icon => {
  icon.addEventListener("mouseenter", () => {
    const tooltip = document.createElement("div");
    tooltip.className = "tooltip";
    tooltip.textContent = icon.dataset.tooltip;
    icon.appendChild(tooltip);
  });
  icon.addEventListener("mouseleave", () => {
    icon.querySelector(".tooltip")?.remove();
  });
});


            // Counter animation for stats
            function animateValue(element, start, end, duration) {
                let startTimestamp = null;
                const step = (timestamp) => {
                    if (!startTimestamp) startTimestamp = timestamp;
                    const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                    element.innerHTML = Math.floor(progress * (end - start) + start).toLocaleString();
                    if (progress < 1) {
                        window.requestAnimationFrame(step);
                    }
                };
                window.requestAnimationFrame(step);
            }

            // Animate numbers on load
            setTimeout(() => {
                const statValues = document.querySelectorAll('.text-3xl');
                statValues.forEach(stat => {
                    const finalValue = parseInt(stat.textContent.replace(/,/g, ''));
                    if (!isNaN(finalValue)) {
                        animateValue(stat, 0, finalValue, 2000);
                    }
                });
            }, 500);

            // Add hover effects to navigation
            document.querySelectorAll('a[href^="vault"], a[href^="settings"]').forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                });
                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        });
    </script>

    <!-- Active Days Notification Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var activeDays = parseInt(document.getElementById('activeDaysCount').textContent.replace(/,/g, ''));
        if (activeDays >= 30) {
            // Show motivational notification
            var motivation = document.createElement('div');
            motivation.innerHTML = '<div style="position:fixed;top:20px;right:20px;z-index:9999;background:#6366f1;color:#fff;padding:20px;border-radius:12px;box-shadow:0 8px 32px rgba(99,102,241,0.3);font-size:18px;">🎉 Congratulations! You have a 30-day streak!<br>Keep up the amazing consistency and keep building your memories!</div>';
            document.body.appendChild(motivation);
            setTimeout(function() { motivation.remove(); }, 10000);
        }
    });
    </script>
</body>
</html>
