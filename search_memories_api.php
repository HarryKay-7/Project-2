<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'admin_config.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get the search query
$query = $_GET['q'] ?? '';
$query = trim($query);

if (empty($query)) {
    echo json_encode(['results' => [], 'message' => 'Empty query']);
    exit;
}

try {
    global $pdo;
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    $searchTerm = "%{$query}%";
    $sql = "
        SELECT 
            id,
            title,
            description,
            file_type,
            file_name,
            created_at,
            unlock_date,
            is_locked,
            CASE 
                WHEN unlock_date <= NOW() THEN 'unlocked'
                ELSE 'locked'
            END as status
        FROM vault_items 
        WHERE user_id = :user_id 
        AND title LIKE :search_term
        ORDER BY 
            CASE 
                WHEN unlock_date <= NOW() THEN created_at
                ELSE unlock_date
            END DESC
        LIMIT 10
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':search_term', $searchTerm, PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $formattedResults = array_map(function($item) {
        $iconClass = 'fa-file';
        if (strpos($item['file_type'], 'image/') === 0) {
            $iconClass = 'fa-image';
        } elseif (strpos($item['file_type'], 'video/') === 0) {
            $iconClass = 'fa-video';
        } elseif (strpos($item['file_type'], 'audio/') === 0) {
            $iconClass = 'fa-music';
        }
        $createdAt = date('M j, Y', strtotime($item['created_at']));
        $unlockDate = date('M j, Y', strtotime($item['unlock_date']));
        return [
            'id' => $item['id'],
            'title' => $item['title'],
            'description' => mb_strlen($item['description']) > 100 ? mb_substr($item['description'], 0, 100) . '...' : $item['description'],
            'file_type' => $item['file_type'],
            'file_name' => $item['file_name'],
            'created_at' => $createdAt,
            'unlock_date' => $unlockDate,
            'status' => $item['status'],
            'icon_class' => $iconClass
        ];
    }, $results);
    echo json_encode([
        'results' => $formattedResults,
        'query' => $query,
        'count' => count($formattedResults)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'query' => $query
    ]);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Search error',
        'message' => $e->getMessage(),
        'query' => $query
    ]);
    exit;
}
