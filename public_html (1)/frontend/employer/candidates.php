<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "AI-Ranked Candidates";
$user_id = getCurrentUserId();

// Configuration
$SCORING_CONFIG = [
    'experience_multiplier' => 2,
    'employability_multiplier' => 0.3,
    'match_score_fallback' => 50,
    'ranking_thresholds' => [
        'excellent' => 80,
        'good' => 60,
        'average' => 40,
        'poor' => 0
    ]
];

$STATUS_CONFIG = [
    'options' => [
        'all' => 'All Statuses',
        'pending' => 'Pending',
        'reviewed' => 'Reviewed', 
        'shortlisted' => 'Shortlisted',
        'interviewed' => 'Interviewed',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected'
    ],
    'colors' => [
        'pending' => '#FF9800',
        'reviewed' => '#2196F3',
        'shortlisted' => '#9C27B0',
        'interviewed' => '#673AB7',
        'accepted' => '#4CAF50',
        'rejected' => '#F44336'
    ],
    'allowed' => ['pending', 'reviewed', 'shortlisted', 'interviewed', 'accepted', 'rejected']
];

$RANKING_CONFIG = [
    'excellent' => ['color' => '#4CAF50', 'icon' => 'fa-crown', 'label' => 'Excellent'],
    'good' => ['color' => '#2196F3', 'icon' => 'fa-thumbs-up', 'label' => 'Good'],
    'average' => ['color' => '#FF9800', 'icon' => 'fa-chart-line', 'label' => 'Average'],
    'poor' => ['color' => '#F44336', 'icon' => 'fa-exclamation-triangle', 'label' => 'Poor']
];

// Initialize variables
$errors = [];
$candidates = [];
$jobs = [];
$status_counts = array_fill_keys($STATUS_CONFIG['allowed'], 0);
$status_counts['total'] = 0;
$unread_count = 0;

// Get employer ID with error handling
$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed");
}

try {
    // Get employer ID
    $stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare employer query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $employer = $result->fetch_assoc();
    $stmt->close();
    
    if (!$employer) {
        die("Employer profile not found. Please contact administrator.");
    }
    
    $employer_id = $employer['employer_id'];
    
    // Validate and sanitize filters
    $job_id = filter_input(INPUT_GET, 'job_id', FILTER_VALIDATE_INT, ['options' => ['default' => 0, 'min_range' => 0]]);
    
    $status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
    if (!in_array($status_filter, array_keys($STATUS_CONFIG['options']))) {
        $status_filter = 'all';
    }
    
    // Verify job belongs to employer if job_id is specified
    if ($job_id > 0) {
        $stmt = $conn->prepare("SELECT job_id FROM job_postings WHERE job_id = ? AND employer_id = ?");
        $stmt->bind_param("ii", $job_id, $employer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $job_id = 0; // Reset to all jobs if not authorized
            $errors[] = "Selected job not found or you don't have permission to view it.";
        }
        $stmt->close();
    }
    
    // Build query with pagination
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
    $limit = 50; // Candidates per page
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $count_query = "
        SELECT COUNT(*) as total 
        FROM applications a
        JOIN applicants ap ON a.applicant_id = ap.applicant_id
        JOIN job_postings jp ON a.job_id = jp.job_id
        WHERE jp.employer_id = ?
    ";
    
    $count_params = [$employer_id];
    $count_types = "i";
    
    if ($job_id > 0) {
        $count_query .= " AND jp.job_id = ?";
        $count_params[] = $job_id;
        $count_types .= "i";
    }
    
    if ($status_filter !== 'all') {
        $count_query .= " AND a.status = ?";
        $count_params[] = $status_filter;
        $count_types .= "s";
    }
    
    $stmt = $conn->prepare($count_query);
    if (!empty($count_params)) {
        $stmt->bind_param($count_types, ...$count_params);
    }
    $stmt->execute();
    $count_result = $stmt->get_result()->fetch_assoc();
    $total_candidates = $count_result['total'];
    $total_pages = ceil($total_candidates / $limit);
    $stmt->close();
    
    // Get candidates with pagination
    $query = "
        SELECT 
            a.application_id,
            a.status,
            a.match_score,
            a.applied_at,
            ap.applicant_id,
            ap.employability_score,
            ap.experience_years,
            ap.education_level,
            ap.skills,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            jp.title as job_title,
            jp.job_id
        FROM applications a
        JOIN applicants ap ON a.applicant_id = ap.applicant_id
        JOIN users u ON ap.user_id = u.user_id
        JOIN job_postings jp ON a.job_id = jp.job_id
        WHERE jp.employer_id = ?
    ";
    
    $params = [$employer_id];
    $types = "i";
    
    if ($job_id > 0) {
        $query .= " AND jp.job_id = ?";
        $params[] = $job_id;
        $types .= "i";
    }
    
    if ($status_filter !== 'all') {
        $query .= " AND a.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    $query .= " ORDER BY a.match_score DESC, a.applied_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Calculate AI ranking scores
    foreach ($candidates as &$candidate) {
        // Calculate ML ranking score
        $score = $candidate['match_score'] ?? $SCORING_CONFIG['match_score_fallback'];
        $score += ($candidate['experience_years'] ?? 0) * $SCORING_CONFIG['experience_multiplier'];
        $score += ($candidate['employability_score'] ?? 0) * $SCORING_CONFIG['employability_multiplier'];
        
        // Ensure score is within 0-100 range
        $candidate['ml_ranking_score'] = min(100, max(0, round($score, 1)));
        
        // Determine ranking category
        foreach ($SCORING_CONFIG['ranking_thresholds'] as $category => $threshold) {
            if ($candidate['ml_ranking_score'] >= $threshold) {
                $candidate['ranking_category'] = $category;
                break;
            }
        }
        
        // Count by status
        if (isset($status_counts[$candidate['status']])) {
            $status_counts[$candidate['status']]++;
        }
    }
    
    // Sort by score for ranking display
    usort($candidates, function($a, $b) {
        return $b['ml_ranking_score'] <=> $a['ml_ranking_score'];
    });
    
    // Get jobs for filter (only this employer's jobs)
    $stmt = $conn->prepare("
        SELECT job_id, title 
        FROM job_postings 
        WHERE employer_id = ? AND status != 'draft' 
        ORDER BY posted_at DESC
    ");
    $stmt->bind_param("i", $employer_id);
    $stmt->execute();
    $jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get unread message count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as unread_count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $unread_count = $row['unread_count'];
    }
    $stmt->close();
    
} catch (Exception $e) {
    error_log("Error in candidates page: " . $e->getMessage());
    $errors[] = "An error occurred while loading candidates. Please try again.";
} finally {
    $conn->close();
}

// Count ranking categories
$ranking_counts = [];
foreach ($RANKING_CONFIG as $category => $config) {
    $ranking_counts[$category] = count(array_filter(
        $candidates, 
        fn($c) => ($c['ranking_category'] ?? '') === $category
    ));
}

include '../includes/header.php';
?>

<!-- Include CSS -->
<style>
:root {
    --color-primary: #1866a3;
    --color-success: #4CAF50;
    --color-warning: #FF9800;
    --color-danger: #F44336;
    --color-info: #2196F3;
    --color-secondary: #6c757d;
    --color-light: #f8f9fa;
    --color-dark: #333;
}

.container-lg {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

.page-header {
    background: linear-gradient(135deg, var(--color-primary) 0%, #1a4a7a 100%);
    padding: 1.5rem 0;
    margin: 0 -8px 1rem -8px;
}

.page-header-content {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
    text-align: center;
    color: white;
}

.page-title {
    color: white;
    margin-bottom: 0.3rem;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.filter-section {
    display: flex;
    gap: 0.8rem;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}

.form-select {
    padding: 0.6rem;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 0.9rem;
    min-width: 200px;
    background-color: white;
}

.btn {
    padding: 0.6rem 1.2rem;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    text-decoration: none;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--color-primary);
    color: white;
}

.btn-primary:hover {
    background: #1a4a7a;
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--color-secondary);
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.8rem;
    margin-bottom: 1rem;
}

.status-card {
    text-align: center;
    padding: 0.8rem;
    background: white;
    border-radius: 6px;
    border: 2px solid;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    color: inherit;
}

.status-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.status-card.active {
    box-shadow: 0 0 0 3px;
}

.ranking-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.8rem;
    margin-bottom: 1.5rem;
}

.ranking-card {
    text-align: center;
    padding: 0.8rem;
    background: white;
    border-radius: 6px;
    border: 2px solid;
}

.kanban-board {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.kanban-column {
    background: var(--color-light);
    border-radius: 8px;
    padding: 0.8rem;
    min-height: 500px;
    border: 1px solid #e0e0e0;
}

.kanban-header {
    color: white;
    padding: 0.8rem;
    border-radius: 6px;
    margin-bottom: 0.8rem;
    text-align: center;
}

.candidate-card {
    border: 2px solid;
    padding: 0.8rem;
    border-radius: 6px;
    background: white;
    position: relative;
    margin-bottom: 0.8rem;
    transition: all 0.2s;
}

.candidate-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.rank-badge {
    position: absolute;
    top: -8px;
    left: 8px;
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-weight: bold;
    font-size: 0.7rem;
}

.status-badge {
    position: absolute;
    top: -8px;
    right: 8px;
    color: white;
    padding: 0.2rem 0.6rem;
    border-radius: 12px;
    font-weight: bold;
    font-size: 0.7rem;
    text-transform: uppercase;
}

.score-display {
    padding: 0.6rem;
    border-radius: 4px;
    margin-bottom: 0.6rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.4rem;
    margin-bottom: 0.6rem;
}

.stat-item {
    text-align: center;
    padding: 0.4rem;
    border-radius: 4px;
}

.action-buttons {
    display: flex;
    gap: 0.4rem;
    justify-content: center;
}

.action-btn {
    padding: 0.4rem 0.8rem;
    border-radius: 4px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.legend {
    padding: 0.8rem;
    background: var(--color-light);
    border-radius: 6px;
    border: 1px solid #e0e0e0;
    margin-top: 1.5rem;
}

.legend-items {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.legend-item {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.legend-color {
    width: 12px;
    height: 12px;
    border-radius: 2px;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1.5rem;
}

.page-link {
    padding: 0.4rem 0.8rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    text-decoration: none;
    color: var(--color-primary);
}

.page-link.active {
    background: var(--color-primary);
    color: white;
    border-color: var(--color-primary);
}

.alert {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1rem;
}

.alert-danger {
    background-color: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

/* Floating message icon styles */
.floating-message-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

.floating-message-btn {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--color-primary) 0%, #1a4a7a 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
    text-decoration: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    position: relative;
    transition: all 0.3s;
}

.floating-message-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
}

.message-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: var(--color-danger);
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.message-tooltip {
    position: absolute;
    right: 70px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 0.5rem 0.8rem;
    border-radius: 4px;
    font-size: 0.8rem;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
}

.floating-message-btn:hover .message-tooltip {
    opacity: 1;
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: #666;
}

.empty-state p {
    margin: 0;
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .kanban-board {
        grid-template-columns: 1fr;
    }
    
    .status-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-select {
        width: 100%;
    }
}
</style>

<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 1.5rem 0; margin: 0 -8px 1rem -8px;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.3rem; font-size: 1.5rem;">
                <i class="fas fa-users"></i> Candidates
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin: 0;">
                Candidate Profiles & Application Insights
            </p>
        </div>
    </div>
</div>

<div class="container-lg">
    <!-- Error Messages -->
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p style="margin: 0 0 0.5rem 0;"><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <!-- Filters Section -->
        <div class="filter-section">
            <form method="GET" class="filter-form">
                <input type="hidden" name="page" value="1">
                
                <select name="job_id" class="form-select">
                    <option value="0">All Jobs</option>
                    <?php foreach ($jobs as $job): ?>
                        <option value="<?php echo htmlspecialchars($job['job_id']); ?>" 
                            <?php echo ($job_id == $job['job_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($job['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <select name="status" class="form-select">
                    <?php foreach ($STATUS_CONFIG['options'] as $value => $label): ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" 
                            <?php echo ($status_filter == $value) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                
                <?php if ($job_id > 0 || $status_filter !== 'all'): ?>
                    <a href="?job_id=0&status=all&page=1" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </form>
        </div>
        
<!-- Status Summary -->
<div style="margin-bottom: 1.5rem;">
    <h3 style="margin-bottom: 0.8rem; font-size: 1rem; color: #333; display: flex; align-items: center; gap: 0.5rem;">
        <i class="fas fa-chart-pie"></i> Application Status Summary
        <span style="font-size: 0.8rem; color: #666; margin-left: 0.5rem;">
            (Total: <?php echo $status_counts['total']; ?> candidates)
        </span>
    </h3>
    <div class="status-grid">
        <?php 
        // First, get total counts for each status (unfiltered)
        $total_status_counts = array_fill_keys($STATUS_CONFIG['allowed'], 0);
        
        try {
            $conn = getDBConnection();
            if ($conn) {
                // Query to get total counts for each status for this employer
                $count_query = "
                    SELECT a.status, COUNT(*) as count
                    FROM applications a
                    JOIN job_postings jp ON a.job_id = jp.job_id
                    WHERE jp.employer_id = ?
                    GROUP BY a.status
                ";
                
                $stmt = $conn->prepare($count_query);
                $stmt->bind_param("i", $employer_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    if (isset($total_status_counts[$row['status']])) {
                        $total_status_counts[$row['status']] = $row['count'];
                    }
                }
                $stmt->close();
                $conn->close();
            }
        } catch (Exception $e) {
            // Fallback to filtered counts if query fails
            $total_status_counts = $status_counts;
        }
        
        $status_display = array_keys($STATUS_CONFIG['colors']);
        foreach ($status_display as $status): 
            $is_active = $status_filter === $status;
        ?>
            <a href="?job_id=<?php echo $job_id; ?>&status=<?php echo $status; ?>&page=1" 
               class="status-card <?php echo $is_active ? 'active' : ''; ?>"
               style="border-color: <?php echo $STATUS_CONFIG['colors'][$status]; ?>;">
                <div style="font-size: 1.2rem; font-weight: bold; color: <?php echo $STATUS_CONFIG['colors'][$status]; ?>;">
                    <?php echo $total_status_counts[$status]; ?>
                </div>
                <div style="color: #666; font-size: 0.75rem; text-transform: capitalize;">
                    <?php echo $status; ?>
                </div>
                <?php if ($is_active && $status_counts[$status] != $total_status_counts[$status]): ?>
                    <div style="font-size: 0.6rem; color: #666; margin-top: 0.2rem;">
                        (<?php echo $status_counts[$status]; ?> filtered)
                    </div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>
        
        <!-- AI Ranking Summary -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 0.8rem; font-size: 1rem; color: #333; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-brain"></i> AI Ranking Summary
            </h3>
            <div class="ranking-grid">
                <?php foreach ($ranking_counts as $category => $count): ?>
                    <div class="ranking-card" style="border-color: <?php echo $RANKING_CONFIG[$category]['color']; ?>;">
                        <div style="font-size: 1.5rem; font-weight: bold; color: <?php echo $RANKING_CONFIG[$category]['color']; ?>;">
                            <?php echo $count; ?>
                        </div>
                        <div style="color: #666; font-size: 0.8rem;">
                            <?php echo $RANKING_CONFIG[$category]['label']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if (count($candidates) > 0): ?>
            <!-- Kanban Board -->
            <div class="kanban-board">
                <?php
                $global_rank = 1;
                
                foreach ($RANKING_CONFIG as $cat => $config): 
                    $cat_candidates = array_filter($candidates, fn($c) => ($c['ranking_category'] ?? '') === $cat);
                ?>
                    <div class="kanban-column">
                        <div class="kanban-header" style="background: <?php echo $config['color']; ?>;">
                            <h3 style="margin: 0; font-size: 1rem; font-weight: 600;">
                                <i class="fas <?php echo $config['icon']; ?>"></i> 
                                <?php echo $config['label']; ?>
                                <span style="font-size: 0.8rem;">(<?php echo count($cat_candidates); ?>)</span>
                            </h3>
                        </div>
                        <div class="candidate-list">
                            <?php foreach ($cat_candidates as $candidate): ?>
                                <div class="candidate-card" style="border-color: <?php echo $config['color']; ?>;">
                                    <div class="rank-badge" style="background: <?php echo $config['color']; ?>;">
                                        #<?php echo $global_rank++; ?>
                                    </div>
                                    
                                    <!-- Status Badge -->
                                    <div class="status-badge" 
                                         style="background: <?php echo $STATUS_CONFIG['colors'][$candidate['status']] ?? '#666'; ?>;">
                                        <?php echo substr($candidate['status'], 0, 3); ?>
                                    </div>
                                    
                                    <div style="margin-bottom: 0.6rem; margin-top: 0.4rem;">
                                        <h4 style="color: #333; margin: 0 0 0.3rem 0; font-size: 0.9rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                        </h4>
                                        <p style="color: #666; margin: 0 0 0.2rem 0; font-size: 0.75rem;">
                                            <i class="fas fa-briefcase"></i> 
                                            <?php echo htmlspecialchars($candidate['job_title']); ?>
                                        </p>
                                        <p style="color: <?php echo $STATUS_CONFIG['colors'][$candidate['status']] ?? '#666'; ?>; 
                                           margin: 0; font-size: 0.7rem; font-weight: 600;">
                                            <i class="fas fa-flag"></i> 
                                            <?php echo htmlspecialchars(ucfirst($candidate['status'])); ?>
                                        </p>
                                    </div>
                                    
                                    <div class="score-display" style="background: <?php echo $config['color']; ?>;">
                                        <p style="font-size: 1rem; font-weight: bold; color: white; margin: 0; text-align: center;">
                                            <?php echo number_format($candidate['ml_ranking_score'], 1); ?>%
                                        </p>
                                    </div>
                                    
                                    <div class="stats-grid">
                                        <div class="stat-item" style="background: #e8f5e8;">
                                            <p style="font-size: 0.8rem; color: #4CAF50; font-weight: 600; margin: 0;">
                                                <?php echo $candidate['experience_years']; ?> yrs
                                            </p>
                                        </div>
                                        <div class="stat-item" style="background: #e3f2fd;">
                                            <p style="font-size: 0.8rem; color: #2196F3; font-weight: 600; margin: 0;">
                                                <?php echo htmlspecialchars(substr($candidate['education_level'], 0, 12)); ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="action-buttons">
                                        <a href="view_candidate.php?id=<?php echo $candidate['application_id']; ?>" 
                                           class="action-btn" style="background: var(--color-primary); color: white;">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="../messages/send_message.php?applicant_id=<?php echo $candidate['applicant_id']; ?>&job_id=<?php echo $job_id; ?>" 
                                           class="action-btn" style="background: var(--color-success); color: white;">
                                            <i class="fas fa-envelope"></i> Message
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($cat_candidates) === 0): ?>
                                <div style="text-align: center; color: #999; padding: 1.5rem; font-style: italic; 
                                     background: white; border-radius: 4px; border: 2px dashed #e0e0e0;">
                                    <p style="margin: 0; font-size: 0.8rem;">No <?php echo strtolower($config['label']); ?> candidates</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?job_id=<?php echo $job_id; ?>&status=<?php echo $status_filter; ?>&page=<?php echo $i; ?>"
                           class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
            
            <!-- Status Legend -->
            <div class="legend">
                <h4 style="margin: 0 0 0.5rem 0; font-size: 0.9rem; color: #333;">
                    <i class="fas fa-key"></i> Status Legend
                </h4>
                <div class="legend-items">
                    <?php foreach ($STATUS_CONFIG['colors'] as $status => $color): ?>
                        <div class="legend-item">
                            <div class="legend-color" style="background: <?php echo $color; ?>;"></div>
                            <span style="font-size: 0.8rem; color: #666; text-transform: capitalize;">
                                <?php echo $status; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
        <?php else: ?>
            <div class="empty-state">
                <p style="margin: 0; font-size: 0.9rem;">No candidates found with the selected filters.</p>
                <a href="?job_id=0&status=all&page=1" class="btn btn-secondary" style="margin-top: 1rem;">
                    <i class="fas fa-times"></i> Clear All Filters
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Floating Message Icon -->
<div class="floating-message-container">
    <a href="../messages/chat.php" class="floating-message-btn" title="Chat" aria-label="Chat">
        <i class="fas fa-comments"></i>
        <?php if ($unread_count > 0): ?>
            <span class="message-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
        <?php endif; ?>
        <span class="message-tooltip">
            <?php echo $unread_count > 0 ? "You have $unread_count unread message(s)" : "Go to Chat"; ?>
        </span>
    </a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add confirmation for status updates
    const statusLinks = document.querySelectorAll('a[href*="update_status.php"]');
    statusLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const newStatus = this.textContent.trim().split(' ')[1];
            const candidateName = this.closest('.candidate-card')?.querySelector('h4')?.textContent || 'this candidate';
            
            if (confirm(`Change status to ${newStatus.toUpperCase()} for ${candidateName}?`)) {
                window.location.href = this.href;
            }
        });
    });
    
    // Quick status update with AJAX (optional enhancement)
    document.querySelectorAll('.quick-status-select').forEach(select => {
        select.addEventListener('change', function() {
            const applicationId = this.dataset.applicationId;
            const newStatus = this.value;
            
            fetch('update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    application_id: applicationId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    const statusBadge = this.closest('.candidate-card').querySelector('.status-badge');
                    statusBadge.textContent = newStatus.substring(0, 3).toUpperCase();
                    statusBadge.style.background = getStatusColor(newStatus);
                    
                    // Show success message
                    showToast('Status updated successfully');
                }
            });
        });
    });
    
    function getStatusColor(status) {
        const colors = <?php echo json_encode($STATUS_CONFIG['colors']); ?>;
        return colors[status] || '#666';
    }
    
    function showToast(message) {
        // Simple toast notification
        const toast = document.createElement('div');
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #4CAF50;
            color: white;
            padding: 1rem;
            border-radius: 4px;
            z-index: 10000;
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }
});
</script>

<?php include '../includes/footer.php'; ?>