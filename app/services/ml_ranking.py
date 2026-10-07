from app.models import Applicant, JobPosting
from app.services.qualification import classify_match_score


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
    """Return the applicant's employability score."""
    return compute_job_match_result(applicant, job)['match_score']
