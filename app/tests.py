import json
import os
import sys
import tempfile
import types
from datetime import date, timedelta
from decimal import Decimal
from pathlib import Path
from unittest.mock import Mock, patch
from django.test import TestCase, Client, override_settings
from django.core import mail
from django.core.files.uploadedfile import SimpleUploadedFile
from django.urls import reverse
from django.utils import timezone
from app.models import (
    User, Applicant, Employer, JobPosting, Application,
    Qualification, JobQualificationMapping, Skill, ContactInquiry, Message, AuditTrail,
    Notification, InterviewSchedule, CmsHeroSlide, CmsBrand, CmsTestimonial
)
from app.auth_utils import verify_password, hash_password
from app.services.mailer import send_application_status_update_email
from app.services.resume_parser import (
    extract_resume_name,
    parse_resume_text,
    resume_name_matches,
)
from app.services.ml_ranking import calculate_candidate_ml_score, compute_job_match_result
from app.views.views_applicant import get_applicant_job_match
from django.core.cache import cache
from app.services.semantic_job_matching import (
    SemanticMatchingUnavailable,
    _load_wordpiece_tokenizer,
    _criteria_groups,
    compute_semantic_match,
)
from app.services.qualification import classify_match_score

class MultiBizConversionTests(TestCase):
    def setUp(self):
        cache.clear()
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

    def _complete_applicant_profile(self, applicant=None):
        from app.models import ResumeAnalysis

        applicant = applicant or self.applicant
        applicant.user.phone = '555-123-4567'
        applicant.user.save(update_fields=['phone'])
        applicant.resume_file = 'uploads/resumes/dashboard-test.pdf'
        applicant.skills = applicant.skills or 'Python, Django'
        applicant.education_level = applicant.education_level or 'Bachelor'
        applicant.save()
        ResumeAnalysis.objects.create(
            applicant=applicant,
            resume_file=applicant.resume_file,
            extracted_text='Applicant resume with education, skills, and experience.',
        )
        return applicant

    def test_get_applicant_job_match_skips_expensive_model_for_empty_profile(self):
        empty_user = User.objects.create(
            email='empty-applicant@example.com',
            password='test-password',
            role='applicant',
            status='active',
        )
        empty_applicant = Applicant.objects.create(
            user=empty_user,
            skills='',
            qualifications='',
            education_level='',
            experience_years=0,
        )

        with patch('app.views.views_applicant.compute_semantic_match') as mocked_match:
            result = get_applicant_job_match(empty_applicant, self.job, '')

        self.assertIsNone(result['score'])
        self.assertFalse(result['model_ready'])
        mocked_match.assert_not_called()

    def test_get_applicant_job_match_uses_cache_for_repeated_calls(self):
        self._complete_applicant_profile()
        cache.clear()
        self.applicant.skills = 'Python, Django, SQL'
        self.applicant.save(update_fields=['skills'])

        with patch('app.views.views_applicant.compute_semantic_match', return_value={'score': 91.2, 'breakdown': [{'label': 'Role', 'score': 91.2}], 'reason': ''}) as mocked_match:
            first = get_applicant_job_match(self.applicant, self.job, '')
            second = get_applicant_job_match(self.applicant, self.job, '')

        self.assertEqual(first['score'], 91.2)
        self.assertEqual(second['score'], 91.2)
        self.assertEqual(mocked_match.call_count, 1)

    def test_match_score_qualification_boundaries(self):
        expected_statuses = [
            (29.99, 'not_qualified'),
            (30, 'not_qualified'),
            (39.99, 'not_qualified'),
            (40, 'under_qualified'),
            (60, 'under_qualified'),
            (60.01, 'qualified'),
        ]
        for score, expected_status in expected_statuses:
            with self.subTest(score=score):
                self.assertEqual(classify_match_score(score), expected_status)

    def test_admin_dashboard_distribution_does_not_reuse_legacy_score_bands(self):
        for index, score in enumerate((
            Decimal('85.00'),
            Decimal('60.01'),
            Decimal('60.00'),
            Decimal('40.00'),
            Decimal('39.99'),
            Decimal('29.99'),
        )):
            user = User.objects.create(
                email=f'dashboard-band-{index}@example.com',
                password='test-password',
                role='applicant',
                status='active',
            )
            applicant = Applicant.objects.create(user=user)
            Application.objects.create(
                job=self.job,
                applicant=applicant,
                match_score=score,
            )

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/dashboard/')

        self.assertEqual(response.status_code, 200)
        chart_data = json.loads(response.context['chart_data_json'])
        self.assertEqual(
            chart_data['match_tier_labels'],
            ['High employability', 'Medium employability', 'Low employability'],
        )
        self.assertEqual(chart_data['match_tier_data'], [0, 0, 6])


    @override_settings(EMAIL_BACKEND='django.core.mail.backends.locmem.EmailBackend')
    def test_unclassified_status_email_includes_result_and_score(self):
        result = send_application_status_update_email(
            to_email=self.applicant_user.email,
            applicant_name='Job Seeker',
            job_title=self.job.title,
            company_name=self.employer.company_name,
            new_status='unclassified',
            match_score=35,
        )

        self.assertTrue(result['success'])
        self.assertIn('Unclassified', mail.outbox[0].subject)
        self.assertIn('Unclassified', mail.outbox[0].body)
        self.assertIn('35%', mail.outbox[0].body)
        self.assertNotIn('Applications Page', mail.outbox[0].alternatives[0][0])

    @override_settings(EMAIL_BACKEND='django.core.mail.backends.locmem.EmailBackend')
    def test_email_helpers_report_backend_zero_delivery_as_failure(self):
        with patch('app.services.mailer.django_send_mail', return_value=0):
            result = send_application_status_update_email(
                to_email=self.applicant_user.email,
                applicant_name='Job Seeker',
                job_title=self.job.title,
                company_name=self.employer.company_name,
                new_status='qualified',
            )

        self.assertFalse(result['success'])
        self.assertIn('sent 0 of 1', result['error'])

    @override_settings(
        EMAIL_BACKEND='django.core.mail.backends.smtp.EmailBackend',
        EMAIL_HOST_USER='',
        EMAIL_HOST_PASSWORD='',
    )
    def test_email_helpers_explain_missing_smtp_credentials(self):
        result = send_application_status_update_email(
            to_email=self.applicant_user.email,
            applicant_name='Job Seeker',
            job_title=self.job.title,
            company_name=self.employer.company_name,
            new_status='qualified',
        )

        self.assertFalse(result['success'])
        self.assertIn('EMAIL_HOST_USER', result['error'])
        self.assertIn('EMAIL_HOST_PASSWORD', result['error'])

    @patch(
        'app.views.views_admin.send_job_posted_live_notification',
        return_value={'success': True, 'error': None},
    )
    def test_admin_publish_action_dispatches_employer_email(self, send_email):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.post(
            '/admin/job_request_action/',
            {'job_id': self.job.job_id, 'action': 'post_live'},
        )

        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.json()['email_sent'])
        send_email.assert_called_once()
        self.assertEqual(
            send_email.call_args.kwargs['to_email'],
            self.employer_user.email,
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

    def test_google_drive_service_account_upload_uses_rest_client(self):
        with tempfile.NamedTemporaryFile('w', suffix='.json', delete=False) as f:
            json.dump({
                'type': 'service_account',
                'client_email': 'example@project.iam.gserviceaccount.com',
                'private_key': '-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----\n',
                'private_key_id': 'id123',
                'project_id': 'demo-project'
            }, f)
            service_account_path = f.name

        credentials = object()

        def fake_from_service_account_file(path, scopes):
            self.assertEqual(path, service_account_path)
            self.assertIn('https://www.googleapis.com/auth/drive', scopes)
            return credentials

        fake_google = types.ModuleType('google')
        fake_oauth2 = types.ModuleType('google.oauth2')
        fake_service_account = types.ModuleType('google.oauth2.service_account')
        fake_service_account.Credentials = types.SimpleNamespace(from_service_account_file=fake_from_service_account_file)
        fake_oauth2.service_account = fake_service_account
        fake_google.oauth2 = fake_oauth2

        original_google = sys.modules.get('google')
        original_oauth2 = sys.modules.get('google.oauth2')
        original_service_account = sys.modules.get('google.oauth2.service_account')

        try:
            sys.modules['google'] = fake_google
            sys.modules['google.oauth2'] = fake_oauth2
            sys.modules['google.oauth2.service_account'] = fake_service_account

            from app.services import google_drive_service
            with patch('app.services.google_drive_service._upload_with_oauth_user', return_value={'success': False, 'error': 'oauth_missing'}), patch(
                'app.services.google_drive_service._create_drive_file',
                return_value={'id': 'file-123', 'name': 'demo.xlsx', 'webViewLink': 'https://example.com'},
            ) as create_drive_file:
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

        self.assertTrue(result['success'])
        create_drive_file.assert_called_once_with(credentials, b'abc', 'demo.xlsx', 'folder-123')

        os.unlink(service_account_path)

    def test_google_drive_rest_upload_uses_multipart_and_shared_drive_support(self):
        from app.services import google_drive_service

        credentials = object()
        session = Mock()
        session.__enter__ = Mock(return_value=session)
        session.__exit__ = Mock(return_value=False)
        response = Mock()
        response.json.return_value = {
            'id': 'file-123',
            'name': 'demo.xlsx',
            'webViewLink': 'https://example.com',
        }
        session.post.return_value = response

        with patch(
            'google.auth.transport.requests.AuthorizedSession',
            return_value=session,
        ) as authorized_session:
            file_obj = google_drive_service._create_drive_file(
                credentials,
                b'workbook bytes',
                'demo.xlsx',
                'folder-123',
            )

        authorized_session.assert_called_once_with(credentials)
        request_args, request_kwargs = session.post.call_args
        self.assertEqual(request_args[0], google_drive_service.DRIVE_UPLOAD_URL)
        self.assertEqual(request_kwargs['params']['supportsAllDrives'], 'true')
        self.assertEqual(request_kwargs['params']['uploadType'], 'multipart')
        self.assertIn('multipart/related', request_kwargs['headers']['Content-Type'])
        self.assertIn(b'"parents": ["folder-123"]', request_kwargs['data'])
        self.assertIn(b'workbook bytes', request_kwargs['data'])
        self.assertEqual(file_obj['id'], 'file-123')

    def test_google_drive_oauth_upload_refreshes_expired_token(self):
        with tempfile.TemporaryDirectory() as temp_dir:
            client_secret_path = os.path.join(temp_dir, 'client_secret.json')
            token_path = os.path.join(temp_dir, 'token.json')
            with open(client_secret_path, 'w', encoding='utf-8') as secret_file:
                json.dump({'installed': {}}, secret_file)
            with open(token_path, 'w', encoding='utf-8') as token_file:
                token_file.write('{}')

            credentials = Mock(expired=True, refresh_token='refresh-token', valid=True)
            credentials.to_json.return_value = '{"refreshed": true}'
            fake_google = types.ModuleType('google')
            fake_oauth2 = types.ModuleType('google.oauth2')
            fake_credentials_module = types.ModuleType('google.oauth2.credentials')
            fake_credentials_module.Credentials = types.SimpleNamespace(
                from_authorized_user_file=Mock(return_value=credentials)
            )
            fake_auth = types.ModuleType('google.auth')
            fake_transport = types.ModuleType('google.auth.transport')
            fake_requests = types.ModuleType('google.auth.transport.requests')
            fake_requests.Request = Mock()
            fake_oauth2.credentials = fake_credentials_module
            fake_auth.transport = fake_transport
            fake_transport.requests = fake_requests
            fake_google.oauth2 = fake_oauth2
            fake_google.auth = fake_auth

            modules = {
                'google': fake_google,
                'google.oauth2': fake_oauth2,
                'google.oauth2.credentials': fake_credentials_module,
                'google.auth': fake_auth,
                'google.auth.transport': fake_transport,
                'google.auth.transport.requests': fake_requests,
            }
            from app.services import google_drive_service
            with patch.dict(sys.modules, modules), patch.object(
                google_drive_service.settings, 'BASE_DIR', Path(temp_dir)
            ), patch.object(
                google_drive_service, '_find_oauth_client_secret', return_value=client_secret_path
            ), patch.object(
                google_drive_service,
                '_create_drive_file',
                return_value={'id': 'file-123', 'name': 'demo.xlsx', 'webViewLink': 'https://example.com'},
            ):
                result = google_drive_service._upload_with_oauth_user(b'abc', 'demo.xlsx', 'folder-123')

            self.assertTrue(result['success'], result)
            self.assertEqual(result['method'], 'oauth_user')
            credentials.refresh.assert_called_once_with(fake_requests.Request.return_value)
            with open(token_path, 'r', encoding='utf-8') as token_file:
                self.assertEqual(token_file.read(), '{"refreshed": true}')

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

        # Access applicant dashboard
        dash_response = self.client.get('/applicant/dashboard.php')
        self.assertEqual(dash_response.status_code, 200)
        self.assertContains(dash_response, 'Job')
        self.assertEqual(self.client.session.get('role'), 'applicant')
        self.applicant.profile_pic = 'https://example.invalid/profile.png'
        self.applicant.save(update_fields=['profile_pic'])
        profile_response = self.client.get('/applicant/profile.php')
        self.assertEqual(profile_response.status_code, 200)
        self.assertContains(profile_response, self.applicant.profile_pic)

    def test_successful_legacy_login_upgrades_plain_password_hash(self):
        legacy_user = User.objects.create(
            email='legacy-password-applicant@example.com',
            password='legacy-plaintext-password',
            role='applicant',
            status='active',
        )

        response = self.client.post('/loginregister.php', {
            'email': legacy_user.email,
            'password': 'legacy-plaintext-password',
        })

        legacy_user.refresh_from_db()
        self.assertEqual(response.status_code, 302)
        self.assertTrue(legacy_user.password.startswith('$2b$'))
        self.assertTrue(
            verify_password('legacy-plaintext-password', legacy_user.password)
        )

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

    def test_dashboard_recommendations_show_top_four_using_semantic_scores(self):
        self._complete_applicant_profile()
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
        with patch(
            'app.views.views_applicant.compute_semantic_match',
            side_effect=lambda applicant, job, resume_text: {
                'score': scores[job.job_id],
                'reason': '',
                'breakdown': [],
            },
        ):
            response = self.client.get('/applicant/dashboard.php')

        self.assertEqual(response.status_code, 200)
        recommended = response.context['recommended_jobs']
        self.assertEqual(len(recommended), 4)
        self.assertEqual(
            [item['match_score'] for item in recommended],
            [99, 83, 39, 22],
        )

    @patch('app.views.views_applicant.get_applicant_job_match')
    def test_dashboard_hides_recommendations_until_profile_is_complete(self, match_job):
        self.applicant.profile_completed = True
        self.applicant.skills = ''
        self.applicant.qualifications = ''
        self.applicant.education_level = ''
        self.applicant.experience_years = 0
        self.applicant.employability_score = Decimal('0')
        self.applicant.resume_file = None
        self.applicant.save()
        self.applicant_user.phone = ''
        self.applicant_user.save(update_fields=['phone'])
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        response = self.client.get('/applicant/dashboard.php')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context['completion_pct'], 0)
        self.assertFalse(response.context['profile_is_complete'])
        self.assertEqual(response.context['employability_score'], 0)
        self.assertEqual(response.context['recommended_jobs'], [])
        self.assertContains(response, 'Complete Your Profile to Unlock AI Job Matches')
        match_job.assert_not_called()

    def test_dashboard_uses_profile_page_fields_without_requiring_resume_analysis(self):
        self.applicant.resume_file = 'uploads/resumes/complete-profile.pdf'
        self.applicant.save(update_fields=['resume_file'])
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        response = self.client.get('/applicant/dashboard.php')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context['completion_pct'], 100)
        self.assertTrue(response.context['profile_is_complete'])
        self.assertEqual(len(response.context['recommended_jobs']), 1)
        self.assertGreater(
            response.context['recommended_jobs'][0]['match_score'],
            0,
        )

    @patch('app.views.views_applicant.compute_semantic_match')
    def test_job_match_api_withholds_score_until_profile_is_complete(self, semantic_match):
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        response = self.client.get(
            f'/applicant/api/job-match/?job_id={self.job.job_id}'
        )

        self.assertEqual(response.status_code, 200)
        self.assertIsNone(response.json()['score'])
        self.assertIn('Complete your profile', response.json()['reason'])
        semantic_match.assert_not_called()

    def test_browse_jobs_layout_does_not_show_match_badges_for_incomplete_profile(self):
        self.applicant.resume_file = None
        self.applicant.skills = ''
        self.applicant.qualifications = ''
        self.applicant.education_level = ''
        self.applicant.experience_years = 0
        self.applicant.profile_completed = False
        self.applicant.save()
        self.applicant_user.phone = ''
        self.applicant_user.save(update_fields=['phone'])
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        response = self.client.get('/applicant/jobs.php')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'Available Positions (1)')
        self.assertContains(response, 'Complete your profile and upload a resume to see job matches.')
        self.assertNotContains(response, 'class="job-score-old"')

    def test_browse_jobs_use_pretrained_semantic_model_without_review_labels(self):
        self._complete_applicant_profile()
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        with patch(
            'app.views.views_applicant.compute_semantic_match',
            return_value={
                'score': 68.4,
                'reason': '',
                'breakdown': [{'label': 'Required skills', 'score': 72.0}],
            },
        ) as semantic_match:
            response = self.client.get('/applicant/jobs.php')
            self.assertEqual(response.status_code, 200)
            self.assertEqual(
                [item['match_score'] for item in response.context['scored_jobs']],
                [68.4],
            )
            self.assertEqual(
                response.context['scored_jobs'][0]['match_score_type'],
                'semantic_model',
            )
            self.assertContains(response, 'pre-trained all-MiniLM-L6-v2')
            self.assertContains(response, 'not a hiring probability')
            semantic_match.assert_called_once()

    def test_candidate_ml_ranking_adds_no_hand_weighted_bonuses(self):
        base = calculate_candidate_ml_score({'ml_match_score': 42})
        with_profile_bonuses = calculate_candidate_ml_score({
            'ml_match_score': 42,
            'experience_years': 20,
            'employability_score': 100,
            'education_level': 'Doctorate',
        })

        self.assertEqual(base, with_profile_bonuses)
        self.assertEqual(base['ml_ranking_score'], 42)
        self.assertIsNone(
            calculate_candidate_ml_score({'match_score': 42})['ml_ranking_score']
        )

    def test_job_match_result_automatically_uses_employability_score(self):
        for score, match_class, status in (
            ('0', 'low', 'not_qualified'),
            ('39.99', 'low', 'not_qualified'),
            ('40', 'medium', 'under_qualified'),
            ('60', 'medium', 'under_qualified'),
            ('60.01', 'high', 'qualified'),
            ('100', 'high', 'qualified'),
        ):
            with self.subTest(score=score):
                self.applicant.employability_score = Decimal(score)
                result = compute_job_match_result(self.applicant, self.job)
                self.assertTrue(result['ready'])
                self.assertEqual(result['match_score'], float(score))
                self.assertEqual(result['match_class'], match_class)
                self.assertEqual(result['qualification_status'], status)
                self.assertEqual(result['reason'], '')

    def test_job_list_uses_semantic_model_regardless_of_review_labels(self):
        self._complete_applicant_profile()
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        with patch(
            'app.views.views_applicant.compute_semantic_match',
            return_value={'score': 77.6, 'reason': '', 'breakdown': []},
        ):
            response = self.client.get('/applicant/jobs.php')

        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.context['match_model_status']['ready'])
        self.assertEqual(response.context['jobs'][0]['match_score_type'], 'semantic_model')
        self.assertEqual(response.context['jobs'][0]['match_score'], 77.6)
        self.assertContains(response, '78% AI relevance')

    def test_job_list_renders_rule_based_match_score_for_complete_profile(self):
        self._complete_applicant_profile()
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        response = self.client.get('/applicant/jobs.php')

        self.assertEqual(response.status_code, 200)
        job_result = response.context['scored_jobs'][0]
        self.assertEqual(job_result['match_score_type'], 'rule_based')
        self.assertIsNotNone(job_result['match_score'])
        self.assertContains(
            response,
            f"{int(job_result['match_score'] + 0.5)}% profile match",
        )
        self.assertContains(response, 'Match Score')
        self.assertContains(response, 'Profile Match')
        self.assertFalse(response.context['semantic_scores_available'])
        self.assertContains(response, 'id="jobsSplit"')
        self.assertContains(response, 'id="closeJobDetails"')
        self.assertNotContains(
            response,
            "The percentage estimates how your profile matches each job's skills, experience, and qualifications.",
        )

    def test_job_list_shows_pretrained_semantic_score_without_admin_reviews(self):
        self._complete_applicant_profile()
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        with patch(
            'app.views.views_applicant.compute_semantic_match',
            return_value={'score': 82.5, 'reason': '', 'breakdown': []},
        ):
            response = self.client.get('/applicant/jobs.php?min_match=80')

        self.assertEqual(response.status_code, 200)
        job_result = next(
            result for result in response.context['jobs']
            if result['job_id'] == self.job.job_id
        )
        self.assertEqual(job_result['match_score'], 82.5)
        self.assertEqual(job_result['match_score_type'], 'semantic_model')
        self.assertEqual(response.context['min_match'], '80')
        self.assertEqual(len(response.context['jobs']), 1)
        self.assertContains(response, 'Pre-trained AI')
        self.assertContains(response, '83% AI relevance')
        self.assertContains(response, 'does not depend on admin-reviewed outcomes')

    def test_job_list_reports_model_download_failure(self):
        self._complete_applicant_profile()
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        with patch(
            'app.views.views_applicant.compute_semantic_match',
            side_effect=SemanticMatchingUnavailable(
                'Could not download the pre-trained semantic model.'
            ),
        ):
            response = self.client.get('/applicant/jobs.php')

        self.assertEqual(response.status_code, 200)
        self.assertFalse(response.context['match_model_status']['ready'])
        self.assertContains(response, 'Could not download the pre-trained semantic model.')

    def test_semantic_match_requires_profile_evidence(self):
        applicant = Applicant.objects.create(user=User.objects.create(
            email='empty-profile@example.com',
            password=hash_password('test-password'),
            role='applicant',
            status='active',
        ))
        job = JobPosting.objects.create(
            employer=self.employer,
            title='Unspecified Role',
            skills_required='',
            requirements='',
            status='active',
        )

        result = compute_semantic_match(applicant, job)
        self.assertIsNone(result['score'])
        self.assertIn('Add skills', result['reason'])

    def test_wordpiece_tokenizer_encodes_special_tokens_and_pads(self):
        vocabulary = {
            '[PAD]': 0,
            '[UNK]': 100,
            '[CLS]': 101,
            '[SEP]': 102,
            '[MASK]': 103,
            'cafe': 200,
            'eng': 201,
            '##ineer': 202,
            '!': 203,
        }
        tokenizer_data = {
            'model': {
                'type': 'WordPiece',
                'vocab': vocabulary,
                'unk_token': '[UNK]',
                'continuing_subword_prefix': '##',
                'max_input_chars_per_word': 100,
            },
            'normalizer': {
                'type': 'BertNormalizer',
                'clean_text': True,
                'handle_chinese_chars': True,
                'strip_accents': None,
                'lowercase': True,
            },
            'pre_tokenizer': {'type': 'BertPreTokenizer'},
            'padding': {
                'strategy': {'Fixed': 8},
                'direction': 'Right',
                'pad_id': 0,
            },
            'added_tokens': [
                {
                    'id': token_id,
                    'content': token,
                    'special': True,
                    'normalized': False,
                }
                for token, token_id in (
                    ('[PAD]', 0),
                    ('[UNK]', 100),
                    ('[CLS]', 101),
                    ('[SEP]', 102),
                    ('[MASK]', 103),
                )
            ],
        }
        with tempfile.TemporaryDirectory() as temp_dir:
            tokenizer_path = Path(temp_dir) / 'tokenizer.json'
            tokenizer_path.write_text(json.dumps(tokenizer_data), encoding='utf-8')
            tokenizer = _load_wordpiece_tokenizer(tokenizer_path)

        ids, attention_mask = tokenizer.encode('CAFÉ engineer! [MASK]')

        self.assertEqual(ids, [101, 200, 201, 202, 203, 103, 102, 0])
        self.assertEqual(attention_mask, [1, 1, 1, 1, 1, 1, 1, 0])

    def test_semantic_match_uses_job_description_skills_and_mapped_qualifications(self):
        JobQualificationMapping.objects.create(
            job=self.job,
            qualification=self.qualification,
        )

        groups = dict(_criteria_groups(self.job))

        self.assertIn('Python', groups['Required skills'])
        self.assertIn('Information Technology', groups['Qualifications'])
        self.assertTrue(groups['Role and requirements'])

    def test_semantic_match_is_labeled_across_job_details_apply_and_applications(self):
        self._complete_applicant_profile()
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        with patch(
            'app.views.views_applicant.compute_semantic_match',
            return_value={
                'score': 68.4,
                'reason': '',
                'breakdown': [{'label': 'Required skills', 'score': 72.0}],
            },
        ):
            details = self.client.get(
                f'/applicant/job_details.php?id={self.job.job_id}'
            )
            apply = self.client.get(
                f'/applicant/apply_job.php?id={self.job.job_id}'
            )
            Application.objects.create(
                job=self.job,
                applicant=self.applicant,
                status='pending',
            )
            applications = self.client.get('/applicant/applications.php')

        self.assertEqual(details.status_code, 200)
        self.assertEqual(details.context['match_score_type'], 'semantic_model')
        self.assertContains(details, 'pre-trained AI relevance')
        self.assertEqual(apply.status_code, 200)
        self.assertEqual(apply.context['match_score_type'], 'semantic_model')
        self.assertContains(apply, 'Meaning-based relevance')
        self.assertEqual(applications.status_code, 200)
        self.assertContains(applications, 'AI relevance')

    def test_admin_analytics_explains_automatic_tier_bands(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/analytics.php')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context['automatic_tier_counts'], {
            'high': 0,
            'medium': 0,
            'low': 0,
        })
        self.assertContains(response, 'Candidate Tiering')
        self.assertContains(response, 'Automatic Employability Tiers')
        self.assertContains(response, 'no admin training labels required')
        response_html = response.content.decode()
        navbar_menu = response_html.split(
            '<div class="navbar-menu" id="navbarMenu">', 1
        )[1].split('</div>', 1)[0]
        profile_dropdown = response_html.split(
            '<div class="ud-dropdown" id="udDropdown"', 1
        )[1].split('</nav>', 1)[0]
        for admin_path in (
            '/admin/users.php',
            '/admin/analytics.php',
            '/admin/messages.php',
        ):
            with self.subTest(admin_path=admin_path):
                self.assertNotIn(admin_path, navbar_menu)
                self.assertIn(admin_path, profile_dropdown)

    def test_admin_navbar_notification_bell_is_separate_from_inquiries(self):
        ContactInquiry.objects.create(
            name='Taylor Candidate',
            email='taylor@example.com',
            subject='Question',
            message='I have a question.',
            is_read=False,
        )
        Notification.objects.create(
            user=self.admin_user,
            title='New job request',
            message='An employer submitted a job request.',
        )
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/analytics.php')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'href="/admin/notifications/"')
        self.assertContains(response, 'aria-label="Notifications, 1 unread"')
        self.assertContains(response, 'href="/admin/messages.php"')
        self.assertContains(response, 'Inquiries (1)')
        self.assertContains(response, 'fas fa-bell')

    def test_admin_analytics_counts_automatic_employability_tiers(self):
        for index, score in enumerate((70, 80, 40, 60, 20, 39)):
            user = User.objects.create(
                email=f'analytics-tier-{index}@example.com',
                password='test-password',
                role='applicant',
                status='active',
            )
            applicant = Applicant.objects.create(
                user=user,
                employability_score=Decimal(score),
            )
            Application.objects.create(job=self.job, applicant=applicant)


        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/analytics.php')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context['automatic_tier_counts'], {
            'high': 2,
            'medium': 2,
            'low': 2,
        })
        self.assertContains(response, 'High · above 60')
        self.assertContains(response, 'Medium · 40 to 60')
        self.assertContains(response, 'Low · below 40')

    def test_public_careers_shows_all_active_jobs_with_company_names(self):
        for index in range(30):
            JobPosting.objects.create(
                employer=self.employer,
                title=f'Service Role {index}',
                status='active',
            )
        JobPosting.objects.create(
            employer=self.employer,
            title='  SENIOR   FULL STACK DEVELOPER ',
            status='active',
        )

        response = self.client.get('/careers.php')

        self.assertEqual(response.status_code, 200)
        jobs = response.context['jobs']
        self.assertEqual(len(jobs), 32)
        self.assertEqual(len({job.job_id for job in jobs}), 32)
        self.assertEqual(response.context['total_jobs_count'], 32)
        self.assertContains(response, 'MultiBiz Corporation')

    def test_admin_active_jobs_match_public_careers_list(self):
        duplicate_job = JobPosting.objects.create(
            employer=self.employer,
            title='  SENIOR   FULL STACK DEVELOPER ',
            status='active',
        )
        JobPosting.objects.create(
            employer=self.employer,
            title='Pending Service Role',
            status='pending',
        )
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        careers_response = self.client.get('/careers.php')
        admin_response = self.client.get('/admin/jobs.php?status=active')

        self.assertEqual(careers_response.status_code, 200)
        self.assertEqual(admin_response.status_code, 200)
        careers_ids = {job.job_id for job in careers_response.context['jobs']}
        admin_ids = {job.job_id for job in admin_response.context['jobs']}
        self.assertEqual(admin_ids, careers_ids)
        matching_title_ids = {self.job.job_id, duplicate_job.job_id}
        self.assertEqual(admin_ids & matching_title_ids, matching_title_ids)
        self.assertEqual(admin_response.context['counts']['active'], len(careers_ids))

    def test_exact_duplicate_postings_are_grouped_on_careers_and_admin(self):
        duplicate_fields = {
            'employer': self.employer,
            'title': 'Principal Python AI Architect',
            'description': 'Build AI services for enterprise clients.',
            'requirements': 'Python and machine learning experience.',
            'skills_required': 'Python, machine learning',
            'location': 'Remote',
            'employment_type': 'full-time',
            'salary_range': 'PHP 150,000 - 200,000',
            'positions_available': 1,
            'status': 'active',
        }
        first_job = JobPosting.objects.create(**duplicate_fields)
        second_job = JobPosting.objects.create(**duplicate_fields)
        second_employer_user = User.objects.create(
            email='duplicate-company-employer@example.com',
            password='test-password',
            role='employer',
            status='active',
        )
        second_employer = Employer.objects.create(
            user=second_employer_user,
            company_name='  MULTIBIZ   CORPORATION ',
            industry='Technology',
        )
        cross_account_duplicate_fields = {
            **duplicate_fields,
            'employer': second_employer,
            'title': '  PRINCIPAL PYTHON AI ARCHITECT ',
        }
        cross_account_duplicate = JobPosting.objects.create(**cross_account_duplicate_fields)
        latest_applied_at = timezone.now()
        Application.objects.create(
            job=first_job,
            applicant=self.applicant,
            applied_at=latest_applied_at - timedelta(days=1),
        )
        latest_application = Application.objects.create(
            job=second_job,
            applicant=self.applicant,
            applied_at=latest_applied_at,
        )

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        careers_response = self.client.get('/careers.php')
        admin_response = self.client.get('/admin/jobs.php?status=active')

        self.assertEqual(careers_response.status_code, 200)
        self.assertEqual(admin_response.status_code, 200)
        careers_ids = {job.job_id for job in careers_response.context['jobs']}
        admin_ids = {job.job_id for job in admin_response.context['jobs']}
        duplicate_ids = {
            first_job.job_id,
            second_job.job_id,
            cross_account_duplicate.job_id,
        }
        self.assertEqual(careers_ids, admin_ids)
        self.assertEqual(len(careers_ids & duplicate_ids), 1)
        grouped_row = next(job for job in admin_response.context['jobs'] if job.job_id in duplicate_ids)
        self.assertCountEqual(grouped_row.duplicate_job_ids, duplicate_ids)
        self.assertEqual(grouped_row.applicant_count, 1)
        self.assertEqual(grouped_row.latest_applied_at, latest_applied_at)
        self.assertEqual(grouped_row.latest_application_id, latest_application.application_id)
        self.assertEqual(admin_response.context['total_application_count'], 2)

        action_response = self.client.post('/admin/jobs.php', {
            'action': 'toggle_status',
            'job_id': grouped_row.job_id,
            'job_ids': ','.join(map(str, grouped_row.duplicate_job_ids)),
        })

        self.assertEqual(action_response.status_code, 302)
        self.assertEqual(
            set(JobPosting.objects.filter(job_id__in=duplicate_ids).values_list('status', flat=True)),
            {'closed'},
        )

    def test_admin_jobs_action_ignores_invalid_duplicate_ids_without_400(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.post('/admin/jobs.php', {
            'action': 'toggle_status',
            'job_id': self.job.job_id,
            'job_ids': f'{self.job.job_id},999999,not-a-number',
        })

        self.assertEqual(response.status_code, 302)
        self.job.refresh_from_db()
        self.assertEqual(self.job.status, 'closed')

    def test_homepage_featured_jobs_are_unique_and_use_employer_names(self):
        JobPosting.objects.create(
            employer=self.employer,
            title='  SENIOR   FULL STACK DEVELOPER ',
            status='active',
        )

        response = self.client.get('/')

        self.assertEqual(response.status_code, 200)
        jobs = response.context['featured_jobs']
        titles = [' '.join(job.title.casefold().split()) for job in jobs]
        self.assertEqual(len(titles), len(set(titles)))
        self.assertEqual(len(jobs), 1)
        self.assertContains(response, 'MultiBiz Corporation')

    def test_admin_job_candidates_page_renders(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get(f'/admin/jobs/{self.job.job_id}/candidates/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'No Candidates Found')
        self.assertContains(response, self.job.title)
        self.assertContains(response, 'High employability only')
        self.assertNotContains(response, 'ML unavailable')

    def test_admin_candidate_action_uses_compact_view_details_label(self):
        Application.objects.create(job=self.job, applicant=self.applicant)
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get(f'/admin/jobs/{self.job.job_id}/candidates/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'View Details')
        self.assertNotContains(response, 'View Candidate Details')

    def test_admin_job_candidates_default_to_newest_applications(self):
        older_application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            applied_at=timezone.now() - timedelta(days=2),
        )
        newer_user = User.objects.create(
            email='new-applicant@example.com',
            password='test-password',
            role='applicant',
            first_name='New',
            last_name='Applicant',
            status='active',
        )
        newer_applicant = Applicant.objects.create(
            user=newer_user,
            skills='Python',
            education_level='Bachelor',
        )
        newer_application = Application.objects.create(
            job=self.job,
            applicant=newer_applicant,
            applied_at=timezone.now(),
        )
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get(f'/admin/jobs/{self.job.job_id}/candidates/')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context['selected_sort'], 'date')
        self.assertEqual(
            [candidate['application'].application_id for candidate in response.context['candidates']],
            [newer_application.application_id, older_application.application_id],
        )
        self.assertNotContains(response, 'Newest applicants')
        self.assertNotContains(response, 'Sort candidates')
        self.assertContains(response, newer_application.applied_at.strftime('%b %d, %Y'))
        self.assertContains(response, 'checking for new applications every 15 seconds')

    def test_admin_job_candidates_live_check_returns_latest_application(self):
        application = Application.objects.create(job=self.job, applicant=self.applicant)
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get(
            f'/admin/jobs/{self.job.job_id}/candidates/',
            {'check_updates': '1'},
        )

        self.assertEqual(response.status_code, 200)
        self.assertEqual(
            response.json()['latest_application_id'],
            application.application_id,
        )
        self.assertEqual(
            response.json()['applied_at'],
            application.applied_at.isoformat(),
        )

    def test_admin_can_remove_one_application_without_deleting_applicant_account(self):
        target_application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
        )
        retained_application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
        )
        unrelated_job = JobPosting.objects.create(
            employer=self.employer,
            title='Unrelated posting',
            status='active',
        )
        unrelated_application = Application.objects.create(
            job=unrelated_job,
            applicant=self.applicant,
        )

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        candidate_page = self.client.get(
            f'/admin/jobs/{self.job.job_id}/candidates/',
            {'job_ids': str(self.job.job_id)},
        )
        self.assertEqual(candidate_page.status_code, 200)
        self.assertContains(candidate_page, 'Remove Application')

        wrong_job_response = self.client.post(
            f'/admin/jobs/{unrelated_job.job_id}/applications/{target_application.application_id}/delete/',
            {'job_ids': str(unrelated_job.job_id)},
        )
        self.assertEqual(wrong_job_response.status_code, 404)
        self.assertTrue(
            Application.objects.filter(pk=target_application.application_id).exists()
        )

        response = self.client.post(
            f'/admin/jobs/{self.job.job_id}/applications/{target_application.application_id}/delete/',
            {'job_ids': str(self.job.job_id)},
        )

        self.assertRedirects(
            response,
            f'/admin/jobs/{self.job.job_id}/candidates/?job_ids={self.job.job_id}&removed=1',
            fetch_redirect_response=False,
        )
        self.assertFalse(
            Application.objects.filter(pk=target_application.application_id).exists()
        )
        self.assertTrue(
            Application.objects.filter(pk=retained_application.application_id).exists()
        )
        self.assertTrue(
            Application.objects.filter(pk=unrelated_application.application_id).exists()
        )
        self.assertTrue(User.objects.filter(pk=self.applicant_user.user_id).exists())
        self.assertTrue(Applicant.objects.filter(pk=self.applicant.applicant_id).exists())

    def test_candidate_pipeline_counts_high_match_separately_from_high_employability(self):
        self.applicant.employability_score = Decimal('70')
        self.applicant.save(update_fields=['employability_score'])
        Application.objects.create(job=self.job, applicant=self.applicant)

        for index, score in enumerate(('90', '50', '30')):
            user = User.objects.create(
                email=f'pipeline-tier-{index}@example.com',
                password='test-password',
                role='applicant',
                status='active',
            )
            applicant = Applicant.objects.create(
                user=user,
                employability_score=Decimal(score),
            )
            Application.objects.create(job=self.job, applicant=applicant)

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/candidates.php')

        self.assertEqual(response.status_code, 200)
        summary = response.context['job_summaries'][0]
        self.assertEqual(summary['count_qualified'], 2)
        self.assertEqual(response.context['total_qualified'], 2)
        self.assertEqual(summary['count_high_match'], 1)
        self.assertEqual(summary['count_under_qualified'], 1)
        self.assertEqual(summary['count_not_qualified'], 1)
        self.assertContains(response, 'HIGH MATCH')
        self.assertContains(response, 'Highest Match')
        self.assertNotContains(response, 'Low Match')
        self.assertContains(response, 'Under-Qualified: 1')
        self.assertContains(response, 'Not Qualified: 1')
        self.assertNotContains(response, '40–60%')
        self.assertNotContains(response, '&lt;40%')
        self.assertNotContains(response, '≥ 85%')
        self.assertContains(
            response,
            f'/admin/jobs/{self.job.job_id}/candidates/?job_ids=',
        )
        self.assertNotContains(response, '?qual=qualified&job_ids=')
        self.assertContains(response, 'id="total-applications-count" aria-live="polite"')
        self.assertContains(response, '>4</span>')

        count_response = self.client.get('/admin/candidates.php?count_only=1')
        self.assertEqual(count_response.status_code, 200)
        self.assertEqual(count_response.json()['high_match_count'], 2)
        self.assertEqual(count_response.json()['total_application_count'], 4)
        self.assertEqual(
            count_response.json()['latest_application_id'],
            Application.objects.order_by('-applied_at', '-application_id')
            .values_list('application_id', flat=True)
            .first(),
        )

        candidates_response = self.client.get(
            f"/admin/jobs/{self.job.job_id}/candidates/",
            {'job_ids': str(self.job.job_id)},
        )
        self.assertEqual(candidates_response.status_code, 200)
        self.assertEqual(candidates_response.context['counts']['all'], 4)
        self.assertEqual(len(candidates_response.context['candidates']), 4)

    def test_candidate_pipeline_orders_jobs_by_latest_application_and_refreshes_new_applications(self):
        older_applied_at = timezone.now() - timedelta(days=3)
        self.applicant.employability_score = Decimal('90')
        self.applicant.save(update_fields=['employability_score'])
        Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            applied_at=older_applied_at,
        )

        newer_job = JobPosting.objects.create(
            employer=self.employer,
            title='Newly Applied Job',
            status='active',
        )
        newer_user = User.objects.create(
            email='recent-pipeline-applicant@example.com',
            password='test-password',
            role='applicant',
            status='active',
        )
        newer_applicant = Applicant.objects.create(
            user=newer_user,
            employability_score=Decimal('20'),
        )
        newer_applied_at = timezone.now() - timedelta(minutes=2)
        newer_application = Application.objects.create(
            job=newer_job,
            applicant=newer_applicant,
            applied_at=newer_applied_at,
        )

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/candidates.php')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(
            [summary['job_id'] for summary in response.context['job_summaries']],
            [newer_job.job_id, self.job.job_id],
        )
        self.assertEqual(
            response.context['job_summaries'][0]['latest_applied_at'],
            newer_applied_at,
        )
        self.assertEqual(
            response.context['latest_application_id'],
            newer_application.application_id,
        )
        self.assertContains(
            response,
            newer_applied_at.strftime('%b %d, %Y'),
        )

        new_user = User.objects.create(
            email='new-live-applicant@example.com',
            password='test-password',
            role='applicant',
            status='active',
        )
        new_applicant = Applicant.objects.create(user=new_user)
        newest_application = Application.objects.create(
            job=self.job,
            applicant=new_applicant,
            applied_at=timezone.now(),
        )
        refresh_response = self.client.get('/admin/candidates.php?count_only=1')

        self.assertEqual(refresh_response.status_code, 200)
        self.assertEqual(
            refresh_response.json()['latest_application_id'],
            newest_application.application_id,
        )
        self.assertEqual(
            refresh_response.json()['latest_applied_at'],
            newest_application.applied_at.isoformat(),
        )
        self.assertEqual(refresh_response.json()['total_application_count'], 3)

    def test_admin_jobs_page_prioritizes_and_live_refreshes_latest_applications(self):
        older_applied_at = timezone.now() - timedelta(days=2)
        Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            applied_at=older_applied_at,
        )
        newer_job = JobPosting.objects.create(
            employer=self.employer,
            title='Recently Applied Job',
            status='active',
            posted_at=timezone.now() - timedelta(days=10),
        )
        newer_user = User.objects.create(
            email='recent-jobs-page-applicant@example.com',
            password='test-password',
            role='applicant',
            status='active',
        )
        newer_applicant = Applicant.objects.create(user=newer_user)
        newer_applied_at = timezone.now() - timedelta(minutes=1)
        newer_application = Application.objects.create(
            job=newer_job,
            applicant=newer_applicant,
            applied_at=newer_applied_at,
        )

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/jobs.php')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(
            [job.job_id for job in response.context['jobs']],
            [newer_job.job_id, self.job.job_id],
        )
        self.assertEqual(
            response.context['jobs'][0].latest_applied_at,
            newer_applied_at,
        )
        self.assertContains(response, 'Latest:')
        self.assertContains(response, 'checking every 15 seconds')

        refresh_response = self.client.get('/admin/jobs.php?check_updates=1')
        self.assertEqual(refresh_response.status_code, 200)
        self.assertEqual(refresh_response.json()['total_application_count'], 2)
        self.assertEqual(
            refresh_response.json()['latest_application_id'],
            newer_application.application_id,
        )

        newest_application = Application.objects.create(
            job=self.job,
            applicant=Applicant.objects.create(
                user=User.objects.create(
                    email='newest-jobs-page-applicant@example.com',
                    password='test-password',
                    role='applicant',
                    status='active',
                )
            ),
            applied_at=timezone.now(),
        )
        refreshed = self.client.get('/admin/jobs.php?check_updates=1')
        self.assertEqual(refreshed.json()['total_application_count'], 3)
        self.assertEqual(
            refreshed.json()['latest_application_id'],
            newest_application.application_id,
        )

    def test_candidate_pipeline_merges_exact_duplicate_job_postings_only(self):
        duplicate_job = JobPosting.objects.create(
            employer=self.employer,
            title='  SENIOR   FULL STACK DEVELOPER ',
            description=self.job.description,
            requirements=self.job.requirements,
            skills_required=self.job.skills_required,
            location=self.job.location,
            employment_type=self.job.employment_type,
            salary_range=self.job.salary_range,
            positions_available=self.job.positions_available,
            status=self.job.status,
            posted_at=self.job.posted_at,
        )
        Application.objects.create(job=self.job, applicant=self.applicant)
        Application.objects.create(job=duplicate_job, applicant=self.applicant)

        second_user = User.objects.create(
            email='duplicate-job-applicant@example.com',
            password='test-password',
            role='applicant',
            status='active',
        )
        second_applicant = Applicant.objects.create(
            user=second_user,
            employability_score=Decimal('50'),
        )
        Application.objects.create(job=duplicate_job, applicant=second_applicant)

        distinct_repost = JobPosting.objects.create(
            employer=self.employer,
            title='Senior Full Stack Developer',
            description=self.job.description,
            requirements=self.job.requirements,
            skills_required=self.job.skills_required,
            location=self.job.location,
            employment_type=self.job.employment_type,
            salary_range=self.job.salary_range,
            positions_available=self.job.positions_available,
            status=self.job.status,
            posted_at=self.job.posted_at + timedelta(days=1),
        )
        Application.objects.create(job=distinct_repost, applicant=self.applicant)

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/candidates.php')

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context['total_jobs'], 2)
        self.assertEqual(response.context['total_applicants'], 3)
        self.assertEqual(response.context['total_applications'], 4)
        self.assertContains(response, 'Total Applications')
        merged_summary = next(
            summary for summary in response.context['job_summaries']
            if len(summary['job_ids']) == 2
        )
        self.assertEqual(merged_summary['count_all'], 2)

        candidates_response = self.client.get(
            f"/admin/jobs/{merged_summary['job_id']}/candidates/",
            {'job_ids': ','.join(map(str, merged_summary['job_ids']))},
        )
        self.assertEqual(candidates_response.status_code, 200)
        self.assertEqual(candidates_response.context['counts']['all'], 2)

    def test_legacy_unavailable_filter_redirects_to_automatic_candidate_list(self):
        Application.objects.create(job=self.job, applicant=self.applicant)
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get(
            f'/admin/jobs/{self.job.job_id}/candidates/?qual=unavailable&sort=score'
        )

        self.assertRedirects(
            response,
            f'/admin/jobs/{self.job.job_id}/candidates/',
            fetch_redirect_response=False,
        )
        candidate_list = self.client.get(response['Location'])
        self.assertEqual(candidate_list.status_code, 200)
        self.assertEqual(candidate_list.context['counts']['qualified'], 1)
        self.assertContains(candidate_list, 'High Employability')

    def test_admin_dossier_shows_automatic_tier_without_manual_assessment(self):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
        )
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get(
            f'/admin/candidates/{application.application_id}/',
        )

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.context['qualification_status'], 'qualified')
        self.assertContains(response, 'Automatic Employability Tier')
        self.assertNotContains(response, 'Record Admin ML Assessment')
        self.assertNotContains(response, 'Admin Qualification Assessment')

    @override_settings(EMAIL_BACKEND='django.core.mail.backends.locmem.EmailBackend')
    def test_admin_dispatch_notice_uses_automatic_employability_tier(self):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='pending',
            match_score=Decimal('5.00'),
        )
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        candidates_response = self.client.get(f'/admin/jobs/{self.job.job_id}/candidates/')

        self.assertEqual(candidates_response.status_code, 200)
        self.assertContains(
            candidates_response,
            f'data-notification-url="/admin/notify_applicant/{application.application_id}/"',
        )

        notice_response = self.client.post(f'/admin/notify_applicant/{application.application_id}/')

        self.assertRedirects(notice_response, f'/admin/jobs/{self.job.job_id}/candidates/?notified=email_sent')
        application.refresh_from_db()
        self.assertEqual(application.status, 'reviewed')
        self.assertTrue(Notification.objects.filter(user=self.applicant_user).exists())
        self.assertEqual(len(mail.outbox), 1)
        self.assertIn('86%', mail.outbox[0].body)

    def test_forwarded_candidate_is_visible_in_employer_candidates_page(self):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='pending',
            match_score=Decimal('0.00'),
            forwarded_to_employer=True,
        )

        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session.save()

        response = self.client.get('/employer/candidates.php')
        self.assertEqual(response.status_code, 200)
        self.assertContains(response, '86% High Employability')
        self.assertNotContains(response, '0% Low Employability')
        self.assertEqual(
            response.context['applications'][0].qualification_status,
            'qualified',
        )

        with patch(
            'app.views.views_employer.compute_job_match_result',
            return_value={
                'match_score': 89.0,
                'qualification_status': 'not_qualified',
                'match_class': 'low',
            },
        ):
            inconsistent_score_response = self.client.get('/employer/candidates.php')
        self.assertContains(inconsistent_score_response, '89% High Employability')
        self.assertNotContains(inconsistent_score_response, '89% Low Employability')
        self.assertEqual(
            inconsistent_score_response.context['applications'][0].match_class,
            'high',
        )

        application.refresh_from_db()
        self.assertEqual(application.match_score, Decimal('0.00'))

    def test_batch_forward_does_not_use_legacy_unclassified_score_bands(self):
        applications_by_score = {}
        for index, score in enumerate((Decimal('29.99'), Decimal('30.00'), Decimal('39.99'), Decimal('40.00'))):
            user = User.objects.create(
                email=f'band-{index}@example.com',
                password='test-password',
                role='applicant',
                first_name=f'Band{index}',
                status='active',
            )
            applicant = Applicant.objects.create(user=user)
            applications_by_score[score] = Application.objects.create(
                job=self.job,
                applicant=applicant,
                match_score=score,
            )

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.post('/admin/batch_forward_candidates/', {
            'job_id': self.job.job_id,
            'scope': 'unclassified',
        })

        self.assertEqual(response.status_code, 400)
        self.assertFalse(response.json()['success'])
        for score, application in applications_by_score.items():
            application.refresh_from_db()
            self.assertFalse(
                application.forwarded_to_employer,
                msg=f'Legacy score {score} must not trigger forwarding',
            )

    def test_employer_active_jobs_are_rendered_in_job_list(self):
        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session.save()

        response = self.client.get('/employer/jobs.php?status=active')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, self.job.title)
        self.assertNotContains(response, 'No Job Requisitions Found')

    def test_admin_post_job_form_omits_employer_login_section(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/post_job.php')

        self.assertEqual(response.status_code, 200)
        self.assertNotContains(response, "Create This Company's Employer Login")
        self.assertNotContains(response, 'partner-account-fields')
        self.assertNotContains(response, 'name="account_email"')
        self.assertNotContains(response, 'name="account_password"')
        self.assertNotContains(response, 'name="contact_first_name"')

    def test_admin_posted_job_uses_admin_managed_company_without_employer_login(self):
        company_name = 'Admin Managed Partner Ltd.'
        legacy_profile = Employer.objects.create(
            user=self.admin_user,
            company_name=company_name,
            industry='Recruitment Partner',
        )
        legacy_job = JobPosting.objects.create(
            employer=legacy_profile,
            title='Legacy Partner Vacancy',
            description='Previously posted by admin.',
            status='active',
        )

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        post_response = self.client.post('/admin/post_job.php', {
            'company_name': company_name,
            'title': 'New Partner Vacancy',
            'description': 'A role for the new partner company.',
            'requirements': 'Relevant experience required.',
            'skills_required': 'Python, Django',
            'location': 'Manila',
            'employment_type': 'full-time',
            'salary_range': 'PHP 50,000',
            'status': 'active',
        })

        self.assertEqual(post_response.status_code, 302)
        new_job = JobPosting.objects.get(title='New Partner Vacancy')
        legacy_job.refresh_from_db()
        self.assertEqual(new_job.employer, legacy_profile)
        self.assertEqual(legacy_job.employer, legacy_profile)
        self.assertFalse(User.objects.filter(email='partner-login@example.com').exists())

    def test_admin_posted_job_reuses_existing_company_employer_account(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.post('/admin/post_job.php', {
            'company_name': self.employer.company_name,
            'title': 'Existing Partner Vacancy',
            'description': 'A role for an existing partner.',
            'requirements': 'Relevant experience required.',
            'skills_required': 'Python',
            'location': 'Manila',
            'employment_type': 'full-time',
            'status': 'active',
        })

        self.assertEqual(response.status_code, 302)
        job = JobPosting.objects.get(title='Existing Partner Vacancy')
        self.assertEqual(job.employer, self.employer)
        self.assertEqual(User.objects.filter(email=self.employer_user.email).count(), 1)

    def test_admin_can_post_for_new_company_without_creating_employer_login(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.post('/admin/post_job.php', {
            'company_name': 'Unregistered Partner Ltd.',
            'title': 'Partner Vacancy',
            'description': 'A role for the partner.',
            'requirements': 'Relevant experience required.',
            'skills_required': 'Python',
            'location': 'Manila',
            'employment_type': 'full-time',
            'status': 'active',
        })

        self.assertEqual(response.status_code, 302)
        admin_company = Employer.objects.get(
            company_name='Unregistered Partner Ltd.',
            user=self.admin_user,
        )
        self.assertTrue(
            JobPosting.objects.filter(
                title='Partner Vacancy',
                employer=admin_company,
            ).exists()
        )
        self.assertFalse(User.objects.filter(role='employer').exclude(pk=self.employer_user.pk).exists())

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

        form_response = self.client.get(
            f'/applicant/apply_job.php?id={self.job.job_id}'
        )
        self.assertEqual(form_response.status_code, 200)
        self.assertNotContains(
            form_response,
            'Upload a custom resume for this application',
        )
        self.assertNotContains(form_response, 'studio-choice-file')
        self.assertContains(form_response, 'Position Description')
        self.assertContains(form_response, 'Required Skills')
        self.assertContains(form_response, 'Compensation')
        self.assertContains(form_response, 'Work Arrangement')
        self.assertContains(form_response, 'Match Score')
        self.assertNotContains(form_response, 'Cover Letter Diagnostics')
        self.assertContains(
            form_response,
            self.job.description,
        )
        self.assertContains(form_response, self.job.requirements)

        # Apply for job
        response = self.client.post(f'/applicant/apply_job.php?id={self.job.job_id}', {
            'cover_letter': 'I am thrilled to apply for this full stack role.',
            'use_existing_resume': '1'
        })
        self.assertEqual(response.status_code, 302)
        self.assertIn('applied=success', response.url)
        
        # Verify application created
        app = Application.objects.filter(job=self.job, applicant=self.applicant).first()
        self.assertIsNotNone(app)
        self.assertEqual(app.status, 'pending')
        application_notification = Notification.objects.get(
            user=self.applicant_user,
            type='application',
        )
        self.assertEqual(application_notification.title, 'Application submitted')
        self.assertIn(self.job.title, application_notification.message)
        employer_application_notification = Notification.objects.get(
            user=self.employer_user,
            type='application',
            title='New applicant applied',
        )
        self.assertIn(self.job.title, employer_application_notification.message)
        self.assertIn('pending admin review', employer_application_notification.message)
        self.assertNotIn(self.applicant_user.full_name, employer_application_notification.message)
        admin_application_notification = Notification.objects.get(
            user=self.admin_user,
            type='application',
            title='New applicant applied',
        )
        self.assertIn(self.applicant_user.first_name, admin_application_notification.message)
        self.assertIn(self.job.title, admin_application_notification.message)

        success_response = self.client.get(response.url)
        self.assertEqual(success_response.status_code, 200)
        self.assertContains(success_response, 'applicationSuccessToast')
        self.assertContains(
            success_response,
            f'You applied for {self.job.title}.',
        )

        notifications_response = self.client.get('/applicant/notifications/')
        self.assertEqual(notifications_response.status_code, 200)
        self.assertContains(notifications_response, 'Application submitted')
        self.assertContains(notifications_response, self.job.title)


        jobs_response = self.client.get('/applicant/jobs.php')
        self.assertEqual(jobs_response.status_code, 200)
        applied_job = next(
            item for item in jobs_response.context['scored_jobs']
            if item['job'].job_id == self.job.job_id
        )
        self.assertTrue(applied_job['has_applied'])
        self.assertEqual(
            applied_job['application_status'],
            'Pending Admin Review',
        )
        self.assertContains(
            jobs_response,
            'class="job-application-status">Pending Admin Review',
        )
        self.assertContains(
            jobs_response,
            "action.setAttribute('aria-disabled', 'true')",
        )

        job_details_response = self.client.get(
            f'/applicant/job_details.php?id={self.job.job_id}'
        )
        self.assertEqual(job_details_response.status_code, 200)
        self.assertContains(
            job_details_response,
            'My application is pending review.',
        )
        self.assertNotContains(job_details_response, 'Apply Now')

        duplicate_apply_response = self.client.get(
            f'/applicant/apply_job.php?id={self.job.job_id}'
        )
        self.assertRedirects(
            duplicate_apply_response,
            '/applicant/applications.php?already_applied=1',
        )

    def test_notifications_include_applications_submitted_before_notice_feature(self):
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()
        Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='pending',
        )

        response = self.client.get('/applicant/notifications/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'Application submitted')
        self.assertContains(response, self.job.title)
        self.assertEqual(
            Notification.objects.filter(
                user=self.applicant_user,
                type='application',
                title='Application submitted',
            ).count(),
            1,
        )

    def test_notifications_render_when_employer_company_name_is_blank(self):
        self.employer.company_name = ''
        self.employer.save(update_fields=['company_name'])
        Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='pending',
        )
        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        response = self.client.get('/applicant/notifications/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'You applied for Senior Full Stack Developer at MultiBiz Employer.')

        self.client.get('/applicant/notifications/')
        self.assertEqual(
            Notification.objects.filter(
                user=self.applicant_user,
                type='application',
                title='Application submitted',
            ).count(),
            1,
        )


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

    def test_employer_can_send_interview_invitation(self):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='shortlisted',
            forwarded_to_employer=True,
        )
        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session['email'] = self.employer_user.email
        session.save()

        with patch('app.views.views_employer.send_interview_scheduled_email') as send_email:
            invalid_response = self.client.post(
                f'/employer/view_candidate.php?id={application.application_id}',
                {
                    'action': 'schedule_interview',
                    'interview_date': '2026-10-15',
                    'start_time': '10:30',
                    'interview_type': 'Online',
                },
            )
            self.assertEqual(invalid_response.status_code, 200)
            self.assertContains(invalid_response, 'Please provide a valid interview end time.')
            self.assertContains(invalid_response, '> Schedule Interview</h6>')
            self.assertFalse(InterviewSchedule.objects.filter(application=application).exists())

            response = self.client.post(
                f'/employer/view_candidate.php?id={application.application_id}',
                {
                    'action': 'schedule_interview',
                    'interview_date': '2026-10-15',
                    'start_time': '10:30',
                    'end_time': '11:30',
                    'interview_type': 'Online',
                    'location_or_link': 'https://meet.example.com/interview',
                    'interviewer_name': 'Hiring Team',
                    'instructions': 'Bring your portfolio.',
                    'notes': 'Technical interview.',
                },
            )

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'Interview successfully scheduled')
        self.assertNotContains(response, '> Schedule Interview</h6>')
        self.assertNotContains(response, 'Online Video')
        self.assertNotContains(response, 'Meeting Link / Location:')
        self.assertNotContains(response, 'Instructions for Candidate:')
        self.assertNotContains(response, 'Send Interview Invitation')
        schedule = InterviewSchedule.objects.get(application=application)
        self.assertEqual(schedule.interview_date.isoformat(), '2026-10-15')
        self.assertEqual(schedule.start_time.strftime('%H:%M'), '10:30')
        self.assertEqual(schedule.end_time.strftime('%H:%M'), '11:30')
        self.assertEqual(schedule.meeting_link, 'https://meet.example.com/interview')
        application.refresh_from_db()
        self.assertEqual(application.employer_status, 'for_interview')
        self.assertEqual(application.status, 'interviewed')
        self.assertTrue(Notification.objects.filter(
            user=self.applicant_user,
            title__contains='Interview Scheduled',
        ).exists())
        send_email.assert_called_once()

        detail_response = self.client.get(
            f'/employer/view_candidate.php?id={application.application_id}'
        )
        self.assertEqual(detail_response.status_code, 200)
        self.assertNotContains(detail_response, 'Schedule Interview')

    def test_interview_form_is_hidden_at_interview_stage(self):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='interviewed',
            employer_status='for_review',
            forwarded_to_employer=True,
        )
        InterviewSchedule.objects.create(
            application=application,
            employer=self.employer,
            interview_date='2026-10-15',
            start_time='10:30',
            end_time='11:30',
            interview_type='Online',
            status='scheduled',
        )
        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session['email'] = self.employer_user.email
        session.save()

        response = self.client.get(
            f'/employer/view_candidate.php?id={application.application_id}'
        )

        self.assertEqual(response.status_code, 200)
        self.assertNotContains(response, 'Schedule Interview')
        self.assertNotContains(response, 'Online Video')
        self.assertNotContains(response, 'Meeting Link / Location:')
        self.assertNotContains(response, 'Instructions for Candidate:')
        self.assertNotContains(response, 'Send Interview Invitation')
        response_html = response.content.decode()
        status_select = response_html.split(
            '<select name="employer_status"', 1
        )[1].split('</select>', 1)[0]
        self.assertIn('value="hired"', status_select)
        self.assertIn('>Hired</option>', status_select)
        self.assertIn('value="hired" selected', status_select)
        self.assertIn('value="rejected"', status_select)
        self.assertIn('>Reject</option>', status_select)
        for unavailable_status in (
            'for_review',
            'qualified',
            'for_interview',
            'interview_completed',
            'not_qualified',
        ):
            self.assertNotIn(f'value="{unavailable_status}"', status_select)

        with patch('app.views.views_employer.send_application_status_update_email'):
            update_response = self.client.post(
                f'/employer/view_candidate.php?id={application.application_id}',
                {
                    'action': 'update_status',
                    'employer_status': 'rejected',
                    'remarks': 'Interview did not meet requirements.',
                },
            )

        self.assertEqual(update_response.status_code, 200)
        application.refresh_from_db()
        self.assertEqual(application.employer_status, 'rejected')
        self.assertEqual(application.status, 'rejected')
        self.assertEqual(
            application.employer_notes,
            'Interview did not meet requirements.',
        )

    def test_for_interview_status_dropdown_defaults_to_hired_or_reject(self):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='interviewed',
            employer_status='for_interview',
            forwarded_to_employer=True,
        )
        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session['email'] = self.employer_user.email
        session.save()

        response = self.client.get(
            f'/employer/view_candidate.php?id={application.application_id}'
        )

        self.assertEqual(response.status_code, 200)
        status_select = response.content.decode().split(
            '<select name="employer_status"', 1
        )[1].split('</select>', 1)[0]
        self.assertIn('value="hired" selected', status_select)
        self.assertIn('value="rejected"', status_select)
        self.assertNotIn('>For Interview</option>', status_select)
        self.assertNotIn('value="for_interview"', status_select)

    def test_hiring_applicant_redirects_to_dashboard_with_named_notification(self):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='interviewed',
            employer_status='for_interview',
            forwarded_to_employer=True,
        )
        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session['email'] = self.employer_user.email
        session.save()

        with patch('app.views.views_employer.send_application_status_update_email'):
            response = self.client.post(
                f'/employer/view_candidate.php?id={application.application_id}',
                {
                    'action': 'update_status',
                    'employer_status': 'hired',
                    'remarks': 'Offer accepted.',
                },
                follow=True,
            )

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.redirect_chain[-1][0], '/employer/dashboard/')
        self.assertContains(response, 'Job Seeker has been hired.')
        application.refresh_from_db()
        self.assertEqual(application.employer_status, 'hired')
        self.assertEqual(application.status, 'accepted')
        hire_notification = Notification.objects.get(
            user=self.applicant_user,
            title='Congratulations, Job Seeker!',
        )
        self.assertIn('Job Seeker has been hired', hire_notification.message)
        self.assertIn(self.job.title, hire_notification.message)

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
        partner_user = User.objects.get(email='newpartner@gmail.com')
        partner_profile = Employer.objects.get(user=partner_user)
        self.assertEqual(partner_user.role, 'employer')
        self.assertEqual(partner_user.status, 'active')
        self.assertTrue(partner_user.created_by_admin)
        self.assertTrue(verify_password('partnerpassword123', partner_user.password))
        self.assertEqual(partner_profile.company_name, 'New Global Partner Inc.')
        self.assertEqual(partner_profile.industry, 'Finance')
        self.assertContains(create_emp_resp, 'Create Employer Partner Account')

        # Check audit trail was logged
        audit = AuditTrail.objects.filter(action_type='create_employer').last()
        self.assertIsNotNone(audit)
        self.assertEqual(audit.target_name, 'New Global Partner Inc.')

        duplicate_email_resp = self.client.post('/admin/users.php', {
            'action': 'create_employer',
            'email': 'NEWPARTNER@gmail.com',
            'password': 'anotherpassword',
            'first_name': 'Duplicate',
            'last_name': 'Partner',
            'company_name': 'Duplicate Company'
        })
        self.assertContains(duplicate_email_resp, 'Email already in use.')
        self.assertFalse(Employer.objects.filter(company_name='Duplicate Company').exists())

        short_password_resp = self.client.post('/admin/users.php', {
            'action': 'create_employer',
            'email': 'short-password@example.com',
            'password': '123',
            'first_name': 'Short',
            'last_name': 'Password',
            'company_name': 'Invalid Company'
        })
        self.assertContains(short_password_resp, 'Password must be at least 6 characters.')
        self.assertFalse(User.objects.filter(email='short-password@example.com').exists())


class RoleAuthorizationTests(TestCase):
    def test_session_role_is_reloaded_from_account_record(self):
        applicant = User.objects.create(
            email='rls-session-applicant@example.com',
            password='not-a-real-account',
            role='applicant',
            status='active',
        )
        client = Client()
        session = client.session
        session['user_id'] = applicant.pk
        session['role'] = 'admin'
        session['email'] = applicant.email
        session.save()

        response = client.get('/admin/dashboard.php')

        self.assertEqual(response.status_code, 302)
        self.assertIn('error=unauthorized', response['Location'])


class PrivateResumeAccessTests(TestCase):
    def setUp(self):
        self.applicant_user = User.objects.create(
            email='private-resume-applicant@example.com',
            password='not-a-real-account',
            role='applicant',
            status='active',
        )
        self.applicant = Applicant.objects.create(
            user=self.applicant_user,
            resume_file='uploads/resumes/private-resume.pdf',
        )
        self.employer_user = User.objects.create(
            email='private-resume-employer@example.com',
            password='not-a-real-account',
            role='employer',
            status='active',
        )
        self.employer = Employer.objects.create(user=self.employer_user)
        self.job = JobPosting.objects.create(
            employer=self.employer,
            title='Private resume test',
            status='active',
        )

    def _login(self, client, user):
        session = client.session
        session['user_id'] = user.user_id
        session['role'] = user.role
        session.save()

    def _write_resume(self, root):
        resume_path = Path(root) / 'resumes' / 'private-resume.pdf'
        resume_path.parent.mkdir(parents=True)
        resume_path.write_bytes(b'private test resume')
        return resume_path

    def test_resume_files_are_not_served_from_public_media_urls(self):
        with tempfile.TemporaryDirectory() as media_root:
            self._write_resume(media_root)
            with override_settings(MEDIA_ROOT=Path(media_root)):
                response = Client().get(
                    '/uploads/resumes/a-missing-resume.pdf'
                )

        self.assertEqual(response.status_code, 404)

    def test_applicant_can_download_own_resume_but_other_applicant_cannot(self):
        with tempfile.TemporaryDirectory() as media_root:
            self._write_resume(media_root)
            with override_settings(MEDIA_ROOT=Path(media_root)):
                owner_client = Client()
                self._login(owner_client, self.applicant_user)
                owner_response = owner_client.get(
                    f'/resume/applicant/{self.applicant.pk}/'
                )

                other_user = User.objects.create(
                    email='other-private-resume-applicant@example.com',
                    password='not-a-real-account',
                    role='applicant',
                    status='active',
                )
                other_client = Client()
                self._login(other_client, other_user)
                other_response = other_client.get(
                    f'/resume/applicant/{self.applicant.pk}/'
                )

        self.assertEqual(owner_response.status_code, 200)
        self.assertEqual(owner_response['Content-Disposition'].split(';')[0], 'attachment')
        self.assertEqual(owner_response['Cache-Control'], 'private, no-store')
        self.assertEqual(other_response.status_code, 404)

    def test_employer_can_download_only_forwarded_application_resume(self):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            resume_file=self.applicant.resume_file,
            forwarded_to_employer=False,
        )
        client = Client()
        self._login(client, self.employer_user)

        with tempfile.TemporaryDirectory() as media_root:
            self._write_resume(media_root)
            with override_settings(MEDIA_ROOT=Path(media_root)):
                denied_response = client.get(
                    f'/resume/application/{application.pk}/'
                )
                application.forwarded_to_employer = True
                application.save(update_fields=['forwarded_to_employer'])
                allowed_response = client.get(
                    f'/resume/application/{application.pk}/'
                )

        self.assertEqual(denied_response.status_code, 404)
        self.assertEqual(allowed_response.status_code, 200)

    def test_resume_upload_is_stored_on_local_media_disk(self):
        from app.services.resume_storage import store_verified_resume

        with tempfile.TemporaryDirectory() as temp_dir:
            source_path = Path(temp_dir) / 'resume.pdf'
            source_path.write_bytes(b'private test resume')
            with override_settings(MEDIA_ROOT=Path(temp_dir) / 'media'):
                reference = store_verified_resume(source_path, '.pdf')
                self.assertTrue(reference.startswith('uploads/resumes/'))
                self.assertEqual(
                    (Path(temp_dir) / 'media' / reference.removeprefix('uploads/')).read_bytes(),
                    b'private test resume',
                )


class ApplicantNotificationTests(TestCase):
    def setUp(self):
        self.user = User.objects.create(
            email='notification-applicant@example.com',
            password='test-password',
            role='applicant',
            first_name='Taylor',
            last_name='Candidate',
            status='active',
        )
        Applicant.objects.create(user=self.user)
        session = self.client.session
        session['user_id'] = self.user.user_id
        session['role'] = 'applicant'
        session.save()

    def test_applicant_navbar_shows_notification_bell_and_unread_count(self):
        Notification.objects.create(
            user=self.user,
            title='Application update',
            message='Your application has been reviewed.',
        )

        response = self.client.get('/applicant/profile/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, '/applicant/notifications/')
        self.assertContains(response, 'fas fa-bell')
        self.assertContains(response, 'aria-label="Notifications, 1 unread"')
        response_html = response.content.decode()
        navbar_menu = response_html.split(
            '<div class="navbar-menu" id="navbarMenu">', 1
        )[1].split('</div>', 1)[0]
        account_actions = response_html.split(
            '<div class="navbar-account-actions">', 1
        )[1].split('</nav>', 1)[0]
        self.assertNotIn('/applicant/notifications/', navbar_menu)
        self.assertNotIn('/applicant/chat.php', navbar_menu)
        self.assertLess(
            account_actions.index('/applicant/notifications/'),
            account_actions.index('id="udWrapper"'),
        )
        self.assertIn('/applicant/chat.php', account_actions)

    def test_notifications_are_private_and_can_be_marked_read(self):
        other_user = User.objects.create(
            email='another-notification-applicant@example.com',
            password='test-password',
            role='applicant',
            status='active',
        )
        own_notification = Notification.objects.create(
            user=self.user,
            title='Application update for me',
            message='This notification belongs to this applicant.',
        )
        other_notification = Notification.objects.create(
            user=other_user,
            title='Private notification for another applicant',
        )

        response = self.client.get('/applicant/notifications/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, own_notification.title)
        self.assertNotContains(response, other_notification.title)
        self.assertEqual(response.context['unread_notification_count'], 1)

        mark_read_response = self.client.post(
            '/applicant/notifications/',
            {
                'action': 'mark_read',
                'notification_id': own_notification.notification_id,
            },
        )

        self.assertRedirects(mark_read_response, '/applicant/notifications/')
        own_notification.refresh_from_db()
        other_notification.refresh_from_db()
        self.assertTrue(own_notification.is_read)
        self.assertFalse(other_notification.is_read)

    def test_mark_all_read_only_updates_current_applicant_notifications(self):
        own_notification = Notification.objects.create(
            user=self.user,
            title='My unread notification',
        )
        other_user = User.objects.create(
            email='other-notification-owner@example.com',
            password='test-password',
            role='applicant',
            status='active',
        )
        other_notification = Notification.objects.create(
            user=other_user,
            title='Other unread notification',
        )

        response = self.client.post(
            '/applicant/notifications/',
            {'action': 'mark_all_read'},
        )

        self.assertRedirects(response, '/applicant/notifications/')
        own_notification.refresh_from_db()
        other_notification.refresh_from_db()
        self.assertTrue(own_notification.is_read)
        self.assertFalse(other_notification.is_read)


class AdminNotificationTests(TestCase):
    def setUp(self):
        self.user = User.objects.create(
            email='notification-admin@example.com',
            password='test-password',
            role='admin',
            first_name='Admin',
            last_name='User',
            status='active',
        )
        session = self.client.session
        session['user_id'] = self.user.user_id
        session['role'] = 'admin'
        session.save()

    def test_notifications_are_private_and_can_be_marked_read(self):
        other_admin = User.objects.create(
            email='another-notification-admin@example.com',
            password='test-password',
            role='admin',
            status='active',
        )
        own_notification = Notification.objects.create(
            user=self.user,
            title='New job request for me',
        )
        other_notification = Notification.objects.create(
            user=other_admin,
            title='Private notification for another admin',
        )

        response = self.client.get('/admin/notifications/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, own_notification.title)
        self.assertNotContains(response, other_notification.title)
        self.assertEqual(response.context['unread_notification_count'], 1)

        mark_read_response = self.client.post(
            '/admin/notifications/',
            {
                'action': 'mark_read',
                'notification_id': own_notification.notification_id,
            },
        )

        self.assertRedirects(mark_read_response, '/admin/notifications/')
        own_notification.refresh_from_db()
        other_notification.refresh_from_db()
        self.assertTrue(own_notification.is_read)
        self.assertFalse(other_notification.is_read)

    def test_admin_notifications_backfill_existing_applications_once(self):
        applicant_user = User.objects.create(
            email='existing-applicant@example.com',
            password='test-password',
            role='applicant',
            first_name='Existing',
            last_name='Applicant',
            status='active',
        )
        applicant = Applicant.objects.create(user=applicant_user)
        job = JobPosting.objects.create(
            employer=Employer.objects.create(
                company_name='Existing Company',
                user=User.objects.create(
                    email='existing-employer@example.com',
                    password='test-password',
                    role='employer',
                    status='active',
                ),
            ),
            title='Existing Application Job',
            status='active',
        )
        application = Application.objects.create(job=job, applicant=applicant)

        response = self.client.get('/admin/notifications/')

        self.assertEqual(response.status_code, 200)
        notification = Notification.objects.get(
            user=self.user,
            type='application',
            title='New applicant applied',
        )
        self.assertIn(f'Application #{application.application_id}:', notification.message)
        self.assertIn('Existing Applicant', notification.message)
        self.assertContains(response, 'New applicant applied')
        self.assertContains(response, 'Existing Application Job')

        self.client.get('/admin/notifications/')
        self.assertEqual(
            Notification.objects.filter(
                user=self.user,
                type='application',
                title='New applicant applied',
            ).count(),
            1,
        )

        poll_response = self.client.get('/admin/notifications/?poll=applications')
        self.assertEqual(poll_response.status_code, 200)
        self.assertEqual(
            [item['notification_id'] for item in poll_response.json()['notifications']],
            [notification.notification_id],
        )

    def test_new_application_notifications_are_returned_once_for_floating_toast(self):
        earlier_notification = Notification.objects.create(
            user=self.user,
            title='Older application alert',
            type='application',
            is_read=True,
        )
        unread_notification = Notification.objects.create(
            user=self.user,
            title='New applicant applied',
            message='Taylor Candidate applied for Software Engineer.',
            type='application',
        )

        first_poll = self.client.get(
            '/admin/notifications/?poll=applications',
        )

        self.assertEqual(first_poll.status_code, 200)
        self.assertEqual(
            first_poll.json()['notifications'],
            [{
                'notification_id': unread_notification.notification_id,
                'title': unread_notification.title,
                'message': unread_notification.message,
            }],
        )
        self.assertEqual(first_poll.json()['unread_notification_count'], 1)

        second_poll = self.client.get('/admin/notifications/?poll=applications')
        self.assertEqual(second_poll.status_code, 200)
        self.assertEqual(second_poll.json()['notifications'], [])

        new_notification = Notification.objects.create(
            user=self.user,
            title='New applicant applied',
            message='Another Candidate applied for Designer.',
            type='application',
        )
        live_poll = self.client.get('/admin/notifications/?poll=applications')
        self.assertEqual(live_poll.status_code, 200)
        self.assertEqual(
            live_poll.json()['notifications'],
            [{
                'notification_id': new_notification.notification_id,
                'title': new_notification.title,
                'message': new_notification.message,
            }],
        )
        self.assertEqual(live_poll.json()['unread_notification_count'], 2)

        notifications_page = self.client.get('/admin/notifications/')
        self.assertEqual(notifications_page.status_code, 200)
        self.assertContains(notifications_page, unread_notification.title)
        self.assertContains(notifications_page, new_notification.title)

        dashboard = self.client.get('/admin/analytics.php')
        self.assertContains(dashboard, 'id="applicationNotificationToast"')
        self.assertContains(dashboard, "notificationUrl + '?poll=applications'")
        self.assertContains(dashboard, '/admin/notifications/')

    def test_employer_bell_and_application_toast_are_scoped_to_employer(self):
        employer_user = User.objects.create(
            email='notification-employer@example.com',
            password='test-password',
            role='employer',
            status='active',
        )
        notification = Notification.objects.create(
            user=employer_user,
            title='New applicant applied',
            message='A new applicant applied for Senior Full Stack Developer.',
            type='application',
        )
        other_employer = User.objects.create(
            email='another-employer@example.com',
            password='test-password',
            role='employer',
            status='active',
        )
        Notification.objects.create(
            user=other_employer,
            title='Private employer notification',
            type='application',
        )

        employer_client = Client()
        session = employer_client.session
        session['user_id'] = employer_user.user_id
        session['role'] = 'employer'
        session.save()

        dashboard = employer_client.get('/employer/dashboard/')
        self.assertEqual(dashboard.status_code, 200)
        self.assertContains(
            dashboard,
            '<a href="/employer/recommended_candidates.php" class="ud-item" role="menuitem">',
        )
        self.assertContains(dashboard, 'Recommended Candidates')
        self.assertContains(dashboard, '/employer/notifications/')
        self.assertContains(dashboard, 'aria-label="Notifications, 1 unread"')
        self.assertContains(dashboard, 'id="applicationNotificationToast"')
        self.assertContains(dashboard, "notificationUrl + '?poll=applications'")

        first_poll = employer_client.get(
            '/employer/notifications/?poll=applications',
        )
        self.assertEqual(first_poll.status_code, 200)
        self.assertEqual(first_poll.json()['unread_notification_count'], 1)
        self.assertEqual(
            first_poll.json()['notifications'],
            [{
                'notification_id': notification.notification_id,
                'title': notification.title,
                'message': notification.message,
            }],
        )
        second_poll = employer_client.get(
            '/employer/notifications/?poll=applications',
        )
        self.assertEqual(second_poll.json()['notifications'], [])

        notifications_page = employer_client.get('/employer/notifications/')
        self.assertEqual(notifications_page.status_code, 200)
        self.assertContains(notifications_page, notification.message)
        self.assertNotContains(notifications_page, 'Private employer notification')
        mark_read_response = employer_client.post(
            '/employer/notifications/',
            {
                'action': 'mark_read',
                'notification_id': notification.notification_id,
            },
        )
        self.assertRedirects(mark_read_response, '/employer/notifications/')
        notification.refresh_from_db()
        self.assertTrue(notification.is_read)

    def test_mark_all_read_only_updates_current_admin_notifications(self):
        own_notification = Notification.objects.create(
            user=self.user,
            title='My unread notification',
        )
        other_admin = User.objects.create(
            email='other-notification-owner@example.com',
            password='test-password',
            role='admin',
            status='active',
        )
        other_notification = Notification.objects.create(
            user=other_admin,
            title='Other unread notification',
        )

        response = self.client.post(
            '/admin/notifications/',
            {'action': 'mark_all_read'},
        )

        self.assertRedirects(response, '/admin/notifications/')
        own_notification.refresh_from_db()
        other_notification.refresh_from_db()
        self.assertTrue(own_notification.is_read)
        self.assertFalse(other_notification.is_read)


class ResumeProfileSyncTests(TestCase):
    def setUp(self):
        self.user = User.objects.create(
            email='resume-sync-applicant@example.com',
            password='not-a-real-account',
            role='applicant',
            first_name='Taylor',
            last_name='Candidate',
            status='active',
        )
        self.applicant = Applicant.objects.create(
            user=self.user,
            skills='Old skill',
            qualifications='Old qualification',
            education_level='Master',
            experience_years=8,
        )
        session = self.client.session
        session['user_id'] = self.user.user_id
        session['role'] = 'applicant'
        session.save()

    def test_resume_name_matching_uses_first_and_last_name(self):
        self.assertEqual(
            extract_resume_name('Taylor Anne Candidate | taylor@example.com\nSkills'),
            ('Taylor Anne', 'Candidate'),
        )
        self.assertTrue(
            resume_name_matches('Taylor Anne', 'Candidate', 'Taylor', 'Candidate')
        )
        self.assertFalse(
            resume_name_matches('Different', 'Person', 'Taylor', 'Candidate')
        )

    @patch(
        'app.services.resume_parser.timezone.localdate',
        return_value=date(2026, 10, 10),
    )
    def test_experience_uses_present_date_range_even_when_resume_summary_is_lower(
        self,
        _today,
    ):
        parsed = parse_resume_text(
            '1 year of experience\nWork Experience\n2024 - present'
        )

        self.assertEqual(parsed['experience_years'], 2)

    @patch(
        'app.services.resume_parser.timezone.localdate',
        return_value=date(2026, 10, 10),
    )
    def test_experience_recognizes_pdf_replacement_character_as_date_separator(
        self,
        _today,
    ):
        parsed = parse_resume_text(
            '1 year of experience\nWork Experience\n2024 \ufffd present'
        )

        self.assertEqual(parsed['experience_years'], 2)

    def test_education_level_reports_highest_recognized_degree(self):
        parsed = parse_resume_text(
            'Education\n'
            'Bachelor of Science in Computer Science\n'
            'Master of Science in Information Technology'
        )

        self.assertEqual(parsed['education_level'], 'Master')

    def test_education_level_recognizes_abbreviated_doctorate(self):
        parsed = parse_resume_text('Education\nB.Sc. in Biology\nPh.D. in Biology')

        self.assertEqual(parsed['education_level'], 'Doctorate')

    @patch(
        'app.services.resume_parser.timezone.localdate',
        return_value=date(2026, 10, 10),
    )
    def test_profile_refreshes_experience_from_existing_resume_analysis(self, _today):
        from app.models import ResumeAnalysis

        self.applicant.resume_file = 'uploads/resumes/current-resume.pdf'
        self.applicant.experience_years = 1
        self.applicant.save()
        analysis = ResumeAnalysis.objects.create(
            applicant=self.applicant,
            resume_file='/tmp/staged-resume.pdf',
            extracted_text=(
                '1 year of experience\n'
                'Work Experience\n'
                '2024 \ufffd present'
            ),
            experience_extracted='1 years',
        )

        response = self.client.get('/applicant/profile/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'value="2" readonly')
        self.applicant.refresh_from_db()
        analysis.refresh_from_db()
        self.assertEqual(self.applicant.experience_years, 2)
        self.assertEqual(analysis.experience_extracted, '2 years')

    @patch('app.views.views_applicant.store_verified_resume')
    @patch('app.views.views_applicant.verify_resume_document')
    def test_profile_rejects_resume_with_different_candidate_name(
        self,
        verify_resume,
        store_resume,
    ):
        verify_resume.return_value = {
            'is_valid': True,
            'confidence_score': 90,
            'extracted_text': 'Different Person\nSkills\nPython',
        }

        response = self.client.post(
            '/applicant/profile/',
            {
                'action': 'upload_resume',
                'resume': SimpleUploadedFile('resume.pdf', b'resume'),
            },
        )

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'does not match')
        store_resume.assert_not_called()
        self.applicant.refresh_from_db()
        self.assertIsNone(self.applicant.resume_file)

    @patch(
        'app.views.views_applicant.store_verified_resume',
        return_value='uploads/resumes/new-resume.pdf',
    )
    @patch('app.views.views_applicant.verify_resume_document')
    def test_profile_refreshes_details_from_matching_resume(
        self,
        verify_resume,
        store_resume,
    ):
        Qualification.objects.create(name='Information Technology', status='active')
        verify_resume.return_value = {
            'is_valid': True,
            'confidence_score': 90,
            'extracted_text': (
                'Taylor Candidate\n'
                '555-123-4567\n'
                'taylor@example.com\n'
                'Professional Experience\n'
                '3 years of experience\n'
                'Education\n'
                'Bachelor of Science in Information Technology\n'
                'Skills\n'
                'Python, Django\n'
            ),
        }

        response = self.client.post(
            '/applicant/profile/',
            {
                'action': 'upload_resume',
                'resume': SimpleUploadedFile('resume.pdf', b'resume'),
            },
        )

        self.assertEqual(response.status_code, 200)
        self.applicant.refresh_from_db()
        self.user.refresh_from_db()
        self.assertEqual(self.applicant.resume_file, 'uploads/resumes/new-resume.pdf')
        self.assertEqual(self.applicant.skills, 'Django, Python')
        self.assertEqual(self.applicant.qualifications, 'Information Technology')
        self.assertEqual(self.applicant.education_level, 'Bachelor')
        self.assertEqual(self.applicant.experience_years, 3)
        self.assertEqual(self.user.phone, '555-123-4567')
        store_resume.assert_called_once()

    @patch('app.views.views_applicant.delete_resume')
    def test_deleting_resume_clears_resume_derived_profile_data(self, delete_resume):
        from app.models import ResumeAnalysis

        self.applicant.resume_file = 'uploads/resumes/current-resume.pdf'
        self.applicant.employability_score = Decimal('82.50')
        self.applicant.profile_completed = True
        self.applicant.save()
        self.user.phone = '555-123-4567'
        self.user.save()
        ResumeAnalysis.objects.create(
            applicant=self.applicant,
            resume_file='uploads/resumes/current-resume.pdf',
            extracted_text='Taylor Candidate resume text',
            skills_extracted='Old skill',
        )

        response = self.client.post(
            '/applicant/profile/',
            {'action': 'delete_resume'},
        )

        self.assertEqual(response.status_code, 200)
        delete_resume.assert_called_once_with('uploads/resumes/current-resume.pdf')
        self.applicant.refresh_from_db()
        self.user.refresh_from_db()
        self.assertIsNone(self.applicant.resume_file)
        self.assertEqual(self.applicant.skills, '')
        self.assertEqual(self.applicant.qualifications, '')
        self.assertEqual(self.applicant.education_level, '')
        self.assertEqual(self.applicant.experience_years, 0)
        self.assertEqual(self.applicant.employability_score, 0)
        self.assertFalse(self.applicant.profile_completed)
        self.assertEqual(self.user.phone, '')
        self.assertEqual(ResumeAnalysis.objects.filter(applicant=self.applicant).count(), 0)
        self.assertEqual(self.user.first_name, 'Taylor')
        self.assertEqual(self.user.last_name, 'Candidate')


class UserSessionValidationMiddlewareTests(TestCase):
    def test_unchanged_user_details_do_not_mark_session_modified(self):
        from django.contrib.sessions.backends.db import SessionStore
        from django.test import RequestFactory
        from app.middleware import UserSessionValidationMiddleware

        user = User.objects.create(
            email='session-check@example.com',
            password='test-password',
            role='applicant',
            first_name='Session',
            last_name='Check',
            status='active',
        )
        session = SessionStore()
        session.update({
            'user_id': user.user_id,
            'email': user.email,
            'role': user.role,
            'first_name': user.first_name,
            'last_name': user.last_name,
        })
        session.save()
        session.modified = False

        request = RequestFactory().get('/applicant/dashboard/')
        request.session = session
        middleware = UserSessionValidationMiddleware(lambda current_request: None)
        middleware.process_view(request, lambda current_request: None, (), {})

        self.assertFalse(session.modified)
