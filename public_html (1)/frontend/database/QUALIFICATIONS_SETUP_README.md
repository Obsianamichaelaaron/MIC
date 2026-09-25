# Qualifications Setup Instructions

## Overview
The chatbot system now supports **10 non-IT qualifications** to cover all job categories, not just IT-related positions.

## Required Qualifications (10 Non-IT)

1. Bachelor of Science in Business Administration
2. Bachelor of Science in Accounting
3. Bachelor of Science in Human Resources
4. Bachelor of Science in Marketing
5. Bachelor of Science in Nursing
6. Bachelor of Science in Education
7. Bachelor of Science in Engineering
8. Master of Business Administration (MBA)
9. Associate Degree in Business Management
10. Certificate in Digital Marketing

## Setup Instructions

### Option 1: Run PHP Script (Recommended)
Visit in your browser:
```
http://localhost/system123/database/insert_non_it_qualifications.php
```

This script will:
- Create the qualifications table if it doesn't exist
- Insert all 10 non-IT qualifications
- Update existing qualifications if they already exist
- Show a summary of what was inserted/updated

### Option 2: Run SQL Script
Import the SQL file:
```
database/insert_sample_data.sql
```

The qualifications section (lines 11-24) contains all 10 non-IT qualifications.

### Option 3: Use add_qualifications_table.php
Visit in your browser:
```
http://localhost/system123/database/add_qualifications_table.php
```

This script has been updated to include all 10 non-IT qualifications.

## Verification

After running any of the above scripts, verify the qualifications are in the database:

1. Check the chatbot page: `applicant/chatbot.php`
2. You should see all 10 qualification cards displayed
3. Or check the database directly:
   ```sql
   SELECT COUNT(*) FROM qualifications WHERE status = 'active';
   ```
   Should return at least 10 (may be more if IT qualifications also exist)

## Notes

- All qualifications are set to `status = 'active'` by default
- The chatbot will display all active qualifications
- Qualifications are ordered alphabetically by name
- If a qualification already exists, it will be updated (not duplicated)

