<?php
/**
 * Insert 5 Web Development Qualification Categories
 * Run this script once to populate the qualifications table with web development categories
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

// 5 Web Development Qualification Categories
$web_dev_qualifications = [
    [
        'name' => 'Frontend Web Development',
        'description' => 'Specialization in frontend technologies including HTML, CSS, JavaScript, React, Vue.js, Angular, and responsive design principles.'
    ],
    [
        'name' => 'Backend Web Development',
        'description' => 'Expertise in server-side development using PHP, Node.js, Python, Java, database management, API development, and server architecture.'
    ],
    [
        'name' => 'Full Stack Web Development',
        'description' => 'Comprehensive knowledge of both frontend and backend technologies, capable of building complete web applications from database to user interface.'
    ],
    [
        'name' => 'Web Development with Modern Frameworks',
        'description' => 'Proficiency in modern web development frameworks and tools including Laravel, Django, Express.js, Next.js, and related ecosystem technologies.'
    ],
    [
        'name' => 'Web Development & Database Management',
        'description' => 'Skills in web development combined with database design, SQL, NoSQL databases, data modeling, and database optimization for web applications.'
    ]
];

$inserted = 0;
$skipped = 0;

foreach ($web_dev_qualifications as $qual) {
    // Check if qualification already exists
    $check_stmt = $conn->prepare("SELECT qualification_id FROM qualifications WHERE name = ?");
    $check_stmt->bind_param("s", $qual['name']);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "⊘ Skipped: '{$qual['name']}' already exists\n";
        $skipped++;
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
echo "  Skipped: $skipped qualification(s)\n";
echo "========================================\n";

closeDBConnection($conn);
?>

