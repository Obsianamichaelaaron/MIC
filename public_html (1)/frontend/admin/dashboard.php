<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('admin');

$pageTitle = "Admin Dashboard";

$conn = getDBConnection();

// Get statistics
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total applicants
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'applicant'");
$stats['total_applicants'] = $result->fetch_assoc()['count'];

// Total employers
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'employer'");
$stats['total_employers'] = $result->fetch_assoc()['count'];

// Total jobs
$result = $conn->query("SELECT COUNT(*) as count FROM job_postings");
$stats['total_jobs'] = $result->fetch_assoc()['count'];

// Active jobs
$result = $conn->query("SELECT COUNT(*) as count FROM job_postings WHERE status = 'active'");
$stats['active_jobs'] = $result->fetch_assoc()['count'];

// Total applications
$result = $conn->query("SELECT COUNT(*) as count FROM applications");
$stats['total_applications'] = $result->fetch_assoc()['count'];

// Recent users
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recent_users = $result->fetch_all(MYSQLI_ASSOC);

// Recent jobs
$result = $conn->query("SELECT jp.*, e.company_name FROM job_postings jp LEFT JOIN employers e ON jp.employer_id = e.employer_id ORDER BY jp.posted_at DESC LIMIT 5");
$recent_jobs = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();

include '../includes/header.php';
?>

<style>
/* Responsive Admin Dashboard Styles */
.admin-container {
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
    margin-bottom: 0.3rem;
    font-size: clamp(1.3rem, 4vw, 1.5rem);
    font-weight: 700;
}

.dashboard-subtitle {
    color: rgba(255,255,255,0.9);
    font-size: clamp(0.8rem, 2.5vw, 0.9rem);
    margin: 0;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
    align-items: stretch;
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
}

.stat-card {
    border: 1px solid #e0e0e0;
    padding: clamp(1rem, 2.5vw, 1.2rem);
    border-radius: 8px;
    transition: all 0.3s ease;
    background: white;
    text-align: center;
}

.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.stat-icon {
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
    width: clamp(35px, 8vw, 45px);
    height: clamp(35px, 8vw, 45px);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.8rem;
    font-size: clamp(0.9rem, 2.5vw, 1.1rem);
}

.stat-value {
    font-size: clamp(1.5rem, 4vw, 1.8rem);
    margin-bottom: 0.3rem;
    line-height: 1.2;
    color: #333;
    font-weight: 700;
}

.stat-label {
    margin: 0;
    color: #666;
    font-weight: 500;
    font-size: clamp(0.75rem, 2vw, 0.85rem);
}

.chatbot-section {
    background: #f8f9fa;
    padding: clamp(1rem, 3vw, 1.2rem);
    border-radius: 8px;
    border-left: 4px solid #9C27B0;
    margin-bottom: 1.5rem;
}

.chatbot-title {
    color: #333;
    margin-bottom: 0.5rem;
    font-size: clamp(1rem, 2.8vw, 1.1rem);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.chatbot-description {
    color: #666;
    margin-bottom: 1rem;
    line-height: 1.5;
    font-size: clamp(0.85rem, 2.2vw, 0.9rem);
}

.chatbot-actions {
    display: flex;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.chatbot-button {
    background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
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
}

.chatbot-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(156, 39, 176, 0.3);
}

.activity-section {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

@media (min-width: 768px) {
    .activity-section {
        grid-template-columns: 1fr 1fr;
    }
}

.activity-column {
    display: flex;
    flex-direction: column;
}

.activity-title {
    margin-bottom: 0.8rem;
    color: #333;
    font-size: clamp(1.1rem, 3vw, 1.2rem);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.activity-list {
    display: grid;
    gap: 1rem;
}

.activity-item {
    border: 1px solid #e0e0e0;
    padding: clamp(0.8rem, 2.5vw, 1rem);
    border-radius: 8px;
    transition: all 0.3s ease;
    background: white;
}

.activity-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #0056b3;
}

.item-header {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 0.8rem;
}

@media (min-width: 480px) {
    .item-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: flex-start;
    }
}

.item-content {
    flex: 1;
}

.item-title {
    color: #1866a3;
    margin-bottom: 0.3rem;
    font-size: clamp(1rem, 2.8vw, 1.1rem);
    font-weight: 600;
    line-height: 1.3;
}

.item-detail {
    color: #666;
    margin-bottom: 0.3rem;
    font-size: clamp(0.8rem, 2.2vw, 0.85rem);
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.item-status {
    text-align: left;
}

@media (min-width: 480px) {
    .item-status {
        text-align: right;
    }
}

.status-badge {
    color: white;
    padding: 0.3rem 0.8rem;
    border-radius: 15px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: clamp(0.65rem, 1.8vw, 0.7rem);
    display: inline-block;
}

.item-actions {
    display: flex;
    gap: 0.8rem;
    align-items: center;
}

.view-button {
    background: #1866a3;
    color: white;
    padding: clamp(0.4rem, 1.5vw, 0.5rem) clamp(0.8rem, 2.5vw, 1rem);
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    font-size: clamp(0.75rem, 2vw, 0.8rem);
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.3s ease;
}

.view-button:hover {
    background: #0056b3;
    transform: translateY(-1px);
}

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
    font-size: clamp(0.9rem, 2.8vw, 1rem);
}

.empty-description {
    font-size: clamp(0.8rem, 2.2vw, 0.85rem);
    margin: 0;
}

/* Color variations for stat cards */
.stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #0056b3 0%, #004494 100%); }
.stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); }
.stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); }
.stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #FF9800 0%, #e55a00 100%); }
.stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); }
.stat-card:nth-child(6) .stat-icon { background: linear-gradient(135deg, #F44336 0%, #d32f2f 100%); }

/* Mobile-specific optimizations */
@media (max-width: 360px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .chatbot-actions {
        flex-direction: column;
    }
    
    .chatbot-button {
        justify-content: center;
    }
}

/* Loading states */
.loading-skeleton {
    opacity: 0.7;
    pointer-events: none;
}

.skeleton-active {
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0% { opacity: 0.7; }
    50% { opacity: 0.4; }
    100% { opacity: 0.7; }
}
</style>

<!-- Full Width Dashboard Header -->
<div class="dashboard-header">
    <div class="dashboard-header-content">
        <h1 class="dashboard-title">
            <i class="fas fa-tachometer-alt"></i> Admin Dashboard
        </h1>
        <p class="dashboard-subtitle">
            System Overview & Management
        </p>
    </div>
</div>

<div class="admin-container">
    <!-- Actual Dashboard Content -->
    <div class="profile-content" id="adminDashboardContent">
        <div class="card" style="margin-top: 0;">
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <!-- Total Users Card -->
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['total_users']; ?></h3>
                    <p class="stat-label">Total Users</p>
                </div>
                
                <!-- Applicants Card -->
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['total_applicants']; ?></h3>
                    <p class="stat-label">Applicants</p>
                </div>
                
                <!-- Employers Card -->
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['total_employers']; ?></h3>
                    <p class="stat-label">Employers</p>
                </div>
                
                <!-- Total Jobs Card -->
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-briefcase"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['total_jobs']; ?></h3>
                    <p class="stat-label">Total Jobs</p>
                </div>
                
                <!-- Active Jobs Card -->
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['active_jobs']; ?></h3>
                    <p class="stat-label">Active Jobs</p>
                </div>
                
                <!-- Applications Card -->
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 class="stat-value"><?php echo $stats['total_applications']; ?></h3>
                    <p class="stat-label">Applications</p>
                </div>
            </div>
            
            <!-- Career Assessment Chatbot Section -->
            <div class="chatbot-section">
                <h3 class="chatbot-title">
                    <i class="fas fa-robot"></i> Career Assessment Chatbot System
                </h3>
                <p class="chatbot-description">
                    Manage and view applicant chatbot assessment data, employability scores, and Q&A responses.
                </p>
                <div class="chatbot-actions">
                    <a href="users.php" class="chatbot-button">
                        <i class="fas fa-users-cog"></i> View Users & Chatbot Data
                    </a>
                </div>
            </div>
            
            <!-- Recent Activity Section -->
            <div class="activity-section">
                <!-- Recent Users -->
                <div class="activity-column">
                    <h2 class="activity-title">
                        <i class="fas fa-users"></i> Recent Users
                    </h2>
                    <?php if (count($recent_users) > 0): ?>
                        <div class="activity-list">
                            <?php foreach ($recent_users as $user): ?>
                                <div class="activity-item">
                                    <div class="item-header">
                                        <div class="item-content">
                                            <h3 class="item-title">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </h3>
                                            <p class="item-detail">
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                                            </p>
                                        </div>
                                        
                                        <div class="item-status">
                                            <?php
                                            $role_colors = [
                                                'admin' => '#F44336',
                                                'employer' => '#2196F3',
                                                'applicant' => '#4CAF50'
                                            ];
                                            $color = $role_colors[$user['role']] ?? '#666';
                                            ?>
                                            <span class="status-badge" style="background: <?php echo $color; ?>;">
                                                <?php echo $user['role']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="item-actions">
                                        <a href="users.php?view=<?php echo $user['user_id']; ?>" class="view-button">
                                            <i class="fas fa-eye"></i>
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users empty-icon"></i>
                            <h3 class="empty-title">No users yet</h3>
                            <p class="empty-description">No users have registered in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Jobs -->
                <div class="activity-column">
                    <h2 class="activity-title">
                        <i class="fas fa-briefcase"></i> Recent Jobs
                    </h2>
                    <?php if (count($recent_jobs) > 0): ?>
                        <div class="activity-list">
                            <?php foreach ($recent_jobs as $job): ?>
                                <div class="activity-item">
                                    <div class="item-header">
                                        <div class="item-content">
                                            <h3 class="item-title">
                                                <?php echo htmlspecialchars($job['title']); ?>
                                            </h3>
                                            <?php if (!empty($job['company_name'])): ?>
                                                <p class="item-detail">
                                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="item-status">
                                            <span class="status-badge" style="background: <?php echo ($job['status'] === 'active') ? '#4CAF50' : '#F44336'; ?>;">
                                                <?php echo $job['status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="item-actions">
                                        <a href="jobs.php?view=<?php echo $job['job_id']; ?>" class="view-button">
                                            <i class="fas fa-eye"></i>
                                            View Details
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-briefcase empty-icon"></i>
                            <h3 class="empty-title">No jobs yet</h3>
                            <p class="empty-description">No jobs have been posted in the system.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Skeleton Loading Animation for Admin Dashboard
document.addEventListener('DOMContentLoaded', function() {
    const content = document.getElementById('adminDashboardContent');
    
    // Add loading state briefly for better UX
    if (content) {
        content.classList.add('loading-skeleton');
        
        setTimeout(function() {
            content.classList.remove('loading-skeleton');
        }, 500);
    }
    
    // Add responsive behavior for very small screens
    function handleResize() {
        const statsGrid = document.querySelector('.stats-grid');
        if (window.innerWidth < 360 && statsGrid) {
            statsGrid.style.gridTemplateColumns = '1fr';
        }
    }
    
    // Initial check
    handleResize();
    
    // Add resize listener
    window.addEventListener('resize', handleResize);
});
</script>

<?php include '../includes/footer.php'; ?>