def unique_public_job_postings(queryset, limit):
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
