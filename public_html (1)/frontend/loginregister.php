<?php
require_once 'includes/config/session.php';
require_once 'includes/auth/login.php';
require_once 'includes/auth/register.php';

// Handle redirect after login
$redirect = $_GET['redirect'] ?? '';
$job_id = $_GET['job_id'] ?? 0;

// Check for session-expired flash (set by logout())
$session_expired = false;
if (isset($_SESSION['session_expired'])) {
    $session_expired = true;
    unset($_SESSION['session_expired']);
}

// If already logged in, redirect to appropriate dashboard or job application
if (isLoggedIn()) {
    $role = getUserRole();
    
    // If redirecting to apply for a job
    if ($redirect === 'apply' && $job_id > 0 && $role === 'applicant') {
        header("Location: applicant/apply_job.php?id=$job_id");
        exit();
    }
    
    // Otherwise go to dashboard
    header("Location: $role/dashboard.php");
    exit();
}

// Enhanced email validation function
function validateEmail($email) {
    $email = trim($email);
    
    // Check if email is empty
    if (empty($email)) {
        return "Email is required";
    }
    
    // Check email length
    if (strlen($email) > 100) {
        return "Email must be less than 100 characters";
    }
    
    // Basic format validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Please enter a valid email address";
    }
    
    // Extract domain part
    $email_parts = explode('@', $email);
    if (count($email_parts) !== 2) {
        return "Invalid email format";
    }
    
    $domain = $email_parts[1];
    
    // Check for valid domain structure
    if (!preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain)) {
        return "Invalid domain in email address";
    }
    
    // Check for consecutive dots in domain
    if (strpos($domain, '..') !== false) {
        return "Invalid domain in email address";
    }
    
    // Check for valid local part (before @)
    $local_part = $email_parts[0];
    if (empty($local_part)) {
        return "Invalid email format";
    }
    
    // Check for common invalid patterns
    $invalid_patterns = [
        '/^\./',  // Starts with dot
        '/\.$/',  // Ends with dot
        '/\.\./', // Contains consecutive dots
        '/\s/',   // Contains spaces
    ];
    
    foreach ($invalid_patterns as $pattern) {
        if (preg_match($pattern, $local_part)) {
            return "Invalid email format";
        }
    }
    
    // Check for obviously fake emails
    $obviously_fake_patterns = [
        '/([a-zA-Z]{10,})/', // Long random strings without proper structure
        '/^[^a-zA-Z0-9]/',   // Starts with non-alphanumeric
        '/[^\x00-\x7F]/',    // Contains non-ASCII characters
    ];
    
    foreach ($obviously_fake_patterns as $pattern) {
        if (preg_match($pattern, $local_part)) {
            return "Please enter a valid email address";
        }
    }
    
    // Common TLDs
    $tlds = [
        'com', 'org', 'net', 'edu', 'gov', 'mil', 'int',
        'biz', 'info', 'name', 'pro', 'aero', 'coop', 'museum',
        'ac', 'ad', 'ae', 'af', 'ag', 'ai', 'al', 'am', 'ao', 'aq', 'ar', 'as', 'at', 'au', 'aw', 'ax', 'az',
        'ba', 'bb', 'bd', 'be', 'bf', 'bg', 'bh', 'bi', 'bj', 'bm', 'bn', 'bo', 'br', 'bs', 'bt', 'bv', 'bw', 'by', 'bz',
        'ca', 'cc', 'cd', 'cf', 'cg', 'ch', 'ci', 'ck', 'cl', 'cm', 'cn', 'co', 'cr', 'cu', 'cv', 'cw', 'cx', 'cy', 'cz',
        'de', 'dj', 'dk', 'dm', 'do', 'dz',
        'ec', 'ee', 'eg', 'er', 'es', 'et', 'eu',
        'fi', 'fj', 'fk', 'fm', 'fo', 'fr',
        'ga', 'gb', 'gd', 'ge', 'gf', 'gg', 'gh', 'gi', 'gl', 'gm', 'gn', 'gp', 'gq', 'gr', 'gs', 'gt', 'gu', 'gw', 'gy',
        'hk', 'hm', 'hn', 'hr', 'ht', 'hu',
        'id', 'ie', 'il', 'im', 'in', 'io', 'iq', 'ir', 'is', 'it',
        'je', 'jm', 'jo', 'jp',
        'ke', 'kg', 'kh', 'ki', 'km', 'kn', 'kp', 'kr', 'kw', 'ky', 'kz',
        'la', 'lb', 'lc', 'li', 'lk', 'lr', 'ls', 'lt', 'lu', 'lv', 'ly',
        'ma', 'mc', 'md', 'me', 'mg', 'mh', 'mk', 'ml', 'mm', 'mn', 'mo', 'mp', 'mq', 'mr', 'ms', 'mt', 'mu', 'mv', 'mw', 'mx', 'my', 'mz',
        'na', 'nc', 'ne', 'nf', 'ng', 'ni', 'nl', 'no', 'np', 'nr', 'nu', 'nz',
        'om',
        'pa', 'pe', 'pf', 'pg', 'ph', 'pk', 'pl', 'pm', 'pn', 'pr', 'ps', 'pt', 'pw', 'py',
        'qa',
        're', 'ro', 'rs', 'ru', 'rw',
        'sa', 'sb', 'sc', 'sd', 'se', 'sg', 'sh', 'si', 'sj', 'sk', 'sl', 'sm', 'sn', 'so', 'sr', 'ss', 'st', 'su', 'sv', 'sx', 'sy', 'sz',
        'tc', 'td', 'tf', 'tg', 'th', 'tj', 'tk', 'tl', 'tm', 'tn', 'to', 'tr', 'tt', 'tv', 'tw', 'tz',
        'ua', 'ug', 'uk', 'us', 'uy', 'uz',
        'va', 'vc', 've', 'vg', 'vi', 'vn', 'vu',
        'wf', 'ws',
        'ye', 'yt',
        'za', 'zm', 'zw'
    ];
    
    $domain_parts = explode('.', $domain);
    $tld = strtolower(end($domain_parts));
    
    if (!in_array($tld, $tlds)) {
        return "Invalid domain extension in email address";
    }
    
    return true;
}

function validateName($name) {
    $name = trim($name);
    if (empty($name)) {
        return "Name is required";
    }
    if (strlen($name) < 2) {
        return "Name must be at least 2 characters long";
    }
    if (strlen($name) > 50) {
        return "Name must be less than 50 characters";
    }
    if (!preg_match("/^[a-zA-Z\s\-']+$/", $name)) {
        return "Name can only contain letters, spaces, hyphens, and apostrophes";
    }
    return true;
}

function validatePhone($phone) {
    $phone = trim($phone);
    if (empty($phone)) {
        return true; // Phone is optional
    }
    
    // Remove all non-numeric characters
    $cleaned_phone = preg_replace('/\D/', '', $phone);
    
    // Check for valid length
    if (strlen($cleaned_phone) < 10 || strlen($cleaned_phone) > 15) {
        return "Phone number must be 10-15 digits";
    }
    
    // Check if it's all numbers
    if (!preg_match('/^[0-9]+$/', $cleaned_phone)) {
        return "Phone number can only contain numbers";
    }
    
    return true;
}

function validatePassword($password) {
    if (empty($password)) {
        return "Password is required";
    }
    if (strlen($password) < 6) {
        return "Password must be at least 6 characters long";
    }
    if (strlen($password) > 255) {
        return "Password must be less than 255 characters";
    }
    // Check for common weak passwords
    $weak_passwords = ['123456', 'password', '12345678', 'qwerty', 'abc123'];
    if (in_array(strtolower($password), $weak_passwords)) {
        return "Please choose a stronger password";
    }
    return true;
}

function validateResume($file) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return true; // Resume is optional
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return "File size too large. Maximum size is 5MB";
            case UPLOAD_ERR_PARTIAL:
                return "File upload was incomplete";
            case UPLOAD_ERR_NO_TMP_DIR:
                return "Server configuration error";
            case UPLOAD_ERR_CANT_WRITE:
                return "Failed to write file to disk";
            case UPLOAD_ERR_EXTENSION:
                return "File upload stopped by extension";
            default:
                return "File upload error occurred";
        }
    }
    
    // Check file size (5MB limit)
    if ($file['size'] > 5 * 1024 * 1024) {
        return "File size must be less than 5MB";
    }
    
    // Check file type
    $allowed_types = ['application/pdf'];
    $file_type = mime_content_type($file['tmp_name']);
    
    if (!in_array($file_type, $allowed_types)) {
        return "Only PDF files are allowed for resumes";
    }
    
    // Check file extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($file_ext !== 'pdf') {
        return "Only PDF files are allowed";
    }
    
    // Check for potential malicious files
    if (preg_match('/\.(php|phtml|exe|bat|cmd|sh)$/i', $file['name'])) {
        return "Invalid file type";
    }
    
    return true;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && !isset($_POST['first_name'])) {
    // This is a login attempt
    require_once 'includes/config/database.php';
    $conn = getDBConnection();
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Basic validation
    $login_errors = [];
    
    if (empty($email)) {
        $login_errors['email'] = "Email is required";
    } else {
        $email_validation = validateEmail($email);
        if ($email_validation !== true) {
            $login_errors['email'] = $email_validation;
        }
    }
    
    if (empty($password)) {
        $login_errors['password'] = "Password is required";
    }
    
    if (empty($login_errors)) {
        // Check if user exists and verify password
        $stmt = $conn->prepare("SELECT user_id, first_name, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                
                // Handle redirect
                if ($redirect === 'apply' && $job_id > 0 && $user['role'] === 'applicant') {
                    header("Location: applicant/apply_job.php?id=$job_id");
                    exit();
                }
                
                header("Location: {$user['role']}/dashboard.php");
                exit();
            } else {
                // Password doesn't match
                $login_errors['password'] = "Invalid email or password";
                $_SESSION['login_errors'] = $login_errors;
                $_SESSION['login_email'] = $email;
            }
        } else {
            // User not found
            $login_errors['email'] = "Invalid email or password";
            $_SESSION['login_errors'] = $login_errors;
            $_SESSION['login_email'] = $email;
        }
        $stmt->close();
    } else {
        $_SESSION['login_errors'] = $login_errors;
        $_SESSION['login_email'] = $email;
    }
    
    $conn->close();
}

// Handle registration with resume extraction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email']) && isset($_POST['password']) && isset($_POST['first_name'])) {
    // Process registration first
    require_once 'includes/config/database.php';
    $conn = getDBConnection();
    
    // Sanitize inputs
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    $qualification_id = intval($_POST['qualification_id'] ?? 0);
    
    // Validate inputs
    $errors = [];
    
    // Validate names
    $first_name_validation = validateName($first_name);
    if ($first_name_validation !== true) {
        $errors['first_name'] = $first_name_validation;
    }
    
    $last_name_validation = validateName($last_name);
    if ($last_name_validation !== true) {
        $errors['last_name'] = $last_name_validation;
    }
    
    // Validate email - using the enhanced validation
    $email_validation = validateEmail($email);
    if ($email_validation !== true) {
        $errors['email'] = $email_validation;
    }
    
    // Validate phone
    $phone_validation = validatePhone($phone);
    if ($phone_validation !== true) {
        $errors['phone'] = $phone_validation;
    }
    
    // Validate password
    $password_validation = validatePassword($password);
    if ($password_validation !== true) {
        $errors['password'] = $password_validation;
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match";
    }
    
    // Validate resume if uploaded
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
        $resume_validation = validateResume($_FILES['resume']);
        if ($resume_validation !== true) {
            $errors['resume'] = $resume_validation;
        }
    }
    
    // Validate qualification if provided
    if ($qualification_id > 0) {
        $qual_stmt = $conn->prepare("SELECT qualification_id FROM qualifications WHERE qualification_id = ? AND status = 'active'");
        $qual_stmt->bind_param("i", $qualification_id);
        $qual_stmt->execute();
        $qual_result = $qual_stmt->get_result();
        if ($qual_result->num_rows === 0) {
            $errors['qualification_id'] = "Invalid qualification selected";
        }
        $qual_stmt->close();
    }
    
    // If no validation errors, proceed with registration
    if (empty($errors)) {
        // --- OTP / Email Verification Gate ---
        // The user must have verified their email via OTP before the account is created.
        $otp_session = $_SESSION['email_otp'] ?? null;
        if (
            !$otp_session ||
            empty($_SESSION['otp_verified_email']) ||
            $_SESSION['otp_verified_email'] !== $email ||
            $otp_session['verified'] !== true ||
            $otp_session['email'] !== $email
        ) {
            $errors['email'] = 'Please verify your email address before completing registration.';
            $_SESSION['validation_errors'] = $errors;
            $_SESSION['form_values'] = [
                'first_name'       => $first_name,
                'last_name'        => $last_name,
                'email'            => $email,
                'phone'            => $phone,
                'qualification_id' => $qualification_id,
            ];
            $_SESSION['error'] = 'Email verification required. Please verify your email address.';
            header('Location: ' . $_SERVER['PHP_SELF'] . '?tab=register');
            exit;
        }
        // Clear OTP session data after successful gate
        unset($_SESSION['email_otp'], $_SESSION['otp_verified_email']);
        // --- End OTP Gate ---

        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $_SESSION['error'] = "Email already registered. Please login or use a different email.";
            $_SESSION['form_values'] = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'qualification_id' => $qualification_id
            ];
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
           $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password, role, email_verified) VALUES (?, ?, ?, ?, ?, 'applicant', 1)");
$stmt->bind_param("sssss", $first_name, $last_name, $email, $phone, $hashed_password);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                
                // Create applicant record
                $qualification_name = '';
                if ($qualification_id > 0) {
                    $qual_stmt = $conn->prepare("SELECT name FROM qualifications WHERE qualification_id = ?");
                    $qual_stmt->bind_param("i", $qualification_id);
                    $qual_stmt->execute();
                    $qual_result = $qual_stmt->get_result()->fetch_assoc();
                    if ($qual_result) {
                        $qualification_name = $qual_result['name'];
                    }
                    $qual_stmt->close();
                }
                
                $stmt = $conn->prepare("INSERT INTO applicants (user_id, qualifications) VALUES (?, ?)");
                $stmt->bind_param("is", $user_id, $qualification_name);
                $stmt->execute();
                $applicant_id = $stmt->insert_id;
                $stmt->close();
                
                // Handle resume upload and skill extraction
                $extracted_skills = [];
                $resume_file_path = null;
                
                if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['resume'];
                    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    
                    if ($file_ext === 'pdf') {
                        $upload_dir = __DIR__ . '/uploads/resumes/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $new_filename = uniqid('resume_', true) . '_' . time() . '.pdf';
                        $upload_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            $resume_file_path = 'uploads/resumes/' . $new_filename;
                            
                            // Extract skills from the uploaded resume
                            $applicant_name = $first_name . ' ' . $last_name;
                            $extracted_skills = extractSkillsFromResume($upload_path, $applicant_name);
                            
                            if (!empty($extracted_skills)) {
                                // Store extracted skills in session to pass to profile
                                $_SESSION['extracted_skills'] = $extracted_skills;
                                $_SESSION['new_resume_path'] = $resume_file_path;
                                
                                // Update applicant record with resume file and skills
                                $skills_string = implode(', ', $extracted_skills);
                                $stmt = $conn->prepare("UPDATE applicants SET resume_file = ?, skills = ? WHERE user_id = ?");
                                $stmt->bind_param("ssi", $resume_file_path, $skills_string, $user_id);
                                $stmt->execute();
                                $stmt->close();
                                
                                $_SESSION['success'] = "Registration successful! " . count($extracted_skills) . " skills extracted from your resume.";
                            } else {
                                // Still update with resume file but no skills
                                $stmt = $conn->prepare("UPDATE applicants SET resume_file = ? WHERE user_id = ?");
                                $stmt->bind_param("si", $resume_file_path, $user_id);
                                $stmt->execute();
                                $stmt->close();
                                
                                $_SESSION['success'] = "Registration successful! Resume uploaded but no skills could be automatically extracted.";
                            }
                        }
                    }
                }
                
                if (empty($extracted_skills)) {
                    $_SESSION['success'] = "Registration successful! Complete your profile to add skills.";
                }
                
                // Store qualification in chatbot_answers if provided
                if ($qualification_id > 0 && $applicant_id) {
                    $stmt = $conn->prepare("INSERT INTO chatbot_answers (applicant_id, qualification_id, created_at) VALUES (?, ?, NOW())");
                    $stmt->bind_param("ii", $applicant_id, $qualification_id);
                    $stmt->execute();
                    $stmt->close();
                }
                
                // Auto-login after registration
                $_SESSION['user_id'] = $user_id;
                $_SESSION['role'] = 'applicant';
                $_SESSION['first_name'] = $first_name;
                
                // Redirect to profile
                header("Location: applicant/Profile.php");
                exit();
            } else {
                $_SESSION['error'] = "Registration failed. Please try again.";
                $_SESSION['form_values'] = [
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'email' => $email,
                    'phone' => $phone,
                    'qualification_id' => $qualification_id
                ];
            }
        }
        $stmt->close();
    } else {
        // Store validation errors in session
        $_SESSION['validation_errors'] = $errors;
        // Store form values for repopulation
        $_SESSION['form_values'] = [
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'phone' => $phone,
            'qualification_id' => $qualification_id
        ];
        $_SESSION['error'] = "Please correct the errors below";
    }
    $conn->close();
}

$pageTitle = "Login / Register - MULTIBIZ INTERNATIONAL CORPORATION";

// Get login errors and email from session
$login_errors = $_SESSION['login_errors'] ?? [];
$login_email = $_SESSION['login_email'] ?? '';
unset($_SESSION['login_errors']);
unset($_SESSION['login_email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo $pageTitle; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="author" content="">
    <meta name="keywords" content="MULTIBIZ INTERNATIONAL CORPORATION, Login, Register, Career Platform">
    <meta name="description" content="Login or register to MULTIBIZ INTERNATIONAL CORPORATION - Your trusted career matching platform">
    
    <link rel="shortcut icon" href="2024/favicon.png" type="image/x-icon">
    <link rel="apple-touch-icon" href="2024/favicon.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================================
           DESIGN SYSTEM — LUXURY CORPORATE (synced with services.php)
        ============================================================ */
        :root {
            --navy:        #0a1628;
            --navy-mid:    #0f2040;
            --blue:        #1a4fa0;
            --blue-light:  #2563c8;
            --gold:        #b8973a;
            --gold-light:  #d4af55;
            --cream:       #f7f5f0;
            --warm-white:  #fafaf8;
            --gray-100:    #f0eff0;
            --gray-200:    #e4e2e8;
            --gray-500:    #8a8691;
            --gray-700:    #4a4752;
            --dark:        #18151f;
            
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body:    'DM Sans', -apple-system, sans-serif;
            
            --shadow-sm: 0 2px 12px rgba(10,22,40,0.07);
            --shadow-md: 0 8px 32px rgba(10,22,40,0.11);
            --shadow-lg: 0 20px 56px rgba(10,22,40,0.16);
            --shadow-xl: 0 32px 80px rgba(10,22,40,0.22);
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-xl: 32px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: var(--font-body);
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            pointer-events: none;
        }
        
        .auth-container {
            background: var(--warm-white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            z-index: 1;
        }
        
        /* Left Panel */
        .auth-left {
            background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
            color: white;
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            position: relative;
            border-bottom: 1px solid rgba(184,151,58,0.2);
        }
        
        .auth-left::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
        }
        
        .home-link {
            position: absolute;
            top: 1.5rem;
            left: 1.5rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 100px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(184,151,58,0.2);
        }
        
        .home-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
            border-color: var(--gold-light);
            transform: translateY(-2px);
        }
        
        .auth-left img {
            max-width: 160px;
            margin-bottom: 2rem;
            filter: brightness(1.05);
        }
        
        .auth-left h1 {
            font-family: var(--font-display);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            line-height: 1.2;
        }
        
        .auth-left h1 em {
            font-style: italic;
            color: var(--gold-light);
        }
        
        .auth-left p {
            font-size: 1rem;
            opacity: 0.8;
            max-width: 280px;
            margin: 0 auto;
        }
        
        .auth-features {
            margin-top: 2.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
            max-width: 280px;
        }
        
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            background: rgba(255,255,255,0.05);
            border-radius: 100px;
            border: 1px solid rgba(184,151,58,0.2);
            backdrop-filter: blur(10px);
        }
        
        .feature-item i {
            color: var(--gold-light);
            font-size: 1rem;
            width: 20px;
        }
        
        .feature-item span {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* Right Panel */
        .auth-right {
            padding: 2.5rem 2rem;
        }
        
        .tab-container {
            display: flex;
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--gray-200);
            gap: 2rem;
        }
        
        .tab {
            padding: 0.75rem 0;
            text-align: center;
            cursor: pointer;
            border: none;
            background: none;
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-500);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .tab::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            transform: scaleX(0);
            transition: transform 0.3s var(--ease-out);
        }
        
        .tab.active {
            color: var(--navy);
        }
        
        .tab.active::after {
            transform: scaleX(1);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s var(--ease-out);
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--navy);
            font-weight: 500;
            font-size: 0.9rem;
            letter-spacing: 0.02em;
        }
        
        .form-group label i {
            color: var(--blue);
            margin-right: 0.5rem;
            width: 16px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.9rem 1.2rem;
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-family: var(--font-body);
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(26,79,160,0.1);
        }
        
        .form-group input.error,
        .form-group select.error {
            border-color: #dc3545;
            background: #fff8f8;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 0.35rem;
            display: block;
            padding-left: 0.5rem;
        }
        
        .btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            color: white;
            border: none;
            border-radius: 100px;
            font-family: var(--font-body);
            font-size: 1rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.3s var(--ease-out);
            box-shadow: 0 6px 20px rgba(10,22,40,0.2);
            margin-top: 1rem;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 28px rgba(10,22,40,0.35);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        /* ── Toast Notifications ── */
        #toast-container {
            position: fixed;
            top: 1.25rem;
            right: 1.25rem;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
            pointer-events: none;
        }
        .toast {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.9rem 1.1rem;
            border-radius: 12px;
            min-width: 280px;
            max-width: 360px;
            font-size: 0.88rem;
            font-weight: 500;
            line-height: 1.4;
            box-shadow: 0 8px 28px rgba(0,0,0,0.15);
            pointer-events: all;
            animation: toastIn 0.35s cubic-bezier(0.16,1,0.3,1) forwards;
            position: relative;
            overflow: hidden;
        }
        .toast.hiding { animation: toastOut 0.3s ease forwards; }
        @keyframes toastIn {
            from { opacity:0; transform:translateX(60px) scale(0.95); }
            to   { opacity:1; transform:translateX(0)    scale(1); }
        }
        @keyframes toastOut {
            from { opacity:1; transform:translateX(0)    scale(1);    max-height:100px; }
            to   { opacity:0; transform:translateX(60px) scale(0.95); max-height:0; padding:0; margin:0; }
        }
        .toast::after {
            content:''; position:absolute; bottom:0; left:0;
            height:3px; border-radius:0 0 12px 12px;
            animation: toastProgress linear forwards;
        }
        .toast-error   { background:#fff; color:#b91c1c; border:1px solid #fecaca; }
        .toast-error   .toast-icon { color:#ef4444; }
        .toast-error::after   { background:#ef4444; }
        .toast-success { background:#fff; color:#166534; border:1px solid #bbf7d0; }
        .toast-success .toast-icon { color:#22c55e; }
        .toast-success::after { background:#22c55e; }
        .toast-info    { background:#fff; color:#1e40af; border:1px solid #bfdbfe; }
        .toast-info    .toast-icon { color:#3b82f6; }
        .toast-info::after    { background:#3b82f6; }
        @keyframes toastProgress { from{width:100%} to{width:0%} }
        .toast-icon  { font-size:1rem; margin-top:1px; flex-shrink:0; }
        .toast-body  { flex:1; }
        .toast-title { font-weight:700; font-size:0.75rem; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:2px; opacity:0.6; }
        .toast-close { background:none; border:none; cursor:pointer; opacity:0.4; font-size:1rem; line-height:1; padding:0; flex-shrink:0; color:inherit; transition:opacity 0.2s; }
        .toast-close:hover { opacity:0.8; }

        /* Inline alert — only used for session-expired banner */
        .alert {
            padding: 0.8rem 1rem;
            border-radius: var(--radius-md);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            border-left: 3px solid;
        }
        .alert i { font-size: 1rem; }
        .alert-error   { background:#fff5f5; color:#b91c1c; border-left-color:#ef4444; }
        .alert-success { background:#f0fdf4; color:#166534; border-left-color:#22c55e; }
        
        small {
            display: block;
            color: var(--gray-500);
            margin-top: 0.35rem;
            font-size: 0.8rem;
            padding-left: 0.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        /* Desktop Styles */
        @media (min-width: 768px) {
            .auth-container {
                flex-direction: row;
            }
            
            .auth-left {
                flex: 1;
                padding: 3.5rem 2rem;
                border-bottom: none;
                border-right: 1px solid rgba(184,151,58,0.2);
            }
            
            .auth-left::after {
                width: 1px;
                height: 100%;
                right: 0;
                left: auto;
                top: 0;
                background: linear-gradient(180deg, transparent, var(--gold), transparent);
            }
            
            .auth-right {
                flex: 1;
                padding: 3.5rem 3rem;
            }
            
            .auth-left img {
                max-width: 200px;
            }
            
            .auth-left h1 {
                font-size: 2.2rem;
            }
        }
        
        /* Mobile-specific adjustments */
        @media (max-width: 480px) {
            body {
                padding: 0.5rem;
            }
            
            .auth-left {
                padding: 2rem 1.5rem;
            }
            
            .auth-left img {
                max-width: 120px;
            }
            
            .auth-left h1 {
                font-size: 1.5rem;
            }
            
            .auth-right {
                padding: 1.5rem 1.25rem;
            }
            
            .home-link {
                top: 1rem;
                left: 1rem;
                padding: 0.4rem 0.8rem;
                font-size: 0.8rem;
            }
            
            .tab {
                font-size: 1rem;
            }
            
            .tab-container {
                gap: 1rem;
                margin-bottom: 1.5rem;
            }
            
            .form-group {
                margin-bottom: 1.25rem;
            }
            
            .form-group input,
            .form-group select {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
            
            .btn {
                padding: 0.85rem;
                font-size: 0.95rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .auth-features {
                margin-top: 1.5rem;
            }
            
            .feature-item {
                padding: 0.6rem 1rem;
            }
        }
    </style>
</head>
<body>
<div id="toast-container"></div>

<script>
    // Replace the current history entry so pressing Back after logout
    // cannot return to a cached protected page.
    history.replaceState(null, '', window.location.href);
    window.onpopstate = function() {
        window.location.replace(window.location.href);
    };

    // ── Toast system ──────────────────────────────────────────────
    function showToast(message, type, duration) {
        type     = type     || 'info';
        duration = duration || 4500;
        const container = document.getElementById('toast-container');
        const icons = { error:'fa-circle-exclamation', success:'fa-circle-check', info:'fa-circle-info' };
        const titles = { error:'Error', success:'Success', info:'Notice' };

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.style.setProperty('--dur', duration + 'ms');
        toast.innerHTML =
            '<i class="fa-solid ' + icons[type] + ' toast-icon"></i>' +
            '<div class="toast-body">' +
                '<div class="toast-title">' + titles[type] + '</div>' +
                '<div class="toast-msg">' + message + '</div>' +
            '</div>' +
            '<button class="toast-close" onclick="dismissToast(this.parentElement)">&times;</button>';

        // Progress bar duration
        toast.querySelector('::after');
        toast.style.cssText += '; --dur:' + duration + 'ms';
        const style = toast.querySelector('.toast-close');
        // Apply animation duration to ::after via style tag trick
        toast.setAttribute('data-dur', duration);

        container.appendChild(toast);

        // Inject per-toast progress duration
        const sheet = document.createElement('style');
        const id = 'toast-' + Date.now();
        toast.id = id;
        sheet.textContent = '#' + id + '::after { animation-duration: ' + duration + 'ms; }';
        document.head.appendChild(sheet);

        setTimeout(() => dismissToast(toast), duration);
    }

    function dismissToast(toast) {
        if (!toast || toast.classList.contains('hiding')) return;
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 320);
    }
</script>
    <div class="auth-container">
        <!-- Left Panel - Branding -->
        <div class="auth-left">
            <a href="index.php" class="home-link">
                <i class="fas fa-home"></i> Home
            </a>
            <img src="images/mbLogo.png" alt="MULTIBIZ INTERNATIONAL CORPORATION">
            <h1>Welcome <em>Back</em></h1>
            <p>Your trusted career matching platform</p>
            
            <div class="auth-features">
                <div class="feature-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Access job opportunities</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Track your applications</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-check-circle"></i>
                    <span>Get matched with employers</span>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Forms -->
        <div class="auth-right">
            <div class="tab-container">
                <button class="tab active" onclick="switchTab('login')">Login</button>
                <button class="tab" onclick="switchTab('register')">Register</button>
            </div>
            
            <!-- Login Form -->
            <div id="login-tab" class="tab-content active">
                <?php if ($session_expired): ?>
                    <div class="alert alert-error" id="session-expired-alert">
                        <i class="fas fa-clock"></i>
                        Your session has expired. Please log in again.
                    </div>
                <?php endif; ?>
                <?php
                $toast_error   = $_SESSION['error']   ?? null; unset($_SESSION['error']);
                $toast_success = $_SESSION['success'] ?? null; unset($_SESSION['success']);
                ?>
                
                <form method="POST" action="">
                    <?php if ($redirect === 'apply' && $job_id > 0): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                        <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($login_email); ?>" 
                               placeholder="Enter your email" required 
                               class="<?php echo isset($login_errors['email']) ? 'error' : ''; ?>">
                        <?php if (isset($login_errors['email'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($login_errors['email']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required 
                               class="<?php echo isset($login_errors['password']) ? 'error' : ''; ?>">
                        <?php if (isset($login_errors['password'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($login_errors['password']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn">
                        <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                        Login to Your Account
                    </button>
                </form>
            </div>
            
            <!-- Register Form -->
            <div id="register-tab" class="tab-content">
                <?php
                // Get active qualifications for dropdown
                require_once 'includes/config/database.php';
                $conn = getDBConnection();
                $qualifications = [];
                $qual_result = $conn->query("SELECT qualification_id, name FROM qualifications WHERE status = 'active' ORDER BY name ASC");
                if ($qual_result) {
                    $qualifications = $qual_result->fetch_all(MYSQLI_ASSOC);
                }
                $conn->close();
                
                // Get form values from session for repopulation
                $form_values = $_SESSION['form_values'] ?? [];
                $validation_errors = $_SESSION['validation_errors'] ?? [];
                unset($_SESSION['form_values']);
                unset($_SESSION['validation_errors']);
                ?>
                
                <form method="POST" action="" enctype="multipart/form-data" id="register-form">
                    <?php if ($redirect === 'apply' && $job_id > 0): ?>
                        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                        <input type="hidden" name="job_id" value="<?php echo $job_id; ?>">
                    <?php endif; ?>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> First Name *</label>
                            <input type="text" name="first_name" value="<?php echo htmlspecialchars($form_values['first_name'] ?? ''); ?>" 
                                   placeholder="Enter first name" required 
                                   class="<?php echo isset($validation_errors['first_name']) ? 'error' : ''; ?>">
                            <?php if (isset($validation_errors['first_name'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($validation_errors['first_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-user"></i> Last Name *</label>
                            <input type="text" name="last_name" value="<?php echo htmlspecialchars($form_values['last_name'] ?? ''); ?>" 
                                   placeholder="Enter last name" required 
                                   class="<?php echo isset($validation_errors['last_name']) ? 'error' : ''; ?>">
                            <?php if (isset($validation_errors['last_name'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($validation_errors['last_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email Address *</label>
                        <input type="email" name="email" id="email-input" 
                               value="<?php echo htmlspecialchars($form_values['email'] ?? ''); ?>" 
                               placeholder="Enter your email" required 
                               class="<?php echo isset($validation_errors['email']) ? 'error' : ''; ?>">
                        <?php if (isset($validation_errors['email'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($validation_errors['email']); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-phone"></i> Phone Number (Optional)</label>
                        <input type="tel" name="phone" id="phone" 
                               value="<?php echo htmlspecialchars($form_values['phone'] ?? ''); ?>" 
                               placeholder="Enter your phone number" 
                               pattern="[0-9]{10,15}" inputmode="numeric"
                               class="<?php echo isset($validation_errors['phone']) ? 'error' : ''; ?>">
                        <?php if (isset($validation_errors['phone'])): ?>
                            <span class="error-message"><?php echo htmlspecialchars($validation_errors['phone']); ?></span>
                        <?php endif; ?>
                        <small>Format: 10-15 digits (numbers only)</small>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Password *</label>
                            <input type="password" name="password" id="password-input" 
                                   placeholder="Create password" required minlength="6" 
                                   class="<?php echo isset($validation_errors['password']) ? 'error' : ''; ?>">
                            <?php if (isset($validation_errors['password'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($validation_errors['password']); ?></span>
                            <?php endif; ?>
                            <small>At least 6 characters long</small>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-lock"></i> Confirm Password *</label>
                            <input type="password" name="confirm_password" id="confirm-password-input" 
                                   placeholder="Confirm password" required 
                                   class="<?php echo isset($validation_errors['confirm_password']) ? 'error' : ''; ?>">
                            <?php if (isset($validation_errors['confirm_password'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($validation_errors['confirm_password']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" id="register-btn">
                        <i class="fas fa-user-plus" style="margin-right: 0.5rem;"></i>
                        Create Your Account
                    </button>
                    
                    <p style="text-align: center; margin-top: 1.5rem; color: var(--gray-500); font-size: 0.85rem;">
                        By registering, you agree to our Terms of Service and Privacy Policy
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- ===== OTP VERIFICATION MODAL ===== -->
    <div id="otp-modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999; align-items:center; justify-content:center;">
        <div id="otp-modal" style="
            background:#fff; border-radius:16px; padding:40px 36px; max-width:420px; width:90%;
            box-shadow:0 20px 60px rgba(0,0,0,0.2); position:relative; text-align:center;
            animation: otpFadeIn 0.25s ease;">
            <style>
                @keyframes otpFadeIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
                #otp-modal h2 { margin:0 0 6px; font-size:1.45rem; color:#1a1a2e; }
                #otp-modal .otp-sub { color:#666; font-size:0.93rem; margin-bottom:28px; line-height:1.5; }
                #otp-modal .otp-sub strong { color:#1a73e8; }
                .otp-inputs { display:flex; gap:10px; justify-content:center; margin-bottom:10px; }
                .otp-inputs input {
                    width:50px; height:60px; text-align:center; font-size:1.8rem; font-weight:700;
                    border:2px solid #d0d0d0; border-radius:10px; outline:none;
                    color:#1a1a2e; transition:border-color 0.2s, box-shadow 0.2s;
                    font-family:'Courier New',monospace;
                }
                .otp-inputs input:focus { border-color:#1a73e8; box-shadow:0 0 0 3px rgba(26,115,232,0.15); }
                .otp-inputs input.otp-error { border-color:#e53935; animation:shake 0.3s; }
                @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-4px)} 75%{transform:translateX(4px)} }
                #otp-message { min-height:22px; font-size:0.88rem; margin-bottom:16px; font-weight:500; }
                #otp-message.success { color:#2e7d32; }
                #otp-message.error   { color:#c62828; }
                #otp-message.info    { color:#1565c0; }
                #otp-timer { font-size:0.82rem; color:#999; margin-bottom:20px; }
                #otp-timer span { font-weight:600; color:#e53935; }
                .otp-btn-verify {
                    width:100%; padding:13px; background:linear-gradient(135deg,#1a73e8,#0d47a1);
                    color:#fff; border:none; border-radius:10px; font-size:1rem; font-weight:600;
                    cursor:pointer; transition:opacity 0.2s, transform 0.15s; letter-spacing:0.3px;
                }
                .otp-btn-verify:hover:not(:disabled) { opacity:0.9; transform:translateY(-1px); }
                .otp-btn-verify:disabled { opacity:0.5; cursor:not-allowed; }
                .otp-resend-row { margin-top:18px; font-size:0.87rem; color:#777; }
                .otp-resend-row button {
                    background:none; border:none; color:#1a73e8; font-weight:600;
                    cursor:pointer; font-size:0.87rem; padding:0; text-decoration:underline;
                }
                .otp-resend-row button:disabled { color:#aaa; cursor:default; text-decoration:none; }
                #otp-close-btn {
                    position:absolute; top:14px; right:18px; background:none; border:none;
                    font-size:1.4rem; color:#aaa; cursor:pointer; line-height:1;
                }
                #otp-close-btn:hover { color:#555; }
                .otp-email-icon { font-size:2.8rem; margin-bottom:12px; }
                .otp-dev-hint {
                    background:#fff8e1; border:1px solid #ffe082; border-radius:8px;
                    padding:8px 12px; font-size:0.82rem; color:#856404; margin-bottom:14px;
                    display:none;
                }
            </style>

            <button id="otp-close-btn" onclick="closeOtpModal()" title="Cancel">&times;</button>
            <div class="otp-email-icon">✉️</div>
            <h2>Verify Your Email</h2>
            <p class="otp-sub">We've sent a 6-digit code to<br><strong id="otp-email-display"></strong></p>

            <div class="otp-dev-hint" id="otp-dev-hint"></div>

            <div class="otp-inputs">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="0" autocomplete="off">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="1" autocomplete="off">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="2" autocomplete="off">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="3" autocomplete="off">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="4" autocomplete="off">
                <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]" class="otp-digit" data-index="5" autocomplete="off">
            </div>

            <div id="otp-message" class="info">Enter the 6-digit code from your email</div>
            <div id="otp-timer">Code expires in <span id="otp-countdown">10:00</span></div>

            <button class="otp-btn-verify" id="otp-verify-btn" onclick="verifyOtp()" disabled>Verify Email</button>

            <div class="otp-resend-row">
                Didn't receive it?
                <button id="otp-resend-btn" onclick="resendOtp()" disabled>Resend code</button>
                <span id="otp-resend-timer" style="color:#999;font-size:0.82rem;"></span>
            </div>
        </div>
    </div>
    <!-- ===== END OTP MODAL ===== -->

    <script>
        function switchTab(tab) {
            // Hide all tabs
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            // Show selected tab
            if (tab === 'login') {
                document.querySelector('.tab:first-child').classList.add('active');
                document.getElementById('login-tab').classList.add('active');
            } else {
                document.querySelector('.tab:last-child').classList.add('active');
                document.getElementById('register-tab').classList.add('active');
            }
        }
        
        // Enhanced real-time validation
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('register-form');
            const emailInput = document.getElementById('email-input');
            const phoneInput = document.getElementById('phone');
            const passwordInput = document.getElementById('password-input');
            const confirmPasswordInput = document.getElementById('confirm-password-input');
            
            // Email validation
            if (emailInput) {
                emailInput.addEventListener('blur', function() {
                    validateEmailField(this);
                });
                
                emailInput.addEventListener('input', function() {
                    clearError(this);
                });
            }
            
            // Phone validation
            if (phoneInput) {
                // Prevent non-numeric input
                phoneInput.addEventListener('keydown', function(e) {
                    // Allow: backspace, delete, tab, escape, enter
                    if ([46, 8, 9, 27, 13].includes(e.keyCode) ||
                        // Allow: Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                        (e.keyCode === 65 && e.ctrlKey === true) ||
                        (e.keyCode === 67 && e.ctrlKey === true) ||
                        (e.keyCode === 86 && e.ctrlKey === true) ||
                        (e.keyCode === 88 && e.ctrlKey === true) ||
                        // Allow: home, end, left, right
                        (e.keyCode >= 35 && e.keyCode <= 39)) {
                        return;
                    }
                    
                    // Ensure it's a number
                    if ((e.keyCode < 48 || e.keyCode > 57) && (e.keyCode < 96 || e.keyCode > 105)) {
                        e.preventDefault();
                    }
                });
                
                phoneInput.addEventListener('paste', function(e) {
                    const pastedText = e.clipboardData.getData('text');
                    if (!/^\d+$/.test(pastedText)) {
                        e.preventDefault();
                        showFieldError(this, 'Only numbers are allowed in phone field');
                    }
                });
                
                phoneInput.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '');
                    clearError(this);
                });
                
                phoneInput.addEventListener('blur', function() {
                    validatePhoneField(this);
                });
            }
            
            // Password validation
            if (passwordInput) {
                passwordInput.addEventListener('blur', function() {
                    validatePasswordField(this);
                });
                
                passwordInput.addEventListener('input', function() {
                    clearError(this);
                    // Also validate confirm password if it has a value
                    if (confirmPasswordInput && confirmPasswordInput.value) {
                        validateConfirmPasswordField(confirmPasswordInput);
                    }
                });
            }
            
            // Confirm password validation
            if (confirmPasswordInput) {
                confirmPasswordInput.addEventListener('blur', function() {
                    validateConfirmPasswordField(this);
                });
                
                confirmPasswordInput.addEventListener('input', function() {
                    clearError(this);
                });
            }
            
            // Form submission validation
            if (registerForm) {
                registerForm.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    // Validate all fields
                    const fields = registerForm.querySelectorAll('input[required]');
                    fields.forEach(field => {
                        let fieldValid = true;
                        
                        switch(field.name) {
                            case 'first_name':
                            case 'last_name':
                                fieldValid = validateNameField(field);
                                break;
                            case 'email':
                                fieldValid = validateEmailField(field);
                                break;
                            case 'password':
                                fieldValid = validatePasswordField(field);
                                break;
                            case 'confirm_password':
                                fieldValid = validateConfirmPasswordField(field);
                                break;
                        }
                        
                        if (!fieldValid) isValid = false;
                    });
                    
                    // Validate optional phone field
                    if (phoneInput && phoneInput.value) {
                        if (!validatePhoneField(phoneInput)) {
                            isValid = false;
                        }
                    }
                    
                    if (!isValid) {
                        e.preventDefault();
                        showFormError('Please correct the errors in the form.');
                    }
                });
            }
            
            // Validation functions
            window.validateEmailField = function(field) {
                const value = field.value.trim();
                let isValid = true;
                let errorMessage = '';
                
                // Basic required validation
                if (!value) {
                    isValid = false;
                    errorMessage = 'Email is required';
                }
                // Basic format validation
                else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    isValid = false;
                    errorMessage = 'Please enter a valid email address';
                }
                // Check for suspicious patterns
                else if (/[^\x00-\x7F]/.test(value)) {
                    isValid = false;
                    errorMessage = 'Invalid characters in email address';
                }
                // Check for consecutive dots
                else if (/\.\./.test(value)) {
                    isValid = false;
                    errorMessage = 'Invalid email format';
                }
                // Check for valid domain structure
                else {
                    const parts = value.split('@');
                    if (parts.length === 2) {
                        const domain = parts[1];
                        if (!/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(domain)) {
                            isValid = false;
                            errorMessage = 'Invalid domain in email address';
                        }
                    }
                }
                
                updateFieldError(field, isValid, errorMessage);
                return isValid;
            }
            
            window.validatePhoneField = function(field) {
                const value = field.value.trim();
                let isValid = true;
                let errorMessage = '';
                
                // Phone is optional, but if provided, validate it
                if (value) {
                    if (!/^[0-9]{10,15}$/.test(value)) {
                        isValid = false;
                        errorMessage = 'Phone number must be 10-15 digits';
                    }
                }
                
                updateFieldError(field, isValid, errorMessage);
                return isValid;
            }
            
            window.validateNameField = function(field) {
                const value = field.value.trim();
                let isValid = true;
                let errorMessage = '';
                
                if (!value) {
                    isValid = false;
                    errorMessage = field.name === 'first_name' ? 'First name is required' : 'Last name is required';
                } else if (value.length < 2) {
                    isValid = false;
                    errorMessage = 'Must be at least 2 characters long';
                } else if (value.length > 50) {
                    isValid = false;
                    errorMessage = 'Must be less than 50 characters';
                } else if (!/^[a-zA-Z\s\-']+$/.test(value)) {
                    isValid = false;
                    errorMessage = 'Can only contain letters, spaces, hyphens, and apostrophes';
                }
                
                updateFieldError(field, isValid, errorMessage);
                return isValid;
            }
            
            window.validatePasswordField = function(field) {
                const value = field.value;
                let isValid = true;
                let errorMessage = '';
                
                if (!value) {
                    isValid = false;
                    errorMessage = 'Password is required';
                } else if (value.length < 6) {
                    isValid = false;
                    errorMessage = 'Password must be at least 6 characters long';
                }
                
                // Check for weak passwords
                const weakPasswords = ['123456', 'password', '12345678', 'qwerty', 'abc123'];
                if (weakPasswords.includes(value.toLowerCase())) {
                    isValid = false;
                    errorMessage = 'Please choose a stronger password';
                }
                
                updateFieldError(field, isValid, errorMessage);
                return isValid;
            }
            
            window.validateConfirmPasswordField = function(field) {
                const value = field.value;
                const password = passwordInput ? passwordInput.value : '';
                let isValid = true;
                let errorMessage = '';
                
                if (!value) {
                    isValid = false;
                    errorMessage = 'Please confirm your password';
                } else if (value !== password) {
                    isValid = false;
                    errorMessage = 'Passwords do not match';
                }
                
                updateFieldError(field, isValid, errorMessage);
                return isValid;
            }
            
            // Helper functions
            window.updateFieldError = function(field, isValid, errorMessage) {
                const formGroup = field.closest('.form-group');
                const existingError = formGroup ? formGroup.querySelector('.error-message') : null;
                
                if (!isValid) {
                    field.classList.add('error');
                    if (!existingError && formGroup) {
                        const errorSpan = document.createElement('span');
                        errorSpan.className = 'error-message';
                        errorSpan.textContent = errorMessage;
                        formGroup.appendChild(errorSpan);
                    } else if (existingError) {
                        existingError.textContent = errorMessage;
                    }
                } else {
                    field.classList.remove('error');
                    if (existingError) {
                        existingError.remove();
                    }
                }
            }
            
            window.clearError = function(field) {
                if (field.classList.contains('error')) {
                    field.classList.remove('error');
                    const formGroup = field.closest('.form-group');
                    const errorMsg = formGroup ? formGroup.querySelector('.error-message') : null;
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                }
            }
            
            window.showFieldError = function(field, message) {
                const formGroup = field.closest('.form-group');
                const existingError = formGroup ? formGroup.querySelector('.error-message') : null;
                
                field.classList.add('error');
                if (!existingError && formGroup) {
                    const errorSpan = document.createElement('span');
                    errorSpan.className = 'error-message';
                    errorSpan.textContent = message;
                    formGroup.appendChild(errorSpan);
                }
            }
            
            window.showFormError = function(message) {
                showToast(message, 'error', 5000);
            }
        });
        
        // Check if URL has hash to open register tab
        if (window.location.hash === '#register' || (new URLSearchParams(window.location.search)).get('tab') === 'register') {
            switchTab('register');
        }

        // Fire PHP flash messages as toasts
        <?php if ($toast_error): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(<?php echo json_encode($toast_error); ?>, 'error', 5000);
        });
        <?php endif; ?>
        <?php if ($toast_success): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showToast(<?php echo json_encode($toast_success); ?>, 'success', 5000);
        });
        <?php endif; ?>

        // ============================================================
        // OTP EMAIL VERIFICATION LOGIC
        // ============================================================
        let otpTimerInterval  = null;
        let otpResendInterval = null;
        let otpExpiresAt      = null;
        let otpVerified       = false;

        // Intercept register form submit → trigger OTP flow instead
       // Intercept register form submit → run validation first, then trigger OTP flow
        const registerFormEl = document.getElementById('register-form');
        if (registerFormEl) {
            registerFormEl.addEventListener('submit', function(e) {
                // If already OTP-verified, let the form submit normally to the server
                if (otpVerified) return true;

                // Always prevent default — we control submission
                e.preventDefault();
                e.stopImmediatePropagation();

                // Run validation manually before opening OTP modal
                let isValid = true;
                const fields = registerFormEl.querySelectorAll('input[required]');
                fields.forEach(field => {
                    let fieldValid = true;
                    switch(field.name) {
                        case 'first_name':
                        case 'last_name':
                            fieldValid = validateNameField(field); break;
                        case 'email':
                            fieldValid = validateEmailField(field); break;
                        case 'password':
                            fieldValid = validatePasswordField(field); break;
                        case 'confirm_password':
                            fieldValid = validateConfirmPasswordField(field); break;
                    }
                    if (!fieldValid) isValid = false;
                });

                const phoneInput = registerFormEl.querySelector('#phone');
                if (phoneInput && phoneInput.value) {
                    if (!validatePhoneField(phoneInput)) isValid = false;
                }

                if (!isValid) {
                    showFormError('Please correct the errors in the form.');
                    return; // Stop here — do NOT open OTP modal
                }

                // Validation passed — proceed to OTP
                const email = document.getElementById('email-input')?.value?.trim();
                const firstName = registerFormEl.querySelector('input[name="first_name"]')?.value?.trim() || 'User';
                if (!email) {
                    showFieldError(document.getElementById('email-input'), 'Please enter your email address first.');
                    return;
                }
                openOtpModal(email, firstName);
            });
        }

        function openOtpModal(email, firstName) {
            document.getElementById('otp-email-display').textContent = email;
            document.getElementById('otp-message').textContent = 'Sending verification code...';
            document.getElementById('otp-message').className = 'info';
            document.getElementById('otp-modal-overlay').style.display = 'flex';
            clearOtpInputs();
            sendOtpRequest(email, firstName);
        }

        function closeOtpModal() {
            document.getElementById('otp-modal-overlay').style.display = 'none';
            clearInterval(otpTimerInterval);
            clearInterval(otpResendInterval);
        }

        function sendOtpRequest(email, firstName) {
            const fd = new FormData();
            fd.append('email', email);
            fd.append('first_name', firstName || 'User');

            fetch('send_otp.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        setOtpMessage(data.message, 'info');
                        otpExpiresAt = Date.now() + 10 * 60 * 1000;
                        startCountdown();
                        startResendCooldown(60);
                        document.querySelectorAll('.otp-digit')[0].focus();
                        // Dev hint: show OTP if returned (remove in production)
                        if (data.dev_otp) {
                            const hint = document.getElementById('otp-dev-hint');
                            hint.style.display = 'block';
                            hint.textContent = '🔧 Dev mode — OTP: ' + data.dev_otp;
                        }
                    } else {
                        setOtpMessage(data.message, 'error');
                    }
                })
                .catch(() => setOtpMessage('Network error. Please try again.', 'error'));
        }

        function resendOtp() {
            const email     = document.getElementById('otp-email-display').textContent;
            const firstName = document.getElementById('register-form')?.querySelector('input[name="first_name"]')?.value?.trim() || 'User';
            clearInterval(otpTimerInterval);
            clearOtpInputs();
            document.getElementById('otp-dev-hint').style.display = 'none';
            sendOtpRequest(email, firstName);
        }

        function verifyOtp() {
            const code  = Array.from(document.querySelectorAll('.otp-digit')).map(i => i.value).join('');
            const email = document.getElementById('otp-email-display').textContent;
            if (code.length < 6) {
                setOtpMessage('Please enter all 6 digits.', 'error');
                return;
            }
            document.getElementById('otp-verify-btn').disabled = true;
            setOtpMessage('Verifying...', 'info');

            const fd = new FormData();
            fd.append('otp', code);
            fd.append('email', email);

            fetch('verify_otp.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        otpVerified = true;
                        clearInterval(otpTimerInterval);
                        clearInterval(otpResendInterval);
                        setOtpMessage('✅ ' + data.message, 'success');
                        document.querySelectorAll('.otp-digit').forEach(i => { i.disabled = true; i.style.borderColor = '#2e7d32'; });
                        document.getElementById('otp-verify-btn').textContent = '✓ Verified';
                        document.getElementById('otp-verify-btn').style.background = '#2e7d32';
                        // Submit the registration form after a brief delay
                        setTimeout(() => {
                            closeOtpModal();
                            registerFormEl.submit();
                        }, 1200);
                    } else {
                        setOtpMessage(data.message, 'error');
                        document.querySelectorAll('.otp-digit').forEach(i => i.classList.add('otp-error'));
                        setTimeout(() => document.querySelectorAll('.otp-digit').forEach(i => i.classList.remove('otp-error')), 400);
                        document.getElementById('otp-verify-btn').disabled = false;
                    }
                })
                .catch(() => {
                    setOtpMessage('Network error. Please try again.', 'error');
                    document.getElementById('otp-verify-btn').disabled = false;
                });
        }

        function startCountdown() {
            clearInterval(otpTimerInterval);
            otpTimerInterval = setInterval(() => {
                const remaining = Math.max(0, otpExpiresAt - Date.now());
                const m = String(Math.floor(remaining / 60000)).padStart(2, '0');
                const s = String(Math.floor((remaining % 60000) / 1000)).padStart(2, '0');
                document.getElementById('otp-countdown').textContent = `${m}:${s}`;
                if (remaining <= 0) {
                    clearInterval(otpTimerInterval);
                    setOtpMessage('Code has expired. Please request a new one.', 'error');
                    document.getElementById('otp-verify-btn').disabled = true;
                }
            }, 1000);
        }

        function startResendCooldown(seconds) {
            const resendBtn   = document.getElementById('otp-resend-btn');
            const resendTimer = document.getElementById('otp-resend-timer');
            resendBtn.disabled = true;
            let remaining = seconds;
            clearInterval(otpResendInterval);
            otpResendInterval = setInterval(() => {
                remaining--;
                resendTimer.textContent = `(${remaining}s)`;
                if (remaining <= 0) {
                    clearInterval(otpResendInterval);
                    resendBtn.disabled = false;
                    resendTimer.textContent = '';
                }
            }, 1000);
        }

        function setOtpMessage(msg, type) {
            const el = document.getElementById('otp-message');
            el.textContent = msg;
            el.className   = type; // 'success', 'error', or 'info'
        }

        function clearOtpInputs() {
            document.querySelectorAll('.otp-digit').forEach(i => {
                i.value    = '';
                i.disabled = false;
                i.style.borderColor = '';
            });
            document.getElementById('otp-verify-btn').disabled  = true;
            document.getElementById('otp-verify-btn').textContent = 'Verify Email';
            document.getElementById('otp-verify-btn').style.background = '';
        }

        // OTP digit input behaviour: auto-advance, backspace, paste
        document.addEventListener('DOMContentLoaded', function() {
            const digits = document.querySelectorAll('.otp-digit');
            digits.forEach((input, idx) => {
                input.addEventListener('input', function() {
                    this.value = this.value.replace(/\D/g, '').slice(-1);
                    if (this.value && idx < digits.length - 1) digits[idx + 1].focus();
                    checkAllFilled();
                });
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && idx > 0) digits[idx - 1].focus();
                    if (e.key === 'Enter') verifyOtp();
                });
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
                    [...pasted].forEach((ch, i) => { if (digits[i]) digits[i].value = ch; });
                    checkAllFilled();
                    if (pasted.length === 6) verifyOtp();
                });
            });

            function checkAllFilled() {
                const allFilled = Array.from(digits).every(i => i.value.length === 1);
                document.getElementById('otp-verify-btn').disabled = !allFilled;
            }

            // Close modal when clicking overlay background
            document.getElementById('otp-modal-overlay')?.addEventListener('click', function(e) {
                if (e.target === this) closeOtpModal();
            });
        });
        // ============================================================
        // END OTP LOGIC
        // ============================================================
    </script>
</body>
</html>