<?php
// Set working directory
chdir(__DIR__ . '/..');

require_once 'includes/config/database.php';

// Set execution time limit for large imports
set_time_limit(300);

$conn = getDBConnection();

// First, ensure all tables exist
echo "<h3>Step 1: Creating Tables (if needed)</h3>";
echo "<pre>";

// Create resume_analysis table
$sql = "CREATE TABLE IF NOT EXISTS resume_analysis (
    analysis_id INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id INT NOT NULL,
    resume_file VARCHAR(255) NOT NULL,
    extracted_text TEXT,
    skills_extracted TEXT,
    education_extracted TEXT,
    experience_extracted TEXT,
    qualifications_extracted TEXT,
    analysis_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(applicant_id) ON DELETE CASCADE,
    INDEX idx_applicant (applicant_id)
)";
if ($conn->query($sql)) {
    echo "✓ Created/Verified resume_analysis table\n";
}

// Create chatbot_recommendations table
$sql = "CREATE TABLE IF NOT EXISTS chatbot_recommendations (
    recommendation_id INT PRIMARY KEY AUTO_INCREMENT,
    applicant_id INT NOT NULL,
    job_id INT NULL,
    qualification_id INT NULL,
    recommendation_type ENUM('job', 'qualification') NOT NULL,
    recommendation_text TEXT,
    match_score DECIMAL(5,2) DEFAULT 0.00,
    is_selected BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES applicants(applicant_id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES job_postings(job_id) ON DELETE CASCADE,
    FOREIGN KEY (qualification_id) REFERENCES qualifications(qualification_id) ON DELETE CASCADE,
    INDEX idx_applicant (applicant_id),
    INDEX idx_job (job_id)
)";
if ($conn->query($sql)) {
    echo "✓ Created/Verified chatbot_recommendations table\n";
}

// Create job_qualification_mapping table
$sql = "CREATE TABLE IF NOT EXISTS job_qualification_mapping (
    mapping_id INT PRIMARY KEY AUTO_INCREMENT,
    job_id INT NOT NULL,
    qualification_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES job_postings(job_id) ON DELETE CASCADE,
    FOREIGN KEY (qualification_id) REFERENCES qualifications(qualification_id) ON DELETE CASCADE,
    UNIQUE KEY unique_mapping (job_id, qualification_id),
    INDEX idx_job (job_id),
    INDEX idx_qualification (qualification_id)
)";
if ($conn->query($sql)) {
    echo "✓ Created/Verified job_qualification_mapping table\n";
}

// Add target_qualifications column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM job_postings LIKE 'target_qualifications'");
if ($result->num_rows == 0) {
    $sql = "ALTER TABLE job_postings ADD COLUMN target_qualifications TEXT NULL COMMENT 'Comma-separated qualification IDs'";
    if ($conn->query($sql)) {
        echo "✓ Added target_qualifications column to job_postings\n";
    }
}

echo "</pre>";
echo "<h3>Step 2: Importing Sample Data</h3>";
echo "<pre>";

// 1. Insert Sample Qualifications
// Note: Run insert_all_qualifications.php first to ensure all qualifications are in the database
echo "Inserting Sample Qualifications...\n";
$qualifications = [
    ['Bachelor of Science in Computer Science', 'Degree in computer science covering programming, algorithms, data structures, and software engineering'],
    ['Bachelor of Science in Information Technology', 'Four-year degree in IT covering systems administration, networking, software development, and technology management'],
    ['Bachelor of Science in Digital Marketing', 'Degree focusing on online marketing, social media strategies, SEO, content marketing, and digital advertising']
];

$qual_stmt = $conn->prepare("INSERT INTO qualifications (name, description, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE name = name");
foreach ($qualifications as $qual) {
    $qual_stmt->bind_param("ss", $qual[0], $qual[1]);
    if ($qual_stmt->execute()) {
        echo "✓ Inserted/Updated: " . $qual[0] . "\n";
    }
}
$qual_stmt->close();

// 2. Insert Job Postings
echo "\nInserting Job Postings...\n";
$employer_result = $conn->query("SELECT employer_id FROM employers LIMIT 1");
if ($employer_result && $employer_result->num_rows > 0) {
    $employer = $employer_result->fetch_assoc();
    $employer_id = $employer['employer_id'];
    
    // Reduced to 3 web development focused jobs
    $jobs = [
        ['Senior PHP Developer', 'We are looking for an experienced PHP developer to join our dynamic team. You will be responsible for developing and maintaining web applications using PHP, MySQL, and modern frameworks.', 'Bachelor of Science in Computer Science or related field. Minimum 5 years of experience in PHP development. Strong knowledge of MySQL, JavaScript, and web technologies.', 'PHP, MySQL, JavaScript, Laravel, Git, REST API', 'Manila, Philippines', 'full-time', '₱50,000 - ₱70,000'],
        ['Web Developer', 'Join our team as a Web Developer to create amazing web experiences. You will work with modern web technologies to build responsive and user-friendly applications.', 'Diploma in Web Development or related field. Experience with front-end and back-end technologies.', 'HTML, CSS, JavaScript, PHP, MySQL, React', 'Makati, Philippines', 'full-time', '₱40,000 - ₱55,000'],
        ['Full Stack Developer', 'We need a Full Stack Developer to design and develop complete web solutions. You will work on both front-end and back-end technologies to build scalable web applications.', 'Full Stack Web Development qualification or related field. Experience with modern frameworks and databases.', 'JavaScript, PHP, React, Node.js, MySQL, MongoDB, REST API', 'BGC, Philippines', 'full-time', '₱55,000 - ₱75,000']
    ];
    
    $job_stmt = $conn->prepare("INSERT INTO job_postings (employer_id, title, description, requirements, skills_required, location, employment_type, salary_range, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active') ON DUPLICATE KEY UPDATE title = VALUES(title)");
    
    $job_ids = [];
    foreach ($jobs as $job) {
        $job_stmt->bind_param("isssssss", $employer_id, $job[0], $job[1], $job[2], $job[3], $job[4], $job[5], $job[6]);
        if ($job_stmt->execute()) {
            $job_id = $conn->insert_id;
            if ($job_id > 0) {
                $job_ids[] = $job_id;
                echo "✓ Inserted Job: " . $job[0] . " (ID: $job_id)\n";
            }
        }
    }
    $job_stmt->close();
} else {
    echo "✗ No employer found. Please create an employer first.\n";
    closeDBConnection($conn);
    exit;
}

echo "\n--- Executing Complex Queries ---\n";

// Get employer ID
$employer_result = $conn->query("SELECT employer_id FROM employers LIMIT 1");
if ($employer_result && $employer_result->num_rows > 0) {
    $employer = $employer_result->fetch_assoc();
    $employer_id = $employer['employer_id'];
    echo "✓ Found Employer ID: $employer_id\n";
    
    // Get job IDs (reduced to 3 jobs)
    $job_result = $conn->query("SELECT job_id, title FROM job_postings ORDER BY job_id DESC LIMIT 3");
    $job_ids = [];
    if ($job_result) {
        while ($row = $job_result->fetch_assoc()) {
            $job_ids[] = $row['job_id'];
            echo "✓ Found Job: {$row['title']} (ID: {$row['job_id']})\n";
        }
    }
    
    // Get qualification IDs (IT-related qualifications)
    $qual_result = $conn->query("SELECT qualification_id, name FROM qualifications WHERE name IN ('Bachelor of Science in Information Technology', 'Bachelor of Science in Computer Science', 'Bachelor of Science in Information Systems', 'Bachelor of Science in Cybersecurity') ORDER BY qualification_id");
    $qual_map = [];
    if ($qual_result) {
        while ($row = $qual_result->fetch_assoc()) {
            $qual_map[$row['name']] = $row['qualification_id'];
        }
        echo "✓ Found " . count($qual_map) . " Web Development Qualifications\n";
    }
    
    // Insert job-qualification mappings (aligned with 3 jobs)
    if (!empty($job_ids) && !empty($qual_map)) {
        $mappings = [];
        
        // Job 1 (Senior PHP Developer) -> Information Technology, Computer Science
        if (isset($job_ids[0])) {
            if (isset($qual_map['Bachelor of Science in Information Technology'])) {
                $mappings[] = [$job_ids[0], $qual_map['Bachelor of Science in Information Technology']];
            }
            if (isset($qual_map['Bachelor of Science in Computer Science'])) {
                $mappings[] = [$job_ids[0], $qual_map['Bachelor of Science in Computer Science']];
            }
        }
        
        // Job 2 (Web Developer) -> Computer Science, Information Systems
        if (isset($job_ids[1])) {
            if (isset($qual_map['Bachelor of Science in Computer Science'])) {
                $mappings[] = [$job_ids[1], $qual_map['Bachelor of Science in Computer Science']];
            }
            if (isset($qual_map['Bachelor of Science in Information Systems'])) {
                $mappings[] = [$job_ids[1], $qual_map['Bachelor of Science in Information Systems']];
            }
        }
        
        // Job 3 (Full Stack Developer) -> Computer Science, Information Technology
        if (isset($job_ids[2])) {
            if (isset($qual_map['Bachelor of Science in Computer Science'])) {
                $mappings[] = [$job_ids[2], $qual_map['Bachelor of Science in Computer Science']];
            }
            if (isset($qual_map['Bachelor of Science in Information Technology'])) {
                $mappings[] = [$job_ids[2], $qual_map['Bachelor of Science in Information Technology']];
            }
        }
        
        $map_stmt = $conn->prepare("INSERT INTO job_qualification_mapping (job_id, qualification_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE job_id = job_id");
        foreach ($mappings as $mapping) {
            $job_id = $mapping[0];
            $qual_id = $mapping[1];
            $map_stmt->bind_param("ii", $job_id, $qual_id);
            if ($map_stmt->execute()) {
                echo "✓ Mapped Job $job_id to Qualification $qual_id\n";
            }
        }
        $map_stmt->close();
    } else {
        echo "⚠ Not enough jobs or qualifications for mapping\n";
    }
    
    // Insert resume analysis for existing applicants
    $applicant_result = $conn->query("SELECT applicant_id FROM applicants LIMIT 10");
    if ($applicant_result && $applicant_result->num_rows > 0) {
        $analysis_stmt = $conn->prepare("
            INSERT INTO resume_analysis 
            (applicant_id, resume_file, extracted_text, skills_extracted, education_extracted, experience_extracted, qualifications_extracted)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            skills_extracted = VALUES(skills_extracted),
            education_extracted = VALUES(education_extracted),
            experience_extracted = VALUES(experience_extracted),
            qualifications_extracted = VALUES(qualifications_extracted)
        ");
        
        $skills = [
            'PHP, MySQL, JavaScript, Laravel, Git',
            'HTML, CSS, JavaScript, React, Node.js',
            'Business Analysis, Project Management, Microsoft Office',
            'Digital Marketing, SEO, Social Media, Content Creation',
            'Accounting, Financial Reporting, Tax Preparation, Excel'
        ];
        
        $educations = [
            'Bachelor of Science in Computer Science, University of the Philippines, 2018-2022',
            'Bachelor of Science in Information Technology, Technical Institute, 2020-2022',
            'Bachelor of Science in Business Administration, Ateneo de Manila, 2015-2017',
            'Bachelor of Science in Digital Marketing, Online Academy, 2021',
            'Bachelor of Science in Accountancy, De La Salle University, 2016-2020'
        ];
        
        $experiences = [
            '5 years of experience in PHP development. Worked on multiple web applications using Laravel framework.',
            '3 years of web development experience. Created responsive websites using modern front-end technologies.',
            '7 years in business analysis. Led multiple projects and improved business processes.',
            '4 years in digital marketing. Managed successful campaigns with high ROI.',
            '6 years in accounting. Handled financial reporting and tax preparation for various clients.'
        ];
        
        $qualifications = [
            'Bachelor of Science in Computer Science',
            'Bachelor of Science in Information Technology',
            'Bachelor of Science in Business Administration',
            'Bachelor of Science in Digital Marketing',
            'Bachelor of Science in Accountancy'
        ];
        
        $index = 0;
        while ($applicant = $applicant_result->fetch_assoc()) {
            $resume_file = 'uploads/resumes/sample_resume_' . $applicant['applicant_id'] . '.pdf';
            $extracted_text = "Sample resume text for applicant " . $applicant['applicant_id'] . ". " . $educations[$index % 5] . ". " . $experiences[$index % 5];
            
            $analysis_stmt->bind_param("issssss",
                $applicant['applicant_id'],
                $resume_file,
                $extracted_text,
                $skills[$index % 5],
                $educations[$index % 5],
                $experiences[$index % 5],
                $qualifications[$index % 5]
            );
            
            if ($analysis_stmt->execute()) {
                echo "✓ Inserted Resume Analysis for Applicant {$applicant['applicant_id']}\n";
            }
            $index++;
        }
        $analysis_stmt->close();
    }
    
    // Update applicant profiles
    $update_result = $conn->query("
        UPDATE applicants a
        LEFT JOIN resume_analysis ra ON a.applicant_id = ra.applicant_id
        SET 
            a.skills = COALESCE(ra.skills_extracted, a.skills),
            a.qualifications = COALESCE(ra.qualifications_extracted, a.qualifications),
            a.employability_score = 75.00
        WHERE ra.applicant_id IS NOT NULL
    ");
    if ($update_result) {
        echo "✓ Updated Applicant Profiles\n";
    }
    
    // Insert chatbot recommendations
    $applicant_result = $conn->query("SELECT applicant_id FROM applicants LIMIT 10");
    if ($applicant_result && $applicant_result->num_rows > 0) {
        $rec_stmt = $conn->prepare("
            INSERT INTO chatbot_recommendations 
            (applicant_id, job_id, qualification_id, recommendation_type, recommendation_text, match_score)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE match_score = VALUES(match_score)
        ");
        
        $app_index = 0;
        while ($applicant = $applicant_result->fetch_assoc()) {
            // Job recommendations (all 3 jobs)
            for ($i = 0; $i < min(3, count($job_ids)); $i++) {
                $job_id = $job_ids[$i];
                $match_score = 60 + ($app_index * 5) + ($i * 3);
                if ($match_score > 100) $match_score = 100;
                
                $job_title_result = $conn->query("SELECT title FROM job_postings WHERE job_id = $job_id");
                $job_title = $job_title_result ? $job_title_result->fetch_assoc()['title'] : "Job $job_id";
                
                $rec_text = "Job: $job_title (Match: " . number_format($match_score, 2) . "%)";
                
                $qual_id_null = null;
                $rec_type = 'job';
                
                $rec_stmt->bind_param("iiissd",
                    $applicant['applicant_id'],
                    $job_id,
                    $qual_id_null,
                    $rec_type,
                    $rec_text,
                    $match_score
                );
                
                if ($rec_stmt->execute()) {
                    echo "✓ Inserted Job Recommendation for Applicant {$applicant['applicant_id']}: $job_title\n";
                }
            }
            
            // Qualification recommendations (using web dev qualifications)
            $qual_result = $conn->query("SELECT qualification_id FROM qualifications WHERE status = 'active' LIMIT 3");
            $qual_ids = [];
            if ($qual_result) {
                while ($row = $qual_result->fetch_assoc()) {
                    $qual_ids[] = $row['qualification_id'];
                }
            }
            
            for ($i = 0; $i < min(2, count($qual_ids)); $i++) {
                $qual_id = $qual_ids[$i];
                $qual_name_result = $conn->query("SELECT name FROM qualifications WHERE qualification_id = $qual_id");
                $qual_name = $qual_name_result ? $qual_name_result->fetch_assoc()['name'] : "Qualification $qual_id";
                
                $rec_text = "Qualification: $qual_name";
                
                $job_id_null = null;
                $rec_type = 'qualification';
                $zero_score = 0.00;
                
                $rec_stmt->bind_param("iiissd",
                    $applicant['applicant_id'],
                    $job_id_null,
                    $qual_id,
                    $rec_type,
                    $rec_text,
                    $zero_score
                );
                
                if ($rec_stmt->execute()) {
                    echo "✓ Inserted Qualification Recommendation for Applicant {$applicant['applicant_id']}: $qual_name\n";
                }
            }
            $app_index++;
        }
        $rec_stmt->close();
    }
    
    // Insert job recommendations
    $applicant_result = $conn->query("SELECT applicant_id FROM applicants LIMIT 10");
    if ($applicant_result && $applicant_result->num_rows > 0) {
        $job_rec_stmt = $conn->prepare("
            INSERT INTO job_recommendations 
            (applicant_id, job_id, recommendation_score, reason)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE recommendation_score = VALUES(recommendation_score)
        ");
        
        $app_index = 0;
        while ($applicant = $applicant_result->fetch_assoc()) {
            for ($i = 0; $i < min(2, count($job_ids)); $i++) {
                $job_id = $job_ids[$i];
                $score = 65 + ($app_index * 5) + ($i * 5);
                if ($score > 100) $score = 100;
                
                $reason = 'Strong match based on skills and qualifications';
                
                $job_rec_stmt->bind_param("iids",
                    $applicant['applicant_id'],
                    $job_id,
                    $score,
                    $reason
                );
                
                if ($job_rec_stmt->execute()) {
                    echo "✓ Inserted Job Recommendation (existing system) for Applicant {$applicant['applicant_id']}\n";
                }
            }
            $app_index++;
        }
        $job_rec_stmt->close();
    }
}

echo "\n--- Summary ---\n";
echo "Import completed successfully!\n";

// Display counts
$tables = ['qualifications', 'job_postings', 'job_qualification_mapping', 'resume_analysis', 'chatbot_recommendations', 'job_recommendations'];
foreach ($tables as $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "\n$table: $count records\n";
    }
}

echo "</pre>";

closeDBConnection($conn);

echo "<h2 style='color: green;'>✓ Sample Data Import Completed!</h2>";
echo "<p><a href='../admin/dashboard.php'>Go to Admin Dashboard</a> | <a href='../employer/dashboard.php'>Go to Employer Dashboard</a> | <a href='../applicant/dashboard.php'>Go to Applicant Dashboard</a></p>";
?>

