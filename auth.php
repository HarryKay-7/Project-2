<?php

// Database configuration
$host = 'localhost';
$dbname = 'memory chain';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=memory chain;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// User authentication functions
function registerUser($username, $email, $password, $profilePicPath = null) {
    global $pdo;
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users_acc (username, email, password, profile_pic) VALUES (?, ?, ?, ?)");
    try {
        $stmt->execute([$username, $email, $hashedPassword, $profilePicPath]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}


// Use a single loginUser function for all login/signin logic
function loginUser($email, $password) {
    global $pdo;
    try {
        // First, get user data
        $stmt = $pdo->prepare("SELECT id, username, email, password, active_days, last_login FROM users_acc WHERE email = ?");
        $stmt->execute([$email]);
        $users_acc = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($users_acc && password_verify($password, $users_acc['password'])) {
            // Update last login and active days
            $today = date('Y-m-d');
            
            // Explicitly set active_days to 1 if it's NULL or 0, otherwise increment if last login was different day
            $updateStmt = $pdo->prepare("
                UPDATE users_acc 
                SET last_login = ?,
                    active_days = IF(active_days IS NULL OR active_days = 0, 1,
                        active_days + IF(DATE(COALESCE(last_login, '1900-01-01')) < ?, 1, 0)
                    )
                WHERE id = ?
            ");
            
            $updateStmt->execute([$today, $today, $users_acc['id']]);
            
            // Fetch the updated user data
            $stmt = $pdo->prepare("SELECT id, username, email, active_days, last_login FROM users_acc WHERE id = ?");
            $stmt->execute([$users_acc['id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function userExists($email) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users_acc WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetchColumn() > 0;
}

// Handle form submissions
// Only handle registration via POST if not an AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'register')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'register' || !isset($_POST['action'])) {
        $firstName = trim($_POST['firstName'] ?? '');
        $lastName = trim($_POST['lastName'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirmPassword'] ?? '';
        $errors = [];
        if (empty($firstName)) {
            $errors['firstName'] = "First name is required";
        }
        if (empty($lastName)) {
            $errors['lastName'] = "Last name is required";
        }
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Valid email is required";
        } elseif (userExists($email)) {
            $errors['email'] = "Email already registered";
        }
        if(empty($phoneNumber) || !preg_match('/^\d{10}$/', $phoneNumber)) {
            $errors['phoneNumber'] = "Valid phone number is required";
        }
        if (strlen($password) < 8) {
            $errors['password'] = "Password must be at least 8 characters";
        }
        if ($password !== $confirmPassword) {
            $errors['confirmPassword'] = "Passwords do not match";
        }
        if (empty($errors)) {
            $username = $firstName . ' ' . $lastName;
            // Get profile_pic from $_FILES if available
            $profilePicPath = null;
            if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $fileType = mime_content_type($_FILES['profile_pic']['tmp_name']);
                if (in_array($fileType, $allowedTypes)) {
                    $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
                    $newName = uniqid('profile_', true) . '.' . $ext;
                    $uploadDir = 'uploads/profile_pics/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                    $profilePicPath = $uploadDir . $newName;
                    move_uploaded_file($_FILES['profile_pic']['tmp_name'], $profilePicPath);
                }
            }
            if (registerUser($username, $email, $password, $profilePicPath)) {
                session_start();
                $users_acc = loginUser($email, $password);
                $_SESSION['users_acc_id'] = $users_acc['id'];
                $_SESSION['username'] = $users_acc['username'];
                $_SESSION['email'] = $users_acc['email'];
                $_SESSION['profile_pic'] = $profilePicPath;
                header("Location: dashboard.php");
                exit();
            } else {
                $errors['general'] = "Registration failed. Please try again.";
            }
        }
        // If not AJAX, errors will be handled by the frontend
    }
}

// Handle AJAX requests for user_exists and login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    if ($action === 'user_exists') {
        $email = $_POST['email'] ?? '';
        $exists = userExists($email);
        echo json_encode(['exists' => $exists]);
        exit();
    }
    // Accept both 'signin' and 'login' for compatibility with frontend
    if ($action === 'login' || $action === 'signin') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            error_log('Missing email or password in AJAX login');
            echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
            exit();
        }
        $users_acc = loginUser($email, $password);
        if ($users_acc) {
            session_start();
            $_SESSION['user_id'] = $users_acc['id'];
            $_SESSION['username'] = $users_acc['username'];
            $_SESSION['email'] = $users_acc['email'];
            echo json_encode(['success' => true, 'redirect' => 'dashboard.php']);
        } else {
            error_log('Invalid login for email: ' . $email);
            echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
        }
        exit();
    }
}

// Enable error reporting for debugging (should be at the top in production, but here for visibility)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
?>
