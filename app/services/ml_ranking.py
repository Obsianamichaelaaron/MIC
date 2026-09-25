from app.models import Applicant, JobPosting

def calculate_candidate_ml_score(candidate_data: dict) -> dict:
    """
    Computes candidate ML ranking score and tier categorization.
    Exact algorithm from PHP candidate_ranking.php.
    """
    match_score = float(candidate_data.get('match_score', 0) or 0)
    exp_years = float(candidate_data.get('experience_years', 0) or 0)
    emp_score = float(candidate_data.get('employability_score', 0) or 0)
    edu_level = str(candidate_data.get('education_level', '') or '').lower()

    score = match_score
    # Experience bonus
    score += min(15.0, exp_years * 1.5)
    # Employability bonus
    score += (emp_score * 0.2)
    # Education bonus
    edu_bonus = 0.0
    if 'bachelor' in edu_level:
        edu_bonus = 5.0
    elif 'master' in edu_level:
        edu_bonus = 8.0
    elif 'doctor' in edu_level or 'phd' in edu_level:
        edu_bonus = 10.0
    score += edu_bonus

    ml_score = min(100.0, round(score, 2))

    if ml_score >= 85.0:
        category = 'excellent'
    elif ml_score >= 70.0:
        category = 'good'
    elif ml_score >= 50.0:
        category = 'average'
    else:
        category = 'poor'

    return {
        'ml_ranking_score': ml_score,
        'ranking_category': category
    }


def compute_job_match_score(applicant: Applicant, job: JobPosting, chatbot_answers=None) -> float:
    """Calculate the shared applicant-to-job match score used across the site."""
    score = 0.0

    applicant_skills = [skill.strip().lower() for skill in (applicant.skills or '').split(',') if skill.strip()]
    required_skills = re_split_skills(job.skills_required or '')
    if required_skills and applicant_skills:
        matched_skills = [
            required for required in required_skills
            if any(required.lower() == applicant_skill or required.lower() in applicant_skill or applicant_skill in required.lower() for applicant_skill in applicant_skills)
        ]
        score += (len(matched_skills) / len(required_skills)) * 40.0

    experience_years = int(applicant.experience_years or 0)
    score += min(25.0, experience_years * 5.0)

    job_qualifications = list(job.qualification_mappings.select_related('qualification').all())
    if job_qualifications:
        applicant_qualifications = (applicant.qualifications or '').lower()
        matched_qualifications = [
            qualification for qualification in job_qualifications
            if qualification.qualification.name.lower().strip() in applicant_qualifications
        ]
        score += (len(matched_qualifications) / len(job_qualifications)) * 15.0
    else:
        score += 7.5

    if chatbot_answers:
        total_value = 0
        maximum_value = 0
        for answer in chatbot_answers:
            if answer.score_value is not None:
                value = int(answer.score_value)
                total_value += value
                maximum_value += 45 if value >= 40 else 35 if value >= 30 else 25 if value >= 20 else 15 if value >= 10 else 10
            elif answer.answer_value is not None:
                total_value += int(answer.answer_value)
                maximum_value += 5
        if maximum_value:
            score += (total_value / maximum_value) * 10.0

    if chatbot_answers and len(chatbot_answers) >= 5:
        score += 5.0
    if applicant.employability_score and float(applicant.employability_score) > 0:
        score += min(5.0, (float(applicant.employability_score) / 100.0) * 5.0)

    return min(round(score, 2), 100.0)

def re_split_skills(text: str):
    import re
    if not text:
        return []
    tokens = re.split(r'[,;\n\r•\-\/]+', text)
    return [t.strip() for t in tokens if t.strip() and len(t.strip()) > 1]
