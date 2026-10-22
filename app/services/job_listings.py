from django.db.models import Q
from django.db.models.functions import Lower, Trim


EXCLUDED_COMPANY_NAME = 'Multibiz Partner'


def exclude_multibiz_partner_jobs(queryset):
    company_name_field = 'employer__company_name'
    normalized_name = Lower(Trim(company_name_field))
    return queryset.alias(
        _normalized_company_name=normalized_name,
    ).exclude(
        Q(**{f'{company_name_field}__isnull': True})
        | Q(_normalized_company_name='')
        | Q(_normalized_company_name=EXCLUDED_COMPANY_NAME.casefold())
    )


def job_posting_duplicate_key(job):
    def normalize(value):
        return ' '.join(str(value or '').casefold().split())

    return (
        normalize(job.employer.company_name if job.employer else ''),
        normalize(job.title),
        normalize(job.description),
        normalize(job.requirements),
        normalize(job.skills_required),
        normalize(job.location),
        normalize(job.employment_type),
        normalize(job.salary_range),
        job.positions_available,
        normalize(job.status),
        job.posted_at.date() if job.posted_at else None,
    )


def unique_public_job_postings(queryset, limit=None):
    jobs = []
    seen_jobs = set()
    for job in queryset:
        key = job_posting_duplicate_key(job)
        if key in seen_jobs:
            continue
        seen_jobs.add(key)
        jobs.append(job)
        if limit is not None and len(jobs) == limit:
            break
    return jobs


def unique_company_title_job_postings(queryset, limit=None):
    if limit is not None and limit <= 0:
        return []

    jobs = []
    seen_jobs = set()
    job_rows = queryset.iterator(chunk_size=100) if limit is not None else queryset
    for job in job_rows:
        company_name = ' '.join((job.employer.company_name or '').casefold().split())
        title = ' '.join((job.title or '').casefold().split())
        if not company_name or not title:
            continue

        key = (company_name, title)
        if key in seen_jobs:
            continue
        seen_jobs.add(key)
        jobs.append(job)
        if limit is not None and len(jobs) == limit:
            break
    return jobs


def unique_featured_job_postings(queryset, limit):
    return unique_company_title_job_postings(queryset, limit=limit)
