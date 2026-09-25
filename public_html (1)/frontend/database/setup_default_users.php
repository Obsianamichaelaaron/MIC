<?php
/**
 * Setup Default Users Script
 * This script inserts or updates the default users in the database
 * Run this script once to set up default accounts
 */

require_once __DIR__ . '/../includes/config/database.php';

// Default users configuration
$default_users = [
    [
        'email' => 'admin@gmail.com',
        'password' => 'admin123',
        'role' => 'admin',
        'first_name' => 'Admin',
        'last_name' => 'User'
    ],
    [
        'email' => 'jobseeker@gmail.com',
        'password' => 'jobseeker123',
        'role' => 'applicant',
        'first_name' => 'Job',
        'last_name' => 'Seeker'
    ],
    [
        'email' => 'employer@gmail.com',
        'password' => 'employer123',
        'role' => 'employer',
        'first_name' => 'Employer',
        'last_name' => 'User'
    ]
];

try {
    $conn = getDBConnection();
    
    echo "Setting up default users...\n\n";
    
    foreach ($default_users as $user_data) {
        $email = $user_data['email'];
        $password = $user_data['password'];
        $role = $user_data['role'];
        $first_name = $user_data['first_name'];
        $last_name = $user_data['last_name'];
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Update existing user
            $existing_user = $result->fetch_assoc();
            $user_id = $existing_user['user_id'];
            
            $stmt = $conn->prepare("UPDATE users SET password = ?, first_name = ?, last_name = ?, status = 'active' WHERE email = ?");
            $stmt->bind_param("ssss", $hashed_password, $first_name, $last_name, $email);
            
            if ($stmt->execute()) {
                echo "✓ Updated user: {$email}\n";
            } else {
                echo "✗ Failed to update user: {$email} - " . $conn->error . "\n";
            }
            $stmt->close();
        } else {
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (email, password, role, first_name, last_name, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->bind_param("sssss", $email, $hashed_password, $role, $first_name, $last_name);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                echo "✓ Created user: {$email} (ID: {$user_id})\n";
            } else {
                echo "✗ Failed to create user: {$email} - " . $conn->error . "\n";
                continue;
            }
            $stmt->close();
        }
        
        // Create role-specific records
        if ($role === 'applicant') {
            $stmt = $conn->prepare("INSERT IGNORE INTO applicants (user_id) VALUES (?)");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        } elseif ($role === 'employer') {
            $stmt = $conn->prepare("INSERT IGNORE INTO employers (user_id) VALUES (?)");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    echo "\n✓ Default users setup completed!\n";
    echo "\nLogin credentials:\n";
    echo "Admin: admin@gmail.com / admin123\n";
    echo "Jobseeker: jobseeker@gmail.com / jobseeker123\n";
    echo "Employer: employer@gmail.com / employer123\n";
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

