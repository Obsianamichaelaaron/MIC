<?php
/**
 * Resume Parser - Extracts text and information from PDF resumes
 */

function parseResumePDF($file_path, $conn = null) {
    if (!file_exists($file_path)) {
        return null;
    }
    
    $extracted_data = [
        'text' => '',
        'skills' => [],
        'education' => '',
        'experience' => '',
        'qualifications' => ''
    ];
    
    // Try to extract text using pdftotext command (if available)
    if (function_exists('shell_exec')) {
        $command = "pdftotext " . escapeshellarg($file_path) . " - 2>/dev/null";
        $text = @shell_exec($command);
        if ($text) {
            // Clean the extracted text
            $text = cleanExtractedText($text);
            if (!empty($text) && isReadableText($text)) {
                $extracted_data['text'] = $text;
                $extracted_data = parseResumeText($text, $conn);
                return $extracted_data;
            }
        }
    }
    
    // Fallback: Try using file_get_contents
    $content = @file_get_contents($file_path);
    if ($content) {
        $text = extractTextFromPDF($content);
        if ($text) {
            $extracted_data['text'] = $text;
            $extracted_data = parseResumeText($text, $conn);
            return $extracted_data;
        }
    }
    
    return $extracted_data;
}

function extractTextFromPDF($pdf_content) {
    $text = '';
    
    // Method 1: Extract text from PDF content streams
    preg_match_all('/stream\s*(.*?)\s*endstream/s', $pdf_content, $streams);
    if (!empty($streams[1])) {
        foreach ($streams[1] as $stream) {
            // Try different decompression methods
            $decoded = @gzuncompress($stream);
            if (!$decoded) {
                $decoded = @gzdecode($stream);
            }
            if (!$decoded) {
                $decoded = $stream; // Use as-is if decompression fails
            }
            
            if ($decoded) {
                // Extract text from parentheses (PDF text objects)
                preg_match_all('/\((.*?)\)/s', $decoded, $decoded_matches);
                if (!empty($decoded_matches[1])) {
                    foreach ($decoded_matches[1] as $dmatch) {
                        // Remove escape sequences and clean up
                        $dmatch = str_replace(['\\n', '\\r', '\\t'], [' ', ' ', ' '], $dmatch);
                        $dmatch = preg_replace('/\\\\(.)/', '$1', $dmatch); // Remove backslashes
                        
                        // Filter out garbled text - only add if it looks readable
                        if (isReadableText($dmatch)) {
                            $text .= $dmatch . ' ';
                        }
                    }
                }
                
                // Also try extracting text from TJ and Tj operators
                preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $tj_matches);
                if (!empty($tj_matches[1])) {
                    foreach ($tj_matches[1] as $tj_match) {
                        preg_match_all('/\((.*?)\)/', $tj_match, $tj_text);
                        if (!empty($tj_text[1])) {
                            foreach ($tj_text[1] as $tj) {
                                $tj = str_replace(['\\n', '\\r', '\\t'], [' ', ' ', ' '], $tj);
                                $tj = preg_replace('/\\\\(.)/', '$1', $tj);
                                
                                // Filter out garbled text
                                if (isReadableText($tj)) {
                                    $text .= $tj . ' ';
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Method 2: Extract from direct text objects
    preg_match_all('/\((.*?)\)/s', $pdf_content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $match) {
            $match = str_replace(['\\n', '\\r', '\\t'], [' ', ' ', ' '], $match);
            $match = preg_replace('/\\\\(.)/', '$1', $match);
            
            // Filter out garbled text
            if (isReadableText($match)) {
                $text .= $match . ' ';
            }
        }
    }
    
    // Clean up the text: remove excessive spaces, fix character spacing issues
    // First, normalize all whitespace
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Remove non-printable characters except common ones
    $text = preg_replace('/[^\x20-\x7E\s]/', '', $text);
    
    // Fix common PDF extraction issues: spaces between letters in words
    // Pattern: lowercase letter, space, lowercase letter (likely a broken word)
    // But be careful not to break actual word boundaries
    $text = preg_replace('/([a-z])\s+([a-z])(?=\s|$|[^a-z])/i', '$1$2', $text);
    $text = preg_replace('/(?<=^|\s|[^a-z])([a-z])\s+([a-z])/i', '$1$2', $text);
    
    // Fix spacing between capital letter and lowercase letters (broken words like "Ma nagement")
    $text = preg_replace('/([A-Z])\s+([a-z]{1,2})\s+([a-z]+)/i', '$1$2$3', $text);
    $text = preg_replace('/([A-Z])\s+([a-z]+)/i', '$1$2', $text);
    
    // Remove spaces before punctuation
    $text = preg_replace('/\s+([.,;:!?])/', '$1', $text);
    
    // Ensure proper spacing after punctuation
    $text = preg_replace('/([.,;:!?])([a-zA-Z])/', '$1 $2', $text);
    
    // Final cleanup: remove multiple spaces again
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Final validation: ensure the text is readable
    $text = cleanExtractedText($text);
    
    return trim($text);
}

function parseResumeText($text, $conn = null) {
    // Clean the text first before parsing
    $text = cleanExtractedText($text);
    
    $data = [
        'text' => $text,
        'skills' => [],
        'education' => '',
        'experience' => '',
        'qualifications' => ''
    ];
    
    // Get skill keywords from SQL database
    $skill_keywords = [];
    if ($conn) {
        $stmt = $conn->prepare("SELECT skill_name FROM skills WHERE status = 'active' ORDER BY skill_name");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $skill_keywords[] = $row['skill_name'];
            }
            $stmt->close();
        }
    }
    
    // Fallback to empty array if no connection or no skills found
    // This ensures the function still works even without database
    if (empty($skill_keywords)) {
        $skill_keywords = [];
    }
    
    $text_lower = strtolower($text);
    $found_skills = [];
    
    // Extract skills with better pattern matching
    foreach ($skill_keywords as $skill) {
        // Use word boundaries to avoid partial matches
        $pattern = '/\b' . preg_quote($skill, '/') . '\b/i';
        if (preg_match($pattern, $text_lower)) {
            $found_skills[] = $skill;
        }
    }
    
    // Extract skills from Skills section - this is the PRIMARY source
    // Look for skills sections with various patterns
    $skills_section_patterns = [
        '/(?:skills|technical skills|proficiencies|competencies|core competencies|key skills|work experience skills)[\s:]*\n?(.*?)(?:\n\n|\n(?:experience|education|projects|qualifications|work|employment|additional|certification|educational|contact|$))/is',
        '/(?:technical skills|skills)[\s:]*\n?(.*?)(?:\n(?:tools|languages|certification|educational|work|experience|contact)|$)/is'
    ];
    
    $skills_from_section = [];
    foreach ($skills_section_patterns as $pattern) {
        if (preg_match($pattern, $text, $skills_match)) {
            $skills_section = $skills_match[1];
            
            // Extract skills from main section and sub-sections (Tools, Languages, etc.)
            // Handle various formats:
            // 1. Comma-separated: "Financial Analysis, Budgeting, Risk Management"
            // 2. Line-separated: "Financial Analysis\nBudgeting\nRisk Management"
            // 3. Bullet points or dashes
            // 4. In sub-sections like "Tools:", "Languages:", etc.
            
            // Extract from sub-sections first (Tools, Languages, etc.)
            if (preg_match_all('/(?:tools?|languages?|software|systems?|certification|additional)[\s:]*\n?(.*?)(?:\n(?:tools?|languages?|software|systems?|certification|additional|educational|work|experience|contact)|$)/is', $skills_section, $sub_sections)) {
                foreach ($sub_sections[1] as $sub_section) {
                    $sub_skills = preg_split('/[,;\n\r•\-\*]+/', $sub_section);
                    foreach ($sub_skills as $skill_item) {
                        $skill_item = trim($skill_item);
                        // Remove language proficiency indicators
                        $skill_item = preg_replace('/\s*\([^)]*\)\s*$/', '', $skill_item); // Remove (Fluent), (Proficient), etc.
                        $skill_item = trim($skill_item);
                        
                        if (!empty($skill_item) && strlen($skill_item) >= 2 && strlen($skill_item) <= 100) {
                            if (preg_match('/[a-zA-Z]{2,}/', $skill_item)) {
                                $skills_from_section[] = $skill_item;
                            }
                        }
                    }
                }
            }
            
            // Extract from main skills section (before sub-sections)
            // Remove sub-section headers first
            $main_section = preg_replace('/(?:tools?|languages?|software|systems?|certification|additional)[\s:]*\n.*?(?=\n(?:tools?|languages?|software|systems?|certification|additional|educational|work|experience|contact)|$)/is', '', $skills_section);
            
            // Split by common delimiters
            $potential_skills = preg_split('/[,;\n\r•\-\*]+/', $main_section);
            
            foreach ($potential_skills as $skill_item) {
                $skill_item = trim($skill_item);
                
                // Skip if too short or looks like a section header
                if (empty($skill_item) || strlen($skill_item) < 2) {
                    continue;
                }
                
                // Skip section headers
                if (preg_match('/^(tools?|languages?|software|systems?|certification|additional|information|methods?|techniques?)[\s:]*$/i', $skill_item)) {
                    continue;
                }
                
                // Clean up the skill
                $skill_item = preg_replace('/\s+/', ' ', $skill_item);
                
                // If it's a reasonable length and looks like a skill, add it
                if (strlen($skill_item) >= 2 && strlen($skill_item) <= 100) {
                    // Check if it contains actual words (not just numbers/symbols)
                    if (preg_match('/[a-zA-Z]{2,}/', $skill_item)) {
                        $skills_from_section[] = $skill_item;
                    }
                }
            }
            
            // If we found skills in a section, use those as primary
            if (!empty($skills_from_section)) {
                break;
            }
        }
    }
    
    // If we found skills from the Skills section, use those primarily
    if (!empty($skills_from_section)) {
        // Clear any keyword-matched skills and use ONLY skills from the section
        $found_skills = [];
        
        // Add all skills from the section - use original names from resume
        foreach ($skills_from_section as $skill) {
            $skill = trim($skill);
            // Skip if empty or too short
            if (empty($skill) || strlen($skill) < 2) {
                continue;
            }
            
            // Normalize capitalization (title case for multi-word skills)
            $skill = ucwords(strtolower($skill));
            
            // Add the skill as-is from the resume
            if (!in_array($skill, $found_skills)) {
                $found_skills[] = $skill;
            }
        }
    } else {
        // Fallback: look for skills in common formats if no dedicated section found
        if (preg_match('/(?:skills|technical skills|proficiencies|competencies)[\s:]*\n?(.*?)(?:\n\n|\n(?:experience|education|projects|qualifications)|$)/is', $text, $skills_match)) {
            $skills_section = $skills_match[1];
            $skills_list = preg_split('/[,;\n\r]+/', $skills_section);
            foreach ($skills_list as $skill_item) {
                $skill_item = trim(strtolower($skill_item));
                if (!empty($skill_item) && strlen($skill_item) > 2) {
                    // Check if it matches any known skill from database
                    if (!empty($skill_keywords)) {
                        foreach ($skill_keywords as $known_skill) {
                            if (stripos($skill_item, $known_skill) !== false || stripos($known_skill, $skill_item) !== false) {
                                if (!in_array($known_skill, $found_skills)) {
                                    $found_skills[] = $known_skill;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    
    $data['skills'] = array_unique($found_skills);
    
    // Improved education extraction with multiple patterns
    $education_patterns = [
        '/(?:education|educational background|academic background|academic qualifications?|qualifications?)[\s:]*\n?(.*?)(?:\n\n|\n(?:experience|work|employment|professional|skills|projects|certifications?|$))/is',
        '/(?:degree|bachelor|master|phd|doctorate|diploma)[\s:]*\n?(.*?)(?:\n\n|\n(?:experience|work|skills|projects|$))/is'
    ];
    
    foreach ($education_patterns as $pattern) {
        if (preg_match($pattern, $text, $edu_match)) {
            $edu_text = trim($edu_match[1]);
            if (strlen($edu_text) > 10) { // Only use if substantial content
                $data['education'] = $edu_text;
                break;
            }
        }
    }
    
    // Improved experience extraction with multiple patterns
    $experience_patterns = [
        '/(?:experience|work experience|employment history|professional experience|career history|employment)[\s:]*\n?(.*?)(?:\n\n|\n(?:education|skills|projects|certifications?|qualifications?|$))/is',
        '/(?:work|employment|position|role)[\s:]*\n?(.*?)(?:\n\n|\n(?:education|skills|projects|$))/is'
    ];
    
    foreach ($experience_patterns as $pattern) {
        if (preg_match($pattern, $text, $exp_match)) {
            $exp_text = trim($exp_match[1]);
            if (strlen($exp_text) > 10) { // Only use if substantial content
                $data['experience'] = $exp_text;
                break;
            }
        }
    }
    
    // Improved qualifications extraction with better filtering
    $qualification_keywords = [
        'bachelor', 'master', 'phd', 'doctorate', 'degree', 'diploma', 'certificate',
        'bs', 'ba', 'ms', 'ma', 'mba', 'bsc', 'msc', 'bachelor of', 'master of',
        'bachelor\'s', 'master\'s', 'doctorate', 'ph.d', 'phd', 'doctor of'
    ];
    
    $qualifications_found = [];
    foreach ($qualification_keywords as $qual) {
        $pattern = '/\b' . preg_quote($qual, '/') . '\b[^\n]{0,150}/i';
        if (preg_match_all($pattern, $text, $qual_matches)) {
            foreach ($qual_matches[0] as $qual_match) {
                $qual_match = trim($qual_match);
                // Filter out garbled/binary text - only keep readable text
                if (strlen($qual_match) > 3 && isReadableText($qual_match)) {
                    // Clean up the qualification text
                    $qual_match = cleanQualificationText($qual_match);
                    if (strlen($qual_match) > 3) {
                        $qualifications_found[] = $qual_match;
                    }
                }
            }
        }
    }
    
    $data['qualifications'] = implode('; ', array_unique($qualifications_found));
    
    return $data;
}

/**
 * Check if text is readable (not garbled/binary)
 */
function isReadableText($text) {
    if (empty($text) || strlen(trim($text)) == 0) {
        return false;
    }
    
    // Check for common PDF font metadata that indicates garbled text
    $font_indicators = ['AdobeUCS', 'ArialNovaVersion', 'endstream', 'endobj', 'FlateDecode', 'StructElem'];
    foreach ($font_indicators as $indicator) {
        if (stripos($text, $indicator) !== false) {
            return false; // Contains font metadata, likely garbled
        }
    }
    
    // Check for excessive repeated words (like "AdobeUCS AdobeUCS AdobeUCS")
    if (preg_match('/\b(\w+)(\s+\1){2,}\b/i', $text)) {
        return false;
    }
    
    // Check for words with excessive repeated characters (like "DDDDXXXXXXXXX")
    if (preg_match('/\b([A-Za-z0-9])\1{4,}\b/', $text)) {
        return false;
    }
    
    // Remove common readable characters and check ratio
    $readable_chars = preg_match_all('/[a-zA-Z0-9\s\.\,\;\:\-\(\)\/]/', $text);
    $total_chars = strlen($text);
    
    if ($total_chars == 0) return false;
    
    // If more than 70% are readable characters, consider it readable
    $readable_ratio = $readable_chars / $total_chars;
    
    // Also check for excessive non-printable characters
    $non_printable = preg_match_all('/[^\x20-\x7E]/', $text);
    $non_printable_ratio = $non_printable / max(1, $total_chars);
    
    // Check for patterns that indicate garbled text (single letters separated by spaces)
    $single_letter_pattern = preg_match_all('/\b[a-zA-Z]\s+[a-zA-Z]\s+[a-zA-Z]\s+[a-zA-Z]\s+[a-zA-Z]\b/', $text);
    if ($single_letter_pattern > 2) {
        return false; // Too many single-letter patterns, likely garbled
    }
    
    // Check if text has too many special characters in sequence
    $special_char_sequence = preg_match_all('/[^a-zA-Z0-9\s]{3,}/', $text);
    if ($special_char_sequence > 5) {
        return false; // Too many special character sequences
    }
    
    // Check for short capitalized encoding patterns (like "Fa aH EhD ZQX")
    $encoding_pattern = preg_match_all('/\b[A-Z][a-z]?[A-Z]\b/', $text);
    if ($encoding_pattern > 5) {
        return false; // Too many encoding-like patterns
    }
    
    // Check for repeated lowercase+uppercase patterns (like "oY oY oY hY hY")
    $repeated_pattern = preg_match_all('/\b([a-z][A-Z])(\s+\1){2,}\b/', $text);
    if ($repeated_pattern > 0) {
        return false; // Contains repeated encoding patterns
    }
    
    // Check if text has enough actual words (at least 3 characters)
    $word_count = preg_match_all('/\b[a-zA-Z]{3,}\b/', $text);
    if ($word_count < 5) {
        return false; // Not enough real words
    }
    
    return $readable_ratio >= 0.7 && $non_printable_ratio < 0.3;
}

/**
 * Clean extracted text - remove garbled content and normalize
 */
function cleanExtractedText($text) {
    if (empty($text)) {
        return '';
    }
    
    // Remove non-printable characters except common punctuation
    $text = preg_replace('/[^\x20-\x7E\s]/', '', $text);
    
    // Remove common PDF font metadata patterns
    $font_patterns = [
        '/AdobeUCS\s*/i',
        '/ArialNovaVersion/i',
        '/Arial Nova/i',
        '/Copyright The Monotype Corporation/i',
        '/All rights reserved/i',
        '/endstream/i',
        '/endobj/i',
        '/obj\s+\d+/i',
        '/Type\s+StructElem/i',
        '/Filter\s+FlateDecode/i',
        '/Length\s+\d+/i',
        '/MONO\s*/i',
        '/prepx\s*/i',
        '/Uoxx\s*/i',
        '/Upn\s*/i',
        '/Ued\s*/i',
        '/odd\s*/i',
        '/RK\s*/i',
    ];
    foreach ($font_patterns as $pattern) {
        $text = preg_replace($pattern, ' ', $text);
    }
    
    // Remove words with excessive repeated characters (like DDDDXXXXXXXXX, UUUUUUUUUU)
    $text = preg_replace('/\b([A-Za-z0-9])\1{4,}\b/', '', $text);
    
    // Remove short capitalized words that look like font encoding (2-3 chars, all caps, mixed with lowercase)
    $text = preg_replace('/\b[A-Z][a-z][A-Z]\b/', '', $text); // Pattern like "FaH", "EhD"
    $text = preg_replace('/\b[A-Z]{2}[a-z][A-Z]\b/', '', $text); // Pattern like "ZQXMD"
    
    // Split into words and filter out garbled segments
    $words = preg_split('/\s+/', $text);
    $clean_words = [];
    $word_counts = []; // Track word frequency to filter repeats
    
    foreach ($words as $word) {
        $word = trim($word);
        if (empty($word)) {
            continue;
        }
        
        // Skip single characters unless they're common (like 'a', 'I')
        if (strlen($word) == 1 && !in_array(strtolower($word), ['a', 'i'])) {
            continue;
        }
        
        // Skip very short words (2 chars) that are all caps (likely encoding)
        if (strlen($word) == 2 && ctype_upper($word)) {
            continue;
        }
        
        // Skip words that are mostly special characters
        $letter_count = preg_match_all('/[a-zA-Z]/', $word);
        $total_chars = strlen($word);
        if ($total_chars > 0 && ($letter_count / $total_chars) < 0.5) {
            continue; // More than 50% special characters, skip
        }
        
        // Skip if word looks like garbled text (alternating single letters and special chars)
        if (preg_match('/^([a-zA-Z][^a-zA-Z]){3,}$/', $word)) {
            continue;
        }
        
        // Skip words with excessive repeated characters
        if (preg_match('/(.)\1{3,}/', $word)) {
            continue;
        }
        
        // Skip short words that are all caps and look like encoding (like "Fa", "EhD", "ZQX")
        if (strlen($word) <= 4 && preg_match('/^[A-Z][a-z]?[A-Z]/', $word)) {
            continue;
        }
        
        // Skip patterns like "oY", "hY", "xY" (repeated lowercase+uppercase)
        if (preg_match('/^[a-z][A-Z]$/', $word)) {
            continue;
        }
        
        // Track word frequency - skip if word appears more than 3 times (likely font metadata)
        $word_lower = strtolower($word);
        if (!isset($word_counts[$word_lower])) {
            $word_counts[$word_lower] = 0;
        }
        $word_counts[$word_lower]++;
        if ($word_counts[$word_lower] > 3 && strlen($word) < 10) {
            continue; // Skip frequently repeated short words
        }
        
        // Only keep words that are at least 3 characters OR are common words
        $common_words = ['a', 'i', 'an', 'as', 'at', 'be', 'by', 'do', 'go', 'he', 'if', 'in', 'is', 'it', 'me', 'my', 'no', 'of', 'on', 'or', 'so', 'to', 'up', 'us', 'we'];
        if (strlen($word) < 3 && !in_array(strtolower($word), $common_words)) {
            continue;
        }
        
        $clean_words[] = $word;
    }
    
    $text = implode(' ', $clean_words);
    
    // Remove repeated word sequences (like "AdobeUCS AdobeUCS AdobeUCS")
    $text = preg_replace('/\b(\w+)(\s+\1){2,}\b/i', '', $text);
    
    // Final validation: check if the cleaned text is readable
    if (!isReadableText($text)) {
        // Try to extract only readable parts - words with at least 3 letters
        preg_match_all('/\b[a-zA-Z]{3,}\b/', $text, $readable_words);
        $text = implode(' ', $readable_words[0]);
    }
    
    // Remove excessive spaces
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Final check: if text is too short or still looks garbled, return empty
    if (strlen($text) < 10) {
        return '';
    }
    
    return trim($text);
}

/**
 * Clean qualification text - remove garbled characters and normalize
 */
function cleanQualificationText($text) {
    // Remove non-printable characters except common punctuation
    $text = preg_replace('/[^\x20-\x7E\s]/', '', $text);
    
    // Remove excessive spaces
    $text = preg_replace('/\s+/', ' ', $text);
    
    // Check if text contains actual qualification keywords - if not, it's likely garbled
    $qual_keywords = ['bachelor', 'master', 'phd', 'doctorate', 'degree', 'diploma', 'certificate', 
                      'bs', 'ba', 'ms', 'ma', 'mba', 'bsc', 'msc', 'university', 'college', 'school'];
    $has_qual_keyword = false;
    foreach ($qual_keywords as $keyword) {
        if (stripos($text, $keyword) !== false) {
            $has_qual_keyword = true;
            break;
        }
    }
    
    // If no qualification keywords found, it's likely garbled - extract only words that might be readable
    if (!$has_qual_keyword) {
        // Extract only sequences of letters (words) that are at least 3 characters
        preg_match_all('/\b[a-zA-Z]{3,}\b/', $text, $word_matches);
        $text = implode(' ', $word_matches[0]);
        
        // If still no qualification keywords, return empty
        $has_qual_keyword = false;
        foreach ($qual_keywords as $keyword) {
            if (stripos($text, $keyword) !== false) {
                $has_qual_keyword = true;
                break;
            }
        }
        if (!$has_qual_keyword) {
            return ''; // Return empty if no qualification keywords found
        }
    }
    
    // Remove text that looks like binary/garbled (has too many special chars or single letters)
    $special_chars = preg_match_all('/[^a-zA-Z0-9\s]/', $text);
    $total_chars = strlen(preg_replace('/\s/', '', $text));
    if ($total_chars > 0 && ($special_chars / $total_chars) > 0.4) {
        // Too many special characters, likely garbled - extract only readable parts
        preg_match_all('/[a-zA-Z0-9\s\.\,\;\:\-\(\)\/]+/', $text, $matches);
        $text = implode(' ', $matches[0]);
    }
    
    // Remove single letter words and numbers that are likely garbled
    $words = explode(' ', $text);
    $clean_words = [];
    foreach ($words as $word) {
        $word = trim($word);
        // Keep words that are at least 3 characters OR are known abbreviations
        if (strlen($word) >= 3 || in_array(strtolower($word), ['bs', 'ba', 'ms', 'ma', 'phd', 'mba', 'bsc', 'msc'])) {
            $clean_words[] = $word;
        }
    }
    $text = implode(' ', $clean_words);
    
    return trim($text);
}

function analyzeResumeForApplicant($conn, $applicant_id) {
    $stmt = $conn->prepare("SELECT resume_file FROM applicants WHERE applicant_id = ?");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return false;
    }
    
    $applicant = $result->fetch_assoc();
    $stmt->close();
    
    if (empty($applicant['resume_file'])) {
        return false;
    }
    
    $resume_path = __DIR__ . '/../../' . $applicant['resume_file'];
    
    if (!file_exists($resume_path)) {
        return false;
    }
    
    $parsed_data = parseResumePDF($resume_path, $conn);
    
    if (!$parsed_data) {
        return false;
    }
    
    // Clean extracted text before storing
    $extracted_text = !empty($parsed_data['text']) ? cleanExtractedText($parsed_data['text']) : '';
    
    // Only store if text is readable
    if (!empty($extracted_text) && !isReadableText($extracted_text)) {
        $extracted_text = ''; // Don't store garbled text
    }
    
    $stmt = $conn->prepare("
        INSERT INTO resume_analysis 
        (applicant_id, resume_file, extracted_text, skills_extracted, education_extracted, experience_extracted, qualifications_extracted)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        extracted_text = VALUES(extracted_text),
        skills_extracted = VALUES(skills_extracted),
        education_extracted = VALUES(education_extracted),
        experience_extracted = VALUES(experience_extracted),
        qualifications_extracted = VALUES(qualifications_extracted),
        analysis_date = CURRENT_TIMESTAMP
    ");
    
    // Join skills with comma - preserve original skill names from resume
    $skills_str = !empty($parsed_data['skills']) ? implode(', ', $parsed_data['skills']) : '';
    $stmt->bind_param("issssss", 
        $applicant_id,
        $applicant['resume_file'],
        $extracted_text,
        $skills_str,
        $parsed_data['education'],
        $parsed_data['experience'],
        $parsed_data['qualifications']
    );
    
    $result = $stmt->execute();
    $stmt->close();
    
    if (!empty($skills_str)) {
        $stmt = $conn->prepare("UPDATE applicants SET skills = ? WHERE applicant_id = ? AND (skills IS NULL OR skills = '')");
        $stmt->bind_param("si", $skills_str, $applicant_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Recalculate employability score based on extracted data
    if ($result) {
        $score = calculateEmployabilityScoreFromAnalysis($conn, $applicant_id, $parsed_data);
        if ($score > 0) {
            $stmt = $conn->prepare("UPDATE applicants SET employability_score = ? WHERE applicant_id = ?");
            $stmt->bind_param("di", $score, $applicant_id);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    return $result;
}

/**
 * Calculate employability score from resume analysis data
 */
function calculateEmployabilityScoreFromAnalysis($conn, $applicant_id, $parsed_data) {
    $score = 0;
    
    // Skills (0-30 points) - based on number of skills extracted
    if (!empty($parsed_data['skills']) && is_array($parsed_data['skills'])) {
        $skills_count = count($parsed_data['skills']);
        $score += min(30, $skills_count * 5);
    } elseif (!empty($parsed_data['skills'])) {
        // If skills is a string, count commas
        $skills_count = count(explode(',', $parsed_data['skills']));
        $score += min(30, $skills_count * 5);
    }
    
    // Qualifications (0-25 points) - if qualifications extracted
    if (!empty($parsed_data['qualifications'])) {
        $qual_text = strtolower($parsed_data['qualifications']);
        // Check for degree keywords
        $degree_keywords = ['bachelor', 'master', 'phd', 'doctorate', 'degree', 'diploma'];
        foreach ($degree_keywords as $keyword) {
            if (stripos($qual_text, $keyword) !== false) {
                $score += 25;
                break;
            }
        }
    }
    
    // Experience (0-25 points) - if experience section extracted
    if (!empty($parsed_data['experience'])) {
        // Try to extract years from experience text
        if (preg_match('/(\d+)\s*(?:year|yr|years)/i', $parsed_data['experience'], $matches)) {
            $years = intval($matches[1]);
            $score += min(25, $years * 5);
        } else {
            // If experience text exists but no years found, give base points
            $score += 15;
        }
    }
    
    // Education (0-20 points) - if education section extracted
    if (!empty($parsed_data['education'])) {
        $edu_text = strtolower($parsed_data['education']);
        $education_scores = [
            'phd' => 20,
            'doctorate' => 20,
            'master' => 20,
            'bachelor' => 18,
            'associate' => 15,
            'high school' => 10
        ];
        
        foreach ($education_scores as $level => $points) {
            if (stripos($edu_text, $level) !== false) {
                $score += $points;
                break;
            }
        }
        
        // If no specific level found but education exists, give base points
        if ($score < 20 && !empty($parsed_data['education'])) {
            $score += 10;
        }
    }
    
    return min(100, $score);
}

/**
 * Get resume analysis data for an applicant
 */
function getResumeAnalysis($conn, $applicant_id) {
    $stmt = $conn->prepare("
        SELECT * FROM resume_analysis 
        WHERE applicant_id = ? 
        ORDER BY analysis_date DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $applicant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $analysis = $result->fetch_assoc();
    $stmt->close();
    
    return $analysis ? $analysis : null;
}
?>

