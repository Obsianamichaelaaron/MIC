<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

// If it's a POST request, handle save/unsave operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $job_id = $_POST['job_id'] ?? '';
    $action = $_POST['action'] ?? '';
    
    // Get applicant ID
    $user_id = getCurrentUserId();
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $applicant = $stmt->get_result()->fetch_assoc();
    $applicant_id = $applicant['applicant_id'];
    $stmt->close();
    
    // Verify job exists and is active
    $stmt = $conn->prepare("SELECT job_id FROM job_postings WHERE job_id = ? AND status = 'active'");
    $stmt->bind_param("i", $job_id);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$job) {
        echo json_encode(['success' => false, 'message' => 'Job not found or inactive']);
        exit();
    }
    
    if ($action === 'save') {
        // Check if already saved
        $stmt = $conn->prepare("SELECT id FROM saved_jobs WHERE applicant_id = ? AND job_id = ?");
        $stmt->bind_param("ii", $applicant_id, $job_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Job already saved']);
            exit();
        }
        
        // Save job
        $stmt = $conn->prepare("INSERT INTO saved_jobs (applicant_id, job_id, saved_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $applicant_id, $job_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        $stmt->close();
        
    } elseif ($action === 'unsave') {
        // Remove saved job
        $stmt = $conn->prepare("DELETE FROM saved_jobs WHERE applicant_id = ? AND job_id = ?");
        $stmt->bind_param("ii", $applicant_id, $job_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
    closeDBConnection($conn);
    exit();
}

// If it's a GET request, show the saved jobs page
$pageTitle = "Saved Jobs";
$user_id = getCurrentUserId();

// Get applicant ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$applicant = $stmt->get_result()->fetch_assoc();
$applicant_id = $applicant['applicant_id'];
$stmt->close();

// Get saved jobs with job details
$stmt = $conn->prepare("
    SELECT sj.*, jp.*, e.company_name 
    FROM saved_jobs sj 
    JOIN job_postings jp ON sj.job_id = jp.job_id 
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    WHERE sj.applicant_id = ? 
    AND jp.status = 'active'
    ORDER BY sj.saved_at DESC
");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$saved_jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get applied job IDs
$stmt = $conn->prepare("SELECT job_id FROM applications WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$applied_jobs = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $applied_jobs[] = $row['job_id'];
}
$stmt->close();

// Check if profile is completed
$stmt = $conn->prepare("SELECT profile_completed, skills, qualifications FROM applicants WHERE applicant_id = ?");
$stmt->bind_param("i", $applicant_id);
$stmt->execute();
$profile_data = $stmt->get_result()->fetch_assoc();
$profile_completed = ($profile_data['profile_completed'] ?? 0) && !empty($profile_data['skills']) && !empty($profile_data['qualifications']);
$stmt->close();

closeDBConnection($conn);

include '../includes/header.php';
?>

<!-- Full Width Header Section -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 2rem 0; margin-top: 0px; margin-right: -8px; margin-bottom: 2rem; margin-left: -8px;">
    <div style="max-width: 1200px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.5rem; font-size: 2rem; font-weight: 700;">
                <i class="fas fa-bookmark"></i> Saved Jobs
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 1.1rem; margin-bottom: 0;">
                Your collection of interesting job opportunities
            </p>
        </div>
    </div>
</div>

<div class="container">
    <div class="profile-content" id="savedJobsContent">
        <div class="card" style="margin-top: 10px;">
            <!-- Saved Jobs Count -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 0 0.5rem;">
                <h2 style="color: #333; margin: 0; font-size: 1.3rem;">
                    <?php echo count($saved_jobs); ?> saved job<?php echo count($saved_jobs) !== 1 ? 's' : ''; ?>
                </h2>
                <div style="color: #666; font-size: 0.9rem;">
                    Sorted by: <strong>Most recent</strong>
                </div>
            </div>
            
            <?php if (count($saved_jobs) > 0): ?>
                <div style="display: grid; gap: 1.5rem;">
                    <?php foreach ($saved_jobs as $job): ?>
                        <div style="border: 1px solid #e0e0e0; padding: 1.5rem; border-radius: 8px; transition: all 0.3s ease; background: white;"
                             onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#1866a3';"
                             onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                            
                            <!-- Job Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <div style="flex: 1;">
                                    <h2 style="color: #1866a3; margin-bottom: 0.5rem; font-size: 1.4rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($job['title']); ?>
                                    </h2>
                                    <?php if (!empty($job['company_name'])): ?>
                                    <p style="color: #666; margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 500;">
                                        <?php echo htmlspecialchars($job['company_name']); ?>
                                    </p>
                                    <?php endif; ?>
                                    <p style="color: #666; margin-bottom: 0.5rem;">
                                        <i class="fas fa-map-marker-alt" style="color: #ff6a00; margin-right: 0.5rem;"></i>
                                        <?php echo htmlspecialchars($job['location'] ?? 'Location Not Specified'); ?>
                                    </p>
                                </div>
                                
                                <!-- Salary and Save Info -->
                                <div style="text-align: right;">
                                    <?php if (!empty($job['salary_range'])): ?>
                                        <p style="color: #4CAF50; font-weight: 600; font-size: 1.1rem; margin-bottom: 0.5rem;">
                                            <?php echo htmlspecialchars($job['salary_range']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <p style="color: #666; font-size: 0.85rem; margin-bottom: 0.5rem;">
                                        <i class="fas fa-calendar-alt"></i> Saved <?php echo date('M d, Y', strtotime($job['saved_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Job Details -->
                            <div style="display: grid; grid-template-columns: auto auto 1fr; gap: 1rem; margin-bottom: 1rem; align-items: center;">
                                <span style="background: #f0f8ff; color: #1866a3; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.85rem; font-weight: 500;">
                                    <i class="fas fa-briefcase" style="margin-right: 0.3rem;"></i>
                                    <?php echo ucfirst($job['employment_type']); ?>
                                </span>
                                
                                <span style="background: #e8f5e8; color: #4CAF50; padding: 0.4rem 0.8rem; border-radius: 15px; font-size: 0.85rem; font-weight: 500;">
                                    <i class="fas fa-bookmark" style="margin-right: 0.3rem;"></i>
                                    Saved
                                </span>
                                
                                <span style="color: #999; font-size: 0.85rem; justify-self: end;">
                                    <?php 
                                    $posted_date = strtotime($job['posted_at']);
                                    $current_date = time();
                                    $days_ago = round(($current_date - $posted_date) / (60 * 60 * 24));
                                    
                                    if ($days_ago == 0) {
                                        echo 'Posted today';
                                    } elseif ($days_ago == 1) {
                                        echo 'Posted 1 day ago';
                                    } else {
                                        echo 'Posted ' . $days_ago . ' days ago';
                                    }
                                    ?>
                                </span>
                            </div>
                            
                            <!-- Job Description -->
                            <p style="color: #666; margin-bottom: 1.5rem; line-height: 1.6;">
                                <?php echo substr(htmlspecialchars($job['description']), 0, 200); ?>...
                            </p>
                            
                            <!-- Action Buttons -->
                            <div style="display: flex; gap: 1rem; align-items: center;">
                                <a href="job_details.php?id=<?php echo $job['job_id']; ?>" 
                                   style="background: #1866a3; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                                
                                <?php if (in_array($job['job_id'], $applied_jobs)): ?>
                                    <span style="background: #4CAF50; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-check"></i>
                                        Applied
                                    </span>
                                <?php elseif (!$profile_completed): ?>
                                    <a href="profile.php" 
                                       style="background: #FF9800; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;"
                                       title="Complete your profile to apply">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Complete Profile to Apply
                                    </a>
                                <?php else: ?>
                                    <a href="apply_job.php?id=<?php echo $job['job_id']; ?>" 
                                       style="background: #ff6a00; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                        <i class="fas fa-paper-plane"></i>
                                        Apply Now
                                    </a>
                                <?php endif; ?>
                                
                                <button onclick="unsaveJob(<?php echo $job['job_id']; ?>, this)" 
                                        style="background: #f44336; color: white; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-trash-alt"></i>
                                    Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: #666;">
                    <i class="fas fa-bookmark" style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;"></i>
                    <h3 style="color: #333; margin-bottom: 0.5rem;">No saved jobs yet</h3>
                    <p>Start saving jobs that interest you to keep track of them here.</p>
                    <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                        <a href="jobs.php" 
                           style="background: #1866a3; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-search"></i>
                            Browse Jobs
                        </a>
                        <a href="browse_jobs.php" 
                           style="background: #ff6a00; color: white; padding: 0.75rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                            <i class="fas fa-briefcase"></i>
                            Find Jobs
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function unsaveJob(jobId, button) {
    if (!confirm('Are you sure you want to remove this job from your saved list?')) {
        return;
    }

    // Show loading state
    const originalHTML = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Removing...';
    button.disabled = true;
    
    // Send AJAX request to unsave job
    fetch('save_job.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `job_id=${jobId}&action=unsave`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the job card from the page with animation
            const jobCard = button.closest('.card > div > div'); // Get the job card element
            jobCard.style.opacity = '0';
            jobCard.style.transform = 'translateX(-100%)';
            jobCard.style.transition = 'all 0.3s ease';
            
            setTimeout(() => {
                jobCard.remove();
                
                // Update the job count
                const jobCountElement = document.querySelector('h2');
                const currentCount = parseInt(jobCountElement.textContent);
                const newCount = currentCount - 1;
                
                if (newCount === 0) {
                    // Show empty state if no more jobs
                    location.reload(); // Reload to show empty state
                } else {
                    jobCountElement.textContent = newCount + ' saved job' + (newCount !== 1 ? 's' : '');
                }
                
                showNotification('Job removed from saved list!', 'success');
            }, 300);
        } else {
            showNotification('Failed to remove job: ' + data.message, 'error');
            button.innerHTML = originalHTML;
            button.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Error removing job. Please try again.', 'error');
        button.innerHTML = originalHTML;
        button.disabled = false;
    });
}

function showNotification(message, type) {
    // Create notification element
    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        border-radius: 6px;
        color: white;
        font-weight: 600;
        z-index: 10001;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
        max-width: 300px;
    `;
    
    if (type === 'success') {
        notification.style.background = '#4CAF50';
    } else {
        notification.style.background = '#f44336';
    }
    
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Remove notification after 3 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}
</script>

<?php include '../includes/footer.php'; ?>