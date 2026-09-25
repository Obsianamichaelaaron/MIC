<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('admin');

$pageTitle = "Manage Users";

$conn = getDBConnection();

$success_message = '';
$error_message = '';

// Handle create user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? 'applicant';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate
    if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error_message = "Please fill in all required fields";
    } elseif (!in_array($role, ['applicant', 'employer', 'admin'])) {
        $error_message = "Invalid role selected";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Email already registered";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user (mark as created by admin)
            $created_by_admin = 1; // TRUE - created by admin
            $stmt = $conn->prepare("INSERT INTO users (email, password, role, first_name, last_name, phone, status, created_by_admin) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssi", $email, $hashed_password, $role, $first_name, $last_name, $phone, $status, $created_by_admin);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Create role-specific record
                if ($role === 'applicant') {
                    $stmt2 = $conn->prepare("INSERT INTO applicants (user_id) VALUES (?)");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $stmt2->close();
                } elseif ($role === 'employer') {
                    $stmt2 = $conn->prepare("INSERT INTO employers (user_id) VALUES (?)");
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $stmt2->close();
                    
                    // Log to audit trail
                    require_once '../includes/handlers/audit_trail.php';
                    $admin_user_id = getCurrentUserId();
                    $employer_name = trim($first_name . ' ' . $last_name);
                    logAuditTrail($conn, $admin_user_id, 'create_employer', 
                        "Created new employer account: {$employer_name} ({$email})", 
                        'employer', $user_id, $employer_name);
                }
                
                $success_message = "User created successfully!";
            } else {
                $error_message = "Failed to create user: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $user_id = intval($_POST['user_id']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
    $stmt->bind_param("si", $status, $user_id);
    $stmt->execute();
    $stmt->close();
    $success_message = "User status updated successfully!";
}

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $user_id = intval($_POST['user_id']);
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = $_POST['role'] ?? '';
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Validate
    if (empty($email) || empty($first_name) || empty($last_name)) {
        $error_message = "Please fill in all required fields";
    } elseif (!in_array($role, ['applicant', 'employer', 'admin'])) {
        $error_message = "Invalid role selected";
    } elseif (!empty($password) && strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters";
    } else {
        // Check if email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Email already registered to another user";
        } else {
            // Get current user data
            $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_user = $stmt->get_result()->fetch_assoc();
            $old_role = $current_user['role'];
            $stmt->close();
            
            // Update user
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET email = ?, password = ?, role = ?, first_name = ?, last_name = ?, phone = ?, status = ? WHERE user_id = ?");
                $stmt->bind_param("sssssssi", $email, $hashed_password, $role, $first_name, $last_name, $phone, $status, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET email = ?, role = ?, first_name = ?, last_name = ?, phone = ?, status = ? WHERE user_id = ?");
                $stmt->bind_param("ssssssi", $email, $role, $first_name, $last_name, $phone, $status, $user_id);
            }
            
            if ($stmt->execute()) {
                // Handle role change - update role-specific tables
                if ($old_role !== $role) {
                    // Remove from old role table
                    if ($old_role === 'applicant') {
                        $stmt2 = $conn->prepare("DELETE FROM applicants WHERE user_id = ?");
                        $stmt2->bind_param("i", $user_id);
                        $stmt2->execute();
                        $stmt2->close();
                    } elseif ($old_role === 'employer') {
                        $stmt2 = $conn->prepare("DELETE FROM employers WHERE user_id = ?");
                        $stmt2->bind_param("i", $user_id);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                    
                    // Add to new role table
                    if ($role === 'applicant') {
                        $stmt2 = $conn->prepare("INSERT INTO applicants (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = user_id");
                        $stmt2->bind_param("i", $user_id);
                        $stmt2->execute();
                        $stmt2->close();
                    } elseif ($role === 'employer') {
                        $stmt2 = $conn->prepare("INSERT INTO employers (user_id) VALUES (?) ON DUPLICATE KEY UPDATE user_id = user_id");
                        $stmt2->bind_param("i", $user_id);
                        $stmt2->execute();
                        $stmt2->close();
                    }
                }
                
                $success_message = "User updated successfully!";
            } else {
                $error_message = "Failed to update user: " . $conn->error;
            }
        }
        $stmt->close();
    }
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    $current_user_id = getCurrentUserId();
    
    // Prevent self-deletion
    if ($user_id == $current_user_id) {
        $error_message = "You cannot delete your own account!";
    } else {
        // Delete user (CASCADE will handle related records)
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $success_message = "User deleted successfully!";
        } else {
            $error_message = "Failed to delete user: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get user to edit (if editing)
$edit_user = null;
if (isset($_GET['edit'])) {
    $edit_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_user = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get filter
$role_filter = $_GET['role'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Build query with LEFT JOIN to get applicant resume information and employability score
$query = "SELECT u.*, a.resume_file, a.qualifications, a.skills, a.applicant_id, a.employability_score 
          FROM users u 
          LEFT JOIN applicants a ON u.user_id = a.user_id 
          WHERE 1=1";
$params = [];
$types = "";

if ($role_filter !== 'all') {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if ($status_filter !== 'all') {
    $query .= " AND u.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY u.created_at DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

include '../includes/header.php';
?>

<style>
/* Responsive User Management Styles */
.users-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

.dashboard-header {
    background: url('../images/bg.jpg') center/cover no-repeat;
    padding: clamp(1rem, 3vw, 1.5rem) 0;
    margin: 0 -1rem 1rem -1rem;
}

.dashboard-header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
    text-align: center;
    color: white;
}

.dashboard-title {
    color: white;
    margin-bottom: 0.5rem;
    font-size: clamp(1.3rem, 4vw, 1.5rem);
    font-weight: 700;
}

.dashboard-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: clamp(0.8rem, 2.5vw, 0.9rem);
    margin: 0;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    flex-wrap: wrap;
}

.action-button {
    color: white;
    padding: clamp(0.5rem, 2vw, 0.6rem) clamp(1rem, 3vw, 1.2rem);
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.3s ease;
    font-size: clamp(0.8rem, 2vw, 0.85rem);
    border: none;
    cursor: pointer;
}

.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

/* Form Styles */
.form-section {
    background: #f8f9fa;
    padding: clamp(1rem, 3vw, 1.5rem);
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border-left: 4px solid;
}

.form-title {
    margin-bottom: 1rem;
    font-size: clamp(1.1rem, 3vw, 1.2rem);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-grid {
    display: grid;
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    display: block;
    margin-bottom: 0.4rem;
    font-weight: 600;
    color: #333;
    font-size: clamp(0.8rem, 2vw, 0.85rem);
}

.form-input {
    width: 100%;
    padding: 0.6rem;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    transition: border-color 0.3s ease;
    font-size: clamp(0.8rem, 2vw, 0.85rem);
}

.form-input:focus {
    outline: none;
    border-color: #0056b3;
}

.form-actions {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Filter Section */
.filter-section {
    background: #f8f9fa;
    padding: clamp(1rem, 3vw, 1.2rem);
    border-radius: 8px;
    margin-bottom: 1.5rem;
    border-left: 4px solid #0056b3;
}

.filter-title {
    color: #333;
    margin-bottom: 0.8rem;
    font-size: clamp(1rem, 2.8vw, 1.1rem);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.filter-form {
    display: grid;
    gap: 0.8rem;
    align-items: end;
}

@media (min-width: 480px) {
    .filter-form {
        grid-template-columns: 1fr 1fr auto;
    }
}

/* Table Styles */
.users-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 8px;
    overflow: hidden;
    font-size: clamp(0.75rem, 2vw, 0.8rem);
    min-width: 800px;
}

.users-table th {
    padding: clamp(0.6rem, 2vw, 0.8rem);
    text-align: left;
    font-weight: 600;
    font-size: clamp(0.75rem, 2vw, 0.85rem);
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
}

.users-table td {
    padding: clamp(0.6rem, 2vw, 0.8rem);
    border-bottom: 1px solid #e0e0e0;
    transition: all 0.3s ease;
}

.users-table tr:hover {
    background-color: #f8f9fa;
}

/* Table Container for Mobile */
.table-container {
    overflow-x: auto;
    margin: 0 -1rem;
    padding: 0 1rem;
}

@media (min-width: 768px) {
    .table-container {
        margin: 0;
        padding: 0;
    }
}

/* Badge Styles */
.badge {
    color: white;
    padding: clamp(0.2rem, 1vw, 0.3rem) clamp(0.4rem, 2vw, 0.6rem);
    border-radius: 12px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: clamp(0.65rem, 1.8vw, 0.7rem);
    display: inline-block;
    text-align: center;
}

/* Action Buttons in Table */
.table-actions {
    display: flex;
    gap: 0.3rem;
    flex-wrap: wrap;
    align-items: center;
}

.table-button {
    color: white;
    padding: clamp(0.2rem, 1vw, 0.3rem) clamp(0.4rem, 2vw, 0.6rem);
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: clamp(0.7rem, 1.8vw, 0.75rem);
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
    transition: all 0.3s ease;
    text-decoration: none;
}

.table-button:hover {
    transform: translateY(-1px);
}

.status-form {
    display: flex;
    gap: 0.3rem;
    align-items: center;
}

.status-select {
    padding: 0.3rem;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: clamp(0.7rem, 1.8vw, 0.75rem);
    min-width: 80px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: clamp(1.5rem, 4vw, 2rem);
    color: #666;
}

.empty-icon {
    font-size: clamp(1.5rem, 5vw, 2rem);
    color: #ddd;
    margin-bottom: 0.5rem;
}

.empty-title {
    color: #333;
    margin-bottom: 0.3rem;
    font-size: clamp(0.9rem, 2.8vw, 1.1rem);
}

.empty-description {
    font-size: clamp(0.8rem, 2.2vw, 0.9rem);
    margin: 0;
}

/* Message Styles */
.message {
    padding: 0.75rem;
    border-radius: 6px;
    margin-bottom: 1rem;
    font-size: clamp(0.8rem, 2.2vw, 0.9rem);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.message-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.message-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Responsive Grid Layouts */
@media (min-width: 768px) {
    .form-grid-2 {
        grid-template-columns: 1fr 1fr;
    }
    
    .form-grid-3 {
        grid-template-columns: 1fr 1fr 1fr;
    }
}

@media (max-width: 767px) {
    .form-grid-2,
    .form-grid-3 {
        grid-template-columns: 1fr;
    }
}

/* Mobile-specific optimizations */
@media (max-width: 480px) {
    .action-buttons {
        flex-direction: column;
    }
    
    .action-button {
        justify-content: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .table-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .status-form {
        flex-direction: column;
        gap: 0.5rem;
    }
    
    .status-select {
        width: 100%;
    }
}

/* Very small screens */
@media (max-width: 360px) {
    .users-container {
        padding: 0 0.5rem;
    }
    
    .table-container {
        margin: 0 -0.5rem;
        padding: 0 0.5rem;
    }
}
</style>

<!-- Full Width Header -->
<div class="dashboard-header">
    <div class="dashboard-header-content">
        <h1 class="dashboard-title">
            <i class="fas fa-users-cog"></i> Manage Users
        </h1>
        <p class="dashboard-subtitle">
            User Management & Chatbot Data Overview
        </p>
    </div>
</div>

<div class="users-container">
    <div class="card" style="margin-top: 0;">
        
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="message message-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="message message-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="action-buttons">
            <button onclick="toggleCreateForm()" class="action-button" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
                <i class="fas fa-plus-circle"></i> Create User
            </button>
            
           
        </div>
        
        <!-- Create User Form (Hidden by default) -->
        <div id="createUserForm" class="form-section" style="display: none; border-left-color: #4CAF50;">
            <h2 class="form-title">
                <i class="fas fa-user-plus"></i> Create New User
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="create_user" value="1">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" required class="form-input">
                    </div>
                </div>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-input">
                    </div>
                </div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role" required class="form-input">
                            <option value="employer">Employer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" required minlength="6" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status *</label>
                        <select name="status" required class="form-input">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="action-button" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <button type="button" onclick="toggleCreateForm()" class="action-button" style="background: #6c757d;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Edit User Form (Shown when editing) -->
        <?php if ($edit_user): ?>
        <div id="editUserForm" class="form-section" style="border-left-color: #ffc107; background: #fff3cd;">
            <h2 class="form-title" style="color: #856404;">
                <i class="fas fa-user-edit"></i> Edit User: <?php echo htmlspecialchars($edit_user['first_name'] . ' ' . $edit_user['last_name']); ?>
            </h2>
            <form method="POST" action="">
                <input type="hidden" name="user_id" value="<?php echo $edit_user['user_id']; ?>">
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" value="<?php echo htmlspecialchars($edit_user['first_name']); ?>" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" value="<?php echo htmlspecialchars($edit_user['last_name']); ?>" required class="form-input">
                    </div>
                </div>
                <div class="form-grid form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($edit_user['email']); ?>" required class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($edit_user['phone'] ?? ''); ?>" class="form-input">
                    </div>
                </div>
                <div class="form-grid form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Role *</label>
                        <select name="role" required class="form-input">
                            <option value="employer" <?php echo ($edit_user['role'] === 'employer') ? 'selected' : ''; ?>>Employer</option>
                            <option value="admin" <?php echo ($edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password (leave blank to keep current)</label>
                        <input type="password" name="password" minlength="6" class="form-input" placeholder="Leave blank to keep current password">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Status *</label>
                        <select name="status" required class="form-input">
                            <option value="active" <?php echo ($edit_user['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($edit_user['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            <option value="suspended" <?php echo ($edit_user['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" name="edit_user" class="action-button" style="background: linear-gradient(135deg, #ffc107 0%, #e6a800 100%); color: #000;">
                        <i class="fas fa-save"></i> Update User
                    </button>
                    <a href="users.php<?php echo $role_filter !== 'all' || $status_filter !== 'all' ? '?role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) : ''; ?>" class="action-button" style="background: #6c757d; text-decoration: none;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filter-section">
            <h3 class="filter-title">
                <i class="fas fa-filter"></i> Filter Users
            </h3>
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-input">
                        <option value="all" <?php echo ($role_filter === 'all') ? 'selected' : ''; ?>>All Roles</option>
                         <option value="applicant" <?php echo ($role_filter === 'applicant') ? 'selected' : ''; ?>>Applicant</option>
                        <option value="employer" <?php echo ($role_filter === 'employer') ? 'selected' : ''; ?>>Employers</option>
                        <option value="admin" <?php echo ($role_filter === 'admin') ? 'selected' : ''; ?>>Admins</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input">
                        <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="suspended" <?php echo ($status_filter === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                    </select>
                </div>
                <div class="form-group">
                    <button type="submit" class="action-button" style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%);">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (count($users) > 0): ?>
            <div class="table-container">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Resume</th>
                            <th>Score</th>
                            <th>Chatbot</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong style="color: #333; font-size: clamp(0.8rem, 2vw, 0.85rem);"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                </td>
                                <td>
                                    <span style="font-size: clamp(0.75rem, 2vw, 0.8rem);"><?php echo htmlspecialchars($user['email']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $role_colors = [
                                        'admin' => '#F44336',
                                        'employer' => '#2196F3',
                                        'applicant' => '#4CAF50'
                                    ];
                                    $color = $role_colors[$user['role']] ?? '#666';
                                    ?>
                                    <span class="badge" style="background: <?php echo $color; ?>;">
                                        <?php echo $user['role']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($user['resume_file']) && file_exists('../' . $user['resume_file'])): ?>
                                        <a href="../<?php echo htmlspecialchars($user['resume_file']); ?>" 
                                           target="_blank" 
                                           class="table-button" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%);"
                                           title="View Resume PDF">
                                            <i class="fas fa-file-pdf"></i> View
                                        </a>
                                    <?php elseif (!empty($user['resume_file'])): ?>
                                        <span style="color: #dc3545; font-size: clamp(0.7rem, 1.8vw, 0.75rem); display: inline-flex; align-items: center; gap: 0.3rem;" title="File not found">
                                            <i class="fas fa-exclamation-triangle"></i> Missing
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-size: clamp(0.7rem, 1.8vw, 0.75rem); display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <i class="fas fa-minus"></i> None
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'applicant' && isset($user['employability_score'])): ?>
                                        <span class="badge" style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%);">
                                            <?php echo number_format($user['employability_score'], 1); ?>%
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-size: clamp(0.7rem, 1.8vw, 0.75rem); display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <i class="fas fa-minus"></i> N/A
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'applicant' && !empty($user['applicant_id'])): 
                                        $conn_temp = getDBConnection();
                                        $chat_count = 0;
                                        $stmt_chat = $conn_temp->prepare("SELECT COUNT(*) as count FROM chatbot_answers WHERE applicant_id = ?");
                                        $stmt_chat->bind_param("i", $user['applicant_id']);
                                        $stmt_chat->execute();
                                        $chat_count = $stmt_chat->get_result()->fetch_assoc()['count'];
                                        $stmt_chat->close();
                                        $conn_temp->close();
                                        
                                        if ($chat_count > 0): ?>
                                            <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                                                <button onclick="viewChatbotData(<?php echo $user['applicant_id']; ?>)" 
                                                        class="table-button" style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);">
                                                    <i class="fas fa-robot"></i> (<?php echo $chat_count; ?>)
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #6c757d; font-size: clamp(0.7rem, 1.8vw, 0.75rem); display: inline-flex; align-items: center; gap: 0.3rem;">
                                                <i class="fas fa-minus"></i> No Data
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #6c757d; font-size: clamp(0.7rem, 1.8vw, 0.75rem); display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <i class="fas fa-minus"></i> N/A
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'active' => '#4CAF50',
                                        'inactive' => '#9E9E9E',
                                        'suspended' => '#F44336'
                                    ];
                                    $color = $status_colors[$user['status']] ?? '#666';
                                    ?>
                                    <span class="badge" style="background: <?php echo $color; ?>;">
                                        <?php echo $user['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: clamp(0.75rem, 2vw, 0.8rem);"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="users.php?edit=<?php echo $user['user_id']; ?><?php echo $role_filter !== 'all' || $status_filter !== 'all' ? '&role=' . urlencode($role_filter) . '&status=' . urlencode($status_filter) : ''; ?>" 
                                           class="table-button" style="background: linear-gradient(135deg, #ffc107 0%, #e6a800 100%); color: #000;">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['user_id'] != getCurrentUserId()): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone!');">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <button type="submit" name="delete_user" 
                                                    class="table-button" style="background: linear-gradient(135deg, #F44336 0%, #d32f2f 100%);">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span style="color: #999; font-size: clamp(0.65rem, 1.8vw, 0.7rem); font-style: italic; display: inline-flex; align-items: center; gap: 0.3rem;">
                                            <i class="fas fa-user"></i>
                                        </span>
                                        <?php endif; ?>
                                        <form method="POST" class="status-form">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <select name="status" class="status-select">
                                                <option value="active" <?php echo ($user['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo ($user['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="suspended" <?php echo ($user['status'] === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                            </select>
                                            <button type="submit" name="update_status" 
                                                    class="table-button" style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%);">
                                                Update
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-users empty-icon"></i>
                <h3 class="empty-title">No users found</h3>
                <p class="empty-description">No users match your current filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleCreateForm() {
    const form = document.getElementById('createUserForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}

function viewChatbotData(applicantId) {
    window.open('view_chatbot_data.php?applicant_id=' + applicantId, '_blank', 'width=800,height=600,scrollbars=yes');
}

// Handle responsive table behavior
document.addEventListener('DOMContentLoaded', function() {
    const tableContainer = document.querySelector('.table-container');
    
    function handleTableResize() {
        if (window.innerWidth < 768 && tableContainer) {
            tableContainer.scrollLeft = 0;
        }
    }
    
    // Initial check
    handleTableResize();
    
    // Add resize listener
    window.addEventListener('resize', handleTableResize);
});
</script>

<?php include '../includes/footer.php'; ?>