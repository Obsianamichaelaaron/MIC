<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "Employer Dashboard";
$user_id = getCurrentUserId();

// Get employer data
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT e.*, u.first_name, u.last_name, u.email FROM employers e JOIN users u ON e.user_id = u.user_id WHERE e.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get statistics
$employer_id = $employer['employer_id'];

// Total jobs posted
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE employer_id = ?");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$total_jobs = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Active jobs
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE employer_id = ? AND status = 'active'");
$stmt->bind_param("i", $employer_id);
$stmt->execute();
$active_jobs = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Total applications (all applications from all jobs - public view)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications");
$stmt->execute();
$total_applications = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Pending applications (all pending applications - public view)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE status = 'pending'");
$stmt->execute();
$pending_applications = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Recent applications (all applications from all jobs - public view)
$stmt = $conn->prepare("
    SELECT a.*, jp.title as job_title, u.first_name, u.last_name, u.email, ap.employability_score, e.company_name
    FROM applications a
    JOIN job_postings jp ON a.job_id = jp.job_id
    JOIN applicants ap ON a.applicant_id = ap.applicant_id
    JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN employers e ON jp.employer_id = e.employer_id
    ORDER BY a.applied_at DESC
    LIMIT 5
");
$stmt->execute();
$recent_applications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();

include '../includes/header.php';
?>

<!-- Full Width Dashboard Header -->
<div style="background: url('../images/bg.jpg') center/cover no-repeat; padding: 1.5rem 0; margin: 0 -8px 1rem -8px;">
    <div style="max-width: 1400px; margin: 0 auto; padding: 0 1rem;">
        <div style="text-align: center; color: white;">
            <h1 style="color: white; margin-bottom: 0.3rem; font-size: 1.5rem;">
                <i class="fas fa-tachometer-alt"></i> Employer Dashboard
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 0.9rem; margin: 0;">
                Welcome back, <?php echo htmlspecialchars($employer['first_name']); ?>! Here's your hiring overview.
            </p>
        </div>
    </div>
</div>

<div class="container" style="max-width: 1400px; padding: 0 1rem;">
    <div class="card" style="margin-top: 0;">
        
        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; align-items: stretch;">
            <!-- Total Jobs Posted Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#1866a3';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-briefcase" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $total_jobs; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Total Jobs</p>
            </div>
            
            <!-- Active Jobs Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#4CAF50';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-check-circle" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $active_jobs; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Active Jobs</p>
            </div>
            
            <!-- Total Applications Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#ff6b00';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #ff6b00 0%, #e55a00 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-paper-plane" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $total_applications; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Total Applications</p>
            </div>
            
            <!-- Pending Review Card -->
            <div style="border: 1px solid #e0e0e0; padding: 1.2rem; border-radius: 6px; transition: all 0.3s ease; background: white; text-align: center;"
                 onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#2196F3';"
                 onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                <div style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%); color: white; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.8rem;">
                    <i class="fas fa-clock" style="font-size: 1.1rem;"></i>
                </div>
                <h3 style="font-size: 1.8rem; margin-bottom: 0.3rem; line-height: 1.2; color: #333;"><?php echo $pending_applications; ?></h3>
                <p style="margin: 0; color: #666; font-weight: 500; font-size: 0.85rem;">Pending Review</p>
            </div>
        </div>
        
        <!-- AI Recommendations Section -->
        <div style="margin-bottom: 1.5rem;">
            <div style="background: #f8f9fa; padding: 1.2rem; border-radius: 8px; border-left: 4px solid #9C27B0;">
                <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1.1rem; font-weight: 600;">
                    <i class="fas fa-robot"></i> AI-Powered Candidate Recommendations
                </h3>
                <p style="color: #666; margin-bottom: 1rem; line-height: 1.5; font-size: 0.9rem;">
                    View candidate chatbot assessment data and employability scores when reviewing applications.
                </p>
                <div style="display: flex; gap: 0.8rem; flex-wrap: wrap;">
                    <a href="recommended_candidates.php" 
                       style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.85rem;"
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(156, 39, 176, 0.3)';"
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-star"></i> Recommended
                    </a>
                    <a href="candidates.php" 
                       style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; transition: all 0.3s ease; font-size: 0.85rem;"
                       onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 4px 12px rgba(24, 102, 163, 0.3)';"
                       onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none';">
                        <i class="fas fa-users"></i> All Candidates
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent Applications Section -->
        <div>
            <h2 style="margin-bottom: 1rem; color: #333; font-size: 1.2rem; font-weight: 600;">
                <i class="fas fa-file-alt"></i> Recent Applications
            </h2>
            <?php if (count($recent_applications) > 0): ?>
                <div style="display: grid; gap: 1rem;">
                    <?php foreach ($recent_applications as $app): ?>
                        <div style="border: 1px solid #e0e0e0; padding: 1rem; border-radius: 6px; transition: all 0.3s ease; background: white;"
                             onmouseover="this.style.boxShadow='0 4px 12px rgba(0,0,0,0.1)'; this.style.borderColor='#1866a3';"
                             onmouseout="this.style.boxShadow='none'; this.style.borderColor='#e0e0e0';">
                            
                            <!-- Application Header -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.8rem;">
                                <div style="flex: 1;">
                                    <h3 style="color: #1866a3; margin-bottom: 0.3rem; font-size: 1.1rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                    </h3>
                                    <p style="color: #666; margin-bottom: 0.3rem; font-size: 0.9rem; font-weight: 500;">
                                        <i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($app['job_title']); ?>
                                    </p>
                                    <p style="color: #666; margin-bottom: 0.3rem; font-size: 0.85rem;">
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($app['email']); ?>
                                    </p>
                                   
                                </div>
                                
                                <!-- Status -->
                                <div style="text-align: right;">
                                    <?php
                                    $status_colors = [
                                        'pending' => '#FF9800',
                                        'reviewed' => '#2196F3',
                                        'shortlisted' => '#9C27B0',
                                        'interviewed' => '#673AB7',
                                        'accepted' => '#4CAF50',
                                        'rejected' => '#F44336'
                                    ];
                                    $color = $status_colors[$app['status']] ?? '#666';
                                    ?>
                                    <span style="background: <?php echo $color; ?>; color: white; padding: 0.3rem 0.8rem; border-radius: 15px; font-weight: 600; text-transform: uppercase; font-size: 0.7rem;">
                                        <?php echo $app['status']; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Action Button -->
                            <div style="display: flex; gap: 0.8rem; align-items: center;">
                                <a href="view_candidate.php?id=<?php echo $app['application_id']; ?>" 
                                   style="background: #1866a3; color: white; padding: 0.5rem 1rem; border-radius: 4px; text-decoration: none; font-weight: 600; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                                    <i class="fas fa-eye"></i>
                                    View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 2rem; color: #666;">
                    <i class="fas fa-file-alt" style="font-size: 2rem; color: #ddd; margin-bottom: 0.5rem;"></i>
                    <h3 style="color: #333; margin-bottom: 0.5rem; font-size: 1.1rem;">No applications yet</h3>
                    <p style="font-size: 0.9rem;">Post your first job to start receiving applications.</p>
                    <a href="post_job.php" 
                       style="background: #1866a3; color: white; padding: 0.6rem 1.2rem; border-radius: 6px; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.4rem; margin-top: 0.8rem; font-size: 0.85rem;">
                        <i class="fas fa-plus-circle"></i>
                        Post a Job
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Floating Message Icon -->
<style>
.floating-message-container {
    position: fixed;
    bottom: 25px;
    right: 25px;
    z-index: 10000;
    transition: all 0.3s ease;
}

.floating-message-btn {
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 20px rgba(0, 86, 179, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    border: none;
    font-size: 1.4rem;
    position: relative;
}

.floating-message-btn:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 86, 179, 0.5);
    color: white;
    text-decoration: none;
}

.floating-message-btn:active {
    transform: translateY(-1px) scale(1.02);
}

.message-badge {
    position: absolute;
    top: -3px;
    right: -3px;
    background: #F44336;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    border: 3px solid white;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { 
        transform: scale(1); 
        box-shadow: 0 0 0 0 rgba(244, 67, 54, 0.7);
    }
    50% { 
        transform: scale(1.05);
        box-shadow: 0 0 0 10px rgba(244, 67, 54, 0);
    }
    100% { 
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(244, 67, 54, 0);
    }
}

.message-tooltip {
    position: absolute;
    right: 75px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    white-space: nowrap;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    pointer-events: none;
}

.message-tooltip::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 100%;
    transform: translateY(-50%);
    border-width: 6px;
    border-style: solid;
    border-color: transparent transparent transparent rgba(0, 0, 0, 0.8);
}

.floating-message-btn:hover .message-tooltip {
    opacity: 1;
    visibility: visible;
    right: 80px;
}

/* Ensure it stays above all other content */
.floating-message-container * {
    box-sizing: border-box;
}

/* Mobile responsiveness */
@media (max-width: 768px) {
    .floating-message-container {
        bottom: 20px;
        right: 20px;
    }
    
    .floating-message-btn {
        width: 60px;
        height: 60px;
        font-size: 1.3rem;
    }
    
    .message-badge {
        width: 22px;
        height: 22px;
        font-size: 0.7rem;
        border-width: 2px;
    }
    
    .message-tooltip {
        display: none; /* Hide tooltip on mobile */
    }
}

@media (max-width: 480px) {
    .floating-message-container {
        bottom: 15px;
        right: 15px;
    }
    
    .floating-message-btn {
        width: 55px;
        height: 55px;
        font-size: 1.2rem;
        box-shadow: 0 3px 15px rgba(0, 86, 179, 0.4);
    }
    
    .message-badge {
        width: 20px;
        height: 20px;
        font-size: 0.65rem;
        top: -2px;
        right: -2px;
    }
}

/* Print styles - hide when printing */
@media print {
    .floating-message-container {
        display: none !important;
    }
}
</style>

<?php
// Get unread message count for the floating icon
$unread_count = 0;
try {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $unread_count = $row['unread_count'];
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    // Silently fail - don't break the page if message count fails
    error_log("Error getting unread count: " . $e->getMessage());
}
?>

<div class="floating-message-container">
    <a href="chat.php" class="floating-message-btn" title="Messages">
        <i class="fas fa-comments"></i>
        <?php if ($unread_count > 0): ?>
            <span class="message-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
        <?php endif; ?>
        <span class="message-tooltip">
            <?php echo $unread_count > 0 ? "You have $unread_count unread message(s)" : "Go to Messages"; ?>
        </span>
    </a>
</div>

<script>
// Remove loading class if it exists
document.addEventListener('DOMContentLoaded', function() {
    const content = document.getElementById('employerDashboardContent');
    if (content) {
        content.classList.remove('loading');
    }

    // Optional: Add interactive effects to floating message button
    const floatingBtn = document.querySelector('.floating-message-btn');
    
    if (floatingBtn) {
        // Add click animation
        floatingBtn.addEventListener('click', function(e) {
            // Add ripple effect
            const ripple = document.createElement('span');
            ripple.style.cssText = `
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.5);
                transform: translate(-50%, -50%);
                animation: ripple 0.6s ease-out;
            `;
            
            const style = document.createElement('style');
            style.textContent = `
                @keyframes ripple {
                    to {
                        width: 200%;
                        height: 200%;
                        opacity: 0;
                    }
                }
            `;
            
            document.head.appendChild(style);
            this.appendChild(ripple);
            
            setTimeout(() => {
                if (ripple.parentNode) {
                    ripple.parentNode.removeChild(ripple);
                }
                if (style.parentNode) {
                    style.parentNode.removeChild(style);
                }
            }, 600);
        });
        
        // Prevent hiding on scroll
        let lastScrollTop = 0;
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const container = document.querySelector('.floating-message-container');
            
            if (container) {
                if (scrollTop > lastScrollTop) {
                    // Scrolling down
                    container.style.transform = 'translateY(0)';
                } else {
                    // Scrolling up
                    container.style.transform = 'translateY(0)';
                }
            }
            lastScrollTop = scrollTop;
        });
    }

    // Auto-update message count every 30 seconds
    function updateFloatingMessageCount() {
        fetch('../includes/handlers/message_handler.php?action=get_unread_count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateMessageBadge(data.count);
                }
            })
            .catch(error => console.error('Error updating message count:', error));
    }

    function updateMessageBadge(count) {
        const badge = document.querySelector('.message-badge');
        const tooltip = document.querySelector('.message-tooltip');
        const btn = document.querySelector('.floating-message-btn');
        
        if (count > 0) {
            if (badge) {
                badge.textContent = count > 9 ? '9+' : count;
            } else {
                // Create badge if it doesn't exist
                const newBadge = document.createElement('span');
                newBadge.className = 'message-badge';
                newBadge.textContent = count > 9 ? '9+' : count;
                btn.appendChild(newBadge);
            }
            
            // Update tooltip
            if (tooltip) {
                tooltip.textContent = `You have ${count} unread message(s)`;
            }
        } else {
            // Remove badge if no unread messages
            if (badge) {
                badge.remove();
            }
            if (tooltip) {
                tooltip.textContent = 'Go to Messages';
            }
        }
    }

    // Start auto-refresh (only if user is logged in)
    if (document.querySelector('.floating-message-btn')) {
        setInterval(updateFloatingMessageCount, 30000); // Every 30 seconds
    }
});
</script>

<?php include '../includes/footer.php'; ?>