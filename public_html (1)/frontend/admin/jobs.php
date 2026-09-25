<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('admin');

$pageTitle = "Manage All Jobs";

$conn = getDBConnection();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $job_id = intval($_POST['job_id']);
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE job_postings SET status = ? WHERE job_id = ?");
    $stmt->bind_param("si", $status, $job_id);
    $stmt->execute();
    $stmt->close();
}

// Get filter
$status_filter = $_GET['status'] ?? 'all';

// Build query
$query = "
    SELECT jp.*, 
           e.company_name,
           COUNT(DISTINCT a.application_id) as application_count
    FROM job_postings jp
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    LEFT JOIN applications a ON jp.job_id = a.job_id
";

if ($status_filter !== 'all') {
    $query .= " WHERE jp.status = ?";
    $stmt = $conn->prepare($query . " GROUP BY jp.job_id ORDER BY jp.posted_at DESC");
    $stmt->bind_param("s", $status_filter);
} else {
    $stmt = $conn->prepare($query . " GROUP BY jp.job_id ORDER BY jp.posted_at DESC");
}

$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

include '../includes/header.php';
?>

<!-- Full Width Header -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 1.5rem 0; margin: 0 -8px 1rem -8px;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.3rem; font-size: 1.5rem;">
                <i class="fas fa-briefcase"></i> Manage All Jobs
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin: 0;">
                Job Management 
            </p>
        </div>
    </div>
</div>

<div class="container" style="max-width: 1400px; padding: 0 1rem;">
    <div class="card" style="margin-top: 0;">
        
        <!-- Filter -->
        <div style="background: #f8f9fa; padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; border-left: 4px solid #0056b3;">
            <form method="GET" style="display: flex; gap: 1rem; align-items: center;">
                <div>
                    <label style="display: block; margin-bottom: 0.4rem; font-weight: 600; color: #333; font-size: 0.85rem;">Status Filter</label>
                    <select name="status" style="padding: 0.6rem; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 0.85rem; min-width: 150px;">
                        <option value="all" <?php echo ($status_filter === 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="closed" <?php echo ($status_filter === 'closed') ? 'selected' : ''; ?>>Closed</option>
                        <option value="draft" <?php echo ($status_filter === 'draft') ? 'selected' : ''; ?>>Draft</option>
                    </select>
                </div>
                <div style="margin-top: 1.2rem;">
                    <button type="submit" 
                            style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.6rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.85rem;"
                            onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(0, 86, 179, 0.3)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-filter"></i> Apply Filter
                    </button>
                </div>
            </form>
        </div>
        
        <?php if (count($jobs) > 0): ?>
            <div style="display: grid; gap: 1rem;">
                <?php foreach ($jobs as $job): ?>
                    <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white;"
                         onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#0056b3';"
                         onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                            <div style="flex: 1;">
                                <h2 style="color: #1866a3; margin-bottom: 0.5rem; font-size: 1.2rem; font-weight: 600;"><?php echo htmlspecialchars($job['title']); ?></h2>
                                <div style="display: flex; flex-direction: column; gap: 0.3rem; margin-bottom: 0.8rem;">
                                    <?php if (!empty($job['company_name'])): ?>
                                    <p style="color: #666; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                                    </p>
                                    <?php endif; ?>
                                    <p style="color: #666; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['location'] ?? 'Not Specified'); ?>
                                    </p>
                                    <p style="color: #666; margin: 0; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem;">
                                        <i class="fas fa-calendar"></i> Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?>
                                    </p>
                                </div>
                                <p style="color: #2196F3; font-weight: 600; font-size: 0.9rem; display: flex; align-items: center; gap: 0.4rem;">
                                    <i class="fas fa-file-alt"></i> <?php echo $job['application_count']; ?> Applications
                                </p>
                            </div>
                            <div>
                                <?php
                                $status_colors = [
                                    'active' => '#4CAF50',
                                    'closed' => '#F44336',
                                    'draft' => '#9E9E9E'
                                ];
                                $color = $status_colors[$job['status']] ?? '#666';
                                ?>
                                <span style="background: <?php echo $color; ?>; color: white; padding: 0.4rem 0.8rem; border-radius: 15px; font-weight: 600; text-transform: uppercase; font-size: 0.75rem;">
                                    <?php echo $job['status']; ?>
                                </span>
                            </div>
                        </div>
                        
                        <form method="POST" style="display: flex; gap: 0.5rem; align-items: center;">
                            <input type="hidden" name="job_id" value="<?php echo $job['job_id']; ?>">
                            <select name="status" style="padding: 0.4rem; border: 2px solid #e0e0e0; border-radius: 4px; font-size: 0.8rem; min-width: 100px;">
                                <option value="active" <?php echo ($job['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="closed" <?php echo ($job['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                                <option value="draft" <?php echo ($job['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                            </select>
                            <button type="submit" name="update_status" 
                                    style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.4rem 1rem; border: none; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.8rem; transition: all 0.3s ease;"
                                    onmouseover="this.style.transform='translateY(-1px)';"
                                    onmouseout="this.style.transform='translateY(0)';">
                                Update
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: #666;">
                <i class="fas fa-briefcase" style="font-size: 2rem; color: #ddd; margin-bottom: 0.5rem;"></i>
                <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1.1rem;">No jobs found</h3>
                <p style="font-size: 0.9rem;">No jobs match your current filter criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>