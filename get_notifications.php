<?php
require_once 'admin_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    exit();
}

// Handle marking notifications as read
if (isset($_POST['action']) && $_POST['action'] === 'mark_read') {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['success' => true]);
    exit();
}

// Get unread notification count
if (isset($_GET['count_only'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);
    exit();
}

function getUpcomingUnlocks($pdo, $userId) {
    // Get memories that will unlock within the next hour
    // Get upcoming unlocks
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
    $unlocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update notifications table
    foreach ($unlocks as $memory) {
        // Check if notification already exists
        $stmt = $pdo->prepare("
            SELECT id FROM notifications 
            WHERE user_id = ? AND memory_id = ? AND unlock_time = ?
        ");
        $stmt->execute([$userId, $memory['id'], $memory['unlock_date']]);
        
        if (!$stmt->fetch()) {
            // Create new notification if it doesn't exist
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, memory_id, title, unlock_time)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $memory['id'], $memory['title'], $memory['unlock_date']]);
        }
    }

    // Clean up old notifications
    $stmt = $pdo->prepare("
        DELETE FROM notifications 
        WHERE user_id = ? AND unlock_time <= NOW()
    ");
    $stmt->execute([$userId]);

    return $unlocks;
}

$upcoming_unlocks = getUpcomingUnlocks($pdo, $_SESSION['user_id']);

if (!empty($upcoming_unlocks)): ?>
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
<?php endif; ?>

<!-- Notification Icon and Dialog -->
<div class="fixed top-6 right-6 z-50">
    <button id="notificationBtn" class="bg-white shadow rounded-full p-3 hover:bg-blue-50 focus:outline-none">
        <i class="fas fa-bell text-blue-600 text-xl"></i>
    </button>
</div>
<div id="notificationDialog" class="hidden fixed top-20 right-6 bg-white border border-gray-200 rounded-xl shadow-2xl w-80 max-w-full p-4 z-50">
    <div class="flex items-center mb-2">
        <i class="fas fa-bell text-blue-600 text-lg mr-2"></i>
        <span class="font-bold text-blue-700">Notifications</span>
        <button id="closeNotification" class="ml-auto text-gray-400 hover:text-gray-700">&times;</button>
    </div>
    <div id="notificationContent" class="text-sm text-gray-700"></div>
</div>
<script>
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDialog = document.getElementById('notificationDialog');
    const closeNotification = document.getElementById('closeNotification');
    const notificationContent = document.getElementById('notificationContent');
    notificationBtn.addEventListener('click', () => {
        notificationDialog.classList.remove('hidden');
        fetch('get_notifications.php')
            .then(res => res.text())
            .then(html => {
                if (html.trim() === '' || html.includes('Upcoming Memory Unlocks') === false) {
                    notificationContent.innerHTML = '<div class="text-gray-400 text-center py-6">No messages or notifications.</div>';
                } else {
                    notificationContent.innerHTML = html;
                }
            })
            .catch(() => {
                notificationContent.innerHTML = '<div class="text-red-500 text-center py-6">Error loading notifications.</div>';
            });
    });
    closeNotification.addEventListener('click', () => {
        notificationDialog.classList.add('hidden');
    });
</script>
