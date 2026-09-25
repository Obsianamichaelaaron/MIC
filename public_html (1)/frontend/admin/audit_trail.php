<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('admin');

$pageTitle = "Audit Trail";

$conn = getDBConnection();

// Check if audit_trail table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'audit_trail'");
if (!$table_check || $table_check->num_rows == 0) {
    // Create the table
    $sql = "CREATE TABLE IF NOT EXISTS audit_trail (
        audit_id INT PRIMARY KEY AUTO_INCREMENT,
        admin_user_id INT NOT NULL,
        admin_name VARCHAR(255) NOT NULL,
        action_type VARCHAR(100) NOT NULL,
        action_description TEXT NOT NULL,
        target_type VARCHAR(50) NULL COMMENT 'Type of entity affected: employer, applicant, user, job, etc.',
        target_id INT NULL COMMENT 'ID of the affected entity',
        target_name VARCHAR(255) NULL COMMENT 'Name/identifier of the affected entity',
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin (admin_user_id),
        INDEX idx_action (action_type),
        INDEX idx_target (target_type, target_id),
        INDEX idx_created (created_at),
        FOREIGN KEY (admin_user_id) REFERENCES users(user_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
}

// Get filter parameters
$filter_action = $_GET['action'] ?? '';
$filter_target = $_GET['target'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Build query
$query = "SELECT at.*, u.email as admin_email 
          FROM audit_trail at 
          LEFT JOIN users u ON at.admin_user_id = u.user_id 
          WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_action)) {
    $query .= " AND at.action_type = ?";
    $params[] = $filter_action;
    $types .= "s";
}

if (!empty($filter_target)) {
    $query .= " AND at.target_type = ?";
    $params[] = $filter_target;
    $types .= "s";
}

if (!empty($filter_date_from)) {
    $query .= " AND DATE(at.created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $query .= " AND DATE(at.created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM (" . $query . ") as count_query";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $per_page);

// Get audit trail records
$query .= " ORDER BY at.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$audit_records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unique action types for filter
$action_types_result = $conn->query("SELECT DISTINCT action_type FROM audit_trail ORDER BY action_type");
$action_types = $action_types_result ? $action_types_result->fetch_all(MYSQLI_ASSOC) : [];
$target_types_result = $conn->query("SELECT DISTINCT target_type FROM audit_trail WHERE target_type IS NOT NULL ORDER BY target_type");
$target_types = $target_types_result ? $target_types_result->fetch_all(MYSQLI_ASSOC) : [];

// Get employer actions count
$employer_count_result = $conn->query("SELECT COUNT(*) as count FROM audit_trail WHERE target_type = 'employer'");
$employer_count = $employer_count_result ? $employer_count_result->fetch_assoc()['count'] : 0;

$conn->close();

include '../includes/header.php';
?>

<div class="container">
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
            <h1 style="margin: 0; color: #0056b3;">
                <i class="fas fa-history"></i> Audit Trail
            </h1>
            <a href="dashboard.php" style="background: #6c757d; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <!-- Filters -->
        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem;">
            <h3 style="color: #333; margin-bottom: 1rem;">
                <i class="fas fa-filter"></i> Filters
            </h3>
            <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: #666; font-weight: 500;">Action Type</label>
                    <select name="action" style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px;">
                        <option value="">All Actions</option>
                        <?php foreach ($action_types as $action): ?>
                            <option value="<?php echo htmlspecialchars($action['action_type']); ?>" <?php echo ($filter_action === $action['action_type']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $action['action_type']))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: #666; font-weight: 500;">Target Type</label>
                    <select name="target" style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px;">
                        <option value="">All Targets</option>
                        <?php foreach ($target_types as $target): ?>
                            <option value="<?php echo htmlspecialchars($target['target_type']); ?>" <?php echo ($filter_target === $target['target_type']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($target['target_type'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: #666; font-weight: 500;">Date From</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_date_from); ?>" style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px;">
                </div>
                
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: #666; font-weight: 500;">Date To</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_date_to); ?>" style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px;">
                </div>
                
                <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                    <button type="submit" style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="audit_trail.php" style="background: #6c757d; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 1.5rem; border-radius: 10px;">
                <h3 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo $total_records; ?></h3>
                <p style="margin: 0; opacity: 0.9;">Total Records</p>
            </div>
            <div style="background: #4CAF50; color: white; padding: 1.5rem; border-radius: 10px;">
                <h3 style="font-size: 2rem; margin-bottom: 0.5rem;">
                    <?php echo $employer_count; ?>
                </h3>
                <p style="margin: 0; opacity: 0.9;">Employer Actions</p>
            </div>
        </div>
        
        <!-- Audit Trail Table -->
        <?php if (count($audit_records) > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden;">
                    <thead>
                        <tr style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white;">
                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Date & Time</th>
                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Admin</th>
                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Action</th>
                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Description</th>
                            <th style="padding: 1rem; text-align: left; font-weight: 600;">Target</th>
                            <th style="padding: 1rem; text-align: left; font-weight: 600;">IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit_records as $record): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 1rem; color: #666;">
                                    <?php echo date('M d, Y h:i A', strtotime($record['created_at'])); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="font-weight: 600; color: #333;">
                                        <?php echo htmlspecialchars($record['admin_name']); ?>
                                    </div>
                                    <div style="font-size: 0.85rem; color: #999;">
                                        <?php echo htmlspecialchars($record['admin_email'] ?? 'N/A'); ?>
                                    </div>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php
                                    $action_colors = [
                                        'create_employer' => '#4CAF50',
                                        'update_employer' => '#2196F3',
                                        'delete_employer' => '#F44336',
                                        'create_user' => '#4CAF50',
                                        'update_user' => '#2196F3',
                                        'delete_user' => '#F44336',
                                        'create_job' => '#9C27B0',
                                        'update_job' => '#FF9800',
                                        'delete_job' => '#F44336'
                                    ];
                                    $color = $action_colors[$record['action_type']] ?? '#6c757d';
                                    ?>
                                    <span style="background: <?php echo $color; ?>; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $record['action_type']))); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: #666; max-width: 400px;">
                                    <?php echo htmlspecialchars($record['action_description']); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if (!empty($record['target_type'])): ?>
                                        <div style="font-weight: 600; color: #333;">
                                            <?php echo htmlspecialchars(ucfirst($record['target_type'])); ?>
                                        </div>
                                        <?php if (!empty($record['target_name'])): ?>
                                            <div style="font-size: 0.85rem; color: #666;">
                                                <?php echo htmlspecialchars($record['target_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color: #999;">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; color: #666; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($record['ip_address'] ?? 'N/A'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div style="display: flex; justify-content: center; align-items: center; gap: 0.5rem; margin-top: 2rem;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&action=<?php echo htmlspecialchars($filter_action); ?>&target=<?php echo htmlspecialchars($filter_target); ?>&date_from=<?php echo htmlspecialchars($filter_date_from); ?>&date_to=<?php echo htmlspecialchars($filter_date_to); ?>" 
                           style="background: #6c757d; color: white; padding: 0.75rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600;">
                            <i class="fas fa-chevron-left"></i> Previous
                        </a>
                    <?php endif; ?>
                    
                    <span style="color: #666; padding: 0.75rem 1rem;">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                    </span>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&action=<?php echo htmlspecialchars($filter_action); ?>&target=<?php echo htmlspecialchars($filter_target); ?>&date_from=<?php echo htmlspecialchars($filter_date_from); ?>&date_to=<?php echo htmlspecialchars($filter_date_to); ?>" 
                           style="background: #6c757d; color: white; padding: 0.75rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600;">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 3rem; color: #666;">
                <i class="fas fa-inbox" style="font-size: 3rem; color: #ccc; margin-bottom: 1rem; display: block;"></i>
                <p>No audit trail records found.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

