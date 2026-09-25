<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "Candidates";
$user_id = getCurrentUserId();

// Get employer ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$stmt->close();

// Get job filter
$job_id = $_GET['job_id'] ?? 0;

// Simple ML ranking without Python for now
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
        u.first_name,
        u.last_name,
        u.email,
        jp.job_id,
        jp.title as job_title,
        e.company_name
    FROM applications a
    JOIN applicants ap ON a.applicant_id = ap.applicant_id
    JOIN users u ON ap.user_id = u.user_id
    JOIN job_postings jp ON a.job_id = jp.job_id
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    WHERE 1=1
";

if ($job_id > 0) {
    $query .= " AND jp.job_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $job_id);
} else {
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Simple ranking algorithm (fallback)
foreach ($candidates as &$candidate) {
    $score = $candidate['match_score'];
    
    // Add experience bonus
    $score += min(15, $candidate['experience_years'] * 1.5);
    
    // Add employability bonus
    $score += ($candidate['employability_score'] * 0.2);
    
    // Education bonus
    $edu_bonus = 0;
    if (stripos($candidate['education_level'], 'bachelor') !== false) $edu_bonus = 5;
    if (stripos($candidate['education_level'], 'master') !== false) $edu_bonus = 8;
    if (stripos($candidate['education_level'], 'doctor') !== false) $edu_bonus = 10;
    $score += $edu_bonus;
    
    $candidate['ml_ranking_score'] = min(100, round($score, 2));
    
    // Category
    if ($candidate['ml_ranking_score'] >= 85) {
        $candidate['ranking_category'] = 'excellent';
    } elseif ($candidate['ml_ranking_score'] >= 70) {
        $candidate['ranking_category'] = 'good';
    } elseif ($candidate['ml_ranking_score'] >= 50) {
        $candidate['ranking_category'] = 'average';
    } else {
        $candidate['ranking_category'] = 'poor';
    }
}

// Sort by ML score within each category
usort($candidates, function($a, $b) {
    return $b['ml_ranking_score'] <=> $a['ml_ranking_score'];
});

// Separate candidates by category
$excellent_candidates = array_filter($candidates, function($candidate) {
    return $candidate['ranking_category'] === 'excellent';
});

$good_candidates = array_filter($candidates, function($candidate) {
    return $candidate['ranking_category'] === 'good';
});

$average_candidates = array_filter($candidates, function($candidate) {
    return $candidate['ranking_category'] === 'average';
});

$poor_candidates = array_filter($candidates, function($candidate) {
    return $candidate['ranking_category'] === 'poor';
});

// Get all jobs for filter
$stmt = $conn->prepare("
    SELECT jp.job_id, jp.title, e.company_name 
    FROM job_postings jp 
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    WHERE jp.status != 'draft'
    ORDER BY jp.posted_at DESC
");
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
?>

<!-- Full Width Header Section -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 2rem 0; margin-top: 0px; margin-right: -8px; margin-bottom: 2rem; margin-left: -8px;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.5rem; font-size: 2rem;">
                <i class="fas fa-robot"></i> AI-Ranked Candidates
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem; margin: 0;">
                Kanban Board View - Smart candidate ranking powered by AI
            </p>
        </div>
    </div>
</div>

<div class="container">
    <div class="profile-content" id="candidatesContent">
        <div class="card" style="margin-top: 10px;">
            
            <!-- Filter Section -->
            <div style="margin-bottom: 2rem;">
                <form method="GET" style="display: flex; gap: 1rem; align-items: center;">
                    <select name="job_id" 
                            style="padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 1rem; transition: all 0.3s ease; min-width: 300px;"
                            onfocus="this.style.borderColor='#1866a3'; this.style.boxShadow='0 0 0 3px rgba(24, 102, 163, 0.1)';"
                            onblur="this.style.borderColor='#e0e0e0'; this.style.boxShadow='none';">
                        <option value="0">All Jobs</option>
                        <?php foreach ($jobs as $job): ?>
                            <option value="<?php echo $job['job_id']; ?>" <?php echo ($job_id == $job['job_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($job['title']); ?>
                                <?php if ($job['company_name']): ?>
                                    - <?php echo htmlspecialchars($job['company_name']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" 
                            style="background: linear-gradient(135deg, #1866a3 0%, #004494 100%); color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;"
                            onmouseover="this.style.transform='translateY(-1px)'; this.style.boxShadow='0 4px 12px rgba(24, 102, 163, 0.3)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-filter"></i> Filter Candidates
                    </button>
                </form>
            </div>
            
            <!-- Summary Statistics - Updated to match dashboard style -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; align-items: stretch;">
                <!-- Excellent Candidates Card -->
                <div style="border: 1px solid #e0e0e0; padding: 2rem; border-radius: 8px; transition: all 0.3s ease; background: white; text-align: center;"
                     onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#4CAF50';"
                     onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                    <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="fas fa-crown" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 style="font-size: 2.5rem; margin-bottom: 0.5rem; line-height: 1.2; color: #333;"><?php echo count($excellent_candidates); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Excellent Candidates</p>
                </div>
                
                <!-- Good Candidates Card -->
                <div style="border: 1px solid #e0e0e0; padding: 2rem; border-radius: 8px; transition: all 0.3s ease; background: white; text-align: center;"
                     onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#2196F3';"
                     onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                    <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="fas fa-thumbs-up" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 style="font-size: 2.5rem; margin-bottom: 0.5rem; line-height: 1.2; color: #333;"><?php echo count($good_candidates); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Good Candidates</p>
                </div>
                
                <!-- Average Candidates Card -->
                <div style="border: 1px solid #e0e0e0; padding: 2rem; border-radius: 8px; transition: all 0.3s ease; background: white; text-align: center;"
                     onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#FF9800';"
                     onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                    <div style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="fas fa-chart-line" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 style="font-size: 2.5rem; margin-bottom: 0.5rem; line-height: 1.2; color: #333;"><?php echo count($average_candidates); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Average Candidates</p>
                </div>
                
                <!-- Poor Candidates Card -->
                <div style="border: 1px solid #e0e0e0; padding: 2rem; border-radius: 8px; transition: all 0.3s ease; background: white; text-align: center;"
                     onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#F44336';"
                     onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                    <div style="background: linear-gradient(135deg, #F44336 0%, #d32f2f 100%); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem;"></i>
                    </div>
                    <h3 style="font-size: 2.5rem; margin-bottom: 0.5rem; line-height: 1.2; color: #333;"><?php echo count($poor_candidates); ?></h3>
                    <p style="margin: 0; color: #666; font-weight: 500;">Poor Candidates</p>
                </div>
            </div>
            
            <!-- AI Recommendations Section - Matching Dashboard Style -->
            <div style="margin-top: 2rem; margin-bottom: 2rem;">
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; border-left: 4px solid #9C27B0;">
                    <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1.3rem; font-weight: 600;">
                        <i class="fas fa-robot"></i> AI-Powered Candidate Ranking
                    </h3>
                    <p style="color: #666; margin-bottom: 1rem; line-height: 1.6;">
                        Our AI system automatically ranks candidates based on their match score, employability score, experience, and education level. Candidates are categorized into four tiers for easy evaluation.
                    </p>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <a href="recommended_candidates.php" 
                           style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;"
                           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(156, 39, 176, 0.3)';"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fas fa-star"></i> Top Recommendations
                        </a>
                        <a href="post_job.php" 
                           style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;"
                           onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(24, 102, 163, 0.3)';"
                           onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                            <i class="fas fa-plus-circle"></i> Post New Job
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Ranking Legend -->
            <div style="display: flex; gap: 1.5rem; margin-bottom: 2rem; padding: 1.5rem; background: #f8f9fa; border-radius: 8px; border: 1px solid #e0e0e0; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 16px; height: 16px; background: #4CAF50; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                    <span style="color: #333; font-weight: 500;">Excellent (85-100%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 16px; height: 16px; background: #2196F3; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                    <span style="color: #333; font-weight: 500;">Good (70-84%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 16px; height: 16px; background: #FF9800; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                    <span style="color: #333; font-weight: 500;">Average (50-69%)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 16px; height: 16px; background: #F44336; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                    <span style="color: #333; font-weight: 500;">Poor (0-49%)</span>
                </div>
            </div>
            
            <?php if (count($candidates) > 0): ?>
                <!-- Kanban Board Layout -->
                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem;">
                    
                    <!-- Excellent Candidates Column -->
                    <div style="background: #f8f9fa; border-radius: 12px; padding: 1rem; min-height: 600px; border: 1px solid #e0e0e0;">
                        <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; padding: 1.25rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">
                                <i class="fas fa-crown" style="margin-right: 0.5rem;"></i>
                                Excellent
                            </h3>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                <?php echo count($excellent_candidates); ?> candidate<?php echo count($excellent_candidates) !== 1 ? 's' : ''; ?>
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($excellent_candidates as $index => $candidate): ?>
                                <div style="border: 2px solid #4CAF50; padding: 1.25rem; border-radius: 8px; background: white; position: relative; transition: all 0.3s ease;"
                                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(76, 175, 80, 0.2)';"
                                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <!-- Ranking Badge -->
                                    <div style="position: absolute; top: -10px; left: 10px; background: #4CAF50; color: white; padding: 0.35rem 0.85rem; border-radius: 15px; font-weight: bold; font-size: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                        #<?php echo $index + 1; ?>
                                    </div>
                                    
                                    <div style="margin-bottom: 0.75rem; margin-top: 0.5rem;">
                                        <h4 style="color: #333; margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                            <i class="fas fa-crown" style="color: #FFD700; margin-left: 0.25rem; font-size: 0.8rem;"></i>
                                        </h4>
                                        <p style="color: #666; margin: 0 0 0.25rem 0; font-size: 0.8rem;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($candidate['email']); ?>
                                        </p>
                                        <p style="color: #666; margin: 0 0 0.5rem 0; font-size: 0.8rem;">
                                            <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['job_title']); ?>
                                        </p>
                                    </div>
                                    
                                    <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.75rem;">
                                        <p style="font-size: 1.2rem; font-weight: bold; color: white; margin: 0; text-align: center;">
                                            <?php echo number_format($candidate['ml_ranking_score'], 1); ?>%
                                        </p>
                                        <p style="font-size: 0.7rem; color: rgba(255,255,255,0.9); margin: 0.25rem 0 0 0; text-align: center;">
                                            AI SCORE
                                        </p>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                                        <div style="text-align: center; background: #e8f5e8; padding: 0.5rem; border-radius: 4px;">
                                            <p style="font-size: 0.85rem; color: #4CAF50; font-weight: 600; margin: 0;">
                                                <?php echo number_format($candidate['match_score'], 1); ?>%
                                            </p>
                                            <p style="font-size: 0.65rem; color: #666; margin: 0.25rem 0 0 0;">Match</p>
                                        </div>
                                        <div style="text-align: center; background: #e3f2fd; padding: 0.5rem; border-radius: 4px;">
                                            <p style="font-size: 0.85rem; color: #2196F3; font-weight: 600; margin: 0;">
                                                <?php echo number_format($candidate['employability_score'], 1); ?>%
                                            </p>
                                            <p style="font-size: 0.65rem; color: #666; margin: 0.25rem 0 0 0;">Employability</p>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #666; margin-bottom: 0.75rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px;">
                                        <span><i class="fas fa-calendar-alt"></i> <?php echo $candidate['experience_years']; ?> yrs</span>
                                        <span><i class="fas fa-graduation-cap"></i> <?php echo substr($candidate['education_level'], 0, 12); ?></span>
                                    </div>
                                    
                                    <div style="text-align: center;">
                                        <a href="view_candidate.php?id=<?php echo $candidate['application_id']; ?>" 
                                           style="background: #1866a3; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;"
                                           onmouseover="this.style.background='#004494'; this.style.transform='translateY(-1px)';"
                                           onmouseout="this.style.background='#1866a3'; this.style.transform='translateY(0)';">
                                            <i class="fas fa-eye"></i> View Profile
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($excellent_candidates) === 0): ?>
                                <div style="text-align: center; color: #999; padding: 2rem; font-style: italic; background: white; border-radius: 6px; border: 2px dashed #e0e0e0;">
                                    <i class="fas fa-crown" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <p style="margin: 0;">No excellent candidates yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Good Candidates Column -->
                    <div style="background: #f8f9fa; border-radius: 12px; padding: 1rem; min-height: 600px; border: 1px solid #e0e0e0;">
                        <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; padding: 1.25rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">
                                <i class="fas fa-thumbs-up" style="margin-right: 0.5rem;"></i>
                                Good
                            </h3>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                <?php echo count($good_candidates); ?> candidate<?php echo count($good_candidates) !== 1 ? 's' : ''; ?>
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($good_candidates as $index => $candidate): ?>
                                <div style="border: 2px solid #2196F3; padding: 1.25rem; border-radius: 8px; background: white; position: relative; transition: all 0.3s ease;"
                                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(33, 150, 243, 0.2)';"
                                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <!-- Ranking Badge -->
                                    <div style="position: absolute; top: -10px; left: 10px; background: #2196F3; color: white; padding: 0.35rem 0.85rem; border-radius: 15px; font-weight: bold; font-size: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                        #<?php echo $index + 1 + count($excellent_candidates); ?>
                                    </div>
                                    
                                    <div style="margin-bottom: 0.75rem; margin-top: 0.5rem;">
                                        <h4 style="color: #333; margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                        </h4>
                                        <p style="color: #666; margin: 0 0 0.25rem 0; font-size: 0.8rem;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($candidate['email']); ?>
                                        </p>
                                        <p style="color: #666; margin: 0 0 0.5rem 0; font-size: 0.8rem;">
                                            <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['job_title']); ?>
                                        </p>
                                    </div>
                                    
                                    <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.75rem;">
                                        <p style="font-size: 1.2rem; font-weight: bold; color: white; margin: 0; text-align: center;">
                                            <?php echo number_format($candidate['ml_ranking_score'], 1); ?>%
                                        </p>
                                        <p style="font-size: 0.7rem; color: rgba(255,255,255,0.9); margin: 0.25rem 0 0 0; text-align: center;">
                                            AI SCORE
                                        </p>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                                        <div style="text-align: center; background: #e8f5e8; padding: 0.5rem; border-radius: 4px;">
                                            <p style="font-size: 0.85rem; color: #4CAF50; font-weight: 600; margin: 0;">
                                                <?php echo number_format($candidate['match_score'], 1); ?>%
                                            </p>
                                            <p style="font-size: 0.65rem; color: #666; margin: 0.25rem 0 0 0;">Match</p>
                                        </div>
                                        <div style="text-align: center; background: #e3f2fd; padding: 0.5rem; border-radius: 4px;">
                                            <p style="font-size: 0.85rem; color: #2196F3; font-weight: 600; margin: 0;">
                                                <?php echo number_format($candidate['employability_score'], 1); ?>%
                                            </p>
                                            <p style="font-size: 0.65rem; color: #666; margin: 0.25rem 0 0 0;">Employability</p>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #666; margin-bottom: 0.75rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px;">
                                        <span><i class="fas fa-calendar-alt"></i> <?php echo $candidate['experience_years']; ?> yrs</span>
                                        <span><i class="fas fa-graduation-cap"></i> <?php echo substr($candidate['education_level'], 0, 12); ?></span>
                                    </div>
                                    
                                    <div style="text-align: center;">
                                        <a href="view_candidate.php?id=<?php echo $candidate['application_id']; ?>" 
                                           style="background: #1866a3; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;"
                                           onmouseover="this.style.background='#004494'; this.style.transform='translateY(-1px)';"
                                           onmouseout="this.style.background='#1866a3'; this.style.transform='translateY(0)';">
                                            <i class="fas fa-eye"></i> View Profile
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($good_candidates) === 0): ?>
                                <div style="text-align: center; color: #999; padding: 2rem; font-style: italic; background: white; border-radius: 6px; border: 2px dashed #e0e0e0;">
                                    <i class="fas fa-thumbs-up" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <p style="margin: 0;">No good candidates yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Average Candidates Column -->
                    <div style="background: #f8f9fa; border-radius: 12px; padding: 1rem; min-height: 600px; border: 1px solid #e0e0e0;">
                        <div style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); color: white; padding: 1.25rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">
                                <i class="fas fa-chart-line" style="margin-right: 0.5rem;"></i>
                                Average
                            </h3>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                <?php echo count($average_candidates); ?> candidate<?php echo count($average_candidates) !== 1 ? 's' : ''; ?>
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($average_candidates as $index => $candidate): ?>
                                <div style="border: 2px solid #FF9800; padding: 1.25rem; border-radius: 8px; background: white; position: relative; transition: all 0.3s ease;"
                                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(255, 152, 0, 0.2)';"
                                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <!-- Ranking Badge -->
                                    <div style="position: absolute; top: -10px; left: 10px; background: #FF9800; color: white; padding: 0.35rem 0.85rem; border-radius: 15px; font-weight: bold; font-size: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                        #<?php echo $index + 1 + count($excellent_candidates) + count($good_candidates); ?>
                                    </div>
                                    
                                    <div style="margin-bottom: 0.75rem; margin-top: 0.5rem;">
                                        <h4 style="color: #333; margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                        </h4>
                                        <p style="color: #666; margin: 0 0 0.25rem 0; font-size: 0.8rem;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($candidate['email']); ?>
                                        </p>
                                        <p style="color: #666; margin: 0 0 0.5rem 0; font-size: 0.8rem;">
                                            <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['job_title']); ?>
                                        </p>
                                    </div>
                                    
                                    <div style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.75rem;">
                                        <p style="font-size: 1.2rem; font-weight: bold; color: white; margin: 0; text-align: center;">
                                            <?php echo number_format($candidate['ml_ranking_score'], 1); ?>%
                                        </p>
                                        <p style="font-size: 0.7rem; color: rgba(255,255,255,0.9); margin: 0.25rem 0 0 0; text-align: center;">
                                            AI SCORE
                                        </p>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                                        <div style="text-align: center; background: #e8f5e8; padding: 0.5rem; border-radius: 4px;">
                                            <p style="font-size: 0.85rem; color: #4CAF50; font-weight: 600; margin: 0;">
                                                <?php echo number_format($candidate['match_score'], 1); ?>%
                                            </p>
                                            <p style="font-size: 0.65rem; color: #666; margin: 0.25rem 0 0 0;">Match</p>
                                        </div>
                                        <div style="text-align: center; background: #e3f2fd; padding: 0.5rem; border-radius: 4px;">
                                            <p style="font-size: 0.85rem; color: #2196F3; font-weight: 600; margin: 0;">
                                                <?php echo number_format($candidate['employability_score'], 1); ?>%
                                            </p>
                                            <p style="font-size: 0.65rem; color: #666; margin: 0.25rem 0 0 0;">Employability</p>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #666; margin-bottom: 0.75rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px;">
                                        <span><i class="fas fa-calendar-alt"></i> <?php echo $candidate['experience_years']; ?> yrs</span>
                                        <span><i class="fas fa-graduation-cap"></i> <?php echo substr($candidate['education_level'], 0, 12); ?></span>
                                    </div>
                                    
                                    <div style="text-align: center;">
                                        <a href="view_candidate.php?id=<?php echo $candidate['application_id']; ?>" 
                                           style="background: #1866a3; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;"
                                           onmouseover="this.style.background='#004494'; this.style.transform='translateY(-1px)';"
                                           onmouseout="this.style.background='#1866a3'; this.style.transform='translateY(0)';">
                                            <i class="fas fa-eye"></i> View Profile
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($average_candidates) === 0): ?>
                                <div style="text-align: center; color: #999; padding: 2rem; font-style: italic; background: white; border-radius: 6px; border: 2px dashed #e0e0e0;">
                                    <i class="fas fa-chart-line" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <p style="margin: 0;">No average candidates yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Poor Candidates Column -->
                    <div style="background: #f8f9fa; border-radius: 12px; padding: 1rem; min-height: 600px; border: 1px solid #e0e0e0;">
                        <div style="background: linear-gradient(135deg, #F44336 0%, #d32f2f 100%); color: white; padding: 1.25rem; border-radius: 8px; margin-bottom: 1rem; text-align: center;">
                            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600;">
                                <i class="fas fa-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                                Poor
                            </h3>
                            <p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                <?php echo count($poor_candidates); ?> candidate<?php echo count($poor_candidates) !== 1 ? 's' : ''; ?>
                            </p>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 1rem;">
                            <?php foreach ($poor_candidates as $index => $candidate): ?>
                                <div style="border: 2px solid #F44336; padding: 1.25rem; border-radius: 8px; background: white; position: relative; transition: all 0.3s ease;"
                                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(244, 67, 54, 0.2)';"
                                     onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                                    <!-- Ranking Badge -->
                                    <div style="position: absolute; top: -10px; left: 10px; background: #F44336; color: white; padding: 0.35rem 0.85rem; border-radius: 15px; font-weight: bold; font-size: 0.75rem; box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                                        #<?php echo $index + 1 + count($excellent_candidates) + count($good_candidates) + count($average_candidates); ?>
                                    </div>
                                    
                                    <div style="margin-bottom: 0.75rem; margin-top: 0.5rem;">
                                        <h4 style="color: #333; margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600;">
                                            <?php echo htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']); ?>
                                        </h4>
                                        <p style="color: #666; margin: 0 0 0.25rem 0; font-size: 0.8rem;">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($candidate['email']); ?>
                                        </p>
                                        <p style="color: #666; margin: 0 0 0.5rem 0; font-size: 0.8rem;">
                                            <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($candidate['job_title']); ?>
                                        </p>
                                    </div>
                                    
                                    <div style="background: linear-gradient(135deg, #F44336 0%, #d32f2f 100%); padding: 0.75rem; border-radius: 6px; margin-bottom: 0.75rem;">
                                        <p style="font-size: 1.2rem; font-weight: bold; color: white; margin: 0; text-align: center;">
                                            <?php echo number_format($candidate['ml_ranking_score'], 1); ?>%
                                        </p>
                                        <p style="font-size: 0.7rem; color: rgba(255,255,255,0.9); margin: 0.25rem 0 0 0; text-align: center;">
                                            AI SCORE
                                        </p>
                                    </div>
                                    
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.75rem;">
                                        <div style="text-align: center; background: #e8f5e8; padding: 0.5rem; border-radius: 4px;">
                                            <p style="font-size: 0.85rem; color: #4CAF50; font-weight: 600; margin: 0;">
                                                <?php echo number_format($candidate['match_score'], 1); ?>%
                                            </p>
                                            <p style="font-size: 0.65rem; color: #666; margin: 0.25rem 0 0 0;">Match</p>
                                        </div>
                                        <div style="text-align: center; background: #e3f2fd; padding: 0.5rem; border-radius: 4px;">
                                            <p style="font-size: 0.85rem; color: #2196F3; font-weight: 600; margin: 0;">
                                                <?php echo number_format($candidate['employability_score'], 1); ?>%
                                            </p>
                                            <p style="font-size: 0.65rem; color: #666; margin: 0.25rem 0 0 0;">Employability</p>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.75rem; color: #666; margin-bottom: 0.75rem; padding: 0.5rem; background: #f8f9fa; border-radius: 4px;">
                                        <span><i class="fas fa-calendar-alt"></i> <?php echo $candidate['experience_years']; ?> yrs</span>
                                        <span><i class="fas fa-graduation-cap"></i> <?php echo substr($candidate['education_level'], 0, 12); ?></span>
                                    </div>
                                    
                                    <div style="text-align: center;">
                                        <a href="view_candidate.php?id=<?php echo $candidate['application_id']; ?>" 
                                           style="background: #1866a3; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;"
                                           onmouseover="this.style.background='#004494'; this.style.transform='translateY(-1px)';"
                                           onmouseout="this.style.background='#1866a3'; this.style.transform='translateY(0)';">
                                            <i class="fas fa-eye"></i> View Profile
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <?php if (count($poor_candidates) === 0): ?>
                                <div style="text-align: center; color: #999; padding: 2rem; font-style: italic; background: white; border-radius: 6px; border: 2px dashed #e0e0e0;">
                                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5;"></i>
                                    <p style="margin: 0;">No poor candidates yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <i class="fas fa-users" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                    <h3 style="color: #333; margin-bottom: 0.5rem;">No candidates found</h3>
                    <p>Post your first job to start receiving applications from qualified candidates.</p>
                    <a href="post_job.php" 
                       style="background: #1866a3; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; margin-top: 1rem;">
                        <i class="fas fa-plus-circle"></i>
                        Post a Job
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Remove loading class if it exists
document.addEventListener('DOMContentLoaded', function() {
    const content = document.getElementById('candidatesContent');
    if (content) {
        content.classList.remove('loading');
    }
});
</script>

<?php include '../includes/footer.php'; ?>