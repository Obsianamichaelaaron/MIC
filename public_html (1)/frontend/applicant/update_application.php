<?php
require_once '../includes/config/session.php';
require_once '../includes/config/database.php';
requireRole('applicant');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = $_POST['application_id'] ?? '';
    $job_id = $_POST['job_id'] ?? '';
    $cover_letter = $_POST['cover_letter'] ?? '';
    
    // Get applicant ID
    $user_id = getCurrentUserId();
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT applicant_id FROM applicants WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $applicant = $stmt->get_result()->fetch_assoc();
    $applicant_id = $applicant['applicant_id'];
    $stmt->close();
    
    // Verify that the application belongs to the applicant
    $stmt = $conn->prepare("SELECT * FROM applications WHERE application_id = ? AND applicant_id = ?");
    $stmt->bind_param("ii", $application_id, $applicant_id);
    $stmt->execute();
    $application = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$application) {
        $_SESSION['error'] = "Application not found or you don't have permission to update it.";
        header("Location: applications.php");
        exit();
    }
    
    // Handle file upload
    $resume_file = $application['resume_file']; // Keep existing file by default
    
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../uploads/resumes/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['resume']['name']);
        $target_path = $upload_dir . $file_name;
        
        // Check file type
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $file_type = $_FILES['resume']['type'];
        
        if (in_array($file_type, $allowed_types) && $_FILES['resume']['size'] <= 5 * 1024 * 1024) { // 5MB max
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $target_path)) {
                $resume_file = 'uploads/resumes/' . $file_name;
                
                // Delete old resume file if it exists and is not the profile resume
                if (!empty($application['resume_file']) && $application['resume_file'] !== $application['profile_resume_file']) {
                    @unlink('../' . $application['resume_file']);
                }
            }
        }
    }
    
    // Update application in database
    $stmt = $conn->prepare("UPDATE applications SET cover_letter = ?, resume_file = ?, updated_at = NOW() WHERE application_id = ?");
    $stmt->bind_param("ssi", $cover_letter, $resume_file, $application_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Application updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update application. Please try again.";
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    header("Location: applications.php");
    exit();
} else {
    header("Location: applications.php");
    exit();
}
?>