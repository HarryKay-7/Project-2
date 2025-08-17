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

// Log search attempt
error_log("Search attempt - Query: " . $query . " User: " . $_SESSION['user_id']);

try {
    global $pdo;
    
    if (!$pdo) {
        error_log("Database connection failed");
        throw new Exception("Database connection failed");
    }
    
    // Log PDO attributes
    error_log("PDO Error Mode: " . $pdo->getAttribute(PDO::ATTR_ERRMODE));
    
    // Search in titles only
    $searchTerm = "%{$query}%";
    
    // Prepare the SQL query
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
    
    error_log("Executing search query - SQL: " . $sql);
    
    $stmt = $pdo->prepare($sql);
    
    // Bind parameters
    $stmt->bindValue(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':search_term', $searchTerm, PDO::PARAM_STR);
    
    // Execute the query
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the results
    $formattedResults = array_map(function($item) {
        // Get file icon
        $iconClass = 'fa-file';
        if (strpos($item['file_type'], 'image/') === 0) {
            $iconClass = 'fa-image';
        } elseif (strpos($item['file_type'], 'video/') === 0) {
            $iconClass = 'fa-video';
        } elseif (strpos($item['file_type'], 'audio/') === 0) {
            $iconClass = 'fa-music';
        }
        
        // Format dates
        $createdAt = date('M j, Y', strtotime($item['created_at']));
        $unlockDate = date('M j, Y', strtotime($item['unlock_date']));
        
        return [
            'id' => $item['id'],
            'title' => $item['title'],
            'description' => mb_strlen($item['description']) > 100 ? 
                           mb_substr($item['description'], 0, 100) . '...' : 
                           $item['description'],
            'file_type' => $item['file_type'],
            'file_name' => $item['file_name'],
            'created_at' => $createdAt,
            'unlock_date' => $unlockDate,
            'status' => $item['status'],
            'icon_class' => $iconClass
        ];
    }, $results);
    
    // Log the results
    error_log("Search results count: " . count($formattedResults));
    
    echo json_encode([
        'results' => $formattedResults,
        'query' => $query,
        'count' => count($formattedResults)
    ]);
    
} catch (PDOException $e) {
    error_log("Search PDO Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'query' => $query
    ]);
    exit;
} catch (Exception $e) {
    error_log("Search General Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Search error',
        'message' => $e->getMessage(),
        'query' => $query
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Memories - MemoryChain</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @media (max-width: 640px) {
            .search-container { padding: 1rem; }
            .results-list { padding: 0.5rem; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-white to-slate-100 min-h-screen flex flex-col items-center justify-start">
    <div class="w-full max-w-xl mx-auto mt-8 search-container">
        <h1 class="text-2xl font-bold text-indigo-700 mb-4 text-center">Search Your Memories</h1>
        <form id="searchForm" class="flex flex-col sm:flex-row gap-2 mb-6">
            <input type="text" id="searchInput" class="flex-1 border border-gray-300 rounded-lg px-4 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" placeholder="Type to search...">
            <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-indigo-700 transition-all">Search</button>
        </form>
        <ul id="resultsList" class="results-list bg-white rounded-lg shadow p-4"></ul>
    </div>
    <script>
        const form = document.getElementById('searchForm');
        const input = document.getElementById('searchInput');
        const resultsList = document.getElementById('resultsList');
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const query = input.value.trim();
            if (!query) return;
            resultsList.innerHTML = '<li class="text-gray-400">Searching...</li>';
            fetch(`search_memories_api.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.results && data.results.length > 0) {
                        resultsList.innerHTML = '';
                        data.results.forEach(item => {
                            const li = document.createElement('li');
                            li.className = 'flex items-center gap-3 py-2 border-b last:border-b-0 cursor-pointer hover:bg-indigo-50 rounded transition';
                            li.innerHTML = `<i class='fas ${item.icon_class} text-indigo-600 text-lg'></i>
                                <div class='flex-1'>
                                    <div class='font-semibold text-indigo-700'>${item.title || item.file_name}</div>
                                    <div class='text-xs text-gray-500'>${item.description}</div>
                                    <div class='text-xs text-gray-400'>Created: ${item.created_at} | Unlock: ${item.unlock_date} | Status: ${item.status}</div>
                                </div>`;
                            li.onclick = () => {
                                window.location.href = `vault_access.php?id=${item.id}`;
                            };
                            resultsList.appendChild(li);
                        });
                    } else {
                        resultsList.innerHTML = '<li class="text-gray-400">No memories found.</li>';
                    }
                })
                .catch(() => {
                    resultsList.innerHTML = '<li class="text-red-500">Error searching memories.</li>';
                });
        });
    </script>
</body>
</html>
