<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Only process registration if this IS a registration request
    // Registration forms have first_name, last_name, or confirm_password fields
    $is_registration = isset($_POST['first_name']) || isset($_POST['confirm_password']);
    
    if ($is_registration) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $first_name = ucfirst(strtolower(trim($_POST['first_name'] ?? '')));
        $last_name = ucfirst(strtolower(trim($_POST['last_name'] ?? '')));
        $phone = trim($_POST['phone'] ?? '');
        $qualification_id = intval($_POST['qualification_id'] ?? 0);
        
        // Registration is only for applicants - employers must be created by admin
        $role = 'applicant';
        
        // Handle file upload
        $resume_file = null;
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['resume'];
            $file_name = $file['name'];
            $file_tmp = $file['tmp_name'];
            $file_size = $file['size'];
            $file_type = $file['type'];
            
            // Check if file is PDF
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if ($file_ext !== 'pdf') {
                $error = "Only PDF files are allowed for resume upload";
            } elseif ($file_size > 5242880) { // 5MB limit
                $error = "File size must be less than 5MB";
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = __DIR__ . '/../../uploads/resumes/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $new_filename = uniqid('resume_', true) . '_' . time() . '.pdf';
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $upload_path)) {
                    $resume_file = 'uploads/resumes/' . $new_filename;
                } else {
                    $error = "Failed to upload resume file";
                }
            }
        }
        
        if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            $error = "Please fill in all required fields";
        } elseif ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters";
        } elseif (!isset($error)) {
            $conn = getDBConnection();
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Email already registered";
            } else {
                // Hash password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user (self-registered, not by admin)
                $created_by_admin = 0; // FALSE - self-registered
                $stmt = $conn->prepare("INSERT INTO users (email, password, role, first_name, last_name, phone, created_by_admin) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $email, $hashed_password, $role, $first_name, $last_name, $phone, $created_by_admin);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    
                    // Get qualification name if selected
                    $qualification_name = null;
                    if ($qualification_id > 0) {
                        $qual_stmt = $conn->prepare("SELECT name FROM qualifications WHERE qualification_id = ? AND status = 'active'");
                        $qual_stmt->bind_param("i", $qualification_id);
                        $qual_stmt->execute();
                        $qual_result = $qual_stmt->get_result();
                        if ($qual_result->num_rows > 0) {
                            $qualification_name = $qual_result->fetch_assoc()['name'];
                        }
                        $qual_stmt->close();
                    }
                    
                    // Create applicant record with resume and qualification
                    if ($resume_file || $qualification_name) {
                        $stmt2 = $conn->prepare("INSERT INTO applicants (user_id, resume_file, qualifications) VALUES (?, ?, ?)");
                        $stmt2->bind_param("iss", $user_id, $resume_file, $qualification_name);
                    } else {
                        $stmt2 = $conn->prepare("INSERT INTO applicants (user_id) VALUES (?)");
                        $stmt2->bind_param("i", $user_id);
                    }
                    $stmt2->execute();
                    $stmt2->close();
                    
                    $success = "Registration successful! Please login.";
                } else {
                    $error = "Registration failed. Please try again.";
                }
            }
            
            $stmt->close();
            $conn->close();
        }
    }
}
?>

