<?php
/**
 * Insert ALL Qualifications - Comprehensive Script
 * This script ensures all qualifications (Web Dev + Non-IT) are in the database
 * Run this to populate all qualification categories for the chatbot
 */

require_once __DIR__ . '/../includes/config/database.php';

$conn = getDBConnection();

// Ensure qualifications table exists
$table_check = $conn->query("SHOW TABLES LIKE 'qualifications'");
if (!$table_check || $table_check->num_rows == 0) {
    // Create qualifications table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS qualifications (
        qualification_id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($create_table_sql)) {
        echo "✓ Qualifications table created successfully!\n";
    } else {
        die("✗ Failed to create qualifications table: " . $conn->error . "\n");
    }
}

// ALL Qualifications - Marketing, Accounting, Business, Human Resources, IT-related
$all_qualifications = [
    // Marketing Qualifications (4)
    [
        'name' => 'Bachelor of Science in Marketing Management',
        'description' => 'Four-year degree covering marketing strategies, consumer behavior, brand management, and market analysis'
    ],
    [
        'name' => 'Bachelor of Science in Digital Marketing',
        'description' => 'Degree focusing on online marketing, social media strategies, SEO, content marketing, and digital advertising'
    ],
    [
        'name' => 'Bachelor of Science in Advertising and Public Relations',
        'description' => 'Degree covering advertising campaigns, public relations, media planning, and brand communication'
    ],
    [
        'name' => 'Bachelor of Science in Entrepreneurship',
        'description' => 'Degree in business creation, innovation, startup management, and entrepreneurial skills'
    ],
    // Accounting Qualifications (4)
    [
        'name' => 'Bachelor of Science in Accountancy',
        'description' => 'Professional degree in accounting principles, financial reporting, auditing, and tax preparation'
    ],
    [
        'name' => 'Bachelor of Science in Accounting Information System',
        'description' => 'Degree combining accounting with information systems, focusing on computerized accounting and financial data management'
    ],
    [
        'name' => 'Bachelor of Science in Management Accounting',
        'description' => 'Degree in managerial accounting, cost analysis, budgeting, and financial decision-making for organizations'
    ],
    [
        'name' => 'Bachelor of Science in Internal Auditing',
        'description' => 'Degree focusing on internal audit processes, risk assessment, compliance, and organizational controls'
    ],
    // Business Qualifications (4)
    [
        'name' => 'Bachelor of Science in Business Administration',
        'description' => 'Four-year degree covering management, finance, marketing, and business operations'
    ],
    [
        'name' => 'Bachelor of Science in Business Management',
        'description' => 'Degree in organizational management, leadership, strategic planning, and business operations'
    ],
    [
        'name' => 'Bachelor of Science in Entrepreneurship',
        'description' => 'Degree in business creation, innovation, startup management, and entrepreneurial skills'
    ],
    [
        'name' => 'Bachelor of Science in Economics',
        'description' => 'Degree in economic theory, market analysis, financial systems, and economic policy'
    ],
    // Human Resources Qualifications (4)
    [
        'name' => 'Bachelor of Science in Human Resource Management',
        'description' => 'Degree focusing on HR management, recruitment, employee relations, and organizational development'
    ],
    [
        'name' => 'Bachelor of Science in Psychology (with HR specialization)',
        'description' => 'Psychology degree with specialization in human resources, organizational behavior, and employee psychology'
    ],
    [
        'name' => 'Bachelor of Science in Industrial Relations',
        'description' => 'Degree in labor relations, employee-employer relations, collective bargaining, and workplace policies'
    ],
    [
        'name' => 'Bachelor of Science in Organizational Development',
        'description' => 'Degree in organizational change, development strategies, team building, and workplace improvement'
    ],
    // IT-related Qualifications (4)
    [
        'name' => 'Bachelor of Science in Information Technology',
        'description' => 'Four-year degree in IT covering systems administration, networking, software development, and technology management'
    ],
    [
        'name' => 'Bachelor of Science in Computer Science',
        'description' => 'Degree in computer science covering programming, algorithms, data structures, and software engineering'
    ],
    [
        'name' => 'Bachelor of Science in Information Systems',
        'description' => 'Degree combining business and technology, focusing on information systems design, database management, and business technology solutions'
    ],
    [
        'name' => 'Bachelor of Science in Cybersecurity',
        'description' => 'Degree in cybersecurity, network security, information security, and protection of digital assets'
    ]
];

$inserted = 0;
$updated = 0;
$skipped = 0;

echo "========================================\n";
echo "Inserting ALL Qualifications\n";
echo "========================================\n\n";

foreach ($all_qualifications as $qual) {
    // Check if qualification already exists
    $check_stmt = $conn->prepare("SELECT qualification_id, description, status FROM qualifications WHERE name = ?");
    $check_stmt->bind_param("s", $qual['name']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update if needed
        $existing = $result->fetch_assoc();
        $needs_update = false;
        
        // Check if description changed or status is inactive
        if ($existing['description'] !== $qual['description'] || $existing['status'] !== 'active') {
            $update_stmt = $conn->prepare("UPDATE qualifications SET description = ?, status = 'active' WHERE qualification_id = ?");
            $update_stmt->bind_param("si", $qual['description'], $existing['qualification_id']);
            if ($update_stmt->execute()) {
                echo "✓ Updated: '{$qual['name']}'\n";
                $updated++;
            }
            $update_stmt->close();
        } else {
            echo "⊘ Skipped: '{$qual['name']}' already exists\n";
            $skipped++;
        }
        $check_stmt->close();
        continue;
    }
    $check_stmt->close();
    
    // Insert qualification
    $stmt = $conn->prepare("INSERT INTO qualifications (name, description, status) VALUES (?, ?, 'active')");
    $stmt->bind_param("ss", $qual['name'], $qual['description']);
    
    if ($stmt->execute()) {
        echo "✓ Inserted: '{$qual['name']}'\n";
        $inserted++;
    } else {
        echo "✗ Failed to insert '{$qual['name']}': " . $conn->error . "\n";
    }
    
    $stmt->close();
}

echo "\n";
echo "========================================\n";
echo "Summary:\n";
echo "  Inserted: $inserted qualification(s)\n";
echo "  Updated: $updated qualification(s)\n";
echo "  Skipped: $skipped qualification(s)\n";
echo "========================================\n\n";

// Display all active qualifications
echo "All Active Qualifications in Database:\n";
echo "========================================\n";
$result = $conn->query("SELECT name FROM qualifications WHERE status = 'active' ORDER BY name");
$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "  $count. {$row['name']}\n";
}
echo "\nTotal: $count active qualification(s)\n";

closeDBConnection($conn);

echo "\n✓ All qualifications setup completed!\n";
echo "The chatbot now supports all qualification categories.\n";
?>

