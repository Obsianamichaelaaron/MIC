import os
import io
import logging
import mimetypes

from django.shortcuts import render, get_object_or_404, redirect
from django.http import (
    FileResponse,
    HttpResponseNotFound,
    HttpResponseServerError,
    JsonResponse,
)
from django.views.decorators.csrf import csrf_exempt
from django.db.models import Q
from django.conf import settings
from app.models import (
    Application, Applicant, CmsSection, CmsContent, CmsHeroSlide,
    CmsTestimonial, CmsBrand, CmsNews, JobPosting, Qualification,
    ContactInquiry, Employer, User,
)
from app.services.resume_storage import ResumeStorageError, read_resume
from app.services.job_listings import unique_public_job_postings


logger = logging.getLogger(__name__)


def get_cms_content_dict():
    """
    Builds a dictionary of CMS content key-values grouped by section_key.
    Equivalent to PHP's getCMSContent() function.
    """
    cms = {}
    try:
        sections = CmsSection.objects.prefetch_related('contents').all()
        for sec in sections:
            sec_dict = {}
            for item in sec.contents.filter(is_active=True):
                sec_dict[item.field_key] = item.field_value
            cms[sec.section_key] = sec_dict
    except Exception:
        pass
    return cms

HARDCODED_BRANDS = [
    {'brand_name': 'Lungsod ng Bacoor',       'brand_description': 'Building progress together.',          'brand_overlay_title': 'Lungsod ng Bacoor',       'brand_overlay_description': 'Empowering local communities.',      'brand_logo': 'images/b1.png',     'brand_category': 'gov', 'sort_order': 1},
    {'brand_name': 'Lungsod ng Imus',          'brand_description': 'Innovative city, empowered citizens.', 'brand_overlay_title': 'Lungsod ng Imus',          'brand_overlay_description': 'Advancing sustainable growth.',      'brand_logo': 'images/b1.png',     'brand_category': 'gov', 'sort_order': 2},
    {'brand_name': 'City of Santa Rosa',       'brand_description': 'The Lion City of the South.',         'brand_overlay_title': 'City of Santa Rosa',       'brand_overlay_description': 'Driving innovation and excellence.', 'brand_logo': 'images/b3.png',     'brand_category': 'gov', 'sort_order': 3},
    {'brand_name': 'City of Trece Martires',   'brand_description': 'Unity in progress.',                  'brand_overlay_title': 'City of Trece Martires',   'brand_overlay_description': 'Committed to public service.',       'brand_logo': 'images/b4.png',     'brand_category': 'gov', 'sort_order': 4},
    {'brand_name': 'City of General Trias',    'brand_description': 'Championing development.',            'brand_overlay_title': 'City of General Trias',    'brand_overlay_description': 'Creating opportunities for all.',    'brand_logo': 'images/b5.png',     'brand_category': 'gov', 'sort_order': 5},
    {'brand_name': 'City of San Pedro',        'brand_description': 'Gateway to Laguna.',                  'brand_overlay_title': 'City of San Pedro',        'brand_overlay_description': 'People-centered governance.',        'brand_logo': 'images/b6.png',     'brand_category': 'gov', 'sort_order': 6},
    {'brand_name': 'Lalawigan ng Laguna',      'brand_description': 'Heart of Calabarzon.',                'brand_overlay_title': 'Lalawigan ng Laguna',      'brand_overlay_description': 'Promoting inclusive prosperity.',    'brand_logo': 'images/b8.png',     'brand_category': 'gov', 'sort_order': 7},
    {'brand_name': 'Lalawigan ng Batangas',    'brand_description': 'Heart of Calabarzon.',                'brand_overlay_title': 'Lalawigan ng Batangas',    'brand_overlay_description': 'Promoting inclusive prosperity.',    'brand_logo': 'images/bnine.png',  'brand_category': 'gov', 'sort_order': 8},
    {'brand_name': 'Globe myBusiness',         'brand_description': 'Empowering Filipino entrepreneurs.',  'brand_overlay_title': 'Globe myBusiness',         'brand_overlay_description': 'Connecting business, powering success.','brand_logo': 'images/b11.png',  'brand_category': 'cor', 'sort_order': 9},
    {'brand_name': 'Asia Brewery Inc.',        'brand_description': 'Refreshing the nation.',              'brand_overlay_title': 'Asia Brewery Inc.',        'brand_overlay_description': 'Committed to quality and innovation.','brand_logo': 'images/b12.jpg',  'brand_category': 'cor', 'sort_order': 10},
    {'brand_name': 'AFFI Entrepreneurs',       'brand_description': 'Fueling business dreams.',            'brand_overlay_title': 'AFFI Entrepreneurs',       'brand_overlay_description': 'Together, we grow stronger.',        'brand_logo': 'images/b13.png',    'brand_category': 'cor', 'sort_order': 11},
    {'brand_name': 'Metrobank',                'brand_description': 'You\'re in good hands.',              'brand_overlay_title': 'Metrobank',                'brand_overlay_description': 'Meaningful banking for Filipinos.',  'brand_logo': 'images/b14.png',    'brand_category': 'cor', 'sort_order': 12},
    {'brand_name': 'CARD SME Bank',            'brand_description': 'Financing your future.',              'brand_overlay_title': 'CARD SME Bank',            'brand_overlay_description': 'Helping small dreams grow big.',     'brand_logo': 'images/b15.png',    'brand_category': 'cor', 'sort_order': 13},
    {'brand_name': 'Mitsubishi Motors',        'brand_description': 'Drive your ambition.',                'brand_overlay_title': 'Mitsubishi Motors',        'brand_overlay_description': 'Innovation in motion.',              'brand_logo': 'images/b17.jpg',    'brand_category': 'cor', 'sort_order': 14},
    {'brand_name': 'Hyundai',                  'brand_description': 'Progress for humanity.',              'brand_overlay_title': 'Hyundai',                  'brand_overlay_description': 'New thinking, new possibilities.',   'brand_logo': 'images/b18.jpg',    'brand_category': 'cor', 'sort_order': 15},
    {'brand_name': 'CITIMOTORS INC.',          'brand_description': 'Driven by trust.',                    'brand_overlay_title': 'CITIMOTORS INC.',          'brand_overlay_description': 'Your road to reliability.',          'brand_logo': 'images/b1nine.jpg', 'brand_category': 'cor', 'sort_order': 16},
    {'brand_name': 'PrimeWater',               'brand_description': 'Clean water, better life.',           'brand_overlay_title': 'PrimeWater',               'brand_overlay_description': 'Sustaining communities nationwide.', 'brand_logo': 'images/b20.png',    'brand_category': 'cor', 'sort_order': 17},
    {'brand_name': 'IMI',                      'brand_description': 'Engineering a smarter world.',        'brand_overlay_title': 'IMI',                      'brand_overlay_description': 'Innovating for the future.',         'brand_logo': 'images/b21.jpg',    'brand_category': 'cor', 'sort_order': 18},
    {'brand_name': 'Concentrix',               'brand_description': 'Designing better human experiences.', 'brand_overlay_title': 'Concentrix',               'brand_overlay_description': 'People. Passion. Performance.',      'brand_logo': 'images/b23.png',    'brand_category': 'cor', 'sort_order': 19},
    {'brand_name': 'Convergys',                'brand_description': 'Empowering people, powering business.','brand_overlay_title': 'Convergys',              'brand_overlay_description': 'Delivering customer excellence.',    'brand_logo': 'images/b24.png',    'brand_category': 'cor', 'sort_order': 20},
    {'brand_name': 'Jollibee',                 'brand_description': 'Bida ang saya!',                      'brand_overlay_title': 'Jollibee',                 'brand_overlay_description': 'Bringing joy to every Filipino.',    'brand_logo': 'images/b25.png',    'brand_category': 'foo', 'sort_order': 21},
    {'brand_name': 'Days Hotel',               'brand_description': 'Stay comfortable, stay inspired.',    'brand_overlay_title': 'Days Hotel',               'brand_overlay_description': 'Your home away from home.',          'brand_logo': 'images/b26.jpg',    'brand_category': 'foo', 'sort_order': 22},
    {'brand_name': 'Manila Ocean Park',        'brand_description': 'Dive into discovery.',                'brand_overlay_title': 'Manila Ocean Park',        'brand_overlay_description': 'Where fun meets the ocean.',         'brand_logo': 'images/b28.jpg',    'brand_category': 'foo', 'sort_order': 23},
    {'brand_name': 'STI',                      'brand_description': 'Education for real life.',             'brand_overlay_title': 'STI',                      'brand_overlay_description': 'Driven by technology and excellence.','brand_logo': 'images/b2nine.jpg','brand_category': 'edu', 'sort_order': 24},
    {'brand_name': 'Department of Agriculture','brand_description': 'Masaganang ani, mataas na kita.',     'brand_overlay_title': 'Department of Agriculture','brand_overlay_description': 'Securing food for every Filipino.',  'brand_logo': 'images/b30.png',    'brand_category': 'gov', 'sort_order': 25},
    {'brand_name': 'DPWH',                     'brand_description': 'Building better roads.',              'brand_overlay_title': 'DPWH',                     'brand_overlay_description': 'Connecting communities nationwide.', 'brand_logo': 'images/b32.png',    'brand_category': 'gov', 'sort_order': 26},
    {'brand_name': 'City of Dasmariñas',       'brand_description': 'The university city.',                'brand_overlay_title': 'City of Dasmariñas',       'brand_overlay_description': 'Home of education and progress.',    'brand_logo': 'images/b6.jpg',     'brand_category': 'gov', 'sort_order': 27},
]

def index_view(request):
    """
    Homepage controller matching index.php.
    """
    cms = get_cms_content_dict()
    
    # Hero slides
    hero_slides = list(CmsHeroSlide.objects.filter(is_active=True).order_by('sort_order', 'id'))
    if not hero_slides:
        hero_slides = [
            {'title': 'Integrated Business<br><em>Solutions</em> That Scale', 'subtitle': 'Connect with opportunities that match your skills. Partner with the Philippines\' leading managed services corporation.', 'button_text': 'Browse Jobs', 'button_link': '/careers.php', 'image_path': 'images/picture1.png'},
            {'title': 'Global <em>Partnerships</em>', 'subtitle': 'Building bridges between talent and opportunity across the Philippines and beyond.', 'button_text': 'Learn More', 'button_link': '/about.php', 'image_path': 'images/picture2.png'},
            {'title': 'Digital <em>Transformation</em>', 'subtitle': 'Empowering businesses with cutting-edge managed services and solutions.', 'button_text': 'Our Services', 'button_link': '/services.php', 'image_path': 'images/picture3.png'}
        ]

    # Stats
    stats_years = cms.get('stats', {}).get('years', '22+')
    stats_clients = cms.get('stats', {}).get('clients', '500+')
    stats_efficiency = cms.get('stats', {}).get('efficiency', '95%')
    stats_units = cms.get('stats', {}).get('units', '3K+')

    # Section titles
    partners_title = cms.get('partners_title', {}).get('title') or cms.get('partners', {}).get('title') or 'Our Partner Brands'
    testimonials_title = cms.get('testimonials_title', {}).get('title') or cms.get('testimonials', {}).get('title') or 'What Our Clients Say'
    featured_jobs_title = cms.get('featured_jobs_title', {}).get('title') or 'Featured Job Openings'
        
    # Testimonials
    testimonials = list(CmsTestimonial.objects.filter(is_active=True).order_by('sort_order', 'id'))
    if not testimonials:
        testimonials = [
            {'author_name': 'John Smith', 'author_role': 'CEO', 'company': 'Tech Solutions Inc.', 'content': 'MULTIBIZ INTERNATIONAL CORPORATION transformed our document management system. Their managed print services have saved us time and reduced our costs significantly. The team is professional and always available when we need support.'},
            {'author_name': 'Sarah Johnson', 'author_role': 'CTO', 'company': 'Global Enterprises', 'content': 'The IT solutions provided by MULTIBIZ INTERNATIONAL CORPORATION have been exceptional. They helped us streamline our operations and implement systems that have increased our productivity by 40%. Highly recommended!'},
            {'author_name': 'Michael Brown', 'author_role': 'Operations Director', 'company': 'Retail Corp', 'content': "We've been working with MULTIBIZ INTERNATIONAL CORPORATION for over 10 years and their service has always been top-notch. Their team understands our business needs and provides solutions that help us grow."}
        ]
    
    # Partner Brands
    partner_brands = list(CmsBrand.objects.filter(is_active=True).order_by('sort_order', 'id'))
    if not partner_brands:
        partner_brands = HARDCODED_BRANDS
    
    # News & Events
    news_articles = list(CmsNews.objects.filter(is_active=True).order_by('-news_date', '-id')[:6])

    # Keep landing-page jobs in sync with the applicant-visible active listings.
    featured_jobs = unique_public_job_postings(
        JobPosting.objects.filter(
            status='active',
            employer__company_name__isnull=False,
        ).exclude(
            employer__company_name='',
        ).select_related('employer').order_by('-posted_at', '-job_id'),
        limit=6,
    )

    context = {
        'page_title': 'MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'index.php',
        'cms': cms,
        'hero_slides': hero_slides,
        'stats_years': stats_years,
        'stats_clients': stats_clients,
        'stats_efficiency': stats_efficiency,
        'stats_units': stats_units,
        'partners_title': partners_title,
        'partner_brands': partner_brands,
        'brands': partner_brands,
        'testimonials_title': testimonials_title,
        'testimonials': testimonials,
        'featured_jobs_title': featured_jobs_title,
        'news_articles': news_articles,
        'news_list': news_articles,
        'featured_jobs': featured_jobs,
    }
    return render(request, 'public/index.html', context)


def about_view(request):
    """
    About Us page matching about.php.
    """
    cms = get_cms_content_dict()
    context = {
        'page_title': 'About Us - MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'about.php',
        'cms': cms,
    }
    return render(request, 'public/about.html', context)


def services_view(request):
    """
    Services page matching services.php.
    """
    cms = get_cms_content_dict()
    context = {
        'page_title': 'Services - MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'services.php',
        'cms': cms,
    }
    return render(request, 'public/services.html', context)


def solutions_view(request):
    """
    Solutions page matching solutions.php.
    """
    cms = get_cms_content_dict()
    context = {
        'page_title': 'Solutions - MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'solutions.php',
        'cms': cms,
    }
    return render(request, 'public/solutions.html', context)


def printmanagement_view(request):
    """Handles printmanagement.php / printmanagement/ route."""
    return redirect('/services.php#managedprint')


def computingproducts_view(request):
    """Handles computingproducts.php / computingproducts/ route."""
    return redirect('/services.php#managedpc')


def retailsolutions_view(request):
    """Handles retailsolutions.php / retailsolutions/ route."""
    return redirect('/solutions.php#retailsolutions')


def audiovisual_view(request):
    """Handles audiovisual.php / audiovisual/ route."""
    return redirect('/solutions.php#audiovisual')


def informationmanagement_view(request):
    """Handles informationmanagement.php / informationmanagement/ route."""
    return redirect('/services.php#managedit')


def businessautomation_view(request):
    """Handles businessautomation.php / businessautomation/ route."""
    return redirect('/services.php#managedhris')


def cybersecurity_view(request):
    """Handles cybersecurity.php / cybersecurity/ route."""
    return redirect('/solutions.php#cybersecurity')


def hp_view(request):
    """Handles hp.php / hp/ route."""
    return redirect('/services.php#managedprint')


def contactus_view(request):
    """
    Contact Us page controller matching contactus.php / contact.php.
    """
    cms = get_cms_content_dict()
    contact_info = cms.get('contact', {})

    directory = [
        {
            'department': 'General Inquiries & Customer Care',
            'email': contact_info.get('email', 'inquiry@multibiz.global'),
            'phone': contact_info.get('phone', '+63 917 544 1674'),
            'hours': 'Mon – Fri: 8:00 AM – 5:30 PM (PHT)',
            'description': 'For general questions, corporate partnerships, and service inquiries.'
        },
        {
            'department': 'Technical Support & Helpdesk',
            'email': 'support@multibiz.global',
            'phone': '(02) 8896-7688 / +63 917 800 4357',
            'hours': '24/7 Priority Support for Enterprise Clients',
            'description': 'For system maintenance, hardware support, and service ticketing.'
        },
        {
            'department': 'Enterprise Solutions & Sales',
            'email': 'sales@multibiz.global',
            'phone': '+63 917 544 1674',
            'hours': 'Mon – Fri: 8:30 AM – 5:00 PM (PHT)',
            'description': 'Consult with our specialists on Managed Print Services, DMS, and IT integration.'
        },
        {
            'department': 'Talent Acquisition & Careers',
            'email': 'careers@multibiz.global',
            'phone': '(02) 8896-7688 loc. 104',
            'hours': 'Mon – Fri: 9:00 AM – 5:00 PM (PHT)',
            'description': 'For applicant inquiries, recruitment status, and HR partnerships.'
        },
    ]

    context = {
        'page_title': 'Contact Us - MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'contactus.php',
        'cms': cms,
        'contact_info': contact_info,
        'directory': directory,
    }
    return render(request, 'public/contactus.html', context)



def news_view(request):
    """Public news listing matching news.php."""
    news_articles = CmsNews.objects.filter(is_active=True).order_by('-news_date', '-id')
    context = {
        'page_title': 'News - MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'news.php',
        'news_articles': news_articles,
        'news_list': news_articles,
    }
    return render(request, 'public/news.html', context)


def careers_view(request):
    """
    Public Job Board matching careers.php.
    """
    search_query = request.GET.get('search', '').strip()
    qualification_id = request.GET.get('qualification', '')
    emp_type = request.GET.get('employment_type', '') or request.GET.get('type', '')
    location = request.GET.get('location', '').strip()

    jobs_qs = JobPosting.objects.filter(
        status='active',
        employer__company_name__isnull=False,
    ).exclude(
        employer__company_name='',
    ).select_related('employer').order_by('-posted_at', '-job_id')

    if search_query:
        jobs_qs = jobs_qs.filter(
            Q(title__icontains=search_query) |
            Q(description__icontains=search_query) |
            Q(skills_required__icontains=search_query) |
            Q(requirements__icontains=search_query) |
            Q(employer__company_name__icontains=search_query)
        )

    if qualification_id and qualification_id.isdigit() and int(qualification_id) > 0:
        jobs_qs = jobs_qs.filter(
            Q(qualification_mappings__qualification_id=int(qualification_id)) |
            Q(target_qualifications__contains=str(qualification_id))
        ).distinct()

    if emp_type:
        jobs_qs = jobs_qs.filter(employment_type=emp_type)

    if location:
        jobs_qs = jobs_qs.filter(location__icontains=location)

    jobs = unique_public_job_postings(jobs_qs, limit=30)

    qualifications = Qualification.objects.filter(status='active').order_by('name')

    context = {
        'page_title': 'Careers - MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'careers.php',
        'jobs': jobs,
        'qualifications': qualifications,
        'search_query': search_query,
        'selected_qualification': int(qualification_id) if qualification_id.isdigit() else 0,
        'selected_type': emp_type,
        'selected_location': location,
        'total_jobs_count': len(jobs),
        'search': search_query,
        'employment_type': emp_type,
        'location': location,
    }
    return render(request, 'public/careers.html', context)


def job_details_public_view(request, job_id=None):
    """
    Public Job Details page matching job_details_public.php.
    """
    j_id = job_id or request.GET.get('id') or request.GET.get('job_id')
    if not j_id:
        return redirect('/careers.php')

    job = get_object_or_404(JobPosting.objects.select_related('employer'), pk=j_id)
    qualifications = job.qualification_mappings.select_related('qualification').all()
    skills = [s.strip() for s in (job.skills_required or '').split(',') if s.strip()]

    similar_jobs = JobPosting.objects.filter(
        status='active'
    ).exclude(pk=job.job_id).select_related('employer')[:4]

    context = {
        'page_title': f"{job.title} - MULTIBIZ INTERNATIONAL CORPORATION",
        'current_page': 'job_details_public.php',
        'job': job,
        'qualifications': qualifications,
        'skills': skills,
        'similar_jobs': similar_jobs,
    }
    return render(request, 'public/job_details_public.html', context)


def news_details_view(request):
    """Public news article detail page matching news_details.php."""
    news_id = request.GET.get('id')
    article = get_object_or_404(CmsNews, pk=news_id, is_active=True)
    article.views = (article.views or 0) + 1
    article.save(update_fields=['views'])

    context = {
        'page_title': f'{article.title} - MULTIBIZ INTERNATIONAL CORPORATION',
        'current_page': 'news_details.php',
        'article': article,
    }
    return render(request, 'public/news_details.html', context)


@csrf_exempt
def contact_handler_view(request):
    """
    JSON endpoint handling contact form submissions matching contact_handler.php.
    """
    if request.method != 'POST':
        return JsonResponse({'success': False, 'errors': ['Invalid request method']}, status=405)

    name = request.POST.get('name', '').strip()
    email = request.POST.get('email', '').strip()
    subject = request.POST.get('subject', 'General Inquiry').strip()
    message = request.POST.get('message', '').strip()

    errors = []
    if not name:
        errors.append('Name is required.')
    elif len(name) > 150:
        errors.append('Name is too long (max 150 chars).')

    if not email:
        errors.append('Email is required.')
    elif '@' not in email or '.' not in email:
        errors.append('Invalid email format.')

    if not subject:
        subject = 'General Inquiry'
    elif len(subject) > 255:
        errors.append('Subject is too long (max 255 chars).')

    if not message:
        errors.append('Message is required.')
    elif len(message) < 10:
        errors.append('Message is too short (min 10 characters).')

    if errors:
        return JsonResponse({'success': False, 'errors': errors})

    ip = request.META.get('HTTP_X_FORWARDED_FOR', request.META.get('REMOTE_ADDR'))

    try:
        ContactInquiry.objects.create(
            name=name,
            email=email,
            subject=subject,
            message=message,
            ip_address=ip
        )
    except Exception as e:
        # Fallback log write
        try:
            log_dir = settings.BASE_DIR / 'logs'
            os.makedirs(log_dir, exist_ok=True)
            log_file = log_dir / 'contact_messages.log'
            with open(log_file, 'a', encoding='utf-8') as f:
                f.write(f"[{ip}] {name} <{email}> | Subject: {subject} | {message}\n")
        except Exception:
            pass

    return JsonResponse({
        'success': True,
        'message': f"Thank you, {name}! Your message has been received. We'll get back to you soon."
    })


def _resume_response(request, applicant, resume_reference, application=None):
    user_id = request.session.get('user_id')
    if not str(user_id or '').isdigit():
        return HttpResponseNotFound()

    user = User.objects.filter(pk=int(user_id), status='active').only(
        'user_id', 'role'
    ).first()
    if user is None:
        return HttpResponseNotFound()

    is_owner = applicant.user_id == user.user_id
    is_admin = user.role == 'admin'
    is_forwarded_employer = (
        user.role == 'employer'
        and application is not None
        and application.job.employer.user_id == user.user_id
        and application.forwarded_to_employer
    )
    if not (is_owner or is_admin or is_forwarded_employer):
        return HttpResponseNotFound()

    try:
        content, filename = read_resume(resume_reference)
    except FileNotFoundError:
        return HttpResponseNotFound()
    except (OSError, ValueError):
        logger.exception('Invalid or unavailable resume reference.')
        return HttpResponseServerError('Resume could not be retrieved.')
    except ResumeStorageError:
        logger.exception('Resume storage request failed.')
        return HttpResponseServerError('Resume storage is temporarily unavailable.')

    content_type = mimetypes.guess_type(filename)[0] or 'application/octet-stream'
    response = FileResponse(
        io.BytesIO(content),
        as_attachment=True,
        filename=os.path.basename(filename),
        content_type=content_type,
    )
    response['Cache-Control'] = 'private, no-store'
    response['X-Content-Type-Options'] = 'nosniff'
    return response


def applicant_resume_view(request, applicant_id):
    applicant = get_object_or_404(Applicant, pk=applicant_id)
    if not applicant.resume_file:
        return HttpResponseNotFound()
    return _resume_response(request, applicant, applicant.resume_file)


def application_resume_view(request, application_id):
    application = get_object_or_404(
        Application.objects.select_related('applicant', 'job__employer'),
        pk=application_id,
    )
    resume_reference = application.resume_file or application.applicant.resume_file
    if not resume_reference:
        return HttpResponseNotFound()
    return _resume_response(
        request,
        application.applicant,
        resume_reference,
        application=application,
    )


def smart_media_serve(request, path):
    """
    Serves public media, but never serves private resume documents directly.
    """
    import os
    from django.views.static import serve
    from django.http import FileResponse

    normalized_path = path.replace('\\', '/').lower()
    if (
        normalized_path.startswith(('resumes/', 'uploads/resumes/'))
        or normalized_path.endswith(('.pdf', '.docx', '.doc'))
    ):
        return HttpResponseNotFound()

    full_path = os.path.join(settings.MEDIA_ROOT, path)
    if os.path.exists(full_path) and os.path.isfile(full_path):
        return serve(request, path, document_root=str(settings.MEDIA_ROOT))

    # Fallback for missing profile images
    if 'profile_pics' in path or path.lower().endswith(('.png', '.jpg', '.jpeg', '.webp')):
        fallback_img = settings.BASE_DIR / 'static' / 'images' / 'picture1.png'
        if fallback_img.exists():
            return FileResponse(open(fallback_img, 'rb'), content_type='image/png')

    return serve(request, path, document_root=str(settings.MEDIA_ROOT))
