from django.contrib import admin
from .models import (
    User, Applicant, Employer, Qualification, JobPosting, JobQualificationMapping,
    Skill, Application, SavedJob, Message, Notification, InterviewSchedule,
    Feedback, CandidateFeedback, JobRecommendation, CandidateRecommendation,
    ResumeAnalysis, ChatbotAnswer, ChatbotRecommendation, ContactInquiry,
    ContactReply, AuditTrail, CmsSection, CmsContent, CmsHeroSlide,
    CmsTestimonial, CmsBrand, CmsNews, CandidateMlFeature,
    MlApplicationScreening, MlFeatureImportance, MlModelPerformance
)

@admin.register(User)
class UserAdmin(admin.ModelAdmin):
    list_display = ('user_id', 'email', 'role', 'first_name', 'last_name', 'status', 'created_at')
    search_fields = ('email', 'first_name', 'last_name')
    list_filter = ('role', 'status')

@admin.register(Applicant)
class ApplicantAdmin(admin.ModelAdmin):
    list_display = ('applicant_id', 'user', 'experience_years', 'education_level', 'employability_score', 'profile_completed')
    search_fields = ('user__email', 'user__first_name', 'user__last_name', 'skills')

@admin.register(Employer)
class EmployerAdmin(admin.ModelAdmin):
    list_display = ('employer_id', 'user', 'company_name', 'industry', 'company_size')
    search_fields = ('company_name', 'user__email')

@admin.register(JobPosting)
class JobPostingAdmin(admin.ModelAdmin):
    list_display = ('job_id', 'title', 'employer', 'employment_type', 'status', 'posted_at')
    search_fields = ('title', 'employer__company_name')
    list_filter = ('status', 'employment_type')

@admin.register(Application)
class ApplicationAdmin(admin.ModelAdmin):
    list_display = ('application_id', 'job', 'applicant', 'status', 'match_score', 'applied_at')
    list_filter = ('status',)

admin.site.register(Qualification)
admin.site.register(JobQualificationMapping)
admin.site.register(Skill)
admin.site.register(SavedJob)
admin.site.register(Message)
admin.site.register(Notification)
admin.site.register(InterviewSchedule)
admin.site.register(Feedback)
admin.site.register(CandidateFeedback)
admin.site.register(JobRecommendation)
admin.site.register(CandidateRecommendation)
admin.site.register(ResumeAnalysis)
admin.site.register(ChatbotAnswer)
admin.site.register(ChatbotRecommendation)
admin.site.register(ContactInquiry)
admin.site.register(ContactReply)
admin.site.register(AuditTrail)
admin.site.register(CmsSection)
admin.site.register(CmsContent)
admin.site.register(CmsHeroSlide)
admin.site.register(CmsTestimonial)
admin.site.register(CmsBrand)
admin.site.register(CmsNews)
admin.site.register(CandidateMlFeature)
admin.site.register(MlApplicationScreening)
admin.site.register(MlFeatureImportance)
admin.site.register(MlModelPerformance)
