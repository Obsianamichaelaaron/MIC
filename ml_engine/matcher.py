import math
import re

# ==============================================================================
# 1. DIRECT SYNONYMS (Exact Functional Equivalence - Weight: 1.0)
# ==============================================================================
SKILL_SYNONYMS = {
    # Databases
    'postgresql': {'postgres', 'postgresql', 'pgsql', 'psql', 'pg sql'},
    'postgres': {'postgres', 'postgresql', 'pgsql', 'psql', 'pg sql'},
    'mysql': {'mysql', 'my sql', 'mariadb'},
    'sqlite': {'sqlite', 'sqlite3'},
    'sql': {'sql', 'structured query language', 'database queries', 'rdbms', 'relational database'},
    'mongodb': {'mongodb', 'mongo', 'nosql', 'document database'},
    'redis': {'redis', 'in-memory cache', 'caching', 'redis cache'},

    # Frontend & Web
    'javascript': {'javascript', 'js', 'es6', 'es2015', 'ecmascript', 'vanilla js'},
    'js': {'javascript', 'js', 'es6', 'ecmascript'},
    'typescript': {'typescript', 'ts'},
    'ts': {'typescript', 'ts'},
    'react': {'react', 'react.js', 'reactjs', 'react js', 'react native'},
    'react.js': {'react', 'react.js', 'reactjs', 'react js'},
    'reactjs': {'react', 'react.js', 'reactjs', 'react js'},
    'next.js': {'next', 'next.js', 'nextjs', 'next js', 'react framework'},
    'vue': {'vue', 'vue.js', 'vuejs', 'vue js', 'vue3', 'vue2'},
    'vue.js': {'vue', 'vue.js', 'vuejs', 'vue js'},
    'angular': {'angular', 'angular.js', 'angularjs', 'angular 2+', 'angular io'},
    'svelte': {'svelte', 'sveltekit', 'svelte js'},
    'html': {'html', 'html5', 'html 5', 'semantic html'},
    'css': {'css', 'css3', 'css 3', 'scss', 'sass', 'css styling'},
    'tailwind': {'tailwind', 'tailwindcss', 'tailwind css'},
    'bootstrap': {'bootstrap', 'bootstrap 5', 'bootstrap 4', 'twitter bootstrap'},

    # Backend & Programming
    'python': {'python', 'py', 'python3', 'python 3'},
    'django': {'django', 'django framework', 'python django', 'django rest framework', 'drf'},
    'flask': {'flask', 'python flask', 'flask framework'},
    'fastapi': {'fastapi', 'fast api', 'python fastapi'},
    'node': {'node', 'node.js', 'nodejs', 'node js'},
    'node.js': {'node', 'node.js', 'nodejs', 'node js'},
    'nodejs': {'node', 'node.js', 'nodejs', 'node js'},
    'express': {'express', 'express.js', 'expressjs', 'express js'},
    'php': {'php', 'php8', 'php7', 'modern php'},
    'laravel': {'laravel', 'laravel framework', 'php laravel'},
    'java': {'java', 'core java', 'java 8', 'java 11', 'java 17', 'java 21'},
    'spring': {'spring', 'spring boot', 'springboot', 'spring framework'},
    'c#': {'c#', 'c-sharp', 'csharp', '.net', 'dotnet', 'asp.net'},
    'c++': {'c++', 'cpp', 'c plus plus'},
    'go': {'go', 'golang', 'go language'},
    'ruby': {'ruby', 'ruby on rails', 'rails'},

    # APIs, Cloud, DevOps
    'rest api': {'rest', 'rest api', 'restful', 'restful api', 'restful apis', 'rest apis', 'web apis', 'api development'},
    'rest': {'rest', 'rest api', 'restful', 'restful api', 'apis', 'web apis'},
    'graphql': {'graphql', 'graph ql', 'apollo graphql'},
    'docker': {'docker', 'docker compose', 'dockerfile', 'containerization', 'containers'},
    'kubernetes': {'kubernetes', 'k8s', 'container orchestration'},
    'aws': {'aws', 'amazon web services', 'amazon aws', 'aws cloud'},
    'gcp': {'gcp', 'google cloud', 'google cloud platform'},
    'azure': {'azure', 'microsoft azure', 'azure cloud'},
    'git': {'git', 'github', 'gitlab', 'bitbucket', 'version control', 'git workflow'},
    'ci/cd': {'ci/cd', 'cicd', 'continuous integration', 'continuous deployment', 'devops pipeline'},

    # UI/UX & Design
    'ui/ux': {'ui/ux', 'ui/ux design', 'ui design', 'ux design', 'ui', 'ux', 'user experience', 'user interface', 'product design'},
    'ui/ux design': {'ui/ux', 'ui/ux design', 'ui design', 'ux design', 'ui', 'ux', 'user experience', 'user interface', 'product design'},
    'figma': {'figma', 'figma design', 'figma prototyping'},
    'graphic design': {'graphic design', 'graphics design', 'visual design', 'visual content creation', 'graphics', 'digital graphics'},

    # Business, Office, Data & Customer Care
    'customer service': {'customer service', 'client relations', 'customer support', 'customer care', 'client support', 'help desk', 'customer assistance', 'guest relations'},
    'client relations': {'client relations', 'customer service', 'account management', 'client management', 'customer relations', 'client support'},
    'data entry': {'data entry', 'data processing', 'records management', 'clerical', 'administrative data processing', 'typing'},
    'administrative': {'administrative', 'office administration', 'administrative support', 'clerical', 'office support', 'general administration'},
    'microsoft excel': {'microsoft excel', 'ms excel', 'excel', 'spreadsheets', 'google sheets', 'spreadsheet analysis'},
    'excel': {'microsoft excel', 'ms excel', 'excel', 'spreadsheets', 'google sheets'},
    'data analysis': {'data analysis', 'data analytics', 'data processing', 'business intelligence', 'reporting analysis'},
    'bookkeeping': {'bookkeeping', 'accounting', 'accounts payable', 'accounts receivable', 'financial record keeping'},
    'construction': {'construction', 'general construction', 'building construction', 'site labor', 'structural work'},
}

# ==============================================================================
# 2. TRANSFERABLE & CROSS-DOMAIN SKILL AFFINITIES (Semantic Relevance: 0.75 - 0.90)
# ==============================================================================
TRANSFERABLE_SKILL_MAP = {
    # Web & Frontend Development
    'javascript': {'react', 'vue', 'angular', 'svelte', 'typescript', 'frontend', 'web development', 'node.js', 'html', 'css', 'ui/ux'},
    'typescript': {'javascript', 'react', 'angular', 'node.js', 'frontend', 'backend', 'full stack'},
    'react': {'javascript', 'typescript', 'next.js', 'frontend', 'web development', 'redux', 'ui/ux', 'html', 'css'},
    'vue': {'javascript', 'typescript', 'nuxt.js', 'frontend', 'web development', 'html', 'css'},
    'angular': {'typescript', 'javascript', 'frontend', 'web development', 'html', 'css'},
    'html': {'css', 'javascript', 'web development', 'web design', 'frontend', 'responsive design', 'ui/ux'},
    'css': {'html', 'bootstrap', 'tailwind', 'sass', 'responsive design', 'web design', 'frontend', 'ui/ux'},
    'tailwind': {'css', 'html', 'bootstrap', 'responsive design', 'frontend', 'web design'},
    'bootstrap': {'css', 'html', 'tailwind', 'responsive design', 'frontend', 'web design'},
    'web development': {'html', 'css', 'javascript', 'python', 'php', 'django', 'react', 'sql', 'frontend', 'backend'},
    'frontend': {'html', 'css', 'javascript', 'typescript', 'react', 'vue', 'angular', 'ui/ux', 'web development'},

    # Backend, Databases & APIs
    'python': {'django', 'flask', 'fastapi', 'backend', 'data analysis', 'sql', 'rest api', 'machine learning', 'web development'},
    'django': {'python', 'backend', 'rest api', 'sql', 'postgresql', 'web development', 'full stack'},
    'flask': {'python', 'backend', 'rest api', 'sql', 'microservices', 'web development'},
    'fastapi': {'python', 'rest api', 'backend', 'microservices', 'asyncio', 'sql'},
    'java': {'spring', 'backend', 'oop', 'c#', 'microservices', 'sql', 'rest api'},
    'spring': {'java', 'backend', 'microservices', 'rest api', 'sql'},
    'c#': {'.net', 'asp.net', 'java', 'backend', 'c++', 'oop', 'sql'},
    'php': {'laravel', 'codeigniter', 'wordpress', 'web development', 'backend', 'mysql', 'sql'},
    'laravel': {'php', 'backend', 'web development', 'mysql', 'sql', 'rest api'},
    'node.js': {'javascript', 'typescript', 'express', 'backend', 'full stack', 'rest api', 'mongodb'},
    'sql': {'mysql', 'postgresql', 'sqlite', 'database', 'data analysis', 'backend', 'data processing'},
    'postgresql': {'sql', 'database', 'mysql', 'backend', 'django', 'python'},
    'mysql': {'sql', 'database', 'postgresql', 'backend', 'php', 'laravel'},
    'rest api': {'backend', 'web development', 'python', 'javascript', 'node.js', 'django', 'flask', 'fastapi', 'microservices'},
    'backend': {'python', 'java', 'node.js', 'php', 'c#', 'django', 'sql', 'rest api', 'web development'},

    # Data Science & Analytics
    'excel': {'microsoft excel', 'data analysis', 'data entry', 'reporting', 'spreadsheets', 'data processing', 'accounting'},
    'microsoft excel': {'excel', 'data analysis', 'data entry', 'reporting', 'spreadsheets', 'data processing', 'accounting'},
    'data analysis': {'excel', 'sql', 'power bi', 'tableau', 'python', 'pandas', 'reporting', 'statistics', 'data entry'},
    'power bi': {'data analysis', 'tableau', 'excel', 'sql', 'reporting', 'business intelligence'},
    'tableau': {'data analysis', 'power bi', 'excel', 'sql', 'reporting', 'business intelligence'},
    'data entry': {'administrative', 'data processing', 'records management', 'office administration', 'excel', 'clerical', 'typing'},
    'data processing': {'data entry', 'data analysis', 'excel', 'administrative', 'records management'},

    # Design & Creative
    'graphic design': {'visual content creation', 'photoshop', 'illustrator', 'canva', 'figma', 'ui/ux', 'branding', 'visual design'},
    'visual content creation': {'graphic design', 'visual design', 'photoshop', 'canva', 'digital marketing', 'content writing'},
    'ui/ux': {'figma', 'adobe xd', 'graphic design', 'web design', 'frontend', 'prototyping', 'wireframing', 'user research'},
    'figma': {'ui/ux', 'prototyping', 'wireframing', 'graphic design', 'web design', 'adobe xd'},
    'photoshop': {'graphic design', 'illustrator', 'visual content creation', 'digital media', 'image editing'},

    # Business, Customer Service & Office Administration
    'customer service': {'client relations', 'customer support', 'communication', 'problem solving', 'crm', 'client management', 'interpersonal skills', 'call center'},
    'client relations': {'customer service', 'account management', 'client management', 'communication', 'business development'},
    'customer support': {'customer service', 'client relations', 'help desk', 'troubleshooting', 'communication'},
    'administrative': {'data entry', 'office administration', 'clerical', 'scheduling', 'records management', 'document filing', 'coordination'},
    'office administration': {'administrative', 'data entry', 'office management', 'clerical', 'scheduling', 'coordination'},
    'project management': {'agile', 'scrum', 'jira', 'leadership', 'operations management', 'coordination', 'planning', 'time management'},
    'sales': {'marketing', 'business development', 'client relations', 'customer service', 'negotiation', 'lead generation'},
    'marketing': {'digital marketing', 'social media marketing', 'content writing', 'sales', 'seo', 'branding', 'graphic design'},
    'digital marketing': {'marketing', 'social media marketing', 'seo', 'content writing', 'copywriting', 'visual content creation'},
    'accounting': {'bookkeeping', 'financial analysis', 'payroll', 'invoicing', 'quickbooks', 'excel', 'financial reporting'},
    'bookkeeping': {'accounting', 'invoicing', 'payroll', 'quickbooks', 'excel', 'data entry'},

    # Construction, Trades & Engineering
    'construction': {'tool handling', 'blueprint reading', 'carpentry', 'building maintenance', 'general labor', 'safety compliance', 'fabrication'},
    'tool handling': {'construction', 'equipment maintenance', 'safety procedures', 'manual labor', 'mechanical skills', 'carpentry'},
    'blueprint reading': {'construction', 'drafting', 'autocad', 'technical drawing', 'schematics', 'engineering'},
    'building maintenance': {'construction', 'facilities maintenance', 'tool handling', 'repairs', 'plumbing', 'electrical'},
    'general labor': {'construction', 'tool handling', 'maintenance', 'manual labor', 'warehouse operations'},
}

# Broader domain clusters to compute background affinity
DOMAIN_CLUSTERS = {
    'software_engineering': {'python', 'django', 'flask', 'fastapi', 'javascript', 'typescript', 'react', 'vue', 'angular', 'node.js', 'java', 'spring', 'c#', 'php', 'laravel', 'sql', 'postgresql', 'mysql', 'docker', 'git', 'rest api', 'web development', 'frontend', 'backend', 'full stack', 'html', 'css'},
    'data_analytics': {'data analysis', 'data analytics', 'sql', 'excel', 'microsoft excel', 'power bi', 'tableau', 'python', 'pandas', 'statistics', 'reporting', 'data entry', 'data processing'},
    'creative_design': {'graphic design', 'ui/ux', 'ui/ux design', 'figma', 'photoshop', 'illustrator', 'visual content creation', 'visual design', 'branding', 'wireframing', 'web design'},
    'customer_business': {'customer service', 'client relations', 'customer support', 'sales', 'marketing', 'digital marketing', 'social media marketing', 'communication', 'account management'},
    'admin_operations': {'data entry', 'administrative', 'office administration', 'records management', 'data processing', 'scheduling', 'clerical', 'project management', 'operations management'},
    'finance_accounting': {'accounting', 'bookkeeping', 'financial analysis', 'payroll', 'invoicing', 'excel', 'microsoft excel', 'quickbooks'},
    'construction_trades': {'construction', 'tool handling', 'blueprint reading', 'building maintenance', 'general labor', 'safety compliance', 'carpentry', 'electrical', 'plumbing'},
}


def clean_skill(name):
    """Normalizes skill strings for semantic and dictionary matching."""
    if not name:
        return ""
    s = str(name).lower().strip()
    s = re.sub(r'[\(\)\[\]\{\}]', '', s)
    s = re.sub(r'[\s_/-]+', ' ', s).strip()
    return s


def extract_stem_keywords(text):
    """Tokenizes and cleans text to capture root keywords (NLP tokenization)."""
    words = re.findall(r'[a-zA-Z0-9#+]+', (text or '').lower())
    stop_words = {'and', 'or', 'the', 'in', 'on', 'at', 'to', 'for', 'with', 'a', 'an', 'of', 'by', 'as', 'is'}
    return {w for w in words if w not in stop_words and len(w) >= 2}


def get_applicant_all_skills(applicant, job_posting=None, resume_text=None):
    """
    Gathers all candidate skills and background data from:
    1. Profile skills list
    2. Profile qualifications & background text
    3. Stored or passed resume text
    4. Direct resume document parsing (PDF / DOCX)
    """
    skills = set()
    text_corpus = resume_text or getattr(applicant, 'parsed_resume_text', '') or ''

    # 1. Profile skills
    if hasattr(applicant, 'get_skills_list'):
        skills.update(clean_skill(s) for s in applicant.get_skills_list() if s)
    elif hasattr(applicant, 'skills') and applicant.skills:
        skills.update(clean_skill(s) for s in applicant.skills.split(',') if s.strip())

    # 2. Profile qualifications
    quals = getattr(applicant, 'qualifications', '') or ''
    if quals:
        for q in re.split(r'[,;\n]+', quals):
            if q.strip():
                skills.add(clean_skill(q.strip()))

    # 3. If no text corpus yet but applicant has a resume_file, parse it
    if not text_corpus and getattr(applicant, 'resume_file', None):
        try:
            import os
            from ml_engine.parser import parse_resume_document
            rf = applicant.resume_file
            file_path = None
            if hasattr(rf, 'path') and os.path.exists(rf.path):
                file_path = rf.path
            elif hasattr(rf, 'name') and rf.name:
                from django.conf import settings
                full_p = os.path.join(settings.MEDIA_ROOT, rf.name)
                if os.path.exists(full_p):
                    file_path = full_p
                elif os.path.exists(rf.name):
                    file_path = rf.name

            if file_path:
                parsed = parse_resume_document(file_path=file_path)
            else:
                parsed = parse_resume_document(file_obj=rf)

            text_corpus = parsed.get('text', '')
            if parsed.get('skills'):
                skills.update(clean_skill(s) for s in parsed['skills'])
            if parsed.get('experience_years') and not getattr(applicant, 'experience_years', 0):
                setattr(applicant, 'experience_years', parsed['experience_years'])
            if parsed.get('education') and not getattr(applicant, 'education_level', ''):
                setattr(applicant, 'education_level', parsed['education'])
        except Exception:
            pass

    # 4. Extract skills from text corpus if present
    if text_corpus:
        from ml_engine.parser import extract_skills
        job_skills = []
        if job_posting:
            js_raw = getattr(job_posting, 'skills_required', '') or ''
            job_skills = [s.strip() for s in re.split(r'[,;\n]+', js_raw) if s.strip()]
        extracted = extract_skills(text_corpus, custom_skill_names=job_skills)
        skills.update(clean_skill(s) for s in extracted if s)

    return skills, text_corpus


def calculate_skill_relevance(job_skill, applicant_skills_set, text_corpus=''):
    """
    Computes semantic relevance score (0.0 to 1.0) and relationship type for a job requirement.
    Uses:
    1. Exact Match (1.0)
    2. Synonym Mapping (1.0)
    3. Transferable & Related Skills (0.85)
    4. NLP Token / Substring overlap (0.80)
    5. Direct Resume Text Occurrence (0.90 - 1.0)
    6. Domain Cluster Connection (0.60)
    """
    js_clean = clean_skill(job_skill)
    if not js_clean:
        return 1.0, 'exact', ''

    # 1. Exact Match
    if js_clean in applicant_skills_set:
        return 1.0, 'exact', js_clean

    # 2. Synonym Matching
    synonyms = SKILL_SYNONYMS.get(js_clean, set())
    for syn in synonyms:
        if syn in applicant_skills_set:
            return 1.0, 'synonym', syn

    for cand_skill in applicant_skills_set:
        cand_syns = SKILL_SYNONYMS.get(cand_skill, set())
        if js_clean in cand_syns or any(s in js_clean for s in cand_syns):
            return 1.0, 'synonym', cand_skill

    # 3. Transferable / Related Skills Mapping
    transferable_targets = TRANSFERABLE_SKILL_MAP.get(js_clean, set())
    for trans in transferable_targets:
        if trans in applicant_skills_set:
            return 0.85, 'transferable', trans

    for cand_skill in applicant_skills_set:
        cand_transferable = TRANSFERABLE_SKILL_MAP.get(cand_skill, set())
        if js_clean in cand_transferable or any(t in js_clean for t in cand_transferable):
            return 0.85, 'transferable', cand_skill

    # 4. NLP Token & Substring Overlap
    js_tokens = extract_stem_keywords(js_clean)
    for cand_skill in applicant_skills_set:
        cand_tokens = extract_stem_keywords(cand_skill)
        common_tokens = js_tokens.intersection(cand_tokens)
        if common_tokens:
            token_ratio = len(common_tokens) / max(len(js_tokens), 1)
            if token_ratio >= 0.5:
                return 0.80, 'semantic', cand_skill

        # Substring containment
        if len(cand_skill) >= 3 and len(js_clean) >= 3:
            if cand_skill in js_clean or js_clean in cand_skill:
                return 0.85, 'semantic', cand_skill

    # 5. Direct Resume Text Corpus Search
    if text_corpus:
        lower_corpus = text_corpus.lower()
        pattern = rf'(?<![a-zA-Z0-9]){re.escape(js_clean)}(?![a-zA-Z0-9])'
        if re.search(pattern, lower_corpus):
            return 0.95, 'in_resume', js_clean
        for syn in synonyms:
            syn_pat = rf'(?<![a-zA-Z0-9]){re.escape(syn)}(?![a-zA-Z0-9])'
            if re.search(syn_pat, lower_corpus):
                return 0.90, 'in_resume', syn
        for trans in transferable_targets:
            trans_pat = rf'(?<![a-zA-Z0-9]){re.escape(trans)}(?![a-zA-Z0-9])'
            if re.search(trans_pat, lower_corpus):
                return 0.80, 'transferable_in_resume', trans

    # 6. Domain Cluster Connection
    for cluster_name, cluster_skills in DOMAIN_CLUSTERS.items():
        if js_clean in cluster_skills:
            matching_in_cluster = [cs for cs in applicant_skills_set if cs in cluster_skills]
            if matching_in_cluster:
                return 0.60, 'domain_connected', matching_in_cluster[0]

    return 0.0, 'missing', ''


def is_skill_matched(job_skill, applicant_skills_set, text_corpus=''):
    """
    Returns True if the candidate satisfies or has transferable background for the job skill.
    Used by filters, candidate badges, and recommendation highlights.
    """
    score, match_type, _ = calculate_skill_relevance(job_skill, applicant_skills_set, text_corpus)
    return score >= 0.60


def check_critical_mandatory_blockers(applicant, job_posting, text_corpus=''):
    """
    Rule-based validation checking for explicit, non-negotiable mandatory blockers.
    Examples: explicit mandatory licenses, severe experience disparity on hard prerequisites.
    Returns (has_blocker: bool, blocker_reason: str).
    """
    job_desc = (getattr(job_posting, 'description', '') or '').lower()
    job_reqs = (getattr(job_posting, 'requirements', '') or '').lower()
    full_job_text = f"{job_desc} {job_reqs}"

    # 1. Mandatory License / Certification checks (if explicitly marked as 'must have' / 'required license')
    license_triggers = ['must have prc license', 'required cpa license', 'must be licensed', 'mandatory license']
    for lt in license_triggers:
        if lt in full_job_text:
            # Check candidate qualifications and text corpus
            quals = (getattr(applicant, 'qualifications', '') or '').lower()
            if not ('license' in quals or 'licensed' in quals or 'license' in text_corpus.lower()):
                return True, f"Missing explicitly mandatory professional license ({lt})"

    # 2. Strict Years Experience Blocker:
    # Candidate is only blocked if job strictly requires high seniority (e.g. 5+ yrs) and candidate has 0 years.
    min_exp = getattr(job_posting, 'min_experience_years', 0) or 0
    candidate_exp = getattr(applicant, 'experience_years', 0) or 0
    if min_exp >= 5 and candidate_exp == 0 and 'must have' in full_job_text and 'years' in full_job_text:
        return True, f"Senior role requires minimum {min_exp} years verified experience."

    return False, ""


def calculate_employability_score(profile):
    """Calculates overall employability score (0 - 100) for an applicant."""
    score = 45.0  # Fair base score

    # Skills factor (up to 30 pts)
    skills = profile.get_skills_list() if hasattr(profile, 'get_skills_list') else []
    score += min(30.0, len(skills) * 3.5)

    # Experience factor (up to 15 pts)
    exp = getattr(profile, 'experience_years', 0) or 0
    score += min(15.0, max(4.0, exp * 3.0))

    # Education factor (up to 10 pts)
    edu = (getattr(profile, 'education_level', '') or '').lower()
    if 'doctor' in edu or 'phd' in edu:
        score += 10.0
    elif 'master' in edu:
        score += 9.0
    elif 'bachelor' in edu or 'degree' in edu or 'college' in edu:
        score += 8.0
    elif 'associate' in edu or 'vocational' in edu or 'diploma' in edu:
        score += 6.5
    elif 'high school' in edu:
        score += 5.0
    else:
        score += 6.0

    # Profile completeness & resume presence (up to 10 pts)
    if getattr(profile, 'profile_completed', False):
        score += 5.0
    if getattr(profile, 'resume_file', None):
        score += 5.0

    return round(min(100.0, max(10.0, score)), 2)


def evaluate_applicant_qualification(applicant, job_posting, resume_text=None):
    """
    Evaluates an applicant against a job posting based on RELEVANCE and TRANSFERABLE SKILLS.
    Separates Match Score (strength of match: 0-100%) from Qualification Decision (Qualified / For Review / Not Qualified).
    """
    applicant_skills_set, text_corpus = get_applicant_all_skills(applicant, job_posting, resume_text)

    # Required skills from job posting
    job_skills_raw = getattr(job_posting, 'skills_required', '') or ''
    job_skills_list = [s.strip() for s in re.split(r'[,;\n]+', job_skills_raw) if s.strip()]

    matched_skills = []
    transferable_skills = []
    missing_skills = []
    skill_relevance_scores = []

    for js in job_skills_list:
        rel_score, rel_type, matched_term = calculate_skill_relevance(js, applicant_skills_set, text_corpus)
        skill_relevance_scores.append(rel_score)

        if rel_score >= 0.90:
            matched_skills.append(js)
        elif rel_score >= 0.60:
            transferable_skills.append(f"{js} (via {matched_term})" if matched_term and matched_term != js else js)
        else:
            missing_skills.append(js)

    # 1. Technical & Transferable Skill Score (up to 65 points)
    if job_skills_list:
        avg_skill_relevance = sum(skill_relevance_scores) / len(job_skills_list)
        skill_score = avg_skill_relevance * 65.0
    elif applicant_skills_set:
        skill_ratio = min(1.0, len(applicant_skills_set) / 5.0)
        skill_score = skill_ratio * 65.0
        avg_skill_relevance = skill_ratio
    else:
        skill_score = 30.0
        avg_skill_relevance = 0.45

    # 2. Experience Alignment (up to 18 points)
    candidate_exp = getattr(applicant, 'experience_years', 0) or 0
    min_exp_req = getattr(job_posting, 'min_experience_years', 0) or 0

    if not min_exp_req and getattr(job_posting, 'talent_request', None):
        tr_exp = job_posting.talent_request.experience_required or ''
        num_match = re.search(r'(\d+)', tr_exp)
        if num_match:
            min_exp_req = int(num_match.group(1))

    if min_exp_req > 0:
        if candidate_exp >= min_exp_req:
            exp_score = 18.0
        elif candidate_exp > 0:
            exp_ratio = candidate_exp / min_exp_req
            exp_score = min(18.0, max(8.0, exp_ratio * 12.0 + 4.0))
        else:
            exp_score = 6.0  # Basic baseline for transferable learners
    else:
        # Entry level / no minimum specified -> full alignment
        exp_score = min(18.0, 14.0 + min(4.0, candidate_exp * 1.0))

    # 3. Education Alignment (up to 12 points)
    candidate_edu = (getattr(applicant, 'education_level', '') or '').lower()
    if 'doctor' in candidate_edu or 'phd' in candidate_edu:
        edu_score = 12.0
    elif 'master' in candidate_edu:
        edu_score = 11.5
    elif 'bachelor' in candidate_edu or 'degree' in candidate_edu or 'college' in candidate_edu:
        edu_score = 10.5
    elif 'associate' in candidate_edu or 'vocational' in candidate_edu or 'diploma' in candidate_edu:
        edu_score = 9.0
    elif 'high school' in candidate_edu or 'secondary' in candidate_edu:
        edu_score = 7.5
    else:
        edu_score = 8.0  # Fair neutral baseline

    # 4. Employability & Resume Quality Factor (up to 5 points)
    raw_emp = float(getattr(applicant, 'employability_score', 0.0) or 0.0)
    has_resume = bool(getattr(applicant, 'resume_file', None) or text_corpus)
    emp_bonus = min(5.0, (raw_emp / 100.0) * 3.0 + (2.0 if has_resume else 1.0))

    # Final Match Score (0 - 100%) representing Strength of Match
    raw_total = skill_score + exp_score + edu_score + emp_bonus
    match_score = round(min(100.0, max(10.0, raw_total)), 2)

    # --------------------------------------------------------------------------
    # 5. QUALIFICATION DECISION (Relevance & Connection, not just high score)
    # --------------------------------------------------------------------------
    has_mandatory_blocker, blocker_msg = check_critical_mandatory_blockers(applicant, job_posting, text_corpus)

    # Count of connected skills (exact + transferable)
    total_connected_skills = len(matched_skills) + len(transferable_skills)
    connected_ratio = (total_connected_skills / len(job_skills_list)) if job_skills_list else 1.0

    # Decision Matrix:
    # 1. QUALIFIED:
    #    - No mandatory blockers AND
    #    - (Match score >= 55.0% OR connected_ratio >= 0.50 OR (candidate has relevant experience and connected skills))
    # 2. UNDER_QUALIFIED (Potentially Relevant / For Review):
    #    - Match score 38.0% - 54.9% OR some transferable connection (connected_ratio >= 0.25)
    # 3. NOT_QUALIFIED:
    #    - Mandatory blocker exists OR match score < 38.0% with 0 connected skills.

    if not has_mandatory_blocker and (match_score >= 55.0 or (connected_ratio >= 0.50 and (candidate_exp >= 1 or min_exp_req <= 1))):
        is_qualified = True
        qualification_status = 'qualified'
        
        # Build intuitive relevance reason
        relevance_parts = []
        if matched_skills:
            relevance_parts.append(f"{len(matched_skills)} direct skill match{'es' if len(matched_skills) > 1 else ''}")
        if transferable_skills:
            relevance_parts.append(f"{len(transferable_skills)} transferable competenc{'ies' if len(transferable_skills) > 1 else 'y'}")
        
        summary = " and ".join(relevance_parts) if relevance_parts else "relevant background alignment"
        reason = f"Qualified ({match_score:.0f}% Match). Demonstrated {summary} with {candidate_exp} yrs experience."

    elif not has_mandatory_blocker and (match_score >= 38.0 or total_connected_skills > 0):
        is_qualified = False
        qualification_status = 'under_qualified'
        reason = f"Potentially Qualified / For Review ({match_score:.0f}% Match). Candidate exhibits transferable background; recruiter review recommended."

    else:
        is_qualified = False
        qualification_status = 'not_qualified'
        if has_mandatory_blocker:
            reason = f"Not Qualified: {blocker_msg}"
        else:
            reason = f"Not Qualified ({match_score:.0f}% Match). Background does not align with core technical requirements ({', '.join(missing_skills[:3])} required)."

    return {
        'is_qualified': is_qualified,
        'qualification_status': qualification_status,
        'match_score': match_score,
        'skills_score': round(skill_score, 2),
        'exp_score': round(exp_score, 2),
        'edu_score': round(edu_score, 2),
        'matched_skills': matched_skills,
        'transferable_skills': transferable_skills,
        'missing_skills': missing_skills,
        'reason': reason
    }


def calculate_match_score(applicant, job_posting, resume_text=None):
    """
    Computes matching score (0 - 100) between an applicant profile and a job posting.
    Unified with evaluate_applicant_qualification for 100% consistency.
    """
    eval_res = evaluate_applicant_qualification(applicant, job_posting, resume_text=resume_text)
    return eval_res['match_score']


def rank_candidate_application(application):
    """
    Ranks an application for employer dashboard with category assignment.
    """
    base_match = float(application.match_score) if application.match_score else 0.0
    exp_years = application.applicant.experience_years if hasattr(application.applicant, 'experience_years') else 0
    emp_score = float(application.applicant.employability_score) if hasattr(application.applicant, 'employability_score') else 0.0
    edu_level = (application.applicant.education_level or '').lower() if hasattr(application.applicant, 'education_level') else ''

    score = base_match + min(12.0, exp_years * 1.2) + (emp_score * 0.15)

    if 'doctor' in edu_level:
        score += 8.0
    elif 'master' in edu_level:
        score += 6.0
    elif 'bachelor' in edu_level or 'college' in edu_level or 'degree' in edu_level:
        score += 4.0

    final_score = round(min(100.0, max(0.0, score)), 2)

    if final_score >= 80:
        category = 'excellent'
    elif final_score >= 60:
        category = 'good'
    elif final_score >= 40:
        category = 'average'
    else:
        category = 'poor'

    return {
        'ml_ranking_score': final_score,
        'ranking_category': category
    }


def find_alternative_job_recommendations(applicant, exclude_job_id=None, limit=4):
    """
    Finds active job postings in the system that best match the applicant's profile and transferable skills.
    """
    from jobs.models import JobPosting
    from applications.models import Application

    applied_job_ids = set(Application.objects.filter(applicant=applicant).values_list('job_id', flat=True))
    if exclude_job_id:
        applied_job_ids.add(exclude_job_id)

    active_jobs = JobPosting.objects.filter(status='active').exclude(id__in=applied_job_ids)
    applicant_skills, text_corpus = get_applicant_all_skills(applicant)
    candidate_exp = getattr(applicant, 'experience_years', 0) or 0

    scored_recommendations = []
    for job in active_jobs:
        score = calculate_match_score(applicant, job)
        job_skills_list = job.get_skills_list()
        matched_skills = [s for s in job_skills_list if is_skill_matched(s, applicant_skills, text_corpus)]

        reasons = []
        if matched_skills:
            reasons.append(f"Connects with your competencies in {', '.join(matched_skills[:3])}")
        if candidate_exp > 0 and job.min_experience_years <= candidate_exp:
            reasons.append(f"Aligns with your {candidate_exp} years experience")
        if job.location and ('remote' in job.location.lower() or 'manila' in job.location.lower()):
            reasons.append(f"Location match: {job.location}")

        match_reason = " &bull; ".join(reasons) if reasons else "Compatible role matching your background profile"

        scored_recommendations.append({
            'job': job,
            'match_score': int(score),
            'matched_skills': matched_skills[:4],
            'match_reason': match_reason
        })

    scored_recommendations.sort(key=lambda x: x['match_score'], reverse=True)
    return scored_recommendations[:limit]
