<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('employer');

$pageTitle = "Candidate Details";
$application_id = $_GET['id'] ?? 0;
$applicant_id_param = $_GET['applicant_id'] ?? 0;
$job_id_param = $_GET['job_id'] ?? 0;
$user_id = getCurrentUserId();

// Get employer ID
$conn = getDBConnection();
$stmt = $conn->prepare("SELECT employer_id FROM employers WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$employer = $stmt->get_result()->fetch_assoc();
$employer_id = $employer['employer_id'];
$stmt->close();

// Check if resume_file column exists in applications table, if not, add it
$column_check = $conn->query("SHOW COLUMNS FROM applications LIKE 'resume_file'");
if (!$column_check || $column_check->num_rows == 0) {
    // Add resume_file column to applications table
    $alter_sql = "ALTER TABLE applications ADD COLUMN resume_file VARCHAR(255) NULL AFTER cover_letter";
    if ($conn->query($alter_sql)) {
        // Column added successfully
    } else {
        // Log error but continue (column might already exist or permission issue)
        error_log("Warning: Could not add resume_file column: " . $conn->error);
    }
}

$application = null;

// If viewing by application ID (existing application)
if ($application_id > 0) {
    $stmt = $conn->prepare("
        SELECT 
            a.*,
            a.resume_file as application_resume_file,
            ap.*,
            ap.resume_file as profile_resume_file,
            ap.employability_score as applicant_employability_score,
            ap.skills as applicant_skills,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            jp.title as job_title,
            jp.job_id,
            e.company_name,
            e.employer_id as job_employer_id
        FROM applications a
        JOIN applicants ap ON a.applicant_id = ap.applicant_id
        JOIN users u ON ap.user_id = u.user_id
        JOIN job_postings jp ON a.job_id = jp.job_id
        LEFT JOIN employers e ON jp.employer_id = e.employer_id
        WHERE a.application_id = ?
    ");
    $stmt->bind_param("i", $application_id);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} 
// If viewing by applicant_id and job_id (recommended candidate who hasn't applied)
elseif ($applicant_id_param > 0 && $job_id_param > 0) {
    $stmt = $conn->prepare("
        SELECT 
            ap.*,
            ap.resume_file as profile_resume_file,
            ap.employability_score as applicant_employability_score,
            ap.skills as applicant_skills,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            jp.title as job_title,
            jp.job_id,
            jp.description as job_description,
            jp.requirements,
            jp.skills_required,
            e.company_name,
            e.employer_id as job_employer_id,
            NULL as application_id,
            NULL as status,
            NULL as cover_letter,
            NULL as match_score,
            NULL as applied_at,
            NULL as application_resume_file
        FROM applicants ap
        JOIN users u ON ap.user_id = u.user_id
        JOIN job_postings jp ON jp.job_id = ?
        LEFT JOIN employers e ON jp.employer_id = e.employer_id
        WHERE ap.applicant_id = ?
    ");
    $stmt->bind_param("ii", $job_id_param, $applicant_id_param);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$application) {
    header('Location: candidates.php');
    exit();
}

// Get the correct employability score - prioritize applicant's actual score with skill bonus
$employability_score = $application['applicant_employability_score'] ?? 0;
$applicant_skills = $application['applicant_skills'] ?? '';

// Calculate skill bonus - +3 points for each skill (same as applicant dashboard)
$skill_bonus = 0;
if (!empty($applicant_skills)) {
    $skills_array = array_map('trim', explode(',', $applicant_skills));
    $valid_skills = array_filter($skills_array); // Remove empty values
    $skill_bonus = count($valid_skills) * 3;
}

// Add skill bonus to the base score (cap at 100)
$final_employability_score = min(100, $employability_score + $skill_bonus);

// Format the score for display
$display_score = number_format($final_employability_score, 2);

// Get resume analysis data (skills extracted from resume)
require_once '../includes/handlers/resume_parser.php';
$resume_analysis = getResumeAnalysis($conn, $application['applicant_id']);

// Get chatbot answers data
$chatbot_answers = [];
$stmt_chatbot = $conn->prepare("
    SELECT ca.*, q.name as qualification_name 
    FROM chatbot_answers ca 
    LEFT JOIN qualifications q ON ca.qualification_id = q.qualification_id 
    WHERE ca.applicant_id = ? 
    ORDER BY ca.question_number ASC
");
$stmt_chatbot->bind_param("i", $application['applicant_id']);
$stmt_chatbot->execute();
$chatbot_answers = $stmt_chatbot->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_chatbot->close();

// Get feedback for this application
$feedback = null;
if (!empty($application['application_id'])) {
    $table_check = $conn->query("SHOW TABLES LIKE 'candidate_feedback'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt_feedback = $conn->prepare("
            SELECT * FROM candidate_feedback 
            WHERE application_id = ? AND employer_id = ?
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt_feedback->bind_param("ii", $application['application_id'], $employer_id);
        $stmt_feedback->execute();
        $feedback = $stmt_feedback->get_result()->fetch_assoc();
        $stmt_feedback->close();
    }
}

// Get remarks/justification history to show to applicant
$applicant_remarks = [];
if (!empty($application['application_id'])) {
    // Check if applicant_remarks_history column exists, if not, add it
    $column_check = $conn->query("SHOW COLUMNS FROM applications LIKE 'applicant_remarks_history'");
    if (!$column_check || $column_check->num_rows == 0) {
        $alter_sql = "ALTER TABLE applications ADD COLUMN applicant_remarks_history TEXT NULL AFTER remarks_history";
        $conn->query($alter_sql);
    }
    
    // Get existing applicant remarks history
    $stmt_remarks = $conn->prepare("SELECT applicant_remarks_history FROM applications WHERE application_id = ?");
    $stmt_remarks->bind_param("i", $application['application_id']);
    $stmt_remarks->execute();
    $result = $stmt_remarks->get_result();
    $existing_applicant_history = '';
    if ($row = $result->fetch_assoc()) {
        $existing_applicant_history = $row['applicant_remarks_history'] ?? '';
    }
    $stmt_remarks->close();
    
    // Decode existing history
    if (!empty($existing_applicant_history)) {
        $applicant_remarks = json_decode($existing_applicant_history, true);
        if (!is_array($applicant_remarks)) {
            $applicant_remarks = [];
        }
    }
}

// Get interview appointments for this application
$interviews = [];
if (!empty($application['application_id'])) {
    // Create interview_schedules table if it doesn't exist
    $table_check = $conn->query("SHOW TABLES LIKE 'interview_schedules'");
    if (!$table_check || $table_check->num_rows == 0) {
        $create_table_sql = "
            CREATE TABLE interview_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                application_id INT NOT NULL,
                employer_id INT NOT NULL,
                interview_date DATE NOT NULL,
                start_time TIME NOT NULL,
                end_time TIME NOT NULL,
                interview_type VARCHAR(50) NOT NULL COMMENT 'in-person, phone, video',
                location VARCHAR(255),
                meeting_link VARCHAR(255),
                notes TEXT,
                status VARCHAR(20) DEFAULT 'scheduled' COMMENT 'scheduled, completed, cancelled, rescheduled',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (application_id) REFERENCES applications(application_id) ON DELETE CASCADE,
                FOREIGN KEY (employer_id) REFERENCES employers(employer_id) ON DELETE CASCADE
            )
        ";
        $conn->query($create_table_sql);
    }
    
    // Get existing interviews
    $stmt_interviews = $conn->prepare("
        SELECT * FROM interview_schedules 
        WHERE application_id = ? 
        ORDER BY interview_date DESC, start_time DESC
    ");
    $stmt_interviews->bind_param("i", $application['application_id']);
    $stmt_interviews->execute();
    $interviews = $stmt_interviews->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_interviews->close();
}

// Use skills from resume analysis if available, otherwise use applicant record
$display_skills = '';
if ($resume_analysis && !empty($resume_analysis['skills_extracted'])) {
    $display_skills = $resume_analysis['skills_extracted'];
} elseif (!empty($application['skills'])) {
    $display_skills = $application['skills'];
}

// Use qualifications from resume analysis if available, otherwise use applicant record
$display_qualifications = '';
if ($resume_analysis && !empty($resume_analysis['qualifications_extracted'])) {
    $display_qualifications = $resume_analysis['qualifications_extracted'];
    // Clean qualifications text for display
    $display_qualifications = preg_replace('/[^\x20-\x7E\s]/', '', $display_qualifications);
    // Check if it contains actual qualification keywords
    $qual_keywords = ['bachelor', 'master', 'phd', 'doctorate', 'degree', 'diploma', 'certificate', 
                      'bs', 'ba', 'ms', 'ma', 'mba', 'bsc', 'msc', 'university', 'college', 'school'];
    $has_qual_keyword = false;
    foreach ($qual_keywords as $keyword) {
        if (stripos($display_qualifications, $keyword) !== false) {
            $has_qual_keyword = true;
            break;
        }
    }
    if (!$has_qual_keyword) {
        $display_qualifications = ''; // Don't show garbled qualifications
    }
}
if (empty($display_qualifications) && !empty($application['qualifications'])) {
    $display_qualifications = $application['qualifications'];
}

// Handle status update (only if application exists)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']) && !empty($application['application_id'])) {
    $new_status = $_POST['status'];
    $remarks = trim($_POST['remarks'] ?? ''); // Get remarks from text box
    $app_id = $application['application_id'];
    
    // Validate that remarks are provided
    if (empty($remarks)) {
        $error = "Please provide remarks/justification for the status change.";
    } else {
        // Get current employer's name
        $stmt_emp = $conn->prepare("SELECT u.first_name, u.last_name FROM users u WHERE u.user_id = ?");
        $stmt_emp->bind_param("i", $user_id);
    $stmt_emp->execute();
        $employer_user = $stmt_emp->get_result()->fetch_assoc();
        $stmt_emp->close();
        
        $employer_name = trim($employer_user['first_name'] . ' ' . $employer_user['last_name']);
        
        // Check if remarks_history column exists, if not, add it
        $column_check = $conn->query("SHOW COLUMNS FROM applications LIKE 'remarks_history'");
        if (!$column_check || $column_check->num_rows == 0) {
            $alter_sql = "ALTER TABLE applications ADD COLUMN remarks_history TEXT NULL AFTER status";
            $conn->query($alter_sql);
        }
        
        // Get existing remarks history
        $stmt_history = $conn->prepare("SELECT remarks_history FROM applications WHERE application_id = ?");
        $stmt_history->bind_param("i", $app_id);
        $stmt_history->execute();
        $result = $stmt_history->get_result();
        $existing_history = '';
        if ($row = $result->fetch_assoc()) {
            $existing_history = $row['remarks_history'] ?? '';
        }
        $stmt_history->close();
        
        // Create new remarks entry
        $new_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $new_status,
            'remarks' => $remarks,
            'reviewed_by' => $employer_name
        ];
        
        // Decode existing history or start new array
        $history_array = [];
        if (!empty($existing_history)) {
            $history_array = json_decode($existing_history, true);
            if (!is_array($history_array)) {
                $history_array = [];
            }
        }
        
        // Add new entry to history
        $history_array[] = $new_entry;
        $updated_history = json_encode($history_array);
        
        // **MODIFIED PART: Create applicant-facing remarks entry**
        $applicant_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => $new_status,
            'remarks' => $remarks, // This will be visible to applicant
            'reviewed_by' => $employer_name
        ];
        
        // Get existing applicant remarks history
        $stmt_applicant = $conn->prepare("SELECT applicant_remarks_history FROM applications WHERE application_id = ?");
        $stmt_applicant->bind_param("i", $app_id);
        $stmt_applicant->execute();
        $applicant_result = $stmt_applicant->get_result();
        $existing_applicant_history = '';
        if ($row = $applicant_result->fetch_assoc()) {
            $existing_applicant_history = $row['applicant_remarks_history'] ?? '';
        }
        $stmt_applicant->close();
        
        // Decode existing applicant history or start new array
        $applicant_history_array = [];
        if (!empty($existing_applicant_history)) {
            $applicant_history_array = json_decode($existing_applicant_history, true);
            if (!is_array($applicant_history_array)) {
                $applicant_history_array = [];
            }
        }
        
        // Add new entry to applicant history
        $applicant_history_array[] = $applicant_entry;
        $updated_applicant_history = json_encode($applicant_history_array);
        
        // Update application with status, reviewer info, and both histories
        if (in_array($new_status, ['accepted', 'rejected'])) {
            $stmt = $conn->prepare("UPDATE applications SET status = ?, reviewed_by_employer_id = ?, reviewed_by_name = ?, remarks_history = ?, applicant_remarks_history = ? WHERE application_id = ?");
            $stmt->bind_param("sisssi", $new_status, $employer_id, $employer_name, $updated_history, $updated_applicant_history, $app_id);
        } else {
            $stmt = $conn->prepare("UPDATE applications SET status = ?, remarks_history = ?, applicant_remarks_history = ? WHERE application_id = ?");
            $stmt->bind_param("sssi", $new_status, $updated_history, $updated_applicant_history, $app_id);
        }
        
        if ($stmt->execute()) {
            $success = "Status updated successfully! Remarks will be visible to the applicant.";
            // Refresh application data
            $stmt_refresh = $conn->prepare("
                SELECT 
                    a.*,
                    ap.*,
                    ap.employability_score as applicant_employability_score,
                    ap.skills as applicant_skills,
                    u.first_name,
                    u.last_name,
                    u.email,
                    u.phone,
                    jp.title as job_title,
                    jp.job_id,
                    e.company_name,
                    e.employer_id as job_employer_id
                FROM applications a
                JOIN applicants ap ON a.applicant_id = ap.applicant_id
                JOIN users u ON ap.user_id = u.user_id
                JOIN job_postings jp ON a.job_id = jp.job_id
                LEFT JOIN employers e ON jp.employer_id = e.employer_id
                WHERE a.application_id = ?
            ");
            $stmt_refresh->bind_param("i", $app_id);
            $stmt_refresh->execute();
            $application = $stmt_refresh->get_result()->fetch_assoc();
            $stmt_refresh->close();
            
            // Update the employability score with the refreshed data
            $employability_score = $application['applicant_employability_score'] ?? 0;
            $applicant_skills = $application['applicant_skills'] ?? '';
            
            // Recalculate the final score with skill bonus
            $skill_bonus = 0;
            if (!empty($applicant_skills)) {
                $skills_array = array_map('trim', explode(',', $applicant_skills));
                $valid_skills = array_filter($skills_array);
                $skill_bonus = count($valid_skills) * 3;
            }
            $final_employability_score = min(100, $employability_score + $skill_bonus);
            $display_score = number_format($final_employability_score, 2);
            
            // Refresh applicant remarks
            $stmt_remarks_refresh = $conn->prepare("SELECT applicant_remarks_history FROM applications WHERE application_id = ?");
            $stmt_remarks_refresh->bind_param("i", $app_id);
            $stmt_remarks_refresh->execute();
            $result = $stmt_remarks_refresh->get_result();
            $existing_applicant_history = '';
            if ($row = $result->fetch_assoc()) {
                $existing_applicant_history = $row['applicant_remarks_history'] ?? '';
            }
            $stmt_remarks_refresh->close();
            
            if (!empty($existing_applicant_history)) {
                $applicant_remarks = json_decode($existing_applicant_history, true);
                if (!is_array($applicant_remarks)) {
                    $applicant_remarks = [];
                }
            }
            
            // Create notification for applicant
            $stmt2 = $conn->prepare("SELECT user_id FROM applicants WHERE applicant_id = ?");
            $stmt2->bind_param("i", $application['applicant_id']);
            $stmt2->execute();
            $applicant_user = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            
            $notification_msg = "Your application status has been updated to: " . $new_status;
            if (in_array($new_status, ['accepted', 'rejected']) && !empty($employer_name)) {
                $notification_msg .= " by " . $employer_name;
            }
            if (!empty($remarks)) {
                $notification_msg .= " - Remarks: " . $remarks;
            }
            
            createNotification($conn, $applicant_user['user_id'], "Application Update", $notification_msg, 'application');
            
            $application['status'] = $new_status;
            if (in_array($new_status, ['accepted', 'rejected'])) {
                $application['reviewed_by_name'] = $employer_name;
            }
        } else {
            $error = "Failed to update status";
        }
        
        $stmt->close();
    }
}

// Handle interview scheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['schedule_interview']) && !empty($application['application_id'])) {
    $interview_date = $_POST['interview_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $interview_type = $_POST['interview_type'];
    $location = $_POST['location'] ?? '';
    $meeting_link = $_POST['meeting_link'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $app_id = $application['application_id'];
    
    // Validate required fields
    if (empty($interview_date) || empty($start_time) || empty($end_time) || empty($interview_type)) {
        $interview_error = "Please fill in all required fields.";
    } elseif (strtotime($end_time) <= strtotime($start_time)) {
        $interview_error = "End time must be after start time.";
    } elseif (strtotime($interview_date . ' ' . $start_time) < time()) {
        $interview_error = "Interview date and time must be in the future.";
    } else {
        // Get employer's name for notification
        $stmt_emp = $conn->prepare("SELECT u.first_name, u.last_name FROM users u WHERE u.user_id = ?");
        $stmt_emp->bind_param("i", $user_id);
        $stmt_emp->execute();
        $employer_user = $stmt_emp->get_result()->fetch_assoc();
        $stmt_emp->close();
        $employer_name = trim($employer_user['first_name'] . ' ' . $employer_user['last_name']);
        
        // Insert interview schedule
        $stmt = $conn->prepare("
            INSERT INTO interview_schedules 
            (application_id, employer_id, interview_date, start_time, end_time, interview_type, location, meeting_link, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisssssss", $app_id, $employer_id, $interview_date, $start_time, $end_time, $interview_type, $location, $meeting_link, $notes);
        
        if ($stmt->execute()) {
            $interview_success = "Interview scheduled successfully!";
            
            // Update application status to 'scheduled for interview' if not already
            if ($application['status'] !== 'scheduled for interview') {
                $conn->query("UPDATE applications SET status = 'scheduled for interview' WHERE application_id = $app_id");
                $application['status'] = 'scheduled for interview';
            }
            
            // Create notification for applicant
            $stmt2 = $conn->prepare("SELECT user_id FROM applicants WHERE applicant_id = ?");
            $stmt2->bind_param("i", $application['applicant_id']);
            $stmt2->execute();
            $applicant_user = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();
            
            $interview_datetime = date('F d, Y h:i A', strtotime($interview_date . ' ' . $start_time));
            $notification_msg = "An interview has been scheduled for your application: $interview_datetime";
            if (!empty($location)) {
                $notification_msg .= " at $location";
            }
            if (!empty($meeting_link)) {
                $notification_msg .= " - Meeting Link: $meeting_link";
            }
            $notification_msg .= " (Type: $interview_type)";
            
            createNotification($conn, $applicant_user['user_id'], "Interview Scheduled", $notification_msg, 'interview');
            
            // Send email notification to applicant (if email function exists)
            if (function_exists('sendInterviewNotification')) {
                sendInterviewNotification(
                    $application['email'],
                    $application['first_name'] . ' ' . $application['last_name'],
                    $interview_date,
                    $start_time,
                    $end_time,
                    $interview_type,
                    $location,
                    $meeting_link,
                    $notes,
                    $application['job_title'],
                    $employer_name
                );
            }
            
            // Refresh interviews list
            $stmt_interviews = $conn->prepare("
                SELECT * FROM interview_schedules 
                WHERE application_id = ? 
                ORDER BY interview_date DESC, start_time DESC
            ");
            $stmt_interviews->bind_param("i", $app_id);
            $stmt_interviews->execute();
            $interviews = $stmt_interviews->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt_interviews->close();
        } else {
            $interview_error = "Failed to schedule interview: " . $conn->error;
        }
        $stmt->close();
    }
}

// Handle interview cancellation/rescheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_interview_status']) && !empty($_POST['interview_id'])) {
    $interview_id = $_POST['interview_id'];
    $new_status = $_POST['interview_status'];
    $cancellation_reason = $_POST['cancellation_reason'] ?? '';
    
    // Update interview status
    $stmt = $conn->prepare("UPDATE interview_schedules SET status = ? WHERE id = ? AND application_id = ?");
    $stmt->bind_param("sii", $new_status, $interview_id, $application['application_id']);
    
    if ($stmt->execute()) {
        $interview_success = "Interview status updated to " . $new_status . ".";
        
        // Create notification for applicant
        $stmt2 = $conn->prepare("SELECT user_id FROM applicants WHERE applicant_id = ?");
        $stmt2->bind_param("i", $application['applicant_id']);
        $stmt2->execute();
        $applicant_user = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();
        
        // Get interview details for notification
        $stmt_int = $conn->prepare("SELECT interview_date, start_time FROM interview_schedules WHERE id = ?");
        $stmt_int->bind_param("i", $interview_id);
        $stmt_int->execute();
        $interview_details = $stmt_int->get_result()->fetch_assoc();
        $stmt_int->close();
        
        $interview_datetime = date('F d, Y h:i A', strtotime($interview_details['interview_date'] . ' ' . $interview_details['start_time']));
        $notification_msg = "Your interview scheduled for $interview_datetime has been $new_status.";
        if (!empty($cancellation_reason) && $new_status === 'cancelled') {
            $notification_msg .= " Reason: $cancellation_reason";
        }
        
        createNotification($conn, $applicant_user['user_id'], "Interview " . ucfirst($new_status), $notification_msg, 'interview');
        
        // Refresh interviews list
        $stmt_interviews = $conn->prepare("
            SELECT * FROM interview_schedules 
            WHERE application_id = ? 
            ORDER BY interview_date DESC, start_time DESC
        ");
        $stmt_interviews->bind_param("i", $application['application_id']);
        $stmt_interviews->execute();
        $interviews = $stmt_interviews->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_interviews->close();
    } else {
        $interview_error = "Failed to update interview status.";
    }
    $stmt->close();
}

function createNotification($conn, $user_id, $title, $message, $type) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    $stmt->execute();
    $stmt->close();
}

// Function to send email notification (you would implement this based on your email system)
function sendInterviewNotification($to_email, $applicant_name, $date, $start_time, $end_time, $type, $location, $meeting_link, $notes, $job_title, $employer_name) {
    // Implement your email sending logic here
    // This could use PHPMailer, mail() function, or a third-party service
    return true; // Return true if email sent successfully
}

include '../includes/header.php';
?>

<div class="container">
    <div class="card">
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($interview_success)): ?>
            <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($interview_success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($interview_error)): ?>
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <?php echo htmlspecialchars($interview_error); ?>
            </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
            <div>
                <h1 style="margin-bottom: 1rem; color: #0056b3;">
                    <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>
                </h1>
                
                <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem;">Contact Information</h3>
                    <p style="color: #666; margin-bottom: 0.5rem;">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($application['email']); ?>
                    </p>
                    <?php if (!empty($application['phone'])): ?>
                        <p style="color: #666; margin-bottom: 0.5rem;">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($application['phone']); ?>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem;">
                        Skills
                        <?php if ($resume_analysis && !empty($resume_analysis['skills_extracted'])): ?>
                            <span style="font-size: 0.8rem; color: #28a745; font-weight: normal;">
                                <i class="fas fa-check-circle"></i> Extracted from Resume
                            </span>
                        <?php endif; ?>
                    </h3>
                    <?php if (!empty($display_skills)): ?>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <?php 
                            $skills = explode(',', $display_skills);
                            foreach ($skills as $skill): 
                                $skill = trim($skill);
                                if (!empty($skill)):
                            ?>
                                <span style="background: #0056b3; color: white; padding: 0.5rem 1rem; border-radius: 20px; font-size: 0.9rem;">
                                    <?php echo htmlspecialchars($skill); ?>
                                </span>
                            <?php 
                                endif;
                            endforeach; 
                            ?>
                        </div>
                    <?php else: ?>
                        <p style="color: #666;">No skills listed</p>
                    <?php endif; ?>
                </div>
                
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem;">
                        Qualifications
                        <?php if ($resume_analysis && !empty($resume_analysis['qualifications_extracted'])): ?>
                            <span style="font-size: 0.8rem; color: #28a745; font-weight: normal;">
                                <i class="fas fa-check-circle"></i> Extracted from Resume
                            </span>
                        <?php endif; ?>
                    </h3>
                    <?php if (!empty($display_qualifications)): ?>
                        <p style="color: #666; line-height: 1.8; white-space: pre-wrap;">
                            <?php echo htmlspecialchars($display_qualifications); ?>
                        </p>
                    <?php else: ?>
                        <p style="color: #666;">No qualifications listed</p>
                    <?php endif; ?>
                </div>
                
                <!-- Interview Schedule Section -->
                <?php if (!empty($application['application_id'])): ?>
                    <div style="margin-bottom: 2rem; border: 2px solid #9C27B0; border-radius: 10px; overflow: hidden;">
                        <div style="background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); padding: 1rem; color: white;">
                            <h3 style="color: white; margin: 0; font-size: 1.2rem;">
                                <i class="fas fa-calendar-alt"></i> Interview Schedule
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                Schedule and manage interviews with the candidate
                            </p>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 1.5rem;">
                            <!-- Interview List -->
                            <?php if (count($interviews) > 0): ?>
                                <h4 style="color: #333; margin-bottom: 1rem; font-size: 1.1rem;">Scheduled Interviews</h4>
                                <?php foreach ($interviews as $interview): ?>
                                    <div style="background: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid #9C27B0;">
                                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                            <div>
                                                <span style="background: <?php 
                                                    switch($interview['status']) {
                                                        case 'scheduled': echo '#28a745';
                                                        case 'completed': echo '#007bff';
                                                        case 'cancelled': echo '#dc3545';
                                                        case 'rescheduled': echo '#ffc107';
                                                        default: echo '#6c757d';
                                                    }
                                                ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem; font-weight: 600; margin-right: 0.5rem;">
                                                    <?php echo htmlspecialchars(ucfirst($interview['status'])); ?>
                                                </span>
                                                <span style="color: #333; font-weight: 600; font-size: 1.1rem;">
                                                    <?php echo date('F d, Y', strtotime($interview['interview_date'])); ?>
                                                </span>
                                            </div>
                                            <span style="color: #666; font-size: 0.9rem;">
                                                <?php echo date('h:i A', strtotime($interview['start_time'])); ?> - 
                                                <?php echo date('h:i A', strtotime($interview['end_time'])); ?>
                                            </span>
                                        </div>
                                        
                                        <div style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                            <i class="fas fa-video"></i> <?php echo htmlspecialchars(ucfirst($interview['interview_type'])); ?> Interview
                                            <?php if ($interview['interview_type'] === 'in-person' && !empty($interview['location'])): ?>
                                                at <?php echo htmlspecialchars($interview['location']); ?>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($interview['meeting_link'])): ?>
                                            <div style="margin-bottom: 0.5rem;">
                                                <a href="<?php echo htmlspecialchars($interview['meeting_link']); ?>" 
                                                   target="_blank"
                                                   style="color: #0056b3; text-decoration: none; font-size: 0.9rem;">
                                                    <i class="fas fa-link"></i> Join Meeting
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($interview['notes'])): ?>
                                            <p style="color: #333; font-size: 0.9rem; margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px solid #eee;">
                                                <strong>Notes:</strong> <?php echo htmlspecialchars($interview['notes']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Interview Actions -->
                                        <?php if ($interview['status'] === 'scheduled'): ?>
                                            <form method="POST" action="" style="margin-top: 0.75rem;">
                                                <input type="hidden" name="interview_id" value="<?php echo $interview['id']; ?>">
                                                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                                    <select name="interview_status" style="padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;" required>
                                                        <option value="">Update Status</option>
                                                        <option value="completed">Mark as Completed</option>
                                                        <option value="cancelled">Cancel Interview</option>
                                                        <option value="rescheduled">Mark as Rescheduled</option>
                                                    </select>
                                                    <textarea name="cancellation_reason" 
                                                              placeholder="Reason for cancellation (if applicable)"
                                                              style="flex: 1; min-width: 200px; padding: 0.5rem; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9rem;"></textarea>
                                                    <button type="submit" name="update_interview_status" 
                                                            style="background: #dc3545; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; font-size: 0.9rem; cursor: pointer;">
                                                        Update
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #666; text-align: center; padding: 2rem 0;">
                                    <i class="fas fa-calendar-times"></i> No interviews scheduled yet.
                                </p>
                            <?php endif; ?>
                            
                            <!-- Schedule New Interview Form -->
                            <hr style="margin: 1.5rem 0; border-color: #eee;">
                            <h4 style="color: #333; margin-bottom: 1rem; font-size: 1.1rem;">Schedule New Interview</h4>
                            <form method="POST" action="">
                                <input type="hidden" name="schedule_interview" value="1">
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                    <div>
                                        <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                            Interview Date <span style="color: #dc3545;">*</span>
                                        </label>
                                        <input type="date" 
                                               name="interview_date" 
                                               min="<?php echo date('Y-m-d'); ?>" 
                                               style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem;"
                                               required>
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                            Interview Type <span style="color: #dc3545;">*</span>
                                        </label>
                                        <select name="interview_type" 
                                                style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem;"
                                                required>
                                            <option value="">Select Type</option>
                                            <option value="in-person">In-Person</option>
                                            <option value="video">Video Call</option>
                                            <option value="phone">Phone Call</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                                    <div>
                                        <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                            Start Time <span style="color: #dc3545;">*</span>
                                        </label>
                                        <input type="time" 
                                               name="start_time" 
                                               style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem;"
                                               required>
                                    </div>
                                    
                                    <div>
                                        <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                            End Time <span style="color: #dc3545;">*</span>
                                        </label>
                                        <input type="time" 
                                               name="end_time" 
                                               style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem;"
                                               required>
                                    </div>
                                </div>
                                
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                        Location / Meeting Link
                                    </label>
                                    <input type="text" 
                                           name="location" 
                                           placeholder="Office address for in-person, or leave blank for virtual"
                                           style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem; margin-bottom: 0.5rem;">
                                    
                                    <input type="url" 
                                           name="meeting_link" 
                                           placeholder="Video meeting link (Zoom, Google Meet, etc.)"
                                           style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem;">
                                </div>
                                
                                <div style="margin-bottom: 1rem;">
                                    <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                        Notes / Instructions for Candidate
                                    </label>
                                    <textarea name="notes" 
                                              placeholder="Any special instructions, agenda, or preparation needed..."
                                              style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem; min-height: 100px;"></textarea>
                                </div>
                                
                                <button type="submit" 
                                        style="width: 100%; background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%); color: white; padding: 0.75rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem;">
                                    <i class="fas fa-calendar-plus"></i> Schedule Interview
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- New Section: Remarks Visible to Applicant -->
                <?php if (!empty($applicant_remarks)): ?>
                    <div style="margin-bottom: 2rem; border: 2px solid #0056b3; border-radius: 10px; overflow: hidden;">
                        <div style="background: linear-gradient(135deg, #0056b3 0%, #004494 100%); padding: 1rem; color: white;">
                            <h3 style="color: white; margin: 0; font-size: 1.2rem;">
                                <i class="fas fa-eye"></i> Remarks Visible to Applicant
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                These remarks will be visible to the candidate when they view their application status.
                            </p>
                        </div>
                        <div style="background: #f8f9fa; padding: 1.5rem;">
                            <?php foreach (array_reverse($applicant_remarks) as $index => $entry): ?>
                                <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e0e0e0; <?php echo $index === count($applicant_remarks) - 1 ? 'border-bottom: none; margin-bottom: 0; padding-bottom: 0;' : ''; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <span style="background: #0056b3; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem; font-weight: 600;">
                                            <?php echo htmlspecialchars(ucfirst($entry['status'])); ?>
                                        </span>
                                        <span style="color: #666; font-size: 0.85rem;">
                                            <i class="fas fa-clock"></i> <?php echo date('F d, Y h:i A', strtotime($entry['timestamp'])); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($entry['reviewed_by'])): ?>
                                        <p style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                            <i class="fas fa-user"></i> Reviewed by: <?php echo htmlspecialchars($entry['reviewed_by']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p style="color: #333; line-height: 1.6; background: white; padding: 1rem; border-radius: 8px; border-left: 4px solid #28a745;">
                                        <strong style="color: #28a745;">Remarks to Applicant:</strong><br>
                                        <?php echo htmlspecialchars($entry['remarks']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div style="margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem;">Resume</h3>
                    <?php 
                    // **MODIFIED: Prioritize profile resume from applicants table**
                    $resume_to_show = null;
                    $resume_source = "";
                    
                    // First, try to get the profile resume from the applicants table
                    if (!empty($application['profile_resume_file']) && file_exists('../' . $application['profile_resume_file'])) {
                        $resume_to_show = $application['profile_resume_file'];
                        $resume_source = "Profile resume (from applicant's profile)";
                    } 
                    // If no profile resume, check for application-specific resume
                    elseif (!empty($application['application_resume_file']) && file_exists('../' . $application['application_resume_file'])) {
                        $resume_to_show = $application['application_resume_file'];
                        $resume_source = "Application-specific resume";
                    }
                    
                    // For recommended candidates who haven't applied yet, check if we can get resume directly
                    if (!$resume_to_show && !empty($application['applicant_id'])) {
                        $stmt_resume = $conn->prepare("SELECT resume_file FROM applicants WHERE applicant_id = ?");
                        $stmt_resume->bind_param("i", $application['applicant_id']);
                        $stmt_resume->execute();
                        $resume_result = $stmt_resume->get_result()->fetch_assoc();
                        $stmt_resume->close();
                        
                        if (!empty($resume_result['resume_file']) && file_exists('../' . $resume_result['resume_file'])) {
                            $resume_to_show = $resume_result['resume_file'];
                            $resume_source = "Profile resume (from applicant's profile)";
                        }
                    }
                    ?>
                    
                    <?php if ($resume_to_show): ?>
                        <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; margin-bottom: 0.5rem;">
                            <p style="color: #666; margin: 0; font-size: 0.9rem;">
                                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($resume_source); ?>
                            </p>
                        </div>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="../<?php echo htmlspecialchars($resume_to_show); ?>" 
                               target="_blank" 
                               style="background: #28a745; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600;"
                               title="View Resume PDF">
                                <i class="fas fa-file-pdf"></i> View Resume PDF
                            </a>
                            <a href="../<?php echo htmlspecialchars($resume_to_show); ?>" 
                               download
                               style="background: #0056b3; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600;"
                               title="Download Resume PDF">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    <?php elseif (!empty($application['resume_file'])): ?>
                        <p style="color: #dc3545;">
                            <i class="fas fa-exclamation-triangle"></i> Resume file not found at the specified path.
                        </p>
                        <?php if (!empty($application['profile_resume_file'])): ?>
                            <p style="color: #666; font-size: 0.9rem;">
                                <i class="fas fa-info-circle"></i> Resume path: <?php echo htmlspecialchars($application['profile_resume_file']); ?>
                            </p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p style="color: #6c757d;">
                            <i class="fas fa-minus"></i> No resume uploaded by the applicant
                        </p>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($application['cover_letter'])): ?>
                    <div style="margin-bottom: 2rem;">
                        <h3 style="color: #333; margin-bottom: 1rem;">Cover Letter</h3>
                        <p style="color: #666; line-height: 1.8; white-space: pre-wrap;">
                            <?php echo htmlspecialchars($application['cover_letter']); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <?php if (count($chatbot_answers) > 0): ?>
                    <div style="margin-bottom: 2rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="color: #333; margin-bottom: 0;">
                                <i class="fas fa-robot"></i> Career Assessment Chatbot Data
                            </h3>
                            <a href="chatbot_review.php?application_id=<?php echo $application_id; ?>" 
                               style="background: #9C27B0; color: white; padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem;">
                                <i class="fas fa-comments"></i> View in Chatbot Interface
                            </a>
                        </div>
                        
                        <?php if ($feedback): ?>
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 1.5rem; border-radius: 10px; margin-bottom: 1.5rem; color: white;">
                                <h4 style="color: white; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-star" style="color: #FFD700;"></i> AI-Generated Feedback
                                </h4>
                                <p style="color: white; line-height: 1.8; margin-bottom: 0.5rem;">
                                    <?php echo htmlspecialchars($feedback['feedback_message']); ?>
                                </p>
                                <p style="color: rgba(255,255,255,0.9); font-size: 0.85rem; margin-top: 0.5rem;">
                                    <i class="fas fa-clock"></i> Generated on <?php echo date('F d, Y h:i A', strtotime($feedback['created_at'])); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($chatbot_answers[0]['qualification_name'])): ?>
                            <p style="color: #666; margin-bottom: 1rem;">
                                <strong>Selected Category:</strong> <?php echo htmlspecialchars($chatbot_answers[0]['qualification_name']); ?>
                            </p>
                        <?php endif; ?>
                        <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px;">
                            <?php foreach ($chatbot_answers as $index => $answer): ?>
                                <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e0e0e0; <?php echo $index === count($chatbot_answers) - 1 ? 'border-bottom: none; margin-bottom: 0; padding-bottom: 0;' : ''; ?>">
                                    <p style="color: #333; font-weight: 600; margin-bottom: 0.5rem;">
                                        Q<?php echo $answer['question_number'] + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?>
                                    </p>
                                    <p style="color: #0056b3; margin-left: 1.5rem;">
                                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($answer['answer_text']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Remarks History Section (Internal) -->
                <?php if (!empty($application['remarks_history'])): 
                    $history_array = json_decode($application['remarks_history'], true);
                    if (is_array($history_array) && count($history_array) > 0): ?>
                    <div style="margin-bottom: 2rem; border: 2px solid #6c757d; border-radius: 10px; overflow: hidden;">
                        <div style="background: #6c757d; padding: 1rem; color: white;">
                            <h3 style="color: white; margin: 0; font-size: 1.2rem;">
                                <i class="fas fa-history"></i> Internal Status Change History
                            </h3>
                            <p style="margin: 0.5rem 0 0 0; font-size: 0.9rem; opacity: 0.9;">
                                This history is only visible to employers and administrators.
                            </p>
                        </div>
                        <div style="background: #f8f9fa; padding: 1.5rem;">
                            <?php foreach (array_reverse($history_array) as $index => $entry): ?>
                                <div style="margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e0e0e0; <?php echo $index === count($history_array) - 1 ? 'border-bottom: none; margin-bottom: 0; padding-bottom: 0;' : ''; ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <span style="background: #6c757d; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem; font-weight: 600;">
                                            <?php echo htmlspecialchars(ucfirst($entry['status'])); ?>
                                        </span>
                                        <span style="color: #666; font-size: 0.85rem;">
                                            <i class="fas fa-clock"></i> <?php echo date('F d, Y h:i A', strtotime($entry['timestamp'])); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($entry['reviewed_by'])): ?>
                                        <p style="color: #666; margin-bottom: 0.5rem; font-size: 0.9rem;">
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($entry['reviewed_by']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p style="color: #333; line-height: 1.6; background: white; padding: 0.75rem; border-radius: 8px; border-left: 3px solid #6c757d;">
                                        <strong>Internal Remarks:</strong> <?php echo htmlspecialchars($entry['remarks']); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; endif; ?>
            </div>
            
            <div>
                <div style="background: #f8f9fa; padding: 2rem; border-radius: 10px; margin-bottom: 2rem;">
                    <h3 style="color: #333; margin-bottom: 1rem;">Application Details</h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Job Applied:</strong>
                        <p style="color: #333; margin-top: 0.25rem;">
                            <?php echo htmlspecialchars($application['job_title']); ?>
                            <?php if ($application['company_name']): ?>
                                <span style="color: #999;"> - <?php echo htmlspecialchars($application['company_name']); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($application['reviewed_by_name'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Reviewed By:</strong>
                        <p style="color: #333; margin-top: 0.25rem;">
                            <?php echo htmlspecialchars($application['reviewed_by_name']); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($application['applied_at'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Applied On:</strong>
                        <p style="color: #333; margin-top: 0.25rem;">
                            <?php echo date('F d, Y', strtotime($application['applied_at'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($application['match_score'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Match Score:</strong>
                        <p style="color: #4CAF50; font-weight: 600; margin-top: 0.25rem;">
                            <?php echo number_format($application['match_score'], 2); ?>%
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Employability Score:</strong>
                        <p style="color: #0056b3; font-weight: 600; margin-top: 0.25rem;">
                            <?php echo $display_score; ?>%
                        </p>
                    </div>
                    
                    <?php if (!empty($application['status'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <strong style="color: #666;">Current Status:</strong>
                        <p style="color: #333; margin-top: 0.25rem;">
                            <?php 
                            $status = $application['status'] ?? 'pending';
                            $status_colors = [
                                'pending' => '#6c757d',
                                'reviewed' => '#17a2b8',
                                'shortlisted' => '#28a745',
                                'scheduled for interview' => '#9C27B0',
                                'accepted' => '#007bff',
                                'rejected' => '#dc3545'
                            ];
                            $color = $status_colors[$status] ?? '#6c757d';
                            ?>
                            <span style="background: <?php echo $color; ?>; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.85rem; font-weight: 600;">
                                <?php echo htmlspecialchars(ucfirst($status)); ?>
                            </span>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($application['application_id'])): ?>
                    <form method="POST" action="">
                        <div style="margin-bottom: 1.5rem; background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <h4 style="color: #333; margin-bottom: 1rem; font-size: 1.1rem;">
                                <i class="fas fa-edit"></i> Update Application Status
                            </h4>
                            
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                    Select New Status
                                </label>
                                <select name="status" style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem;" required>
                                    <option value="">-- Select Status --</option>
                                    <option value="pending" <?php echo (($application['status'] ?? '') === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="reviewed" <?php echo (($application['status'] ?? '') === 'reviewed') ? 'selected' : ''; ?>>Reviewed</option>
                                    <option value="shortlisted" <?php echo (($application['status'] ?? '') === 'shortlisted') ? 'selected' : ''; ?>>Shortlisted</option>
                                    <option value="scheduled for interview" <?php echo (($application['status'] ?? '') === 'scheduled for interview') ? 'selected' : ''; ?>>Scheduled for Interview</option>
                                    <option value="accepted" <?php echo (($application['status'] ?? '') === 'accepted') ? 'selected' : ''; ?>>Accepted</option>
                                    <option value="rejected" <?php echo (($application['status'] ?? '') === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div style="margin-bottom: 1rem;">
                                <label style="display: block; margin-bottom: 0.5rem; color: #333; font-weight: 500;">
                                    <i class="fas fa-comment-dots"></i> Remarks / Justification
                                    <span style="color: #dc3545; font-size: 0.9rem;">* Required</span>
                                </label>
                                <textarea 
                                    name="remarks" 
                                    style="width: 100%; padding: 0.75rem; border: 2px solid #eee; border-radius: 8px; font-size: 1rem; min-height: 120px; font-family: inherit;" 
                                    placeholder="Enter remarks, comments, or justification for this status change..."
                                    required
                                ></textarea>
                                <p style="color: #666; font-size: 0.85rem; margin-top: 0.25rem;">
                                    <i class="fas fa-info-circle"></i> This will be visible to the applicant.
                                </p>
                            </div>
                            
                            <button type="submit" name="update_status" style="width: 100%; background: linear-gradient(135deg, #0056b3 0%, #004494 100%); color: white; padding: 0.75rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 1rem; transition: all 0.3s ease;">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div style="background: #fff3cd; padding: 1rem; border-radius: 8px; border-left: 4px solid #ffc107;">
                        <p style="color: #856404; margin: 0; font-size: 0.9rem;">
                            <i class="fas fa-info-circle"></i> <strong>Recommended Candidate:</strong> This candidate hasn't applied yet, but has been recommended based on their chatbot assessment.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
$conn->close();
include '../includes/footer.php'; 