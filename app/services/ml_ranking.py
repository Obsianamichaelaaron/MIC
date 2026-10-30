import re
from app.models import Applicant, JobPosting
from app.services.qualification import classify_match_score


GENERIC_SOFT_SKILLS = {
    'communication', 'teamwork', 'time management', 'problem solving',
    'leadership', 'adaptability', 'interpersonal skills', 'work ethic',
    'critical thinking', 'multitasking', 'organization', 'collaboration'
}


def re_split_skills(text: str) -> list[str]:
    if not text:
        return []
    tokens = re.split(r'[,;\n\r•\-\/]+', text)
    return [t.strip().lower() for t in tokens if t.strip() and len(t.strip()) > 1]


def compute_rule_based_match_score(applicant: Applicant, job: JobPosting, chatbot_answers=None) -> dict:
    """
    Domain-aware rule-based matching engine:
    - Core Skills Match (up to 40 pts)
    - Domain-Gated Experience Match (up to 25 pts)
    - Domain-Gated Qualifications Match (up to 15 pts)
    - Profile & Assessment Verification (up to 10-20 pts)
    Prevents unrelated professions (e.g. janitorial/utility) from scoring high on specialized technical roles.
    """
    applicant_skills = [s.strip().lower() for s in (applicant.skills or '').split(',') if s.strip()]
    required_skills = re_split_skills(job.skills_required or '')
    matched_skills = []
    missing_skills = []

    if required_skills:
        for req in required_skills:
            if any(req == ask or (len(ask) > 2 and ask in req) or (len(req) > 2 and req in ask) for ask in applicant_skills):
                matched_skills.append(req)
            else:
                missing_skills.append(req)
        skill_ratio = len(matched_skills) / len(required_skills)
        skills_score = skill_ratio * 40.0
    else:
        job_text = f"{job.title} {job.description or ''}".lower()
        overlap = sum(1 for ask in applicant_skills if ask in job_text)
        if applicant_skills and overlap > 0:
            skill_ratio = min(1.0, overlap / max(1, len(applicant_skills)))
            skills_score = skill_ratio * 30.0
        else:
            skill_ratio = 0.05
            skills_score = 5.0

    # Differentiate hard technical skills vs generic soft skills
    hard_required = [s for s in required_skills if s not in GENERIC_SOFT_SKILLS]
    matched_hard = [s for s in matched_skills if s not in GENERIC_SOFT_SKILLS]
    only_soft_matched = (len(hard_required) > 0 and len(matched_hard) == 0 and len(matched_skills) > 0)

    # ── Experience Match (Domain Gated) ──────────────────────────────────────
    experience_years = max(0, int(applicant.experience_years or 0))
    raw_exp = min(25.0, experience_years * 5.0)

    if len(required_skills) > 0:
        if skill_ratio == 0:
            exp_score = min(2.5, raw_exp * 0.1) # Nominal credit for general work etiquette
        elif only_soft_matched:
            exp_score = min(5.0, raw_exp * 0.2)
        else:
            exp_score = raw_exp * (0.3 + 0.7 * skill_ratio)
    else:
        exp_score = raw_exp * 0.5

    # ── Qualifications Match (Domain Gated) ──────────────────────────────────
    job_quals = list(job.qualification_mappings.select_related('qualification').all())
    applicant_qual_text = (applicant.qualifications or '').lower()
    job_title_lower = (job.title or '').lower()

    if job_quals:
        matched_q = [
            q for q in job_quals
            if getattr(q, 'qualification', None) and q.qualification.name.lower().strip() in applicant_qual_text
        ]
        qual_score = (len(matched_q) / len(job_quals)) * 15.0
    else:
        has_domain_degree = any(word in applicant_qual_text for word in job_title_lower.split() if len(word) > 3)
        if has_domain_degree:
            qual_score = 12.0
        elif skill_ratio > 0.4:
            qual_score = 8.0 * skill_ratio
        elif applicant.qualifications:
            qual_score = 2.0
        else:
            qual_score = 0.0

    # ── Profile & Assessment Bonus ───────────────────────────────────────────
    bonus_score = 0.0
    if chatbot_answers:
        bonus_score += min(3.0, len(chatbot_answers) * 1.0)
    elif applicant.employability_score and float(applicant.employability_score) > 0:
        bonus_score += min(4.0, (float(applicant.employability_score) / 100.0) * 4.0)
    else:
        bonus_score += 1.0

    if applicant.resume_file:
        bonus_score += 3.0
    if applicant.profile_completed:
        bonus_score += 3.0

    if len(required_skills) > 0 and skill_ratio == 0:
        bonus_score = min(4.0, bonus_score * 0.4)
    elif only_soft_matched:
        bonus_score = min(6.0, bonus_score * 0.6)

    total_score = skills_score + exp_score + qual_score + bonus_score

    # Uncompromising guardrails: candidate missing ALL core technical skills cannot be High/Medium match
    if len(required_skills) > 0 and len(matched_skills) == 0 and total_score > 25.0:
        total_score = 20.0
    elif only_soft_matched and total_score > 35.0:
        total_score = 35.0

    final_score = min(100.0, max(0.0, round(total_score, 1)))

    return {
        'score': final_score,
        'matched_skills': matched_skills,
        'missing_skills': missing_skills,
        'breakdown': [
            {'label': 'Skills Match', 'score': round(skills_score, 1)},
            {'label': 'Experience', 'score': round(exp_score, 1)},
            {'label': 'Qualifications', 'score': round(qual_score, 1)},
        ],
        'reason': f"{final_score:.0f}% Match",
        'model_ready': True,
    }


def calculate_candidate_ml_score(candidate_data: dict) -> dict:
    """
    Return the employability score and its automatically assigned category.
    """
    raw_score = candidate_data.get('ml_match_score')
    if raw_score is None:
        return {
            'ml_ranking_score': None,
            'ranking_category': 'unavailable',
        }
    ml_score = round(float(raw_score), 2)

    return {
        'ml_ranking_score': ml_score,
        'ranking_category': candidate_data.get('ml_match_class', 'model_scored'),
    }


def compute_job_match_result(applicant: Applicant, job: JobPosting) -> dict:
    """Score a candidate against a specific job using the shared profile matcher."""
    match_score = compute_rule_based_match_score(applicant, job)['score']
    qualification_status = classify_match_score(match_score)
    match_class = {
        'qualified': 'high',
        'under_qualified': 'medium',
        'not_qualified': 'low',
    }[qualification_status]
    return {
        'ready': True,
        'reason': f'{match_score:.0f}% Match',
        'match_score': match_score,
        'match_class': match_class,
        'qualification_status': qualification_status,
    }


def compute_job_match_score(applicant: Applicant, job: JobPosting, chatbot_answers=None) -> float | None:
    """Return the applicant's match score using rule-based calculations."""
    return compute_rule_based_match_score(applicant, job, chatbot_answers)['score']
