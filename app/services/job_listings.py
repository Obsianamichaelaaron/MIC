def job_posting_duplicate_key(job):
    def normalize(value):
        return ' '.join(str(value or '').casefold().split())

    return (
        job.employer_id,
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
    for job in queryset.iterator(chunk_size=100):
        key = job_posting_duplicate_key(job)
        if key in seen_jobs:
            continue
        seen_jobs.add(key)
        jobs.append(job)
        if limit is not None and len(jobs) == limit:
            break
    return jobs


def unique_featured_job_postings(queryset, limit):
    jobs = []
    seen_jobs = set()
    for job in queryset.iterator(chunk_size=100):
        company_name = ' '.join((job.employer.company_name or '').casefold().split())
        title = ' '.join((job.title or '').casefold().split())
        if not company_name or not title:
            continue

        key = (company_name, title)
        if key in seen_jobs:
            continue
        seen_jobs.add(key)
        jobs.append(job)
        if len(jobs) == limit:
            break
    return jobs
