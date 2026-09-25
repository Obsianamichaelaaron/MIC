<?php
/**
 * Verify Default Users Script
 * This script verifies that default users exist and passwords work
 */

require_once __DIR__ . '/../includes/config/database.php';

$users_to_check = [
    ['email' => 'admin@gmail.com', 'password' => 'admin123', 'role' => 'admin'],
    ['email' => 'jobseeker@gmail.com', 'password' => 'jobseeker123', 'role' => 'applicant'],
    ['email' => 'employer@gmail.com', 'password' => 'employer123', 'role' => 'employer']
];

try {
    $conn = getDBConnection();
    
    echo "Verifying default users...\n\n";
    
    foreach ($users_to_check as $check_user) {
        $email = $check_user['email'];
        $expected_password = $check_user['password'];
        $expected_role = $check_user['role'];
        
        $stmt = $conn->prepare("SELECT user_id, email, password, role, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $password_valid = password_verify($expected_password, $user['password']);
            $role_valid = ($user['role'] === $expected_role);
            $status_valid = ($user['status'] === 'active');
            
            echo "User: {$email}\n";
            echo "  - Exists: ✓\n";
            echo "  - Password verification: " . ($password_valid ? "✓ PASS" : "✗ FAIL") . "\n";
            echo "  - Role ({$expected_role}): " . ($role_valid ? "✓ PASS" : "✗ FAIL (found: {$user['role']})") . "\n";
            echo "  - Status (active): " . ($status_valid ? "✓ PASS" : "✗ FAIL (found: {$user['status']})") . "\n";
            
            // Check role-specific records
            if ($expected_role === 'applicant') {
                $stmt2 = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
                $stmt2->bind_param("i", $user['user_id']);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                echo "  - Applicant record: " . ($result2->num_rows > 0 ? "✓ EXISTS" : "✗ MISSING") . "\n";
                $stmt2->close();
            } elseif ($expected_role === 'employer') {
                $stmt2 = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
                $stmt2->bind_param("i", $user['user_id']);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                echo "  - Employer record: " . ($result2->num_rows > 0 ? "✓ EXISTS" : "✗ MISSING") . "\n";
                $stmt2->close();
            }
            
            if (!$password_valid || !$role_valid || !$status_valid) {
                echo "  ⚠ ISSUES FOUND - Run setup_default_users.php to fix\n";
            }
            
            echo "\n";
        } else {
            echo "User: {$email}\n";
            echo "  ✗ NOT FOUND - Run setup_default_users.php to create\n\n";
        }
        
        $stmt->close();
    }
    
    closeDBConnection($conn);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

