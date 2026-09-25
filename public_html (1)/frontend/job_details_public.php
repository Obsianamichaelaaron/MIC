<?php
require_once 'includes/config/database.php';

$pageTitle = "Job Details";
$job_id = $_GET['id'] ?? 0;

$conn = getDBConnection();
$stmt = $conn->prepare("
    SELECT jp.*, 
           e.company_name, 
           e.company_address,
           e.company_website
    FROM job_postings jp 
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    WHERE jp.job_id = ? AND jp.status = 'active'
");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$job) {
    header('Location: jobs.php');
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($job['title']); ?> - MULTIBIZ INTERNATIONAL CORPORATION</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
        }
        
        .navbar {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0056b3;
            text-decoration: none;
        }
        
        .navbar-menu {
            display: flex;
            gap: 2rem;
            align-items: center;
        }
        
        .navbar-menu a {
            color: #333;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .navbar-menu a:hover {
            color: #0056b3;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .job-details {
            background: white;
            padding: 3rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .back-link {
            color: #0056b3;
            text-decoration: none;
            margin-bottom: 2rem;
            display: inline-block;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .job-header {
            margin-bottom: 2rem;
        }
        
        .job-title {
            font-size: 2.5rem;
            color: #0056b3;
            margin-bottom: 1rem;
        }
        
        .job-company {
            font-size: 1.3rem;
            color: #666;
            margin-bottom: 1rem;
        }
        
        .job-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .job-meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
        }
        
        .job-meta-item i {
            color: #0056b3;
        }
        
        .job-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
        }
        
        .job-main {
            line-height: 1.8;
        }
        
        .job-section {
            margin-bottom: 2rem;
        }
        
        .job-section h3 {
            color: #333;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .job-section p {
            color: #666;
            white-space: pre-wrap;
        }
        
        .job-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .skill-tag {
            background: #0056b3;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }
        
        .job-sidebar {
            position: sticky;
            top: 2rem;
            height: fit-content;
        }
        
        .sidebar-card {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .sidebar-card h3 {
            color: #333;
            margin-bottom: 1rem;
        }
        
        .sidebar-card p {
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .btn-apply-large {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            font-size: 1.1rem;
            transition: transform 0.2s;
            margin-top: 1rem;
        }
        
        .btn-apply-large:hover {
            transform: translateY(-2px);
        }
        
        .btn-apply-green {
            background: #4CAF50;
        }
        
        .btn-apply-green:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="index.php" class="navbar-brand">
            <i class="fas fa-briefcase"></i> MULTIBIZ INTERNATIONAL CORPORATION
        </a>
        <div class="navbar-menu">
            <a href="index.php">Home</a>
            <a href="jobs.php">Browse Jobs</a>
            <a href="loginregister" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login / Register
            </a>
        </div>
    </nav>

    <div class="container">
        <div class="job-details">
            <a href="jobs.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Jobs
            </a>
            
            <div class="job-header">
                <h1 class="job-title"><?php echo htmlspecialchars($job['title']); ?></h1>
                <p class="job-company">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name'] ?? 'Company Not Specified'); ?>
                </p>
                
                <div class="job-meta-grid">
                    <div class="job-meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($job['location'] ?? 'Location Not Specified'); ?></span>
                    </div>
                    <div class="job-meta-item">
                        <i class="fas fa-briefcase"></i>
                        <span><?php echo ucfirst(str_replace('-', ' ', $job['employment_type'])); ?></span>
                    </div>
                    <div class="job-meta-item">
                        <i class="fas fa-clock"></i>
                        <span>Posted <?php echo date('F d, Y', strtotime($job['posted_at'])); ?></span>
                    </div>
                    <?php if (!empty($job['salary_range'])): ?>
                        <div class="job-meta-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <span style="color: #4CAF50; font-weight: 600;"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="job-content">
                <div class="job-main">
                    <div class="job-section">
                        <h3>Job Description</h3>
                        <p><?php echo htmlspecialchars($job['description']); ?></p>
                    </div>
                    
                    <?php if (!empty($job['requirements'])): ?>
                        <div class="job-section">
                            <h3>Requirements</h3>
                            <p><?php echo htmlspecialchars($job['requirements']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($job['skills_required'])): ?>
                        <div class="job-section">
                            <h3>Required Skills</h3>
                            <div class="job-skills">
                                <?php 
                                $skills = explode(',', $job['skills_required']);
                                foreach ($skills as $skill): 
                                ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars(trim($skill)); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="job-sidebar">
                    <div class="sidebar-card">
                        <h3>Job Summary</h3>
                        <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                        <p><strong>Type:</strong> <?php echo ucfirst(str_replace('-', ' ', $job['employment_type'])); ?></p>
                        <?php if (!empty($job['salary_range'])): ?>
                            <p><strong>Salary:</strong> <?php echo htmlspecialchars($job['salary_range']); ?></p>
                        <?php endif; ?>
                        <p><strong>Posted:</strong> <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></p>
                    </div>
                    
                    <?php if (!empty($job['company_name'])): ?>
                        <div class="sidebar-card">
                            <h3>About Company</h3>
                            <p><strong><?php echo htmlspecialchars($job['company_name']); ?></strong></p>
                            <?php if (!empty($job['company_address'])): ?>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($job['company_address']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($job['company_website'])): ?>
                                <p>
                                    <a href="<?php echo htmlspecialchars($job['company_website']); ?>" target="_blank" style="color: #0056b3; text-decoration: none;">
                                        <i class="fas fa-globe"></i> Visit Website
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <a href="loginregister?redirect=apply&job_id=<?php echo $job_id; ?>" class="btn-apply-large btn-apply-green">
                        <i class="fas fa-paper-plane"></i> Apply for this Job
                    </a>
                    
                    <p style="text-align: center; color: #666; margin-top: 1rem; font-size: 0.9rem;">
                        You need to <a href="loginregister" style="color: #0056b3;">login</a> or <a href="loginregister" style="color: #0056b3;">register</a> to apply
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <footer style="background: #333; color: white; padding: 2rem; text-align: center; margin-top: 4rem;">
        <p>&copy; <?php echo date('Y'); ?> MULTIBIZ INTERNATIONAL CORPORATION. All Rights Reserved.</p>
    </footer>
</body>
</html>

