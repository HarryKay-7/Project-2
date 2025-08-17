<?php
require_once 'admin_config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    global $pdo;
    
    // Get total memory stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_memories,
            SUM(CASE WHEN is_locked = 0 OR unlock_date <= NOW() THEN 1 ELSE 0 END) as unlocked_memories,
            SUM(file_size) as total_storage,
            COUNT(DISTINCT DATE(created_at)) as active_days,
            MIN(created_at) as first_memory_date
        FROM vault_items 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $general_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get memory types distribution
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN file_type LIKE 'image/%' THEN 'Images'
                WHEN file_type LIKE 'video/%' THEN 'Videos'
                WHEN file_type LIKE 'audio/%' THEN 'Audio'
                WHEN file_type LIKE 'application/pdf' THEN 'PDFs'
                ELSE 'Others'
            END as type,
            COUNT(*) as count
        FROM vault_items 
        WHERE user_id = ?
        GROUP BY 
            CASE 
                WHEN file_type LIKE 'image/%' THEN 'Images'
                WHEN file_type LIKE 'video/%' THEN 'Videos'
                WHEN file_type LIKE 'audio/%' THEN 'Audio'
                WHEN file_type LIKE 'application/pdf' THEN 'PDFs'
                ELSE 'Others'
            END
    ");
    $stmt->execute([$user_id]);
    $type_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get monthly activity
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as memories_added,
            SUM(file_size) as storage_used
        FROM vault_items 
        WHERE user_id = ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute([$user_id]);
    $monthly_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get unlock schedule for next 30 days
    $stmt = $pdo->prepare("
        SELECT 
            DATE(unlock_date) as unlock_day,
            COUNT(*) as memories_unlocking
        FROM vault_items 
        WHERE user_id = ? 
        AND unlock_date > NOW() 
        AND unlock_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(unlock_date)
        ORDER BY unlock_day
    ");
    $stmt->execute([$user_id]);
    $upcoming_unlocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get security level distribution
    $stmt = $pdo->prepare("
        SELECT 
            security_level,
            COUNT(*) as count
        FROM vault_items 
        WHERE user_id = ?
        GROUP BY security_level
    ");
    $stmt->execute([$user_id]);
    $security_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Analytics error: " . $e->getMessage());
    $error_message = "Error loading analytics data";
}

// Function to format bytes to readable size
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

// Calculate days since first memory
$days_active = 0;
if (!empty($general_stats['first_memory_date'])) {
    $first_memory = new DateTime($general_stats['first_memory_date']);
    $today = new DateTime();
    $days_active = max(1, $today->diff($first_memory)->days); // Ensure at least 1 day
} else {
    $days_active = 1; // Default to 1 if no memories exist yet
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - MemoryChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        .analytics-card {
            transition: all 0.3s ease;
        }
        
        .analytics-card:hover {
            transform: translateY(-5px);
        }
        
        .chart-container {
            position: relative;
            margin: auto;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 font-sans">
    <div class="flex h-screen overflow-hidden">
        <!-- Responsive Sidebar -->
        <aside class="fixed inset-y-0 left-0 z-30 w-64 bg-gradient-to-b from-slate-900 to-slate-800 shadow-2xl transform -translate-x-full md:translate-x-0 md:static md:inset-auto transition-transform duration-300 ease-in-out" id="sidebar">
            <div class="p-6">
                <div class="flex items-center space-x-3 mb-8">
                    <div class="w-12 h-12 bg-gradient-to-r from-blue-500 via-purple-500 to-pink-500 rounded-xl flex items-center justify-center">
                        <i class="fas fa-brain text-white text-lg"></i>
                    </div>
                    <span class="text-xl font-bold text-white">MemoryChain</span>
                </div>
                
                <nav class="space-y-1">
                    <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="vault_access.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-slate-700 text-gray-300 transition-all duration-200">
                        <i class="fas fa-vault"></i>
                        <span>My Vault</span>
                    </a>
                    <a href="analytics.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg bg-gradient-to-r from-blue-500 to-purple-600 text-white font-medium shadow-lg">
                        <i class="fas fa-chart-line"></i>
                        <span>Analytics</span>
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

        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Header -->
            <header class="bg-white/80 backdrop-blur-sm border-b border-gray-200/50 px-8 py-6">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex-1">
                        <h1 class="text-3xl font-bold text-gray-900 mb-1 md:mb-0">Analytics Dashboard</h1>
                        <p class="text-gray-600 mb-4 md:mb-0">Track and analyze your memory patterns</p>
                    </div>
                    <div class="flex flex-col md:flex-row items-start md:items-center gap-2 md:gap-4">
                        <form action="export_analytics.php" method="get" class="inline">
                            <input type="hidden" name="format" value="pdf">
                            <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors flex items-center w-full md:w-auto">
                                <i class="far fa-file-code mr-2"></i>
                                Download HTML Report
                            </button>
                        </form>
                        <form action="export_analytics.php" method="get" class="inline">
                            <input type="hidden" name="format" value="txt">
                            <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors flex items-center w-full md:w-auto">
                                <i class="far fa-file-text mr-2"></i>
                                Download Text Report
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Analytics Content -->
            <div class="p-8">
                <!-- Overview Stats -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Memories -->
                    <div class="analytics-card bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Memories</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($general_stats['total_memories']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-500 bg-opacity-10 rounded-full flex items-center justify-center">
                                <i class="fas fa-archive text-blue-500"></i>
                            </div>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <span><?php echo $general_stats['unlocked_memories']; ?> unlocked</span>
                            <span class="mx-2">•</span>
                            <span><?php echo $general_stats['total_memories'] - $general_stats['unlocked_memories']; ?> locked</span>
                        </div>
                    </div>

                    <!-- Storage Used -->
                    <div class="analytics-card bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Storage Used</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo formatBytes($general_stats['total_storage']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-500 bg-opacity-10 rounded-full flex items-center justify-center">
                                <i class="fas fa-database text-green-500"></i>
                            </div>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <?php $storage_percentage = min(100, ($general_stats['total_storage']/(1024*1024*1024))*100); ?>
                            <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $storage_percentage; ?>%"></div>
                        </div>
                        <p class="text-sm text-gray-600 mt-2"><?php echo round($storage_percentage, 1); ?>% of 1GB used</p>
                    </div>

                    <!-- Active Days -->
                    <div class="analytics-card bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Active Days</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($general_stats['active_days']); ?></p>
                            </div>
                            <div class="w-12 h-12 bg-purple-500 bg-opacity-10 rounded-full flex items-center justify-center">
                                <i class="fas fa-calendar-check text-purple-500"></i>
                            </div>
                        </div>
                        <div class="flex items-center text-sm text-gray-600">
                            <span><?php 
                                // Calculate activity rate, avoiding division by zero
                                $activity_rate = $days_active > 0 ? 
                                    round(($general_stats['active_days']/$days_active)*100, 1) : 
                                    0;
                                echo $activity_rate; ?>% activity rate</span>
                            <span class="mx-2">•</span>
                            <span><?php echo $days_active; ?> total days</span>
                        </div>
                    </div>

                    <!-- Upcoming Unlocks -->
                    <div class="analytics-card bg-white rounded-xl shadow-lg p-6">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Upcoming Unlocks</p>
                                <?php
                                $total_upcoming = array_sum(array_column($upcoming_unlocks, 'memories_unlocking'));
                                ?>
                                <p class="text-3xl font-bold text-gray-900"><?php echo $total_upcoming; ?></p>
                            </div>
                            <div class="w-12 h-12 bg-orange-500 bg-opacity-10 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-orange-500"></i>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600">
                            Next 30 days
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Memory Types Chart -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Memory Types Distribution</h3>
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="typeDistributionChart"></canvas>
                        </div>
                    </div>

                    <!-- Monthly Activity Chart -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Monthly Activity</h3>
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="monthlyActivityChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Additional Stats -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Upcoming Unlocks Calendar -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Upcoming Memory Unlocks</h3>
                        <div class="space-y-4">
                            <?php foreach ($upcoming_unlocks as $unlock): ?>
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 bg-blue-500 bg-opacity-10 rounded-full flex items-center justify-center">
                                            <i class="fas fa-unlock text-blue-500"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo date('M j, Y', strtotime($unlock['unlock_day'])); ?></p>
                                            <p class="text-sm text-gray-600"><?php echo $unlock['memories_unlocking']; ?> memories unlocking</p>
                                        </div>
                                    </div>
                                    <div class="text-sm font-medium text-blue-600">
                                        <?php 
                                        $days_until = (strtotime($unlock['unlock_day']) - time()) / (60*60*24);
                                        echo 'in ' . round($days_until) . ' days';
                                        ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Security Distribution -->
                    <div class="bg-white rounded-xl shadow-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Security Level Distribution</h3>
                        <div class="chart-container" style="height: 300px;">
                            <canvas id="securityDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Memory Types Distribution Chart
            new Chart(document.getElementById('typeDistributionChart'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($type_distribution, 'type')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($type_distribution, 'count')); ?>,
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(249, 115, 22, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(236, 72, 153, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            // Monthly Activity Chart
            new Chart(document.getElementById('monthlyActivityChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_map(function($item) {
                        return date('M Y', strtotime($item['month'] . '-01'));
                    }, $monthly_activity)); ?>,
                    datasets: [{
                        label: 'Memories Added',
                        data: <?php echo json_encode(array_column($monthly_activity, 'memories_added')); ?>,
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });

            // Security Distribution Chart
            new Chart(document.getElementById('securityDistributionChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($security_distribution, 'security_level')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($security_distribution, 'count')); ?>,
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>
