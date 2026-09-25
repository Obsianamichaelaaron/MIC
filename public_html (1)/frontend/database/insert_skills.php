<?php
/**
 * Insert Skill Keywords into SQL Database
 * This script populates the skills table with all skill keywords used for resume parsing
 */

require_once __DIR__ . '/../includes/config/database.php';

$conn = getDBConnection();

// Ensure skills table exists
$table_check = $conn->query("SHOW TABLES LIKE 'skills'");
if (!$table_check || $table_check->num_rows == 0) {
    // Create skills table if it doesn't exist
    $create_table_sql = "CREATE TABLE IF NOT EXISTS skills (
        skill_id INT PRIMARY KEY AUTO_INCREMENT,
        skill_name VARCHAR(255) NOT NULL UNIQUE,
        category VARCHAR(100),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category (category),
        INDEX idx_status (status)
    )";
    
    if ($conn->query($create_table_sql)) {
        echo "✓ Skills table created successfully!\n";
    } else {
        die("✗ Failed to create skills table: " . $conn->error . "\n");
    }
}

// Skill keywords organized by category
$skills = [
    // Programming Languages
    ['php', 'Programming Languages'],
    ['javascript', 'Programming Languages'],
    ['python', 'Programming Languages'],
    ['java', 'Programming Languages'],
    ['c++', 'Programming Languages'],
    ['c#', 'Programming Languages'],
    ['sql', 'Programming Languages'],
    ['mysql', 'Programming Languages'],
    ['html', 'Programming Languages'],
    ['css', 'Programming Languages'],
    ['typescript', 'Programming Languages'],
    ['ruby', 'Programming Languages'],
    ['go', 'Programming Languages'],
    ['rust', 'Programming Languages'],
    ['swift', 'Programming Languages'],
    ['kotlin', 'Programming Languages'],
    ['scala', 'Programming Languages'],
    ['perl', 'Programming Languages'],
    
    // Frameworks & Libraries
    ['react', 'Frameworks & Libraries'],
    ['angular', 'Frameworks & Libraries'],
    ['vue', 'Frameworks & Libraries'],
    ['node', 'Frameworks & Libraries'],
    ['laravel', 'Frameworks & Libraries'],
    ['django', 'Frameworks & Libraries'],
    ['spring', 'Frameworks & Libraries'],
    ['express', 'Frameworks & Libraries'],
    ['jquery', 'Frameworks & Libraries'],
    ['bootstrap', 'Frameworks & Libraries'],
    ['tailwind', 'Frameworks & Libraries'],
    ['sass', 'Frameworks & Libraries'],
    ['less', 'Frameworks & Libraries'],
    ['webpack', 'Frameworks & Libraries'],
    ['npm', 'Frameworks & Libraries'],
    ['yarn', 'Frameworks & Libraries'],
    
    // Tools & Technologies
    ['git', 'Tools & Technologies'],
    ['github', 'Tools & Technologies'],
    ['docker', 'Tools & Technologies'],
    ['kubernetes', 'Tools & Technologies'],
    ['aws', 'Tools & Technologies'],
    ['azure', 'Tools & Technologies'],
    ['gcp', 'Tools & Technologies'],
    ['jenkins', 'Tools & Technologies'],
    ['ci/cd', 'Tools & Technologies'],
    ['agile', 'Tools & Technologies'],
    ['scrum', 'Tools & Technologies'],
    ['devops', 'Tools & Technologies'],
    ['linux', 'Tools & Technologies'],
    ['unix', 'Tools & Technologies'],
    ['windows', 'Tools & Technologies'],
    
    // Databases
    ['postgresql', 'Databases'],
    ['mongodb', 'Databases'],
    ['redis', 'Databases'],
    ['oracle', 'Databases'],
    ['sqlite', 'Databases'],
    ['mariadb', 'Databases'],
    
    // Accounting & Finance Skills
    ['financial analysis', 'Accounting & Finance'],
    ['budgeting', 'Accounting & Finance'],
    ['forecasting', 'Accounting & Finance'],
    ['risk management', 'Accounting & Finance'],
    ['invoicing', 'Accounting & Finance'],
    ['taxation', 'Accounting & Finance'],
    ['accounting', 'Accounting & Finance'],
    ['finance', 'Accounting & Finance'],
    ['financial reporting', 'Accounting & Finance'],
    ['auditing', 'Accounting & Finance'],
    ['bookkeeping', 'Accounting & Finance'],
    ['payroll', 'Accounting & Finance'],
    ['accounts payable', 'Accounting & Finance'],
    ['accounts receivable', 'Accounting & Finance'],
    ['general ledger', 'Accounting & Finance'],
    ['financial statements', 'Accounting & Finance'],
    ['cost accounting', 'Accounting & Finance'],
    ['management accounting', 'Accounting & Finance'],
    ['financial planning', 'Accounting & Finance'],
    ['accounting software', 'Accounting & Finance'],
    ['finance software', 'Accounting & Finance'],
    ['spreadsheet', 'Accounting & Finance'],
    ['excel', 'Accounting & Finance'],
    ['quickbooks', 'Accounting & Finance'],
    ['sap', 'Accounting & Finance'],
    ['oracle financials', 'Accounting & Finance'],
    ['xero', 'Accounting & Finance'],
    ['sage', 'Accounting & Finance'],
    ['peachtree', 'Accounting & Finance'],
    
    // Soft Skills
    ['project management', 'Soft Skills'],
    ['leadership', 'Soft Skills'],
    ['communication', 'Soft Skills'],
    ['teamwork', 'Soft Skills'],
    ['problem solving', 'Soft Skills'],
    ['analytical', 'Soft Skills'],
    ['critical thinking', 'Soft Skills'],
    ['time management', 'Soft Skills'],
    ['collaboration', 'Soft Skills'],
    
    // Office & Design Tools
    ['microsoft office', 'Office & Design Tools'],
    ['word', 'Office & Design Tools'],
    ['powerpoint', 'Office & Design Tools'],
    ['outlook', 'Office & Design Tools'],
    ['sharepoint', 'Office & Design Tools'],
    ['photoshop', 'Office & Design Tools'],
    ['illustrator', 'Office & Design Tools'],
    ['figma', 'Office & Design Tools'],
    ['sketch', 'Office & Design Tools'],
    ['adobe', 'Office & Design Tools'],
    ['autocad', 'Office & Design Tools'],
    
    // Other
    ['api', 'Other'],
    ['rest', 'Other'],
    ['graphql', 'Other'],
    ['microservices', 'Other'],
    ['machine learning', 'Other'],
    ['ai', 'Other'],
    ['data science', 'Other']
];

$inserted = 0;
$skipped = 0;

$stmt = $conn->prepare("INSERT INTO skills (skill_name, category, status) VALUES (?, ?, 'active') ON DUPLICATE KEY UPDATE category = VALUES(category), status = 'active'");

foreach ($skills as $skill) {
    $skill_name = $skill[0];
    $category = $skill[1];
    
    $stmt->bind_param("ss", $skill_name, $category);
    
    if ($stmt->execute()) {
        if ($conn->affected_rows > 0) {
            echo "✓ Inserted: '{$skill_name}' ({$category})\n";
            $inserted++;
        } else {
            echo "⊘ Skipped: '{$skill_name}' already exists\n";
            $skipped++;
        }
    } else {
        echo "✗ Failed to insert '{$skill_name}': " . $conn->error . "\n";
    }
}

$stmt->close();

echo "\n";
echo "========================================\n";
echo "Summary:\n";
echo "  Inserted: $inserted skill(s)\n";
echo "  Skipped: $skipped skill(s)\n";
echo "========================================\n";

closeDBConnection($conn);
?>

