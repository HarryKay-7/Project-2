<?php
session_start();
require_once 'auth.php'; // or your DB connection file

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'] ?? null;
    $password = $_POST['password'] ?? '';
    if ($userId && $password) {
        $stmt = $pdo->prepare('SELECT password FROM users_acc WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            header('Location: vault.php');
            exit;
        } else {
            $error = 'Incorrect password. Please try again.';
        }
    } else {
        $error = 'Please enter your password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vault Access</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <div class="min-h-screen bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 relative">
    <a href="dashboard.php" class="fixed top-6 left-6 z-50 flex items-center text-black hover:text-gray-700 font-semibold bg-transparent px-4 py-2 rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-7 h-7 mr-2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Dashboard
        </a>
        <div class="flex items-center justify-center min-h-screen">
            <form method="POST" class="bg-white/90 backdrop-blur-lg p-10 rounded-2xl shadow-2xl w-full max-w-md border border-gray-200 relative">
            <div class="flex flex-col items-center mb-6">
                <div class="w-16 h-16 bg-gradient-to-r from-indigo-600 to-purple-600 rounded-full flex items-center justify-center mb-2 shadow-lg">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0v2m0-2h2m-2 0h-2m6-6a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                </div>
                <h2 class="text-3xl font-extrabold text-gray-900 mb-1">Vault Access</h2>
                <p class="text-gray-500 text-sm">Enter your password to continue</p>
            </div>
            <?php if ($error): ?>
                <div class="text-red-500 mb-4 text-center font-medium"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <div class="mb-4">
                <label for="password" class="block text-gray-700 font-semibold mb-2">Password</label>
                <input type="password" name="password" id="password" class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="w-full py-3 rounded-lg bg-gradient-to-r from-indigo-600 to-purple-600 text-white font-bold text-lg shadow-md hover:from-indigo-700 hover:to-purple-700 transition">Access Vault</button>
        </form>
    </div>
</body>
</html>
