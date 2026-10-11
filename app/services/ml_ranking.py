import re
from app.models import Applicant, JobPosting
from app.services.qualification import classify_match_score


def re_split_skills(text: str) -> list[str]:
    if not text:
        return []
    tokens = re.split(r'[,;\n\r•\-\/]+', text)
    return [t.strip().lower() for t in tokens if t.strip() and len(t.strip()) > 1]


def compute_rule_based_match_score(applicant: Applicant, job: JobPosting, chatbot_answers=None) -> dict:
    """
    Fast rule-based matching engine from candidate_ranking:
    - Skills Match (up to 40 pts)
    - Experience Match (up to 25 pts)
    - Qualifications Match (up to 15 pts)
    - Profile & Assessment Bonus (up to 20 pts)
    """
    score = 0.0

    applicant_skills = [s.strip().lower() for s in (applicant.skills or '').split(',') if s.strip()]
    required_skills = re_split_skills(job.skills_required or '')
    matched_skills = []
    missing_skills = []

    if required_skills:
        for req in required_skills:
            if any(req == ask or req in ask or ask in req for ask in applicant_skills):
                matched_skills.append(req)
            else:
                missing_skills.append(req)
        score += (len(matched_skills) / len(required_skills)) * 40.0
    else:
        score += 30.0 if applicant_skills else 15.0

    experience_years = int(applicant.experience_years or 0)
    score += min(25.0, experience_years * 5.0) if experience_years > 0 else 5.0

    job_quals = list(job.qualification_mappings.all())
    applicant_qual_text = (applicant.qualifications or '').lower()
    if job_quals:
        matched_q = [
            q for q in job_quals
            if getattr(q, 'qualification', None) and q.qualification.name.lower().strip() in applicant_qual_text
        ]
        score += (len(matched_q) / len(job_quals)) * 15.0
    else:
        score += 10.0 if applicant.qualifications else 5.0

    # Profile / Quiz bonus (up to 20 pts)
    bonus_score = 0.0
    if chatbot_answers:
        bonus_score += min(10.0, len(chatbot_answers) * 2.0)
    elif applicant.employability_score and float(applicant.employability_score) > 0:
        bonus_score += min(10.0, (float(applicant.employability_score) / 100.0) * 10.0)
    else:
        bonus_score += 5.0

    if applicant.resume_file:
        bonus_score += 5.0
    if applicant.profile_completed:
        bonus_score += 5.0

    score += min(20.0, bonus_score)
    final_score = min(100.0, max(0.0, round(score, 1)))

    return {
        'score': final_score,
        'matched_skills': matched_skills,
        'missing_skills': missing_skills,
        'breakdown': [
            {'label': 'Skills Match', 'score': round((len(matched_skills) / max(1, len(required_skills))) * 40.0, 1)},
            {'label': 'Experience', 'score': min(25.0, experience_years * 5.0)},
            {'label': 'Qualifications', 'score': 15.0 if job_quals else 10.0},
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
    """Classify candidates automatically from their existing employability score."""
    match_score = round(float(applicant.employability_score or 0), 2)
    qualification_status = classify_match_score(match_score)
    match_class = {
        'qualified': 'high',
        'under_qualified': 'medium',
        'not_qualified': 'low',
    }[qualification_status]
    return {
        'ready': True,
        'reason': '',
        'match_score': match_score,
        'match_class': match_class,
        'qualification_status': qualification_status,
    }


def compute_job_match_score(applicant: Applicant, job: JobPosting, chatbot_answers=None) -> float | None:
    """Return the applicant's match score using rule-based calculations."""
    return compute_rule_based_match_score(applicant, job, chatbot_answers)['score']

