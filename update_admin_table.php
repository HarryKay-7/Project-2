<?php
require_once 'admin_config.php';

try {
    // Add role column if it doesn't exist
    $pdo->exec("
        ALTER TABLE admin_users 
        ADD COLUMN IF NOT EXISTS role ENUM('super_admin', 'moderator') NOT NULL DEFAULT 'moderator' AFTER email,
        ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) NULL AFTER role
    ");
    
    echo "Successfully updated admin_users table structure.";
} catch (PDOException $e) {
    die("Error updating database structure: " . $e->getMessage());
}
?>
