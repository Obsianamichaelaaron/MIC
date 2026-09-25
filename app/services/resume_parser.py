import os
import re
from pypdf import PdfReader
from docx import Document
from app.models import Applicant, Skill, Qualification, ResumeAnalysis

COMMON_SKILL_KEYWORDS = [
    'PHP', 'JavaScript', 'Python', 'Java', 'C++', 'C#', 'SQL', 'MySQL', 'HTML', 'CSS',
    'TypeScript', 'Ruby', 'Go', 'Rust', 'Swift', 'Kotlin', 'React', 'Angular', 'Vue',
    'Node', 'Laravel', 'Django', 'Spring', 'Express', 'jQuery', 'Bootstrap', 'Tailwind',
    'Git', 'GitHub', 'Docker', 'Kubernetes', 'AWS', 'Azure', 'Linux', 'PostgreSQL',
    'MongoDB', 'Redis', 'Communication', 'Teamwork', 'Problem Solving', 'Leadership',
    'Project Management', 'Time Management', 'Customer Service', 'Sales', 'Marketing',
    'Accounting', 'Financial Analysis', 'Budgeting', 'Auditing', 'Excel', 'QuickBooks',
    'Graphic Design', 'Photoshop', 'Illustrator', 'Figma', 'UI/UX', 'SEO', 'Data Analysis'
]

EDUCATION_LEVELS = [
    ('Doctorate', ['phd', 'doctorate', 'doctor of']),
    ('Master', ['master', 'mba', 'msc', 'ma', 'master of']),
    ('Bachelor', ['bachelor', 'bs', 'ba', 'bba', 'bsc', 'undergraduate', 'college graduate', 'degree', 'bachelor of']),
    ('Associate', ['associate', 'diploma', 'vocational', 'associate in']),
    ('High School', ['high school', 'secondary', 'senior high school', 'shs']),
]

# Non-resume negative indicator patterns
DISQUALIFIER_PATTERNS = {
    'invoice': [
        r'\b(?:tax\s+)?invoice\s*(?:no\.?|#|number)?\b',
        r'\bbill\s+to\b',
        r'\bship\s+to\b',
        r'\b(?:subtotal|amount\s+due|total\s+due|balance\s+due)\b',
        r'\b(?:unit\s+price|qty\b|remit\s+to|payment\s+terms)\b',
        r'\b(?:official\s+receipt|cash\s+receipt|receipt\s*#)\b',
        r'\b(?:purchase\s+order|p\.o\.\s*#|statement\s+of\s+account)\b',
        r'\bbilling\s+address\b',
    ],
    'legal': [
        r'\bplaintiff\b',
        r'\bdefendant\b',
        r'\bin\s+the\s+(?:regional|municipal|supreme|district)\s+trial\s+court\b',
        r'\baffidavit\s+of\b',
        r'\bwhereas,\b',
        r'\bin\s+witness\s+whereof\b',
        r'\bpower\s+of\s+attorney\b',
        r'\bnon-disclosure\s+agreement\b',
        r'\bterms\s+and\s+conditions\s+of\s+service\b',
    ],
    'essay': [
        r'\btable\s+of\s+contents\b',
        r'\babstract\b',
        r'\bchapter\s+[1-9]\b',
        r'\bliterature\s+review\b',
        r'\bmethodology\b',
        r'\breferences\s+cited\b',
        r'\bworks\s+cited\b',
        r'\bbibliography\b',
        r'\bdissertation\s+submitted\b',
        r'\bthesis\s+submitted\b',
    ],
    'medical': [
        r'\bpatient\s+name\b',
        r'\bprescribing\s+physician\b',
        r'\blaboratory\s+results\b',
        r'\bclinical\s+diagnosis\b',
        r'\bdosage\s+instructions\b',
        r'\bspecimen\s+type\b',
    ]
}

# Positive Resume Structural Patterns
RESUME_SECTION_PATTERNS = [
    ('experience', r'(?i)\b(work\s+experience|professional\s+experience|employment\s+history|work\s+history|career\s+history|experience|positions?\s+held|internships?|job\s+experience|relevant\s+experience)\b'),
    ('education', r'(?i)\b(education|academic\s+background|educational\s+background|academic\s+history|qualifications|academic\s+attainment|educational\s+attainment|degrees?)\b'),
    ('skills', r'(?i)\b(skills|technical\s+skills|core\s+competencies|key\s+skills|areas\s+of\s+expertise|proficiencies|technologies|tools\s+and\s+technologies|hard\s+skills|soft\s+skills)\b'),
    ('summary', r'(?i)\b(professional\s+summary|career\s+objective|summary\s+of\s+qualifications|professional\s+profile|executive\s+summary|about\s+me|personal\s+statement|career\s+summary|objective)\b'),
    ('certifications', r'(?i)\b(certifications?|certificates?|projects?|key\s+projects?|achievements?|awards?|honors?|seminars?|trainings?|licenses?|affiliations?|references?)\b'),
]

ACTION_VERBS = [
    'developed', 'engineered', 'architected', 'designed', 'implemented', 'managed',
    'led', 'created', 'optimized', 'spearheaded', 'deployed', 'built', 'maintained',
    'collaborated', 'scaled', 'improved', 'delivered', 'reduced', 'increased',
    'analyzed', 'automated', 'integrated', 'streamlined', 'resolved', 'assisted',
    'handled', 'organized', 'facilitated', 'prepared', 'executed', 'conducted'
]

COMMON_JOB_ROLES = [
    'engineer', 'developer', 'manager', 'specialist', 'assistant', 'coordinator',
    'administrator', 'analyst', 'officer', 'lead', 'designer', 'consultant',
    'director', 'accountant', 'executive', 'representative', 'supervisor', 'associate',
    'technician', 'intern', 'nurse', 'clerk', 'cashier', 'programmer', 'teacher'
]


def extract_text_from_pdf(file_path: str) -> str:
    """Extracts plain text from a PDF file using pypdf."""
    if not os.path.exists(file_path):
        return ""
    try:
        reader = PdfReader(file_path)
        text_pages = []
        for page in reader.pages:
            t = page.extract_text()
            if t:
                text_pages.append(t)
        return "\n".join(text_pages)
    except Exception as e:
        print(f"Error reading PDF {file_path}: {e}")
        return ""


def extract_text_from_docx(file_path: str) -> str:
    """Extract paragraph text from a DOCX file."""
    if not os.path.exists(file_path):
        return ""
    try:
        document = Document(file_path)
        runs = [paragraph.text for paragraph in document.paragraphs if paragraph.text]
        for table in document.tables:
            for row in table.rows:
                for cell in row.cells:
                    if cell.text.strip():
                        runs.append(cell.text.strip())
        return "\n".join(runs).strip()
    except Exception as e:
        print(f"Error reading DOCX {file_path}: {e}")
        return ""


def extract_document_text(file_path: str) -> str:
    """Extracts plain text from PDF or DOCX file."""
    ext = os.path.splitext(file_path)[1].lower()
    if ext == '.pdf':
        return extract_text_from_pdf(file_path)
    elif ext in {'.docx', '.doc'}:
        return extract_text_from_docx(file_path)
    # Fallback try pdf then docx
    txt = extract_text_from_pdf(file_path)
    if not txt:
        txt = extract_text_from_docx(file_path)
    return txt


def verify_resume_document(file_path: str) -> dict:
    """
    AI-Powered Resume Document Verification Engine.
    Inspects document text, structure, sections, contact patterns, and non-resume disqualifiers.
    Rejects any non-resume document (receipts, invoices, legal contracts, essays, arbitrary files).
    
    Returns:
        dict: {
            'is_valid': bool,
            'confidence_score': float (0-100),
            'rejection_reason': str or None,
            'document_type': str,
            'detected_sections': list[str],
            'detected_skills': list[str],
            'extracted_text': str
        }
    """
    text = extract_document_text(file_path)
    clean_text = (text or '').strip()
    words = clean_text.split()
    word_count = len(words)

    # 1. Check for empty or unreadable / extremely short document
    if word_count < 30 or len(clean_text) < 120:
        return {
            'is_valid': False,
            'confidence_score': 0.0,
            'rejection_reason': (
                "AI Verification Failed: The uploaded document contains insufficient or unreadable text "
                f"({word_count} words detected). Please upload a valid text-based Resume or CV (PDF or DOCX)."
            ),
            'document_type': 'Insufficient Content / Scanned Image',
            'detected_sections': [],
            'detected_skills': [],
            'extracted_text': clean_text
        }

    lower_text = clean_text.lower()

    # 2. Check for Non-Resume Disqualifiers (Negative Indicators)
    # 2a. Invoice / Receipt detection
    invoice_hits = sum(1 for pat in DISQUALIFIER_PATTERNS['invoice'] if re.search(pat, lower_text))
    if invoice_hits >= 2:
        return {
            'is_valid': False,
            'confidence_score': 5.0,
            'rejection_reason': (
                "AI Verification Failed: The uploaded document appears to be a financial invoice or receipt, "
                "not a Resume/CV. Please upload your official Resume or CV."
            ),
            'document_type': 'Invoice / Billing Document',
            'detected_sections': [],
            'detected_skills': [],
            'extracted_text': clean_text
        }

    # 2b. Legal Agreement / Court Document detection
    legal_hits = sum(1 for pat in DISQUALIFIER_PATTERNS['legal'] if re.search(pat, lower_text))
    if legal_hits >= 2:
        return {
            'is_valid': False,
            'confidence_score': 5.0,
            'rejection_reason': (
                "AI Verification Failed: The uploaded document appears to be a legal contract or court document, "
                "not a Resume/CV. Please upload your official Resume or CV."
            ),
            'document_type': 'Legal Document',
            'detected_sections': [],
            'detected_skills': [],
            'extracted_text': clean_text
        }

    # 2c. Medical Document detection
    medical_hits = sum(1 for pat in DISQUALIFIER_PATTERNS['medical'] if re.search(pat, lower_text))
    if medical_hits >= 2:
        return {
            'is_valid': False,
            'confidence_score': 5.0,
            'rejection_reason': (
                "AI Verification Failed: The uploaded document appears to be a medical or laboratory record, "
                "not a Resume/CV. Please upload your official Resume or CV."
            ),
            'document_type': 'Medical Document',
            'detected_sections': [],
            'detected_skills': [],
            'extracted_text': clean_text
        }

    # 2d. Academic Paper / Essay detection
    essay_hits = sum(1 for pat in DISQUALIFIER_PATTERNS['essay'] if re.search(pat, lower_text))
    has_contact_indicator = bool(
        re.search(r'\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b', clean_text) or
        re.search(r'(?:\+?\d{1,3}[-.\s]?)?\(?\d{2,4}\)?[-.\s]?\d{3,4}[-.\s]?\d{3,4}', clean_text)
    )
    if essay_hits >= 2 and not has_contact_indicator:
        return {
            'is_valid': False,
            'confidence_score': 10.0,
            'rejection_reason': (
                "AI Verification Failed: The uploaded document appears to be an academic essay or research paper, "
                "not a Resume/CV. Please upload your official Resume or CV."
            ),
            'document_type': 'Academic Paper / Essay',
            'detected_sections': [],
            'detected_skills': [],
            'extracted_text': clean_text
        }

    # 3. Detect Positive Resume Structural Features
    detected_sections = []
    for sec_name, pattern in RESUME_SECTION_PATTERNS:
        if re.search(pattern, clean_text):
            detected_sections.append(sec_name)

    # Education mentions
    has_education_degree = False
    for level, kws in EDUCATION_LEVELS:
        for kw in kws:
            if re.search(r'\b' + re.escape(kw) + r'\b', lower_text):
                has_education_degree = True
                if 'education' not in detected_sections:
                    detected_sections.append('education')
                break
        if has_education_degree:
            break

    # Skill extraction from database and taxonomy
    db_skills = list(Skill.objects.filter(status='active').values_list('skill_name', flat=True)) if Skill.objects.exists() else []
    skill_map = {}
    for sk in (db_skills + COMMON_SKILL_KEYWORDS):
        s_clean = sk.strip()
        if not s_clean:
            continue
        s_low = s_clean.lower()
        if s_low not in skill_map or any(c.isupper() for c in s_clean):
            skill_map[s_low] = s_clean

    found_skills = set()
    for s_low, s_canon in skill_map.items():
        pat = r'(?:\b|\W)' + re.escape(s_low) + r'(?:\b|\W)'
        if re.search(pat, lower_text):
            found_skills.add(s_canon)

    if len(found_skills) >= 2 and 'skills' not in detected_sections:
        detected_sections.append('skills')

    # Date ranges / timeline check (e.g. 2018 - 2023, 2021 - Present)
    year_ranges = re.findall(r'\b(20[012]\d|19[89]\d)\s*[-–—to]+\s*(20[012]\d|present|current)\b', lower_text)
    has_timeline = len(year_ranges) > 0

    # Job roles and Action verbs check
    verbs_found = [v for v in ACTION_VERBS if re.search(r'\b' + v + r'\b', lower_text)]
    roles_found = [r for r in COMMON_JOB_ROLES if re.search(r'\b' + r + r'\b', lower_text)]

    # 4. AI Confidence Score Calculation
    score = 0.0
    if has_contact_indicator:
        score += 20.0
    
    # Section presence (up to 40 pts)
    core_section_set = {'experience', 'education', 'skills', 'summary', 'certifications'}
    matching_core_sections = set(detected_sections).intersection(core_section_set)
    score += min(len(matching_core_sections) * 12.0, 40.0)

    # Skills presence (up to 20 pts)
    score += min(len(found_skills) * 3.0, 20.0)

    # Timeline and roles (up to 20 pts)
    if has_timeline:
        score += 10.0
    if len(roles_found) > 0:
        score += 5.0
    if len(verbs_found) > 0:
        score += 5.0

    confidence_score = round(min(score, 100.0), 1)

    # 5. Core Decision Rule:
    # A valid resume must have at least 2 distinct core pillars (e.g. Experience/Education/Skills/Summary)
    # OR at least 2 detected skills along with contact info or timeline, AND have confidence >= 40.0%
    is_valid = (
        (len(matching_core_sections) >= 2 or (len(found_skills) >= 2 and (has_contact_indicator or has_timeline)))
        and confidence_score >= 40.0
    )

    if not is_valid:
        missing_parts = []
        if 'experience' not in detected_sections:
            missing_parts.append('Work Experience')
        if 'education' not in detected_sections:
            missing_parts.append('Education')
        if 'skills' not in detected_sections:
            missing_parts.append('Skills')

        missing_text = f" (missing {', '.join(missing_parts)})" if missing_parts else ""
        return {
            'is_valid': False,
            'confidence_score': confidence_score,
            'rejection_reason': (
                f"AI Verification Failed: The uploaded document is not recognized as a valid Resume or CV{missing_text}. "
                "Please upload a standard document containing your experience, education, and professional skills."
            ),
            'document_type': 'Unrecognized Non-Resume Document',
            'detected_sections': detected_sections,
            'detected_skills': sorted(list(found_skills)),
            'extracted_text': clean_text
        }

    return {
        'is_valid': True,
        'confidence_score': confidence_score,
        'rejection_reason': None,
        'document_type': 'Resume / Curriculum Vitae',
        'detected_sections': detected_sections,
        'detected_skills': sorted(list(found_skills)),
        'extracted_text': clean_text
    }


def parse_resume_text(text: str) -> dict:
    """
    Analyzes resume text for skills, qualifications, education, and experience.
    """
    text_lower = text.lower()
    
    # 1. Extract Skills
    db_skills = list(Skill.objects.filter(status='active').values_list('skill_name', flat=True)) if Skill.objects.exists() else []
    skill_map = {}
    for sk in (db_skills + COMMON_SKILL_KEYWORDS):
        s_clean = sk.strip()
        if not s_clean:
            continue
        s_low = s_clean.lower()
        if s_low not in skill_map or any(c.isupper() for c in s_clean):
            skill_map[s_low] = s_clean

    found_skills = set()
    for s_low, s_canon in skill_map.items():
        pat = r'(?:\b|\W)' + re.escape(s_low) + r'(?:\b|\W)'
        if re.search(pat, text_lower):
            found_skills.add(s_canon)
            
    # 2. Extract Education Level
    detected_education = 'High School'
    for level, keywords in EDUCATION_LEVELS:
        for kw in keywords:
            if re.search(r'\b' + re.escape(kw) + r'\b', text_lower):
                detected_education = level
                break
        if detected_education != 'High School':
            break

    # 3. Extract Experience Years
    detected_exp = 0
    exp_matches = re.findall(r'(\d+)\+?\s*(?:years?|yrs?)(?:\s+of)?\s+experience', text_lower)
    if exp_matches:
        try:
            detected_exp = max([int(m) for m in exp_matches if int(m) < 40])
        except Exception:
            detected_exp = 0
    else:
        # Year ranges e.g. 2018 - 2023
        year_ranges = re.findall(r'(20\d\d)\s*[-–to]+\s*(20\d\d|present|current)', text_lower)
        total_range = 0
        for start_yr, end_yr in year_ranges:
            try:
                s = int(start_yr)
                e = 2026 if end_yr in ['present', 'current'] else int(end_yr)
                if e >= s:
                    total_range += (e - s)
            except Exception:
                pass
        if total_range > 0:
            detected_exp = min(total_range, 30)

    # 4. Extract Qualifications
    db_quals = Qualification.objects.filter(status='active') if Qualification.objects.exists() else []
    found_qual = None
    for qual in db_quals:
        if qual.name.lower() in text_lower:
            found_qual = qual.name
            break

    # 5. Compute Employability Score
    # Base: 30 pts + skills (up to 30 pts) + education (up to 20 pts) + exp (up to 20 pts)
    score = 30.0
    score += min(len(found_skills) * 3.0, 30.0)
    
    edu_scores = {'Doctorate': 20.0, 'Master': 18.0, 'Bachelor': 15.0, 'Associate': 10.0, 'High School': 5.0}
    score += edu_scores.get(detected_education, 5.0)
    score += min(detected_exp * 2.5, 20.0)
    employability_score = min(round(score, 2), 100.0)

    return {
        'text': text,
        'skills': sorted(list(found_skills)),
        'skills_str': ', '.join(sorted(list(found_skills))),
        'education_level': detected_education,
        'experience_years': detected_exp,
        'qualification': found_qual or '',
        'employability_score': employability_score,
    }


def parse_and_save_applicant_resume(applicant: Applicant, file_path: str) -> dict:
    """
    Parses an uploaded resume, saves resume analysis record, and updates Applicant profile.
    """
    text = extract_document_text(file_path)
    if not text:
        return {}
        
    data = parse_resume_text(text)
    
    # Save to ResumeAnalysis
    ResumeAnalysis.objects.create(
        applicant=applicant,
        resume_file=file_path,
        extracted_text=text[:5000],
        skills_extracted=data['skills_str'],
        education_extracted=data['education_level'],
        experience_extracted=f"{data['experience_years']} years",
        qualifications_extracted=data['qualification']
    )
    
    # Update Applicant
    if data['skills_str']:
        applicant.skills = data['skills_str']
    if data['education_level']:
        applicant.education_level = data['education_level']
    if data['experience_years'] > 0:
        applicant.experience_years = data['experience_years']
    if data['qualification'] and not applicant.qualifications:
        applicant.qualifications = data['qualification']
    if data['employability_score'] > 0:
        applicant.employability_score = data['employability_score']
        
    applicant.profile_completed = True
    applicant.save()
    
    return data
