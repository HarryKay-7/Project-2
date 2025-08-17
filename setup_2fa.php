<?php
session_start();
require_once 'admin_config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $redirect = false;
    if ($type === 'pin') {
        $pin = $_POST['pin'] ?? '';
        if (preg_match('/^\d{4,6}$/', $pin)) {
            $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users_acc SET two_factor_pin = ? WHERE id = ?');
            $stmt->execute([$hashed_pin, $user_id]);
            $redirect = true;
        } else {
            $message = 'PIN must be 4-6 digits.';
        }
    } elseif ($type === 'password') {
        $password = $_POST['password'] ?? '';
        if (strlen($password) >= 8) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users_acc SET two_factor_password = ? WHERE id = ?');
            $stmt->execute([$hashed_password, $user_id]);
            $redirect = true;
        } else {
            $message = 'Password must be at least 8 characters.';
        }
    } elseif ($type === 'question') {
        $question = $_POST['question'] ?? '';
        $answer = $_POST['answer'] ?? '';
        if ($question && $answer) {
            $hashed_answer = password_hash(strtolower($answer), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users_acc SET two_factor_question = ?, two_factor_answer = ? WHERE id = ?');
            $stmt->execute([$question, $hashed_answer, $user_id]);
            $redirect = true;
        } else {
            $message = 'Please fill both question and answer.';
        }
    }
    if ($redirect) {
        header('Location: settings.php?success=1&msg=' . urlencode('2FA setup successful!'));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Two-Factor Authentication</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-blue-50 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-lg p-8 w-full max-w-md">
        <h2 class="text-2xl font-bold mb-6 text-center">Setup Two-Factor Authentication</h2>
        <?php if ($message): ?>
            <div class="mb-4 p-3 rounded bg-green-100 text-green-700 text-center font-semibold"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-6">
            <div>
                <label class="block font-semibold mb-2">Choose 2FA Type:</label>
                <select name="type" class="w-full rounded border-gray-300 p-2" required>
                    <option value="">Select type</option>
                    <option value="pin">PIN</option>
                    <option value="password">Password</option>
                    <option value="question">Security Question</option>
                </select>
            </div>
            <div id="pin-fields" class="hidden">
                <label class="block mb-2">Enter PIN (4-6 digits):</label>
                <input type="text" name="pin" maxlength="6" pattern="\d{4,6}" class="w-full rounded border-gray-300 p-2">
            </div>
            <div id="password-fields" class="hidden">
                <label class="block mb-2">Enter Password (min 8 chars):</label>
                <input type="password" name="password" minlength="8" class="w-full rounded border-gray-300 p-2">
            </div>
            <div id="question-fields" class="hidden">
                <label class="block mb-2">Security Question:</label>
                <input type="text" name="question" class="w-full rounded border-gray-300 p-2 mb-2" placeholder="e.g. What is your favorite color?">
                <label class="block mb-2">Answer:</label>
                <input type="text" name="answer" class="w-full rounded border-gray-300 p-2">
            </div>
            <button type="submit" class="w-full py-3 rounded-lg bg-blue-600 text-white font-bold text-lg shadow-md hover:bg-blue-700 transition">Save 2FA Method</button>
        </form>
    </div>
    <script>
        const typeSelect = document.querySelector('select[name="type"]');
        const pinFields = document.getElementById('pin-fields');
        const passwordFields = document.getElementById('password-fields');
        const questionFields = document.getElementById('question-fields');
        typeSelect.addEventListener('change', function() {
            pinFields.style.display = this.value === 'pin' ? 'block' : 'none';
            passwordFields.style.display = this.value === 'password' ? 'block' : 'none';
            questionFields.style.display = this.value === 'question' ? 'block' : 'none';
        });
    </script>
</body>
</html>
