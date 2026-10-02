import json
import os
import sys
import tempfile
import types
from decimal import Decimal
from unittest.mock import Mock, patch
from django.test import TestCase, Client
from django.urls import reverse
from app.models import (
    User, Applicant, Employer, JobPosting, Application,
    Qualification, Skill, ContactInquiry, Message, AuditTrail,
    CmsHeroSlide, CmsBrand, CmsTestimonial
)
from app.auth_utils import verify_password, hash_password

class MultiBizConversionTests(TestCase):
    def setUp(self):
        self.client = Client()

        # Create Admin
        self.admin_user = User.objects.create(
            email='admin@gmail.com',
            password=hash_password('admin123'),
            role='admin',
            first_name='Admin',
            last_name='User',
            status='active'
        )

        # Create Employer
        self.employer_user = User.objects.create(
            email='employer@gmail.com',
            password=hash_password('employer123'),
            role='employer',
            first_name='Employer',
            last_name='User',
            status='active'
        )
        self.employer = Employer.objects.create(
            user=self.employer_user,
            company_name='MultiBiz Corporation',
            industry='Technology'
        )

        # Create Applicant
        self.applicant_user = User.objects.create(
            email='jobseeker@gmail.com',
            password=hash_password('jobseeker123'),
            role='applicant',
            first_name='Job',
            last_name='Seeker',
            status='active'
        )
        self.applicant = Applicant.objects.create(
            user=self.applicant_user,
            skills='Python, Django, JavaScript, HTML, CSS',
            qualifications='Bachelor of Science in Information Technology',
            experience_years=3,
            education_level='Bachelor',
            employability_score=Decimal('85.50')
        )

        # Create Qualification
        self.qualification = Qualification.objects.create(
            name='Information Technology',
            description='IT and Software Engineering',
            status='active'
        )

        # Create Job Posting
        self.job = JobPosting.objects.create(
            employer=self.employer,
            title='Senior Full Stack Developer',
            description='Exciting full-stack development role building modern enterprise systems.',
            requirements='Python, Django, JavaScript, CSS experience.',
            skills_required='Python, Django, JavaScript',
            location='Manila, Philippines',
            employment_type='full-time',
            salary_range='PHP 80,000 - 120,000',
            status='active'
        )

    def test_password_verification(self):
        """Test password verification with bcrypt compatible with PHP"""
        self.assertTrue(verify_password('admin123', self.admin_user.password))
        self.assertTrue(verify_password('employer123', self.employer_user.password))
        self.assertTrue(verify_password('jobseeker123', self.applicant_user.password))
        self.assertFalse(verify_password('wrongpass', self.admin_user.password))

    def test_public_pages(self):
        """Test public routes render properly"""
        pages = ['/', '/about/', '/services/', '/solutions/', '/careers/', '/loginregister.php']
        for url in pages:
            response = self.client.get(url)
            self.assertEqual(response.status_code, 200, f"Failed on URL: {url}")

    def test_google_drive_service_account_upload_uses_shared_drive_support(self):
        """Service account uploads should request shared-drive support for compatible parent folders."""
        with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False) as f:
            json.dump({
                'type': 'service_account',
                'client_email': 'example@project.iam.gserviceaccount.com',
                'private_key': '-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----\n',
                'private_key_id': 'id123',
                'project_id': 'demo-project'
            }, f)
            service_account_path = f.name

        def fake_from_service_account_file(path, scopes):
            self.assertEqual(path, service_account_path)
            self.assertIn('https://www.googleapis.com/auth/drive', scopes)
            return object()

        fake_drive = Mock()
        fake_file = Mock()
        fake_create = Mock()
        fake_create.execute.return_value = {'id': 'file-123', 'name': 'demo.xlsx', 'webViewLink': 'https://example.com'}
        fake_file.create.return_value = fake_create
        fake_drive.files.return_value = fake_file

        fake_google = types.ModuleType('google')
        fake_oauth2 = types.ModuleType('google.oauth2')
        fake_service_account = types.ModuleType('google.oauth2.service_account')
        fake_service_account.Credentials = types.SimpleNamespace(from_service_account_file=fake_from_service_account_file)
        fake_oauth2.service_account = fake_service_account
        fake_google.oauth2 = fake_oauth2
        fake_discovery = types.ModuleType('googleapiclient.discovery')
        fake_discovery.build = Mock(return_value=fake_drive)
        fake_http = types.ModuleType('googleapiclient.http')
        fake_http.MediaIoBaseUpload = Mock()

        original_google = sys.modules.get('google')
        original_oauth2 = sys.modules.get('google.oauth2')
        original_service_account = sys.modules.get('google.oauth2.service_account')
        original_discovery = sys.modules.get('googleapiclient.discovery')
        original_http = sys.modules.get('googleapiclient.http')

        try:
            sys.modules['google'] = fake_google
            sys.modules['google.oauth2'] = fake_oauth2
            sys.modules['google.oauth2.service_account'] = fake_service_account
            sys.modules['googleapiclient.discovery'] = fake_discovery
            sys.modules['googleapiclient.http'] = fake_http

            from app.services import google_drive_service
            with patch('app.services.google_drive_service._upload_with_oauth_user', return_value={'success': False, 'error': 'oauth_missing'}):
                with self.settings(GOOGLE_SERVICE_ACCOUNT_FILE=service_account_path):
                    result = google_drive_service.upload_excel_to_google_drive(b'abc', 'demo.xlsx', folder_id='folder-123')
        finally:
            if original_google is not None:
                sys.modules['google'] = original_google
            else:
                sys.modules.pop('google', None)
            if original_oauth2 is not None:
                sys.modules['google.oauth2'] = original_oauth2
            else:
                sys.modules.pop('google.oauth2', None)
            if original_service_account is not None:
                sys.modules['google.oauth2.service_account'] = original_service_account
            else:
                sys.modules.pop('google.oauth2.service_account', None)
            if original_discovery is not None:
                sys.modules['googleapiclient.discovery'] = original_discovery
            else:
                sys.modules.pop('googleapiclient.discovery', None)
            if original_http is not None:
                sys.modules['googleapiclient.http'] = original_http
            else:
                sys.modules.pop('googleapiclient.http', None)

        self.assertTrue(result['success'])
        fake_file.create.assert_called_once()
        kwargs = fake_file.create.call_args.kwargs
        self.assertTrue(kwargs['supportsAllDrives'])

        os.unlink(service_account_path)

    def test_contact_form_submission(self):
        """Test contact inquiry submission"""
        response = self.client.post('/includes/handlers/contact_handler.php', {
            'name': 'Test User',
            'email': 'test@example.com',
            'subject': 'Enterprise Inquiry',
            'message': 'We are interested in your managed services solutions.'
        })
        self.assertEqual(response.status_code, 200)
        data = response.json()
        self.assertTrue(data['success'])
        self.assertTrue(ContactInquiry.objects.filter(email='test@example.com').exists())

    def test_applicant_login_and_dashboard(self):
        """Test applicant authentication and dashboard access"""
        response = self.client.post('/loginregister.php', {
            'email': 'jobseeker@gmail.com',
            'password': 'jobseeker123'
        })
        self.assertEqual(response.status_code, 302)
        self.assertEqual(self.client.session.get('role'), 'applicant')

        # Access applicant dashboard
        dash_response = self.client.get('/applicant/dashboard.php')
        self.assertEqual(dash_response.status_code, 200)
        self.assertContains(dash_response, 'Job')

    def test_login_switches_to_the_new_applicant_dashboard(self):
        second_user = User.objects.create(
            email='second-applicant@gmail.com',
            password=hash_password('second123'),
            role='applicant',
            first_name='Second',
            last_name='Applicant',
            status='active',
        )
        second_applicant = Applicant.objects.create(
            user=second_user,
            skills='Rust, Go',
            qualifications='Software Engineering',
            experience_years=1,
        )

        first_login = self.client.post('/loginregister.php', {
            'email': self.applicant_user.email,
            'password': 'jobseeker123',
        })
        self.assertEqual(first_login.status_code, 302)

        second_login = self.client.post('/loginregister.php', {
            'email': second_user.email,
            'password': 'second123',
        })
        self.assertEqual(second_login.status_code, 302)

        dashboard = self.client.get('/applicant/dashboard.php')
        self.assertEqual(dashboard.status_code, 200)
        self.assertEqual(dashboard.context['applicant'].pk, second_applicant.pk)
        self.assertEqual(dashboard.context['candidate_skills'], ['Rust', 'Go'])

    def test_dashboard_recommendations_require_resume_and_show_top_four(self):
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        jobs = [self.job]
        for index in range(4):
            jobs.append(JobPosting.objects.create(
                employer=self.employer,
                title=f'Recommendation Job {index}',
                description='Test job description',
                requirements='Test requirements',
                skills_required='Python',
                location='Manila',
                employment_type='full-time',
                status='active',
            ))

        scores = {job.job_id: score for job, score in zip(jobs, [12, 39, 22, 99, 83])}
        with patch('app.views.views_applicant.compute_job_match_score', side_effect=lambda applicant, job, answers: scores[job.job_id]):
            response = self.client.get('/applicant/dashboard.php')
            self.assertEqual(response.status_code, 200)
            self.assertEqual(response.context['recommended_jobs'], [])

            self.applicant.resume_file = 'resumes/test-resume.pdf'
            self.applicant.save(update_fields=['resume_file'])
            response = self.client.get('/applicant/dashboard.php')

        self.assertEqual(response.status_code, 200)
        recommended = response.context['recommended_jobs']
        self.assertEqual(len(recommended), 4)
        self.assertEqual(
            [item['match_score'] for item in recommended],
            [99, 83, 39, 22],
        )

    def test_browse_job_matches_are_zero_until_resume_uploaded(self):
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        with patch('app.views.views_applicant.compute_applicant_job_match', return_value=68) as score_match:
            response = self.client.get('/applicant/jobs.php')
            self.assertEqual(response.status_code, 200)
            self.assertEqual(
                [item['match_score'] for item in response.context['scored_jobs']],
                [0],
            )
            score_match.assert_not_called()

            self.applicant.resume_file = 'resumes/test-resume.pdf'
            self.applicant.save(update_fields=['resume_file'])
            response = self.client.get('/applicant/jobs.php')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(
            [item['match_score'] for item in response.context['scored_jobs']],
            [68],
        )
        score_match.assert_called_once()

    def test_job_application_flow(self):
        """Test applicant applying for a job"""
        # Log in applicant
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session['email'] = self.applicant_user.email
        session['first_name'] = self.applicant_user.first_name
        session['last_name'] = self.applicant_user.last_name
        session.save()

        # Apply for job
        response = self.client.post(f'/applicant/apply_job.php?id={self.job.job_id}', {
            'cover_letter': 'I am thrilled to apply for this full stack role.',
            'use_existing_resume': '1'
        })
        self.assertEqual(response.status_code, 302)
        
        # Verify application created
        app = Application.objects.filter(job=self.job, applicant=self.applicant).first()
        self.assertIsNotNone(app)
        self.assertEqual(app.status, 'pending')

    def test_employer_candidate_review_and_status_update(self):
        """Test employer viewing candidate and updating status"""
        # Create an application
        app = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='pending',
            match_score=Decimal('88.00')
        )

        # Log in as employer
        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session['email'] = self.employer_user.email
        session['first_name'] = self.employer_user.first_name
        session['last_name'] = self.employer_user.last_name
        session.save()

        # View candidates Kanban board
        cand_resp = self.client.get('/employer/candidates.php')
        self.assertEqual(cand_resp.status_code, 200)

        # Update application status via API
        update_resp = self.client.post('/employer/update_status.php', {
            'application_id': app.application_id,
            'status': 'shortlisted',
            'remarks': 'Outstanding technical profile.'
        })
        self.assertEqual(update_resp.status_code, 200)
        app.refresh_from_db()
        self.assertEqual(app.status, 'shortlisted')

    def test_chat_messaging_system(self):
        """Test real-time messaging between employer and applicant"""
        # Log in as employer
        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session['email'] = self.employer_user.email
        session.save()

        # Send message to applicant
        send_resp = self.client.post('/includes/handlers/message_handler.php', {
            'action': 'send',
            'receiver_id': self.applicant_user.user_id,
            'message': 'Hello Job Seeker, we would like to invite you for an interview.'
        })
        self.assertEqual(send_resp.status_code, 200)
        send_data = send_resp.json()
        self.assertTrue(send_data['success'])

        # Switch to applicant session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session['email'] = self.applicant_user.email
        session.save()

        # Fetch messages
        get_resp = self.client.get(f'/includes/handlers/message_handler.php?action=get_messages&other_user_id={self.employer_user.user_id}')
        self.assertEqual(get_resp.status_code, 200)
        get_data = get_resp.json()
        self.assertTrue(get_data['success'])
        self.assertEqual(len(get_data['messages']), 1)
        self.assertEqual(get_data['messages'][0]['message'], 'Hello Job Seeker, we would like to invite you for an interview.')

    def test_admin_portal_operations(self):
        """Test admin operations and audit logging"""
        # Log in as admin
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session['email'] = self.admin_user.email
        session.save()

        # View admin dashboard
        dash_resp = self.client.get('/admin/dashboard.php')
        self.assertEqual(dash_resp.status_code, 200)

        # Create new employer from admin panel
        create_emp_resp = self.client.post('/admin/users.php', {
            'action': 'create_employer',
            'email': 'newpartner@gmail.com',
            'password': 'partnerpassword123',
            'first_name': 'Partner',
            'last_name': 'Lead',
            'company_name': 'New Global Partner Inc.',
            'industry': 'Finance'
        })
        self.assertEqual(create_emp_resp.status_code, 200)
        self.assertTrue(User.objects.filter(email='newpartner@gmail.com').exists())
        self.assertTrue(Employer.objects.filter(company_name='New Global Partner Inc.').exists())

        # Check audit trail was logged
        audit = AuditTrail.objects.filter(action_type='create_employer').last()
        self.assertIsNotNone(audit)
        self.assertEqual(audit.target_name, 'New Global Partner Inc.')
