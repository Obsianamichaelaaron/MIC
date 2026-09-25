<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only process login if this is NOT a registration request
    // Registration forms have first_name, last_name, or confirm_password fields
    $is_registration = isset($_POST['first_name']) || isset($_POST['confirm_password']);
    
    if (!$is_registration) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = "Please fill in all fields";
        } else {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT user_id, email, password, role, first_name, last_name, status FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'active') {
                    // Set session variables
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    
                    // Check for redirect parameters (from GET or POST)
                    $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '';
                    $job_id = intval($_GET['job_id'] ?? $_POST['job_id'] ?? 0);
                    
                    // Redirect based on role and redirect parameter
                    // Determine base path (since this file can be included from root)
                    $base_path = '/frontend/';
                    switch ($user['role']) {
                        case 'applicant':
                            if ($redirect === 'apply' && $job_id > 0) {
                                header("Location: {$base_path}applicant/apply_job.php?id=$job_id");
                            } else {
                                header("Location: {$base_path}applicant/jobs.php");
                            }
                            break;
                        case 'employer':
                            header("Location: {$base_path}employer/dashboard.php");
                            break;
                        case 'admin':
                            header("Location: {$base_path}admin/dashboard.php");
                            break;
                        default:
                            $error = "Invalid user role";
                    }
                    exit();
                } else {
                    $error = "Your account is " . $user['status'];
                }
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        
        $stmt->close();
     $conn->close();
        }
    }
}
?>

