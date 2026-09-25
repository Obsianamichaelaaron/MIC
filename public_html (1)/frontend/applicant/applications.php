<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

$pageTitle = "My Applications";
$user_id = getCurrentUserId();

// Get applicant ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$applicant_id = $applicant['applicant_id'];
$stmt->close();

// Get applications with resume information
$stmt = $conn->prepare("
    SELECT a.*, jp.title, jp.location, jp.employment_type, jp.skills_required, jp.requirements, e.company_name,
           ap.skills, ap.qualifications, ap.experience_years, ap.resume_file as profile_resume_file
    FROM applications a 
    JOIN job_postings jp ON a.job_id = jp.job_id 
    JOIN applicants ap ON a.applicant_id = ap.applicant_id
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    WHERE a.applicant_id = ? 
    ORDER BY a.applied_at DESC
");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate base match score and resume quality for each application
require_once '../includes/handlers/resume_parser.php';
foreach ($applications as &$app) {
    // Get the resume file (application-specific or profile)
    $resume_file = !empty($app['resume_file']) ? $app['resume_file'] : $app['profile_resume_file'];
    
    // Calculate resume quality score if resume exists
    $resume_quality_score = 0;
    if ($resume_file && file_exists('../' . $resume_file)) {
        $resume_analysis = getResumeAnalysis($conn, $app['applicant_id']);
        if ($resume_analysis) {
            if (!empty($resume_analysis['skills_extracted'])) $resume_quality_score += 3;
            if (!empty($resume_analysis['education_extracted']) || !empty($resume_analysis['qualifications_extracted'])) $resume_quality_score += 3;
            if (!empty($resume_analysis['experience_extracted'])) $resume_quality_score += 2;
            if (!empty($resume_analysis['extracted_text']) && strlen($resume_analysis['extracted_text']) > 500) $resume_quality_score += 2;
        }
    }
    
    // Calculate base match score (final score minus resume quality)
    $app['base_match_score'] = max(0, round($app['match_score'] - $resume_quality_score, 2));
    $app['resume_quality_score'] = round($resume_quality_score, 2);
    $app['has_resume'] = !empty($resume_file);
}
unset($app);

// Get feedback for all applications
$feedback_map = [];
$table_check = $conn->query("SHOW TABLES LIKE 'candidate_feedback'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt_feedback = $conn->prepare("
        SELECT cf.*, e.company_name, u.first_name as employer_first, u.last_name as employer_last, jp.title as job_title
        FROM candidate_feedback cf
        JOIN employers e ON cf.employer_id = e.employer_id
        JOIN users u ON e.user_id = u.user_id
        JOIN applications a ON cf.application_id = a.application_id
        JOIN job_postings jp ON a.job_id = jp.job_id
        WHERE cf.applicant_id = ?
        ORDER BY cf.created_at DESC
    ");
    $stmt_feedback->bind_param("i", $applicant_id);
    $stmt_feedback->execute();
    $all_feedback = $stmt_feedback->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_feedback->close();
    
    // Map feedback by application_id
    foreach ($all_feedback as $feedback) {
        $feedback_map[$feedback['application_id']] = $feedback;
    }
}

$conn->close();

include '../includes/header.php';
?>

<style>
.applications-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 1rem;
}

.applications-header {
    background: url('../images/bg.jpg') center/cover no-repeat;
    padding: 1.5rem 0;
    margin: 0 -8px 1rem -8px;
    text-align: center;
    color: white;
}

.applications-header h1 {
    color: white;
    margin-bottom: 0.3rem;
    font-size: 1.5rem;
    font-weight: 700;
}

.applications-header p {
    color: rgba(255,255,255,0.9);
    font-size: 0.9rem;
    margin-bottom: 0;
}

.applications-count {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin-bottom: 1.2rem;
    padding: 0 0.5rem;
}

@media (min-width: 768px) {
    .applications-count {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.applications-count h2 {
    color: #333;
    margin: 0;
    font-size: 1.2rem;
    font-weight: 600;
}

.applications-count .sort-info {
    color: #666;
    font-size: 0.85rem;
}

.application-card {
    border: 1px solid #e0e0e0;
    padding: 1.2rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    background: white;
    margin-bottom: 1.2rem;
}

.application-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #1866a3;
}

.application-header {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    margin-bottom: 0.8rem;
}

@media (min-width: 768px) {
    .application-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: flex-start;
    }
}

.application-info {
    flex: 1;
}

.application-title {
    color: #1866a3;
    margin-bottom: 0.4rem;
    font-size: 1.1rem;
    font-weight: 600;
    line-height: 1.3;
}

.company-name {
    color: #666;
    margin-bottom: 0.4rem;
    font-size: 0.95rem;
    font-weight: 500;
}

.application-location {
    color: #666;
    margin-bottom: 0.4rem;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
}

.application-status {
    text-align: left;
}

@media (min-width: 768px) {
    .application-status {
        text-align: right;
    }
}

.status-badge {
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 15px;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    display: inline-block;
    margin-bottom: 0.4rem;
}

.score-button {
    color: white;
    padding: 0.4rem 0.8rem;
    border: none;
    border-radius: 15px;
    font-weight: 600;
    cursor: pointer;
    font-size: 0.8rem;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    transition: transform 0.2s;
}

.score-button:hover {
    transform: translateY(-1px);
}

.status-notice {
    padding: 0.8rem;
    border-radius: 6px;
    margin-bottom: 0.8rem;
    border-left: 4px solid;
    font-size: 0.85rem;
}

.notice-text {
    color: #333;
    margin: 0;
    line-height: 1.4;
    font-weight: 500;
}

.application-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.8rem;
    margin-bottom: 0.8rem;
    align-items: center;
}

.detail-badge {
    padding: 0.3rem 0.6rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
}

.score-breakdown {
    background: #f8f9fa;
    padding: 0.8rem;
    border-radius: 6px;
    margin-bottom: 0.8rem;
    border: 1px solid #e9ecef;
}

.score-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.4rem;
}

.score-grid {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.4rem;
    align-items: center;
    font-size: 0.85rem;
}

.feedback-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 0.8rem;
    color: white;
}

.feedback-header {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-bottom: 0.6rem;
}

@media (min-width: 480px) {
    .feedback-header {
        flex-direction: row;
        justify-content: space-between;
        align-items: start;
    }
}

.feedback-content {
    color: white;
    line-height: 1.4;
    margin-bottom: 0.6rem;
    font-size: 0.85rem;
}

.feedback-footer {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
    padding-top: 0.6rem;
    border-top: 1px solid rgba(255,255,255,0.3);
    font-size: 0.8rem;
}

@media (min-width: 480px) {
    .feedback-footer {
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
    }
}

.action-buttons {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    align-items: stretch;
}

@media (min-width: 480px) {
    .action-buttons {
        flex-direction: row;
        align-items: center;
    }
}

.action-button {
    color: white;
    padding: 0.6rem 1.2rem;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    text-align: center;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    flex: 1;
}

.action-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 10000;
    overflow-y: auto;
    padding: 1rem;
}

.modal-content {
    max-width: 500px;
    margin: 2rem auto;
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    width: 100%;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.2rem;
}

.modal-header h2 {
    color: #1866a3;
    margin: 0;
    font-size: 1.2rem;
}

.close-button {
    background: #f44336;
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
    color: #666;
}

.empty-state-icon {
    font-size: 2.5rem;
    color: #ddd;
    margin-bottom: 0.8rem;
}

/* Form Styles */
.form-group {
    margin-bottom: 1.2rem;
}

.form-label {
    display: block;
    color: #333;
    font-weight: 600;
    margin-bottom: 0.4rem;
    font-size: 0.9rem;
}

.form-textarea {
    width: 100%;
    padding: 0.6rem;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 0.85rem;
    resize: vertical;
    min-height: 100px;
}

.file-upload-area {
    border: 2px dashed #e0e0e0;
    border-radius: 6px;
    padding: 1.2rem;
    text-align: center;
    background: #f8f9fa;
}

.file-input {
    width: 100%;
    margin-bottom: 0.4rem;
}

.file-info {
    color: #666;
    font-size: 0.75rem;
    margin: 0;
}

.form-actions {
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

@media (min-width: 480px) {
    .form-actions {
        flex-direction: row;
    }
}

/* Utility Classes */
.text-center { text-align: center; }
.mb-1 { margin-bottom: 1rem; }
.mt-1 { margin-top: 1rem; }
</style>

<!-- Full Width Header Section -->
<div class="applications-header">
    <div class="applications-container">
        <h1><i class="fas fa-file-alt"></i> My Applications</h1>
        <p>Track and manage all your job applications in one place</p>
    </div>
</div>

<div class="container" style="max-width: 1400px; padding: 0 1rem;">
    <div class="card" style="margin-top: 0;">
        <!-- Applications Count -->
        <div class="applications-count">
            <h2>
                <?php echo count($applications); ?> application<?php echo count($applications) !== 1 ? 's' : ''; ?> found
            </h2>
            <div class="sort-info">
                Sorted by: <strong>Most recent</strong>
            </div>
        </div>
        
        <?php if (count($applications) > 0): ?>
            <div>
                <?php foreach ($applications as $app): ?>
                    <div class="application-card">
                        
                        <!-- Application Header -->
                        <div class="application-header">
                            <div class="application-info">
                                <h2 class="application-title">
                                    <?php echo htmlspecialchars($app['title']); ?>
                                </h2>
                                <?php if (!empty($app['company_name'])): ?>
                                <p class="company-name">
                                    <?php echo htmlspecialchars($app['company_name']); ?>
                                </p>
                                <?php endif; ?>
                                <p class="application-location">
                                    <i class="fas fa-map-marker-alt" style="color: #ff6a00;"></i>
                                    <?php echo htmlspecialchars($app['location'] ?? 'Location Not Specified'); ?>
                                </p>
                            </div>
                            
                            <!-- Status and Match Score -->
                            <div class="application-status">
                                <?php
                                // First, get the status from database and normalize it
                                $db_status = strtolower(trim($app['status']));
                                $display_status = $db_status;
                                
                                // Map database status to display status
                                $status_mapping = [
                                    'scheduled' => 'scheduled',
                                    'scheduled for interview' => 'scheduled',
                                    'interview scheduled' => 'scheduled',
                                    'scheduled_for_interview' => 'scheduled',
                                    'pending' => 'pending',
                                    'reviewed' => 'reviewed',
                                    'shortlisted' => 'shortlisted',
                                    'accepted' => 'accepted',
                                    'rejected' => 'rejected'
                                ];
                                
                                // Normalize the status for display
                                if (isset($status_mapping[$db_status])) {
                                    $display_status = $status_mapping[$db_status];
                                }
                                
                                // Status colors for the normalized status
                                $status_colors = [
                                    'pending' => '#FF9800',
                                    'reviewed' => '#2196F3',
                                    'shortlisted' => '#9C27B0',
                                    'scheduled' => '#673AB7',
                                    'accepted' => '#4CAF50',
                                    'rejected' => '#F44336'
                                ];
                                
                                $color = $status_colors[$display_status] ?? '#666';
                                ?>
                                <span class="status-badge" style="background: <?php echo $color; ?>;">
                                    <?php echo ucfirst($display_status); ?>
                                </span>
                                
                                <?php if ($app['match_score'] > 0): ?>
                                    <?php 
                                    $score_color = $app['match_score'] >= 70 ? '#4CAF50' : ($app['match_score'] >= 50 ? '#FF9800' : '#2196F3');
                                    ?>
                                    <button onclick="showMatchScorePopup(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['title'], ENT_QUOTES); ?>', <?php echo $app['match_score']; ?>, <?php echo $app['base_match_score']; ?>, <?php echo $app['resume_quality_score']; ?>, <?php echo $app['has_resume'] ? 'true' : 'false'; ?>)" 
                                            class="score-button" style="background: <?php echo $score_color; ?>;">
                                        <i class="fas fa-star"></i> <?php echo number_format($app['match_score'], 1); ?>%
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Status Notice -->
                        <?php
                        // Status notices - using the normalized display status
                        $status_notices = [
                            'pending' => "You're in a large pool of all applicants.",
                            'reviewed' => "Your application has been looked at.",
                            'shortlisted' => "You have been shortlisted. We will contact you soon to schedule an interview.",
                            'scheduled' => "Your interview has been scheduled. Please check your email for details.",
                            'accepted' => "Congratulations! You have been accepted.",
                            'rejected' => "Thank you for your interest. We have chosen to proceed with another candidate."
                        ];
                        $notice = $status_notices[$display_status] ?? '';
                        if ($notice): 
                        ?>
                        <div class="status-notice" style="background: <?php echo $color; ?>15; border-left-color: <?php echo $color; ?>;">
                            <p class="notice-text">
                                <i class="fas fa-info-circle" style="color: <?php echo $color; ?>; margin-right: 0.4rem;"></i> 
                                <strong>Status Update:</strong> <?php echo htmlspecialchars($notice); ?>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Application Details -->
                        <div class="application-details">
                            <span class="detail-badge" style="background: #f0f8ff; color: #1866a3;">
                                <i class="fas fa-briefcase"></i>
                                <?php echo ucfirst($app['employment_type']); ?>
                            </span>
                            
                            <span class="detail-badge" style="background: #fff3cd; color: #856404;">
                                <i class="fas fa-clock"></i>
                                Applied <?php echo date('M d, Y', strtotime($app['applied_at'])); ?>
                            </span>
                            
                            <?php if (!empty($app['reviewed_by_name'])): ?>
                                <span style="color: #999; font-size: 0.8rem; text-align: center;">
                                    <i class="fas fa-user-check" style="margin-right: 0.3rem;"></i>
                                    Reviewed by <?php echo htmlspecialchars($app['reviewed_by_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Match Score Breakdown -->
                        <?php if ($app['match_score'] > 0): ?>
                            <div class="score-breakdown">
                                <div class="score-header">
                                    <span style="color: #333; font-size: 0.85rem; font-weight: 600;">
                                        <i class="fas fa-chart-bar"></i> Score Breakdown
                                    </span>
                                    <span style="color: #4CAF50; font-weight: 700; font-size: 0.9rem;">
                                        <?php echo number_format($app['match_score'], 1); ?>%
                                    </span>
                                </div>
                                
                                <div class="score-grid">
                                    <div>
                                        <div style="color: #666; font-size: 0.75rem;">Base Match Score</div>
                                        <div style="color: #0056b3; font-weight: 600; font-size: 0.85rem;">
                                            <?php echo number_format($app['base_match_score'], 1); ?>%
                                        </div>
                                    </div>
                                    
                                    <?php if ($app['resume_quality_score'] > 0): ?>
                                        <div style="text-align: right;">
                                            <div style="color: #2e7d32; font-size: 0.75rem;">Resume Bonus</div>
                                            <div style="color: #2e7d32; font-weight: 700; font-size: 0.85rem;">
                                                +<?php echo number_format($app['resume_quality_score'], 1); ?>%
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Employer Feedback -->
                        <?php if (isset($feedback_map[$app['application_id']])): 
                            $feedback = $feedback_map[$app['application_id']];
                        ?>
                            <div class="feedback-card">
                                <div class="feedback-header">
                                    <h4 style="color: white; margin: 0; font-size: 0.9rem; font-weight: 600;">
                                        <i class="fas fa-comments"></i> Employer Feedback
                                    </h4>
                                    <span style="background: rgba(255,255,255,0.2); padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.65rem; font-weight: 500;">
                                        <?php echo ucfirst($feedback['feedback_type']); ?>
                                    </span>
                                </div>
                                <p class="feedback-content">
                                    <?php echo htmlspecialchars($feedback['feedback_message']); ?>
                                </p>
                                <div class="feedback-footer">
                                    <p style="color: rgba(255,255,255,0.9); margin: 0;">
                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($feedback['employer_first'] . ' ' . $feedback['employer_last']); ?>
                                    </p>
                                    <p style="color: rgba(255,255,255,0.8); margin: 0;">
                                        <i class="fas fa-clock"></i> <?php echo date('M d, Y', strtotime($feedback['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Action Buttons -->
                        <div class="action-buttons">
                            <a href="job_details.php?id=<?php echo $app['job_id']; ?>" 
                               class="action-button" style="background: #1866a3;">
                               <i class="fas fa-eye"></i>
                                View Job
                            </a>
                            
                            <button onclick="showApplicationDetails(<?php echo $app['application_id']; ?>, '<?php echo htmlspecialchars($app['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($app['company_name'], ENT_QUOTES); ?>', '<?php echo $app['applied_at']; ?>', '<?php echo htmlspecialchars($app['cover_letter'] ?? 'No cover letter provided', ENT_QUOTES); ?>', '<?php echo $app['resume_file'] ?? $app['profile_resume_file']; ?>')" 
                                    class="action-button" style="background: #6c757d;">
                                <i class="fas fa-info-circle"></i>
                                Details
                            </button>
                            
                            <?php if ($app['status'] === 'pending' || $app['status'] === 'reviewed'): ?>
                                <button onclick="showUpdateApplicationModal(<?php echo $app['application_id']; ?>, <?php echo $app['job_id']; ?>, '<?php echo htmlspecialchars($app['cover_letter'] ?? '', ENT_QUOTES); ?>', '<?php echo $app['resume_file'] ?? ''; ?>')" 
                                        class="action-button" style="background: #ff6a00;">
                                    <i class="fas fa-edit"></i>
                                    Update
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-file-alt empty-state-icon"></i>
                <h3 style="color: #333; margin-bottom: 0.4rem; font-size: 1.1rem;">No applications yet</h3>
                <p style="font-size: 0.9rem;">Start applying to jobs to see your applications here.</p>
                <a href="jobs.php" 
                   class="action-button" style="background: #1866a3; max-width: 180px; margin: 0.8rem auto 0;">
                    <i class="fas fa-search"></i>
                    Browse Jobs
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Match Score Popup -->
<div id="matchScorePopup" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-star"></i> Match Score Breakdown</h2>
            <button onclick="closeMatchScorePopup()" class="close-button">×</button>
        </div>
        
        <div id="matchScoreContent">
            <!-- Content will be inserted here -->
        </div>
        
        <div class="text-center mt-1">
            <button onclick="closeMatchScorePopup()" class="action-button" style="background: #1866a3; max-width: 180px; margin: 0 auto;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Application Details Modal -->
<div id="applicationDetailsModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-info-circle"></i> Application Details</h2>
            <button onclick="closeApplicationDetailsModal()" class="close-button">×</button>
        </div>
        
        <div id="applicationDetailsContent">
            <!-- Content will be inserted here -->
        </div>
        
        <div class="text-center mt-1">
            <button onclick="closeApplicationDetailsModal()" class="action-button" style="background: #1866a3; max-width: 180px; margin: 0 auto;">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Update Application Modal -->
<div id="updateApplicationModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> Update Application</h2>
            <button onclick="closeUpdateApplicationModal()" class="close-button">×</button>
        </div>
        
        <form id="updateApplicationForm" method="POST" action="update_application.php" enctype="multipart/form-data">
            <input type="hidden" id="updateApplicationId" name="application_id">
            <input type="hidden" id="updateJobId" name="job_id">
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-file-alt"></i> Cover Letter
                </label>
                <textarea id="updateCoverLetter" name="cover_letter" class="form-textarea" placeholder="Update your cover letter..."></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-file-pdf"></i> Resume (Optional)
                </label>
                <div class="file-upload-area">
                    <input type="file" id="updateResume" name="resume" accept=".pdf,.doc,.docx" class="file-input">
                    <p class="file-info">
                        Accepted formats: PDF, DOC, DOCX (Max: 5MB)
                    </p>
                    <p id="currentResume" style="color: #4CAF50; font-size: 0.75rem; margin: 0.4rem 0 0 0; font-weight: 600;"></p>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" onclick="closeUpdateApplicationModal()" 
                        class="action-button" style="background: #6c757d;">
                    Cancel
                </button>
                <button type="submit" 
                        class="action-button" style="background: #ff6a00;">
                    <i class="fas fa-save"></i> Update Application
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showMatchScorePopup(applicationId, jobTitle, matchScore, baseScore, resumeBonus, hasResume) {
    const popup = document.getElementById('matchScorePopup');
    const content = document.getElementById('matchScoreContent');
    
    const scoreColor = matchScore >= 70 ? '#4CAF50' : matchScore >= 50 ? '#FF9800' : '#2196F3';
    const scoreLabel = matchScore >= 70 ? 'Excellent Match' : matchScore >= 50 ? 'Good Match' : 'Fair Match';
    
    let html = `
        <h3 style="color: #333; margin-bottom: 0.8rem; text-align: center; font-size: 1.1rem;">${jobTitle}</h3>
        <div style="background: linear-gradient(135deg, ${scoreColor} 0%, ${scoreColor}dd 100%); padding: 1.5rem; border-radius: 8px; margin-bottom: 1.2rem;">
            <p style="color: white; font-size: 0.8rem; margin-bottom: 0.4rem; opacity: 0.9; text-align: center;">Your Match Score</p>
            <p style="color: white; font-size: 2.5rem; font-weight: 700; margin: 0.4rem 0; text-align: center;">
                ${matchScore.toFixed(1)}%
            </p>
            <p style="color: white; font-size: 0.9rem; margin-top: 0.4rem; opacity: 0.9; text-align: center;">
                ${scoreLabel}
            </p>
        </div>
        
        <div style="text-align: left; background: #f8f9fa; padding: 1.2rem; border-radius: 6px; margin-bottom: 1rem;">
            <h4 style="color: #333; margin-bottom: 0.8rem; text-align: center; font-size: 0.95rem;">Score Breakdown</h4>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; padding: 0.6rem; background: white; border-radius: 6px; font-size: 0.85rem;">
                <span style="color: #666;">Base Match Score</span>
                <span style="color: #0056b3; font-weight: 600;">${baseScore.toFixed(1)}%</span>
            </div>
            
            ${resumeBonus > 0 ? `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; padding: 0.6rem; background: #e8f5e9; border-radius: 6px; border-left: 4px solid #4CAF50; font-size: 0.85rem;">
                <span style="color: #2e7d32; font-weight: 600;">Resume Quality Bonus</span>
                <span style="color: #2e7d32; font-weight: 700;">+${resumeBonus.toFixed(1)}%</span>
            </div>
            ` : hasResume ? `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; padding: 0.6rem; background: #fff3cd; border-radius: 6px; border-left: 4px solid #ffc107; font-size: 0.85rem;">
                <span style="color: #856404;">Resume Analysis</span>
                <span style="color: #856404; font-weight: 600;">Pending</span>
            </div>
            ` : `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; padding: 0.6rem; background: #f8f9fa; border-radius: 6px; border-left: 4px solid #6c757d; font-size: 0.85rem;">
                <span style="color: #6c757d;">Resume Uploaded</span>
                <span style="color: #6c757d; font-weight: 600;">No</span>
            </div>
            `}
            
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem; background: #e3f2fd; border-radius: 6px; border-left: 4px solid #2196F3; font-size: 0.85rem;">
                <span style="color: #0d47a1; font-weight: 600;">Final Score</span>
                <span style="color: #0d47a1; font-weight: 700;">${matchScore.toFixed(1)}%</span>
            </div>
        </div>
        
        <div style="text-align: left; background: #fff3e0; padding: 0.8rem; border-radius: 6px; border-left: 4px solid #ff9800;">
            <h4 style="color: #e65100; margin-bottom: 0.4rem; font-size: 0.85rem;">
                <i class="fas fa-lightbulb"></i> Tips to improve your score:
            </h4>
            <ul style="color: #e65100; font-size: 0.75rem; line-height: 1.5; margin: 0; padding-left: 1rem;">
                <li>Complete your chatbot assessment for better matching</li>
                <li>Upload a detailed resume with relevant skills</li>
                <li>Keep your profile information up to date</li>
                <li>Apply to jobs that match your qualifications</li>
            </ul>
        </div>
    `;
    
    content.innerHTML = html;
    popup.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeMatchScorePopup() {
    document.getElementById('matchScorePopup').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function showApplicationDetails(applicationId, jobTitle, companyName, appliedDate, coverLetter, resumeFile) {
    const modal = document.getElementById('applicationDetailsModal');
    const content = document.getElementById('applicationDetailsContent');
    
    // Format the applied date
    const appliedDateFormatted = new Date(appliedDate).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    let html = `
        <div style="margin-bottom: 1.2rem;">
            <h3 style="color: #1866a3; margin-bottom: 0.4rem; font-size: 1.1rem;">${jobTitle}</h3>
            ${companyName ? `<p style="color: #666; margin-bottom: 0.4rem; font-weight: 500; font-size: 0.9rem;">${companyName}</p>` : ''}
            <p style="color: #666; font-size: 0.85rem;">
                <i class="fas fa-clock"></i> Applied on: ${appliedDateFormatted}
            </p>
        </div>
        
        <div style="margin-bottom: 1.2rem;">
            <h4 style="color: #333; margin-bottom: 0.6rem; font-size: 0.95rem;">
                <i class="fas fa-file-alt"></i> Cover Letter
            </h4>
            <div style="background: #f8f9fa; padding: 0.8rem; border-radius: 6px; border-left: 4px solid #1866a3;">
                <p style="color: #666; margin: 0; line-height: 1.5; white-space: pre-wrap; font-size: 0.85rem;">${coverLetter || 'No cover letter provided.'}</p>
            </div>
        </div>
        
        <div style="margin-bottom: 1.2rem;">
            <h4 style="color: #333; margin-bottom: 0.6rem; font-size: 0.95rem;">
                <i class="fas fa-file-pdf"></i> Resume
            </h4>
            <div style="background: #f8f9fa; padding: 0.8rem; border-radius: 6px; border-left: 4px solid #007bff;">
                ${resumeFile ? `
                    <p style="color: #666; margin: 0 0 0.4rem 0; font-size: 0.85rem;">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i> Resume attached
                    </p>
                    <a href="../${resumeFile}" target="_blank" 
                       class="action-button" style="background: #007bff; max-width: 180px;">
                        <i class="fas fa-eye"></i> View Resume
                    </a>
                ` : `
                    <p style="color: #666; margin: 0; font-size: 0.85rem;">
                        <i class="fas fa-info-circle" style="color: #6c757d;"></i> No resume attached to this application.
                    </p>
                `}
            </div>
        </div>
        
        <div style="background: #e7f3ff; padding: 0.8rem; border-radius: 6px; border-left: 4px solid #2196F3;">
            <h5 style="color: #0d47a1; margin: 0 0 0.4rem 0; font-size: 0.9rem;">
                <i class="fas fa-lightbulb"></i> Application Status
            </h5>
            <p style="color: #666; margin: 0; font-size: 0.85rem;">
                Your application is currently being reviewed by the employer. 
                You will be notified when there are updates.
            </p>
        </div>
    `;
    
    content.innerHTML = html;
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeApplicationDetailsModal() {
    document.getElementById('applicationDetailsModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

function showUpdateApplicationModal(applicationId, jobId, currentCoverLetter, currentResumeFile) {
    const modal = document.getElementById('updateApplicationModal');
    const form = document.getElementById('updateApplicationForm');
    const coverLetterTextarea = document.getElementById('updateCoverLetter');
    const currentResumeElement = document.getElementById('currentResume');
    
    // Set form values
    document.getElementById('updateApplicationId').value = applicationId;
    document.getElementById('updateJobId').value = jobId;
    coverLetterTextarea.value = currentCoverLetter;
    
    // Show current resume info
    if (currentResumeFile) {
        currentResumeElement.innerHTML = `<i class="fas fa-check-circle"></i> Current resume: ${currentResumeFile.split('/').pop()}`;
    } else {
        currentResumeElement.innerHTML = '<i class="fas fa-info-circle"></i> No resume currently attached';
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeUpdateApplicationModal() {
    document.getElementById('updateApplicationModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
document.getElementById('matchScorePopup')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeMatchScorePopup();
    }
});

document.getElementById('applicationDetailsModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeApplicationDetailsModal();
    }
});

document.getElementById('updateApplicationModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeUpdateApplicationModal();
    }
});

// Handle form submission with confirmation
document.getElementById('updateApplicationForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    if (confirm('Are you sure you want to update your application? This will replace your current cover letter and resume.')) {
        this.submit();
    }
});
</script>

<?php include '../includes/footer.php'; ?>