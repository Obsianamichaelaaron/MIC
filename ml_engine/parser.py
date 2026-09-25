import io
import re
import os

try:
    from pypdf import PdfReader
except ImportError:
    try:
        from PyPDF2 import PdfReader
    except ImportError:
        PdfReader = None

try:
    import docx
except ImportError:
    docx = None

COMMON_SKILLS_TAXONOMY = [
    # Programming Languages & Frameworks
    'Python', 'Django', 'Flask', 'FastAPI', 'JavaScript', 'TypeScript', 'React', 'React.js', 'Next.js',
    'Vue', 'Vue.js', 'Nuxt.js', 'Angular', 'Svelte', 'Node.js', 'Express', 'Express.js',
    'HTML', 'HTML5', 'CSS', 'CSS3', 'Sass', 'SCSS', 'Bootstrap', 'Tailwind', 'TailwindCSS',
    'PHP', 'Laravel', 'CodeIgniter', 'Java', 'Spring', 'Spring Boot',
    'C#', '.NET', 'ASP.NET', 'C++', 'C', 'Go', 'Golang', 'Rust', 'Ruby', 'Rails', 'Ruby on Rails',
    # Databases & Storage
    'SQL', 'MySQL', 'PostgreSQL', 'Postgres', 'SQLite', 'MongoDB', 'Redis', 'Firebase', 'Supabase',
    'Oracle', 'MSSQL', 'DynamoDB', 'Cassandra', 'Database Design', 'NoSQL',
    # Cloud, DevOps & Tools
    'Git', 'GitHub', 'GitLab', 'Docker', 'Kubernetes', 'AWS', 'Azure', 'GCP', 'Google Cloud',
    'REST API', 'RESTful API', 'REST', 'GraphQL', 'Linux', 'Ubuntu', 'Apache', 'Nginx',
    'DevOps', 'CI/CD', 'Microservices', 'Celery', 'Kafka', 'RabbitMQ', 'Jira', 'Confluence',
    # Testing & QA
    'Unit Testing', 'Pytest', 'Jest', 'Mocha', 'Cypress', 'Selenium', 'Quality Assurance', 'QA',
    # Data Science & Machine Learning
    'Machine Learning', 'Data Analysis', 'Data Science', 'Pandas', 'NumPy', 'Scikit-Learn',
    'TensorFlow', 'PyTorch', 'Keras', 'NLP', 'Computer Vision', 'Deep Learning',
    'Power BI', 'Tableau', 'Excel', 'Data Visualization', 'Big Data', 'Spark',
    # Design & Multimedia
    'UI/UX Design', 'UI Design', 'UX Design', 'Figma', 'Adobe XD', 'Photoshop', 'Illustrator',
    'InDesign', 'Visual Design', 'Wireframing', 'Prototyping', 'User Research',
    # Business, Soft Skills & Management
    'Communication', 'Leadership', 'Project Management', 'Problem Solving',
    'Teamwork', 'Collaboration', 'Adaptability', 'Time Management', 'Attention to Detail',
    'Critical Thinking', 'Conflict Resolution', 'Agile', 'Scrum', 'Kanban', 'Customer Service',
    'Sales', 'Marketing', 'Digital Marketing', 'Social Media Marketing', 'SEO', 'SEM',
    'Content Writing', 'Copywriting', 'Technical Writing', 'Public Speaking',
    'Financial Analysis', 'Accounting', 'Bookkeeping', 'Operations Management', 'Cybersecurity'
]

EDUCATION_PATTERNS = [
    (r'(?i)\b(doctorate|ph\.?d|doctor of philosophy)\b', 'Doctorate'),
    (r'(?i)\b(master(?:\'s)?|ms|m\.s\.|msc|mba|m\.b\.a\.|ma|m\.a\.)\b', 'Master'),
    (r'(?i)\b(bachelor(?:\'s)?|bs|b\.s\.|bsc|b\.sc\.|ba|b\.a\.|btech|b\.tech|bse)\b', 'Bachelor'),
    (r'(?i)\b(associate(?:\'s)?|aa|a\.s\.)\b', 'Associate'),
    (r'(?i)\b(diploma|vocational|certificate)\b', 'Vocational'),
    (r'(?i)\b(high school|secondary)\b', 'High School'),
]

def extract_text_from_pdf(file_input):
    """Extracts text from PDF file path or file-like object."""
    if not PdfReader or not file_input:
        return ""
    try:
        if isinstance(file_input, (str, os.PathLike)):
            if not os.path.exists(file_input):
                return ""
            reader = PdfReader(file_input)
        else:
            if hasattr(file_input, 'path') and not os.path.exists(file_input.path):
                return ""
            if hasattr(file_input, 'seek'):
                file_input.seek(0)
            reader = PdfReader(file_input)

        text_pages = []
        for page in reader.pages:
            t = page.extract_text()
            if t:
                text_pages.append(t)
        return "\n".join(text_pages)
    except Exception:
        return ""

def extract_text_from_docx(file_input):
    """Extracts text from DOCX file path or file-like object."""
    if not docx or not file_input:
        return ""
    try:
        if isinstance(file_input, (str, os.PathLike)):
            if not os.path.exists(file_input):
                return ""
            doc = docx.Document(file_input)
        else:
            if hasattr(file_input, 'path') and not os.path.exists(file_input.path):
                return ""
            if hasattr(file_input, 'seek'):
                file_input.seek(0)
            doc = docx.Document(file_input)

        text_runs = [p.text for p in doc.paragraphs if p.text.strip()]
        for table in doc.tables:
            for row in table.rows:
                for cell in row.cells:
                    if cell.text.strip():
                        text_runs.append(cell.text.strip())
        return "\n".join(text_runs)
    except Exception:
        return ""

def extract_experience_years(text):
    """Estimates years of experience using regex patterns."""
    patterns = [
        r'(?i)(\d{1,2})\+?\s*(?:years?|yrs?)(?:\s+of)?\s+experience',
        r'(?i)experience\s*(?:of)?\s*(\d{1,2})\+?\s*(?:years?|yrs?)',
        r'(?i)working\s+for\s+(\d{1,2})\+?\s*(?:years?|yrs?)',
    ]
    max_years = 0
    for p in patterns:
        matches = re.findall(p, text)
        for m in matches:
            try:
                y = int(m)
                if 0 < y <= 40 and y > max_years:
                    max_years = y
            except ValueError:
                pass

    if max_years == 0:
        # Check for year ranges e.g. 2018 - 2023
        year_ranges = re.findall(r'\b(20[012]\d|19[89]\d)\s*[-–—to]+\s*(20[012]\d|present|current)\b', text, re.I)
        total_span = 0
        for start, end in year_ranges:
            try:
                sy = int(start)
                ey = 2026 if 'present' in end.lower() or 'current' in end.lower() else int(end)
                if ey >= sy:
                    total_span += (ey - sy)
            except ValueError:
                pass
        if total_span > 0:
            max_years = min(total_span, 30)

    return max_years

def extract_education(text):
    """Detects highest educational attainment."""
    for pattern, edu_level in EDUCATION_PATTERNS:
        if re.search(pattern, text):
            return edu_level
    return "Bachelor" if "university" in text.lower() or "college" in text.lower() else "High School"

def extract_skills(text, custom_skill_names=None):
    """Extracts identified skills matching skill catalog and custom skill list."""
    skills_to_search = set(COMMON_SKILLS_TAXONOMY)
    if custom_skill_names:
        for cs in custom_skill_names:
            if cs and str(cs).strip():
                skills_to_search.add(str(cs).strip())

    found_skills = set()
    lower_text = (text or '').lower()

    for skill in skills_to_search:
        s_clean = skill.strip()
        if not s_clean:
            continue
        s_lower = s_clean.lower()
        # Word boundary / non-alphanumeric lookaround
        pattern = rf'(?<![a-zA-Z0-9]){re.escape(s_lower)}(?![a-zA-Z0-9])'
        if re.search(pattern, lower_text):
            found_skills.add(s_clean)

    return sorted(list(found_skills))


RESUME_SECTION_PATTERNS = [
    ('experience', r'(?i)\b(work\s+experience|employment\s+history|professional\s+experience|work\s+history|career\s+history|experience)\b'),
    ('education', r'(?i)\b(education|academic\s+background|qualifications|academic\s+history|degrees?)\b'),
    ('skills', r'(?i)\b(skills|technical\s+skills|core\s+competencies|technologies|expertise|proficiencies)\b'),
    ('contact', r'(?i)\b(email|phone|tel|mobile|contact|linkedin|github|address|location)\b'),
    ('summary', r'(?i)\b(summary|professional\s+summary|objective|profile|about\s+me|career\s+objective)\b'),
    ('projects', r'(?i)\b(projects|portfolio|key\s+achievements|publications|certifications|awards)\b'),
]

ACTION_VERBS = [
    'developed', 'engineered', 'architected', 'designed', 'implemented', 'managed',
    'led', 'created', 'optimized', 'spearheaded', 'deployed', 'built', 'maintained',
    'collaborated', 'scaled', 'improved', 'delivered', 'reduced', 'increased',
    'analyzed', 'automated', 'integrated', 'streamlined', 'resolved'
]

def validate_and_audit_resume(text):
    """
    Validates whether extracted text represents a genuine resume/CV.
    If valid, computes a comprehensive strength score, section audit, and actionable suggestions.
    """
    clean_text = (text or '').strip()
    if not clean_text or len(clean_text) < 100:
        return {
            'is_valid_resume': False,
            'rejection_reason': 'The uploaded file is empty, unreadable, or does not contain readable Resume/CV content. Please upload a genuine Resume/CV in PDF or DOCX format.',
            'strength_score': 0,
            'strength_level': 'Invalid',
            'sections_detected': [],
            'missing_sections': ['Experience', 'Education', 'Skills', 'Contact Info'],
            'suggestions': ['Please upload a standard PDF or DOCX resume document with selectable text.'],
            'metrics_found': [],
            'action_verbs_found': [],
        }

    lower_text = clean_text.lower()

    # 1. Detect standard resume sections
    sections_detected = []
    missing_sections = []

    for sec_name, pattern in RESUME_SECTION_PATTERNS:
        if re.search(pattern, clean_text):
            sections_detected.append(sec_name)
        else:
            missing_sections.append(sec_name)

    # 2. Check for contact indicators
    has_email = bool(re.search(r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b', clean_text))
    has_phone = bool(re.search(r'(?:\+?\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}', clean_text))
    if (has_email or has_phone) and 'contact' not in sections_detected:
        sections_detected.append('contact')
        if 'contact' in missing_sections:
            missing_sections.remove('contact')

    # 3. Check for skills
    extracted_skills = extract_skills(clean_text)
    if extracted_skills and 'skills' not in sections_detected:
        sections_detected.append('skills')
        if 'skills' in missing_sections:
            missing_sections.remove('skills')

    # 4. Check for experience / education keywords
    has_exp_timeline = extract_experience_years(clean_text) > 0 or bool(re.search(r'\b(20[012]\d|19[89]\d)\b', clean_text))

    # CRITICAL VALIDATION THRESHOLD:
    # Must contain recognized core resume sections or verified skill taxonomy with contact/timeline
    has_core_sections = len(set(sections_detected).intersection({'experience', 'education', 'skills', 'summary', 'projects'})) >= 2
    has_skill_identity = len(extracted_skills) >= 2 and (has_email or has_phone or has_exp_timeline)

    if not (has_core_sections or has_skill_identity):
        return {
            'is_valid_resume': False,
            'rejection_reason': 'The uploaded file does not appear to be a genuine Resume or Curriculum Vitae. It lacks essential professional sections such as Work Experience, Education, or Skills.',
            'strength_score': 0,
            'strength_level': 'Invalid',
            'sections_detected': sections_detected,
            'missing_sections': missing_sections,
            'suggestions': [
                'Please upload a valid Resume/CV in PDF or DOCX format containing your work history, education, and technical skills.'
            ],
            'metrics_found': [],
            'action_verbs_found': [],
        }

    # =========================================================
    # DOCUMENT IS VALID RESUME: COMPUTE STRENGTH AUDIT & SUGGESTIONS
    # =========================================================
    score = 0
    suggestions = []

    # Section Completeness (up to 25 pts)
    section_score = len(sections_detected) * 5.0
    score += min(25.0, section_score)
    if 'summary' in missing_sections:
        suggestions.append('Add a concise 2–3 sentence "Professional Summary" at the top highlighting your core expertise and target role.')
    if 'projects' in missing_sections and len(extracted_skills) < 8:
        suggestions.append('Include a dedicated "Projects & Portfolio" section highlighting real-world deliverables or GitHub repositories.')

    # Skills Depth & Density (up to 25 pts)
    skill_count = len(extracted_skills)
    score += min(25.0, skill_count * 2.5)
    if skill_count < 5:
        suggestions.append(f'Skill profile is relatively thin ({skill_count} detected). Add at least 5+ specific technical tools, frameworks, and methodologies relevant to your career path.')
    elif skill_count < 8:
        suggestions.append('Group your technical skills into clear categories (e.g. Languages, Frameworks, Databases, Cloud & DevOps) for enhanced recruiter visibility.')

    # Action Verbs (up to 20 pts)
    action_verbs_found = [v for v in ACTION_VERBS if re.search(r'\b' + v + r'\b', lower_text)]
    score += min(20.0, len(action_verbs_found) * 3.5)
    if len(action_verbs_found) < 3:
        suggestions.append('Strengthen work experience bullets using strong action verbs (e.g., "Architected", "Spearheaded", "Optimized", "Engineered") instead of passive responsibilities.')

    # Quantifiable Impact & Metrics (up to 15 pts)
    metrics_patterns = [
        r'\b\d+%\b',
        r'\$\d+(?:,\d+)*(?:\.\d+)?(?:k|m|b)?\b',
        r'\b\d+(?:x|k|m)\b',
        r'\b(?:increased|reduced|saved|improved|scaled|delivered)\s+(?:by\s+)?\d+',
        r'\b(?:team\s+of|managed|led)\s+\d+\b'
    ]
    metrics_found = []
    for mp in metrics_patterns:
        m = re.findall(mp, lower_text)
        if m:
            metrics_found.extend(m)

    if metrics_found:
        score += min(15.0, len(metrics_found) * 5.0)
    else:
        suggestions.append('Include measurable results and metrics (e.g., "Reduced loading latency by 35%", "Managed team of 5 engineers") to prove business value.')

    # Education & Contact Presence (up to 15 pts)
    if 'education' in sections_detected:
        score += 8.0
    else:
        suggestions.append('Ensure your highest degree, educational institution, and graduation year are clearly highlighted under an "Education" heading.')

    if has_email and has_phone:
        score += 7.0
    elif has_email or has_phone:
        score += 4.0
    else:
        suggestions.append('Ensure complete, professional contact details (email and mobile number) are clearly placed at the top header.')

    final_score = int(min(100.0, max(20.0, score)))

    if final_score >= 75:
        strength_level = 'Strong'
    elif final_score >= 55:
        strength_level = 'Moderate'
    else:
        strength_level = 'Weak'

    if not suggestions:
        suggestions.append('Your resume exhibits exceptional structural integrity, rich keyword depth, and strong impact metrics.')

    return {
        'is_valid_resume': True,
        'rejection_reason': None,
        'strength_score': final_score,
        'strength_level': strength_level,
        'sections_detected': sections_detected,
        'missing_sections': missing_sections,
        'skills': extracted_skills,
        'action_verbs_found': action_verbs_found,
        'metrics_found': metrics_found[:5],
        'suggestions': suggestions[:4]
    }


def parse_resume_document(file_path=None, file_obj=None, custom_skill_names=None):
    """Main entry point to parse a resume document with built-in validation & AI audit."""
    text = ""
    target = file_obj or file_path

    if not target:
        return {
            'text': '',
            'skills': [],
            'education': 'Bachelor',
            'experience_years': 0,
            'validation': {
                'is_valid_resume': False,
                'rejection_reason': 'No file provided.',
                'strength_score': 0,
                'strength_level': 'Invalid',
                'suggestions': ['Upload a valid resume document.']
            }
        }

    filename = ""
    if hasattr(target, 'name'):
        filename = target.name.lower()
    elif isinstance(target, str):
        filename = target.lower()

    if filename.endswith('.pdf') or (isinstance(target, io.BytesIO) and target.getvalue()[:4] == b'%PDF'):
        text = extract_text_from_pdf(target)
    elif filename.endswith('.docx'):
        text = extract_text_from_docx(target)
    else:
        # Fallback PDF attempt
        text = extract_text_from_pdf(target)
        if not text:
            text = extract_text_from_docx(target)

    # Run full AI validation and strength audit
    validation = validate_and_audit_resume(text)
    is_valid = validation.get('is_valid_resume', False)

    skills = validation.get('skills', []) if is_valid else []
    education = extract_education(text) if is_valid else ""
    exp_years = extract_experience_years(text) if is_valid else 0

    return {
        'text': text[:5000] if is_valid else '',
        'skills': skills,
        'education': education,
        'experience_years': exp_years,
        'validation': validation
    }
