from django.db import models
from django.utils import timezone

class User(models.Model):
    ROLE_CHOICES = [
        ('applicant', 'Applicant'),
        ('employer', 'Employer'),
        ('admin', 'Admin'),
    ]
    STATUS_CHOICES = [
        ('active', 'Active'),
        ('inactive', 'Inactive'),
        ('suspended', 'Suspended'),
    ]

    user_id = models.AutoField(primary_key=True)
    email = models.CharField(max_length=255, unique=True)
    password = models.CharField(max_length=255)
    role = models.CharField(max_length=20, choices=ROLE_CHOICES)
    first_name = models.CharField(max_length=100, null=True, blank=True)
    last_name = models.CharField(max_length=100, null=True, blank=True)
    phone = models.CharField(max_length=20, null=True, blank=True)
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='active')
    created_by_admin = models.BooleanField(default=False)
    created_at = models.DateTimeField(default=timezone.now)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'users'
        verbose_name = 'User'
        verbose_name_plural = 'Users'

    def __str__(self):
        return f"{self.email} ({self.role})"

    @property
    def full_name(self):
        return f"{self.first_name or ''} {self.last_name or ''}".strip()


class Applicant(models.Model):
    applicant_id = models.AutoField(primary_key=True)
    user = models.ForeignKey(User, on_delete=models.CASCADE, db_column='user_id', related_name='applicant_profile')
    resume_file = models.CharField(max_length=255, null=True, blank=True)
    skills = models.TextField(null=True, blank=True)
    qualifications = models.TextField(null=True, blank=True)
    experience_years = models.IntegerField(default=0)
    education_level = models.CharField(max_length=100, null=True, blank=True)
    employability_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    profile_completed = models.BooleanField(default=False)
    profile_pic = models.CharField(max_length=255, null=True, blank=True)

    class Meta:
        db_table = 'applicants'


class Employer(models.Model):
    employer_id = models.AutoField(primary_key=True)
    user = models.ForeignKey(User, on_delete=models.CASCADE, db_column='user_id', related_name='employer_profile')
    company_name = models.CharField(max_length=255, null=True, blank=True)
    company_logo = models.CharField(max_length=500, null=True, blank=True)
    company_address = models.TextField(null=True, blank=True)
    company_website = models.CharField(max_length=255, null=True, blank=True)
    industry = models.CharField(max_length=100, null=True, blank=True)
    company_size = models.CharField(max_length=50, null=True, blank=True)

    class Meta:
        db_table = 'employers'


class Qualification(models.Model):
    qualification_id = models.AutoField(primary_key=True)
    name = models.CharField(max_length=255)
    description = models.TextField(null=True, blank=True)
    status = models.CharField(max_length=20, default='active')
    created_at = models.DateTimeField(default=timezone.now)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'qualifications'

    def __str__(self):
        return self.name


class JobPosting(models.Model):
    EMPLOYMENT_TYPE_CHOICES = [
        ('full-time', 'Full-time'),
        ('part-time', 'Part-time'),
        ('contract', 'Contract'),
        ('internship', 'Internship'),
    ]
    STATUS_CHOICES = [
        ('pending', 'Pending Review'),
        ('approved', 'Approved'),
        ('rejected', 'Rejected'),
        ('active', 'Active / Posted'),
        ('closed', 'Closed'),
        ('draft', 'Draft'),
    ]

    job_id = models.AutoField(primary_key=True)
    employer = models.ForeignKey(Employer, on_delete=models.CASCADE, db_column='employer_id', related_name='job_postings')
    title = models.CharField(max_length=255)
    description = models.TextField(null=True, blank=True)
    requirements = models.TextField(null=True, blank=True)
    skills_required = models.TextField(null=True, blank=True)
    location = models.CharField(max_length=255, null=True, blank=True)
    employment_type = models.CharField(max_length=20, choices=EMPLOYMENT_TYPE_CHOICES, default='full-time')
    salary_range = models.CharField(max_length=100, null=True, blank=True)
    positions_available = models.IntegerField(default=1)
    urgency = models.CharField(max_length=50, default='Normal', null=True, blank=True)
    special_notes = models.TextField(null=True, blank=True)
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='pending')
    rejection_reason = models.TextField(null=True, blank=True)
    admin_notes = models.TextField(null=True, blank=True)
    target_qualifications = models.TextField(null=True, blank=True, help_text="Comma-separated qualification IDs")
    posted_at = models.DateTimeField(default=timezone.now)
    approved_at = models.DateTimeField(null=True, blank=True)
    reviewed_by_admin = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, blank=True, related_name='reviewed_jobs')
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'job_postings'


class JobQualificationMapping(models.Model):
    mapping_id = models.AutoField(primary_key=True)
    job = models.ForeignKey(JobPosting, on_delete=models.CASCADE, db_column='job_id', related_name='qualification_mappings')
    qualification = models.ForeignKey(Qualification, on_delete=models.CASCADE, db_column='qualification_id')
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'job_qualification_mapping'


class Skill(models.Model):
    skill_id = models.AutoField(primary_key=True)
    skill_name = models.CharField(max_length=255)
    category = models.CharField(max_length=100, null=True, blank=True)
    status = models.CharField(max_length=20, default='active')
    created_at = models.DateTimeField(default=timezone.now)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'skills'


class Application(models.Model):
    STATUS_CHOICES = [
        ('pending', 'Pending Admin Review'),
        ('forwarded', 'Forwarded to Employer'),
        ('for_review', 'For Employer Review'),
        ('qualified', 'Qualified'),
        ('not_qualified', 'Not Qualified'),
        ('for_interview', 'For Interview'),
        ('interviewed', 'Interviewed'),
        ('interview_completed', 'Interview Completed'),
        ('accepted', 'Hired / Accepted'),
        ('hired', 'Hired'),
        ('rejected', 'Rejected'),
        ('reviewed', 'Reviewed'),
        ('shortlisted', 'Shortlisted'),
    ]

    EMPLOYER_STATUS_CHOICES = [
        ('for_review', 'For Review'),
        ('qualified', 'Qualified'),
        ('not_qualified', 'Not Qualified'),
        ('for_interview', 'For Interview'),
        ('interview_completed', 'Interview Completed'),
        ('hired', 'Hired'),
        ('rejected', 'Rejected'),
    ]

    application_id = models.AutoField(primary_key=True)
    job = models.ForeignKey(JobPosting, on_delete=models.CASCADE, db_column='job_id', related_name='applications')
    applicant = models.ForeignKey(Applicant, on_delete=models.CASCADE, db_column='applicant_id', related_name='applications')
    status = models.CharField(max_length=30, choices=STATUS_CHOICES, default='pending')
    forwarded_to_employer = models.BooleanField(default=False)
    forwarded_at = models.DateTimeField(null=True, blank=True)
    forwarded_by_admin = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, blank=True, related_name='forwarded_applications')
    admin_qualification = models.CharField(max_length=50, default='pending')
    admin_notes = models.TextField(null=True, blank=True)
    employer_status = models.CharField(max_length=50, choices=EMPLOYER_STATUS_CHOICES, default='for_review')
    employer_notes = models.TextField(null=True, blank=True)
    status_history = models.JSONField(default=list, blank=True)
    remarks_history = models.TextField(null=True, blank=True)
    applicant_remarks_history = models.TextField(null=True, blank=True)
    reviewed_by_employer_id = models.IntegerField(null=True, blank=True)
    reviewed_by_name = models.CharField(max_length=255, null=True, blank=True)
    match_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    cover_letter = models.TextField(null=True, blank=True)
    resume_file = models.CharField(max_length=255, null=True, blank=True)
    applied_at = models.DateTimeField(default=timezone.now)
    updated_at = models.DateTimeField(auto_now=True)
    classification = models.CharField(max_length=50, null=True, blank=True)
    classified_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        db_table = 'applications'


class SavedJob(models.Model):
    id = models.AutoField(primary_key=True)
    applicant = models.ForeignKey(Applicant, on_delete=models.CASCADE, db_column='applicant_id', related_name='saved_jobs')
    job = models.ForeignKey(JobPosting, on_delete=models.CASCADE, db_column='job_id', related_name='saved_by_applicants')
    saved_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'saved_jobs'


class Message(models.Model):
    message_id = models.AutoField(primary_key=True)
    sender = models.ForeignKey(User, on_delete=models.CASCADE, db_column='sender_id', related_name='sent_messages')
    receiver = models.ForeignKey(User, on_delete=models.CASCADE, db_column='receiver_id', related_name='received_messages')
    message = models.TextField()
    is_read = models.BooleanField(default=False)
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'messages'


class Notification(models.Model):
    TYPE_CHOICES = [
        ('application', 'Application'),
        ('recommendation', 'Recommendation'),
        ('system', 'System'),
        ('job_update', 'Job Update'),
    ]

    notification_id = models.AutoField(primary_key=True)
    user = models.ForeignKey(User, on_delete=models.CASCADE, db_column='user_id', related_name='notifications')
    title = models.CharField(max_length=255)
    message = models.TextField(null=True, blank=True)
    type = models.CharField(max_length=30, choices=TYPE_CHOICES, default='system')
    is_read = models.BooleanField(default=False)
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'notifications'


class InterviewSchedule(models.Model):
    id = models.AutoField(primary_key=True)
    application = models.ForeignKey(Application, on_delete=models.CASCADE, db_column='application_id', related_name='interviews')
    employer = models.ForeignKey(Employer, on_delete=models.CASCADE, db_column='employer_id')
    interview_date = models.DateField()
    start_time = models.TimeField(null=True, blank=True)
    end_time = models.TimeField(null=True, blank=True)
    interview_time = models.CharField(max_length=50, null=True, blank=True)
    interview_type = models.CharField(max_length=50, default='Online', help_text="Online, In-person, phone, video")
    interviewer_name = models.CharField(max_length=255, null=True, blank=True)
    location = models.CharField(max_length=255, null=True, blank=True)
    meeting_link = models.CharField(max_length=500, null=True, blank=True)
    instructions = models.TextField(null=True, blank=True)
    notes = models.TextField(null=True, blank=True)
    status = models.CharField(max_length=20, default='scheduled', help_text="scheduled, completed, cancelled, rescheduled")
    created_at = models.DateTimeField(default=timezone.now)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'interview_schedules'


class Feedback(models.Model):
    feedback_id = models.AutoField(primary_key=True)
    user = models.ForeignKey(User, on_delete=models.CASCADE, db_column='user_id')
    recommendation_id = models.IntegerField(null=True, blank=True)
    feedback_type = models.CharField(max_length=50)
    rating = models.IntegerField(null=True, blank=True)
    comments = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'feedback'


class CandidateFeedback(models.Model):
    feedback_id = models.AutoField(primary_key=True)
    application_id = models.IntegerField()
    applicant_id = models.IntegerField()
    employer_id = models.IntegerField()
    employability_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    feedback_message = models.TextField()
    feedback_type = models.CharField(max_length=50, default='automatic')
    created_at = models.DateTimeField(default=timezone.now)
    updated_at = models.DateTimeField(null=True, blank=True, auto_now=True)

    class Meta:
        db_table = 'candidate_feedback'


class JobRecommendation(models.Model):
    recommendation_id = models.AutoField(primary_key=True)
    applicant = models.ForeignKey(Applicant, on_delete=models.CASCADE, db_column='applicant_id')
    job = models.ForeignKey(JobPosting, on_delete=models.CASCADE, db_column='job_id')
    recommendation_score = models.DecimalField(max_digits=5, decimal_places=2)
    reason = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'job_recommendations'


class CandidateRecommendation(models.Model):
    recommendation_id = models.AutoField(primary_key=True)
    employer = models.ForeignKey(Employer, on_delete=models.CASCADE, db_column='employer_id')
    job = models.ForeignKey(JobPosting, on_delete=models.CASCADE, db_column='job_id')
    applicant = models.ForeignKey(Applicant, on_delete=models.CASCADE, db_column='applicant_id')
    recommendation_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    reason = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'candidate_recommendations'


class ResumeAnalysis(models.Model):
    analysis_id = models.AutoField(primary_key=True)
    applicant = models.ForeignKey(Applicant, on_delete=models.CASCADE, db_column='applicant_id')
    resume_file = models.CharField(max_length=255)
    extracted_text = models.TextField(null=True, blank=True)
    skills_extracted = models.TextField(null=True, blank=True)
    education_extracted = models.TextField(null=True, blank=True)
    experience_extracted = models.TextField(null=True, blank=True)
    qualifications_extracted = models.TextField(null=True, blank=True)
    analysis_date = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'resume_analysis'


class ChatbotAnswer(models.Model):
    answer_id = models.AutoField(primary_key=True)
    applicant = models.ForeignKey(Applicant, on_delete=models.CASCADE, db_column='applicant_id')
    qualification = models.ForeignKey(Qualification, on_delete=models.SET_NULL, null=True, blank=True, db_column='qualification_id')
    question_number = models.IntegerField(default=1)
    question_text = models.TextField(default='')
    answer_text = models.TextField(default='')
    answer_value = models.CharField(max_length=255, null=True, blank=True)
    category = models.CharField(max_length=255, default='general')
    score_value = models.IntegerField(default=0)
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'chatbot_answers'


class ChatbotRecommendation(models.Model):
    recommendation_id = models.AutoField(primary_key=True)
    applicant = models.ForeignKey(Applicant, on_delete=models.CASCADE, db_column='applicant_id')
    recommended_role = models.CharField(max_length=255, null=True, blank=True)
    confidence_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    matched_skills = models.TextField(null=True, blank=True)
    missing_skills = models.TextField(null=True, blank=True)
    reasoning = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'chatbot_recommendations'


class ContactInquiry(models.Model):
    STATUS_CHOICES = [
        ('new', 'New'),
        ('open', 'Open'),
        ('replied', 'Replied'),
        ('closed', 'Closed'),
    ]

    id = models.AutoField(primary_key=True)
    name = models.CharField(max_length=150)
    email = models.CharField(max_length=255)
    subject = models.CharField(max_length=255, default='General Inquiry')
    message = models.TextField()
    is_read = models.BooleanField(default=False)
    status = models.CharField(max_length=20, choices=STATUS_CHOICES, default='new')
    ip_address = models.CharField(max_length=45, null=True, blank=True)
    created_at = models.DateTimeField(default=timezone.now)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'contact_inquiries'


class ContactReply(models.Model):
    id = models.AutoField(primary_key=True)
    inquiry = models.ForeignKey(ContactInquiry, on_delete=models.CASCADE, db_column='inquiry_id', related_name='replies')
    admin = models.ForeignKey(User, on_delete=models.SET_NULL, null=True, blank=True, db_column='admin_id')
    reply_text = models.TextField()
    sent_at = models.DateTimeField(default=timezone.now)
    email_sent = models.BooleanField(default=False)

    class Meta:
        db_table = 'contact_replies'


class AuditTrail(models.Model):
    audit_id = models.AutoField(primary_key=True)
    admin_user = models.ForeignKey(User, on_delete=models.CASCADE, db_column='admin_user_id')
    admin_name = models.CharField(max_length=255)
    action_type = models.CharField(max_length=100)
    action_description = models.TextField()
    target_type = models.CharField(max_length=50, null=True, blank=True)
    target_id = models.IntegerField(null=True, blank=True)
    target_name = models.CharField(max_length=255, null=True, blank=True)
    ip_address = models.CharField(max_length=45, null=True, blank=True)
    user_agent = models.TextField(null=True, blank=True)
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'audit_trail'


class CmsSection(models.Model):
    CONTENT_TYPE_CHOICES = [
        ('text', 'Text'),
        ('html', 'HTML'),
        ('image', 'Image'),
        ('gallery', 'Gallery'),
        ('slider', 'Slider'),
    ]

    id = models.AutoField(primary_key=True)
    section_key = models.CharField(max_length=100, unique=True)
    section_name = models.CharField(max_length=100)
    content_type = models.CharField(max_length=20, choices=CONTENT_TYPE_CHOICES, default='text')
    created_at = models.DateTimeField(null=True, default=timezone.now)
    updated_at = models.DateTimeField(null=True, auto_now=True)

    class Meta:
        db_table = 'cms_sections'

    def __str__(self):
        return self.section_name


class CmsContent(models.Model):
    id = models.AutoField(primary_key=True)
    section = models.ForeignKey(CmsSection, on_delete=models.CASCADE, db_column='section_id', related_name='contents')
    field_key = models.CharField(max_length=100)
    field_value = models.TextField(null=True, blank=True)
    language = models.CharField(max_length=10, default='en')
    sort_order = models.IntegerField(default=0)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(null=True, default=timezone.now)
    updated_at = models.DateTimeField(null=True, auto_now=True)

    class Meta:
        db_table = 'cms_content'


class CmsHeroSlide(models.Model):
    id = models.AutoField(primary_key=True)
    title = models.CharField(max_length=255, null=True, blank=True)
    subtitle = models.TextField(null=True, blank=True)
    button_text = models.CharField(max_length=100, null=True, blank=True, default='Learn More')
    button_link = models.CharField(max_length=255, null=True, blank=True, default='#')
    image_path = models.CharField(max_length=255, null=True, blank=True)
    sort_order = models.IntegerField(default=0)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(null=True, default=timezone.now)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        db_table = 'cms_hero_slides'
        ordering = ['sort_order', 'id']


class CmsTestimonial(models.Model):
    id = models.AutoField(primary_key=True)
    author_name = models.CharField(max_length=100)
    author_role = models.CharField(max_length=100, null=True, blank=True)
    company = models.CharField(max_length=100, null=True, blank=True)
    content = models.TextField()
    rating = models.IntegerField(default=5)
    image_path = models.CharField(max_length=255, null=True, blank=True)
    sort_order = models.IntegerField(default=0)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(null=True, default=timezone.now)

    class Meta:
        db_table = 'cms_testimonials'
        ordering = ['sort_order', 'id']


class CmsBrand(models.Model):
    id = models.AutoField(primary_key=True)
    brand_name = models.CharField(max_length=255)
    brand_description = models.TextField(null=True, blank=True)
    brand_overlay_title = models.CharField(max_length=255, null=True, blank=True)
    brand_overlay_description = models.TextField(null=True, blank=True)
    brand_logo = models.CharField(max_length=500, null=True, blank=True)
    brand_category = models.CharField(max_length=50, default='cor')
    sort_order = models.IntegerField(default=0)
    is_active = models.BooleanField(default=True)
    created_at = models.DateTimeField(null=True, default=timezone.now)
    updated_at = models.DateTimeField(null=True, blank=True)

    class Meta:
        db_table = 'cms_brands'
        ordering = ['sort_order', 'id']


class CmsNews(models.Model):
    id = models.AutoField(primary_key=True)
    title = models.CharField(max_length=255)
    category = models.CharField(max_length=100, null=True, blank=True)
    excerpt = models.TextField(null=True, blank=True)
    content = models.TextField(null=True, blank=True)
    image_path = models.CharField(max_length=255, null=True, blank=True)
    news_date = models.DateField(null=True, blank=True)
    is_featured = models.BooleanField(default=False)
    is_active = models.BooleanField(default=True)
    views = models.IntegerField(default=0)
    created_at = models.DateTimeField(null=True, default=timezone.now)
    updated_at = models.DateTimeField(null=True, auto_now=True)

    class Meta:
        db_table = 'cms_news'


class CandidateMlFeature(models.Model):
    feature_id = models.AutoField(primary_key=True)
    application = models.ForeignKey(Application, on_delete=models.CASCADE, db_column='application_id')
    skills_match_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    experience_match_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    education_match_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    employability_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    overall_ml_score = models.DecimalField(max_digits=5, decimal_places=2, default=0.00)
    extracted_features = models.JSONField(null=True, blank=True)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'candidate_ml_features'


class MlApplicationScreening(models.Model):
    PREDICTION_CLASS_CHOICES = [
        ('high', 'High'),
        ('medium', 'Medium'),
        ('low', 'Low'),
    ]

    screening_id = models.AutoField(primary_key=True)
    application = models.ForeignKey(Application, on_delete=models.CASCADE, db_column='application_id')
    ml_score = models.DecimalField(max_digits=5, decimal_places=2)
    prediction_class = models.CharField(max_length=10, choices=PREDICTION_CLASS_CHOICES)
    confidence_score = models.DecimalField(max_digits=5, decimal_places=4)
    features_used = models.JSONField(null=True, blank=True)
    model_version = models.CharField(max_length=50, default='v1.0')
    created_at = models.DateTimeField(default=timezone.now)
    updated_at = models.DateTimeField(auto_now=True)

    class Meta:
        db_table = 'ml_application_screening'


class MlFeatureImportance(models.Model):
    feature_id = models.AutoField(primary_key=True)
    feature_name = models.CharField(max_length=100)
    importance_score = models.DecimalField(max_digits=5, decimal_places=4)
    model_version = models.CharField(max_length=50, default='v1.0')
    created_at = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'ml_feature_importance'


class MlModelPerformance(models.Model):
    performance_id = models.AutoField(primary_key=True)
    model_version = models.CharField(max_length=50)
    accuracy_score = models.DecimalField(max_digits=5, decimal_places=4)
    precision_score = models.DecimalField(max_digits=5, decimal_places=4)
    recall_score = models.DecimalField(max_digits=5, decimal_places=4)
    f1_score = models.DecimalField(max_digits=5, decimal_places=4)
    training_date = models.DateTimeField(default=timezone.now)

    class Meta:
        db_table = 'ml_model_performance'
