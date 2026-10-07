import re

from app.models import Applicant, JobPosting


EXPERIENCE_REQUIREMENT_PATTERN = re.compile(
    r'(\d+)\s*\+?\s*(?:years?|yrs?)\s*(?:of\s+)?(?:professional\s+)?experience',
    re.IGNORECASE,
)


def _contains_phrase(text, phrase):
    words = re.escape(phrase.strip())
    words = re.sub(r'\\\s+', r'\\s+', words)
    return bool(re.search(r'(?<!\w)' + words + r'(?!\w)', text, re.IGNORECASE))


def compute_profile_fit(
    applicant: Applicant,
    job: JobPosting,
    resume_text: str = '',
) -> dict[str, object]:
    """Return transparent coverage of explicit job criteria, not an ML prediction."""
    applicant_skills = (applicant.skills or '').strip()
    qualifications = (applicant.qualifications or '').strip()
    education = (applicant.education_level or '').strip()
    experience_years = max(0, int(applicant.experience_years or 0))
    evidence = ' '.join(
        value for value in (
            applicant_skills,
            qualifications,
            education,
            resume_text or '',
        )
        if value
    )
    if not evidence and experience_years == 0:
        return {
            'score': None,
            'reason': 'Add skills, qualifications, experience, or a resume to your profile to get an estimate.',
        }

    required_skills = [
        skill.strip()
        for skill in (job.skills_required or '').split(',')
        if skill.strip()
    ]
    matched_skills = [
        skill for skill in required_skills
        if _contains_phrase(evidence, skill)
    ]
    missing_skills = [
        skill for skill in required_skills
        if skill not in matched_skills
    ]

    criteria_coverage = [
        1.0 if skill in matched_skills else 0.0
        for skill in required_skills
    ]
    experience_match = None
    experience_text = ' '.join(value for value in (job.requirements, job.description) if value)
    experience_required = EXPERIENCE_REQUIREMENT_PATTERN.search(experience_text)
    if experience_required:
        required_years = int(experience_required.group(1))
        experience_match = min(experience_years / required_years, 1.0) if required_years else 1.0
        criteria_coverage.append(experience_match)

    mapped_qualifications = [
        mapping.qualification.name
        for mapping in job.qualification_mappings.select_related('qualification').all()
        if mapping.qualification and mapping.qualification.name
    ]
    matched_qualifications = [
        name for name in mapped_qualifications
        if _contains_phrase(evidence, name)
    ]
    criteria_coverage.extend(
        1.0 if name in matched_qualifications else 0.0
        for name in mapped_qualifications
    )

    if not criteria_coverage:
        return {
            'score': None,
            'reason': 'This job has no explicit skill, experience, or mapped qualification criteria to estimate profile fit.',
        }

    return {
        'score': round(sum(criteria_coverage) / len(criteria_coverage) * 100),
        'reason': '',
        'matched_skills': matched_skills,
        'missing_skills': missing_skills,
        'experience_years': experience_years,
        'experience_required': int(experience_required.group(1)) if experience_required else None,
        'experience_coverage': round(experience_match * 100) if experience_match is not None else None,
        'matched_qualifications': matched_qualifications,
        'required_qualifications': mapped_qualifications,
    }
