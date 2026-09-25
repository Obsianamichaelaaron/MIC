<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('admin');

$pageTitle = "System Analytics";

$conn = getDBConnection();

// Get overall statistics
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

// Total applications
$result = $conn->query("SELECT COUNT(*) as count FROM applications");
$stats['total_applications'] = $result->fetch_assoc()['count'];

// User growth (last 30 days)
$result = $conn->query("
    SELECT DATE(created_at) as date, COUNT(*) as count 
    FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$user_growth = $result->fetch_all(MYSQLI_ASSOC);

// Application status distribution
$result = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM applications 
    GROUP BY status
    ORDER BY count DESC
");
$application_status = $result->fetch_all(MYSQLI_ASSOC);

// Job status distribution
$result = $conn->query("
    SELECT status, COUNT(*) as count 
    FROM job_postings 
    GROUP BY status
    ORDER BY count DESC
");
$job_status = $result->fetch_all(MYSQLI_ASSOC);

// Average employability scores by education
$result = $conn->query("
    SELECT education_level, 
           AVG(employability_score) as avg_score, 
           COUNT(*) as count,
           MIN(employability_score) as min_score,
           MAX(employability_score) as max_score
    FROM applicants
    WHERE education_level IS NOT NULL AND education_level != ''
    GROUP BY education_level
    ORDER BY avg_score DESC
");
$education_stats = $result->fetch_all(MYSQLI_ASSOC);

// Monthly job postings trend (last 6 months)
$result = $conn->query("
    SELECT DATE_FORMAT(posted_at, '%Y-%m') as month, COUNT(*) as count
    FROM job_postings
    WHERE posted_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(posted_at, '%Y-%m')
    ORDER BY month ASC
");
$job_trends = $result->fetch_all(MYSQLI_ASSOC);

// Monthly applications trend (last 6 months)
$result = $conn->query("
    SELECT DATE_FORMAT(applied_at, '%Y-%m') as month, COUNT(*) as count
    FROM applications
    WHERE applied_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(applied_at, '%Y-%m')
    ORDER BY month ASC
");
$application_trends = $result->fetch_all(MYSQLI_ASSOC);

// Get average match score from applications
$result = $conn->query("SELECT AVG(match_score) as avg_match FROM applications WHERE match_score > 0");
$avg_match = $result->fetch_assoc()['avg_match'] ?? 0;

// Get average recommendation score
$result = $conn->query("SELECT AVG(recommendation_score) as avg_rec FROM job_recommendations WHERE recommendation_score > 0");
$avg_recommendation = $result->fetch_assoc()['avg_rec'] ?? 0;

$conn->close();

include '../includes/header.php';
?>

<!-- Full Width Header -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 1.5rem 0; margin: 0 -8px 1rem -8px;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.3rem; font-size: 1.5rem;">
                <i class="fas fa-chart-bar"></i> System Analytics
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin: 0;">
                Comprehensive System Performance & Statistics
            </p>
        </div>
    </div>
</div>

<div class="container" style="max-width: 1400px; padding: 0 1rem;">
    <div class="card" style="margin-top: 0;">
        
        <!-- Overview Statistics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; align-items: stretch;">
            <!-- Total Users Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#0056b3';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-users" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $stats['total_users']; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Total Users</p>
            </div>
            
            <!-- Applicants Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#4CAF50';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-user-graduate" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $stats['total_applicants']; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Applicants</p>
            </div>
            
            <!-- Employers Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#2196F3';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-building" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $stats['total_employers']; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Employers</p>
            </div>
            
            <!-- Total Jobs Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#FF9800';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #FF9800 0%, #e55a00 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-briefcase" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $stats['total_jobs']; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Total Jobs</p>
            </div>
            
            <!-- Applications Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#9C27B0';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-file-alt" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $stats['total_applications']; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Applications</p>
            </div>
        </div>
        
        <!-- Status Distributions -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div>
                <h3 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                    <i class="fas fa-file-alt"></i> Application Status
                </h3>
                <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #0056b3;">
                    <?php if (count($application_status) > 0): ?>
                        <?php 
                        $max_app_count = max(array_column($application_status, 'count'));
                        $max_app_count = $max_app_count > 0 ? $max_app_count : 1;
                        ?>
                        <?php foreach ($application_status as $stat): ?>
                            <div style="margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="text-transform: capitalize; font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($stat['status']); ?></span>
                                    <span style="color: #0056b3; font-weight: 600; font-size: 0.85rem;"><?php echo $stat['count']; ?></span>
                                </div>
                                <div style="background: #e0e0e0; height: 16px; border-radius: 8px; overflow: hidden;">
                                    <div style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); height: 100%; width: <?php echo min(100, ($stat['count'] / $max_app_count) * 100); ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 1rem; font-size: 0.9rem;">No applications yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <h3 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                    <i class="fas fa-briefcase"></i> Job Status
                </h3>
                <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #4CAF50;">
                    <?php if (count($job_status) > 0): ?>
                        <?php 
                        $max_job_count = max(array_column($job_status, 'count'));
                        $max_job_count = $max_job_count > 0 ? $max_job_count : 1;
                        ?>
                        <?php foreach ($job_status as $stat): ?>
                            <div style="margin-bottom: 1rem;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                                    <span style="text-transform: capitalize; font-weight: 600; font-size: 0.85rem;"><?php echo htmlspecialchars($stat['status']); ?></span>
                                    <span style="color: #4CAF50; font-weight: 600; font-size: 0.85rem;"><?php echo $stat['count']; ?></span>
                                </div>
                                <div style="background: #e0e0e0; height: 16px; border-radius: 8px; overflow: hidden;">
                                    <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); height: 100%; width: <?php echo min(100, ($stat['count'] / $max_job_count) * 100); ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 1rem; font-size: 0.9rem;">No jobs posted yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- User Growth Chart -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                <i class="fas fa-users"></i> User Growth (Last 30 Days)
            </h3>
            <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #2196F3;">
                <?php if (count($user_growth) > 0): ?>
                    <div style="display: flex; align-items: flex-end; gap: 0.4rem; height: 150px; border-bottom: 2px solid #ddd; padding-bottom: 1rem;">
                        <?php 
                        $max_growth = max(array_column($user_growth, 'count'));
                        $max_growth = $max_growth > 0 ? $max_growth : 1;
                        foreach ($user_growth as $day): 
                            $height = ($day['count'] / $max_growth) * 120;
                        ?>
                            <div style="flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.4rem;">
                                <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); width: 100%; border-radius: 4px 4px 0 0; min-height: <?php echo max(3, $height); ?>px; display: flex; align-items: flex-end; justify-content: center; color: white; font-weight: 600; font-size: 0.7rem; padding: 0.2rem;">
                                    <?php echo $day['count']; ?>
                                </div>
                                <span style="font-size: 0.7rem; color: #666; transform: rotate(-45deg); white-space: nowrap;">
                                    <?php echo date('M d', strtotime($day['date'])); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="margin-top: 1rem; text-align: center; color: #666; font-size: 0.85rem;">
                        Total new users: <?php echo array_sum(array_column($user_growth, 'count')); ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 1rem; font-size: 0.9rem;">No user growth data available for the last 30 days.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Education Stats -->
        <div style="margin-bottom: 1.5rem;">
            <h3 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                <i class="fas fa-graduation-cap"></i> Employability by Education
            </h3>
            <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #FF9800;">
                <?php if (count($education_stats) > 0): ?>
                    <div style="display: grid; gap: 0.8rem;">
                        <?php foreach ($education_stats as $stat): ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.8rem; background: white; border-radius: 6px; border: 1px solid #e0e0e0;">
                                <div>
                                    <span style="font-weight: 600; color: #333; font-size: 0.9rem;"><?php echo htmlspecialchars($stat['education_level']); ?></span>
                                    <div style="font-size: 0.8rem; color: #666; margin-top: 0.2rem;">
                                        <?php echo $stat['count']; ?> applicants
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="color: #FF9800; font-weight: 600; font-size: 1rem;">
                                        <?php echo number_format($stat['avg_score'], 1); ?>%
                                    </div>
                                    <div style="font-size: 0.7rem; color: #999;">
                                        <?php echo number_format($stat['min_score'], 1); ?>-<?php echo number_format($stat['max_score'], 1); ?>%
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #666; padding: 1rem; font-size: 0.9rem;">No education data available.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Trends and Additional Stats -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
            <div>
                <h3 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                    <i class="fas fa-chart-line"></i> Job Trends (6 Months)
                </h3>
                <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #9C27B0;">
                    <?php if (count($job_trends) > 0): ?>
                        <div style="display: grid; gap: 0.6rem;">
                            <?php foreach ($job_trends as $trend): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem; background: white; border-radius: 6px; border: 1px solid #e0e0e0;">
                                    <span style="font-weight: 600; color: #333; font-size: 0.85rem;"><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></span>
                                    <span style="color: #9C27B0; font-weight: 600; font-size: 0.85rem;"><?php echo $trend['count']; ?> jobs</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 1rem; font-size: 0.9rem;">No job posting trends available.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div>
                <h3 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                    <i class="fas fa-chart-line"></i> Application Trends (6 Months)
                </h3>
                <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #4CAF50;">
                    <?php if (count($application_trends) > 0): ?>
                        <div style="display: grid; gap: 0.6rem;">
                            <?php foreach ($application_trends as $trend): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.6rem; background: white; border-radius: 6px; border: 1px solid #e0e0e0;">
                                    <span style="font-weight: 600; color: #333; font-size: 0.85rem;"><?php echo date('M Y', strtotime($trend['month'] . '-01')); ?></span>
                                    <span style="color: #4CAF50; font-weight: 600; font-size: 0.85rem;"><?php echo $trend['count']; ?> apps</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #666; padding: 1rem; font-size: 0.9rem;">No application trends available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Matching Statistics -->
        <div>
            <h3 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                <i class="fas fa-percentage"></i> Matching Statistics
            </h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #0056b3;">
                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">Average Match Score</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #0056b3;">
                        <?php echo number_format($avg_match, 1); ?>%
                    </div>
                    <div style="font-size: 0.8rem; color: #999; margin-top: 0.5rem;">
                        Based on <?php echo $stats['total_applications']; ?> applications
                    </div>
                </div>
                <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #4CAF50;">
                    <div style="font-size: 0.85rem; color: #666; margin-bottom: 0.5rem;">Average Recommendation Score</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: #4CAF50;">
                        <?php echo number_format($avg_recommendation, 1); ?>%
                    </div>
                    <div style="font-size: 0.8rem; color: #999; margin-top: 0.5rem;">
                        Based on job recommendations
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>