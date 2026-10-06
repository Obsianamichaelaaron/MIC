from app.models import Applicant, JobPosting, ResumeAnalysis


MATCH_CLASS_TO_QUALIFICATION = {
    'high': 'qualified',
    'medium': 'under_qualified',
    'low': 'not_qualified',
}


def calculate_candidate_ml_score(candidate_data: dict) -> dict:
    """
    Use the supervised model score directly; do not add hand-authored bonuses.
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
    """Return an applicant-excluded trained-model prediction, or an explicit unavailable result."""
    from app.services.ml_job_matching import (
        get_match_classifier,
        predict_match_class,
        predict_match_score,
    )

    model, status = get_match_classifier(exclude_applicant_id=applicant.pk)
    if model is None:
        return {
            'ready': False,
            'reason': status.get('reason', 'The trained match model is unavailable.'),
            'match_score': None,
            'match_class': None,
            'qualification_status': 'unavailable',
        }

    resume_text = ResumeAnalysis.objects.filter(
        applicant=applicant
    ).order_by('-analysis_date').values_list('extracted_text', flat=True).first() or ''
    match_score = predict_match_score(model, applicant, job, resume_text)
    match_class = predict_match_class(model, applicant, job, resume_text)
    if match_score is None or match_class not in MATCH_CLASS_TO_QUALIFICATION:
        return {
            'ready': False,
            'reason': 'The model could not score this applicant and job text.',
            'match_score': None,
            'match_class': None,
            'qualification_status': 'unavailable',
        }

    return {
        'ready': True,
        'reason': '',
        'match_score': match_score,
        'match_class': match_class,
        'qualification_status': MATCH_CLASS_TO_QUALIFICATION[match_class],
    }


def compute_job_match_score(applicant: Applicant, job: JobPosting, chatbot_answers=None) -> float | None:
    """Return the trained classifier's estimated probability of a high match."""
    return compute_job_match_result(applicant, job)['match_score']
