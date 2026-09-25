<?php
require_once '../includes/config/database.php';

$conn = getDBConnection();

// Create qualifications table
$sql = "CREATE TABLE IF NOT EXISTS qualifications (
    qualification_id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "Qualifications table created successfully!<br>";
} else {
    echo "Error creating table: " . $conn->error . "<br>";
}

// Insert qualifications - Marketing, Accounting, Business, Human Resources, IT-related
$mock_qualifications = [
    // Marketing (4)
    ['Bachelor of Science in Marketing Management', 'Four-year degree covering marketing strategies, consumer behavior, brand management, and market analysis'],
    ['Bachelor of Science in Digital Marketing', 'Degree focusing on online marketing, social media strategies, SEO, content marketing, and digital advertising'],
    ['Bachelor of Science in Advertising and Public Relations', 'Degree covering advertising campaigns, public relations, media planning, and brand communication'],
    ['Bachelor of Science in Entrepreneurship', 'Degree in business creation, innovation, startup management, and entrepreneurial skills'],
    // Accounting (4)
    ['Bachelor of Science in Accountancy', 'Professional degree in accounting principles, financial reporting, auditing, and tax preparation'],
    ['Bachelor of Science in Accounting Information System', 'Degree combining accounting with information systems, focusing on computerized accounting and financial data management'],
    ['Bachelor of Science in Management Accounting', 'Degree in managerial accounting, cost analysis, budgeting, and financial decision-making for organizations'],
    ['Bachelor of Science in Internal Auditing', 'Degree focusing on internal audit processes, risk assessment, compliance, and organizational controls'],
    // Business (4)
    ['Bachelor of Science in Business Administration', 'Four-year degree covering management, finance, marketing, and business operations'],
    ['Bachelor of Science in Business Management', 'Degree in organizational management, leadership, strategic planning, and business operations'],
    ['Bachelor of Science in Entrepreneurship', 'Degree in business creation, innovation, startup management, and entrepreneurial skills'],
    ['Bachelor of Science in Economics', 'Degree in economic theory, market analysis, financial systems, and economic policy'],
    // Human Resources (4)
    ['Bachelor of Science in Human Resource Management', 'Degree focusing on HR management, recruitment, employee relations, and organizational development'],
    ['Bachelor of Science in Psychology (with HR specialization)', 'Psychology degree with specialization in human resources, organizational behavior, and employee psychology'],
    ['Bachelor of Science in Industrial Relations', 'Degree in labor relations, employee-employer relations, collective bargaining, and workplace policies'],
    ['Bachelor of Science in Organizational Development', 'Degree in organizational change, development strategies, team building, and workplace improvement'],
    // IT-related (4)
    ['Bachelor of Science in Information Technology', 'Four-year degree in IT covering systems administration, networking, software development, and technology management'],
    ['Bachelor of Science in Computer Science', 'Degree in computer science covering programming, algorithms, data structures, and software engineering'],
    ['Bachelor of Science in Information Systems', 'Degree combining business and technology, focusing on information systems design, database management, and business technology solutions'],
    ['Bachelor of Science in Cybersecurity', 'Degree in cybersecurity, network security, information security, and protection of digital assets']
];

$stmt = $conn->prepare("INSERT INTO qualifications (name, description, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE name = name");

foreach ($mock_qualifications as $qual) {
    $stmt->bind_param("ss", $qual[0], $qual[1]);
    if ($stmt->execute()) {
        echo "Inserted: " . $qual[0] . "<br>";
    } else {
        echo "Error inserting " . $qual[0] . ": " . $conn->error . "<br>";
    }
}

$stmt->close();
closeDBConnection($conn);

echo "<br>Setup complete! Qualifications table has been created.";
?>

