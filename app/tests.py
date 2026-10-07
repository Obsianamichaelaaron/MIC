import json
import os
import sys
import tempfile
import types
from datetime import timedelta
from decimal import Decimal
from pathlib import Path
from unittest.mock import Mock, patch
from django.db import connection, transaction
from django.test import TestCase, Client, TransactionTestCase, override_settings
from django.core import mail
from django.urls import reverse
from app.models import (
    User, Applicant, Employer, JobPosting, Application,
    Qualification, JobQualificationMapping, Skill, ContactInquiry, Message, AuditTrail,
    Notification, CmsHeroSlide, CmsBrand, CmsTestimonial
)
from app.auth_utils import verify_password, hash_password
from app.services.mailer import send_application_status_update_email
from app.services.ml_ranking import calculate_candidate_ml_score, compute_job_match_result
from app.services.semantic_job_matching import (
    SemanticMatchingUnavailable,
    _load_wordpiece_tokenizer,
    _criteria_groups,
    compute_semantic_match,
)
from app.services.qualification import classify_match_score
from app.services.database_rls import set_authenticated_rls_context

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

    def test_browse_jobs_use_pretrained_semantic_model_without_review_labels(self):
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

    def test_job_list_shows_pretrained_semantic_score_without_admin_reviews(self):
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

    def test_public_careers_shows_up_to_30_unique_jobs_with_company_names(self):
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
        self.assertEqual(len(jobs), 30)
        keys = {
            (
                ' '.join(job.employer.company_name.casefold().split()),
                ' '.join(job.title.casefold().split()),
            )
            for job in jobs
        }
        self.assertEqual(len(keys), len(jobs))
        self.assertEqual(response.context['total_jobs_count'], 30)
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
        self.assertEqual(len(admin_ids & matching_title_ids), 1)
        self.assertEqual(admin_response.context['counts']['active'], len(careers_ids))

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
            match_score=Decimal('88.00'),
        )

        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        forward_response = self.client.post('/admin/batch_forward_candidates/', {
            'job_id': self.job.job_id,
            'scope': 'qualified',
        })

        self.assertEqual(forward_response.status_code, 200)
        self.assertTrue(forward_response.json()['success'])
        application.refresh_from_db()
        self.assertTrue(application.forwarded_to_employer)
        self.assertTrue(Notification.objects.filter(user=self.employer_user).exists())

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

    def test_admin_posted_job_creates_company_account_and_appears_only_for_that_employer(self):
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
            'contact_first_name': 'Partner',
            'contact_last_name': 'Contact',
            'account_email': 'partner-login@example.com',
            'account_password': 'temporary123',
            'contact_phone': '555-0100',
            'industry': 'Technology',
            'company_website': 'https://partner.example.com',
            'company_address': 'Manila',
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
        partner_user = User.objects.get(email='partner-login@example.com')
        partner = Employer.objects.get(user=partner_user)
        new_job = JobPosting.objects.get(title='New Partner Vacancy')
        legacy_job.refresh_from_db()
        self.assertEqual(partner_user.role, 'employer')
        self.assertTrue(partner_user.created_by_admin)
        self.assertTrue(verify_password('temporary123', partner_user.password))
        self.assertEqual(new_job.employer, partner)
        self.assertEqual(legacy_job.employer, partner)

        session = self.client.session
        session['user_id'] = partner_user.user_id
        session['role'] = 'employer'
        session.save()
        dashboard_response = self.client.get('/employer/dashboard.php')
        jobs_response = self.client.get('/employer/jobs.php')
        self.assertEqual(dashboard_response.status_code, 200)
        self.assertContains(dashboard_response, 'New Partner Vacancy')
        self.assertContains(dashboard_response, 'Legacy Partner Vacancy')
        self.assertEqual(jobs_response.status_code, 200)
        self.assertContains(jobs_response, 'New Partner Vacancy')
        self.assertContains(jobs_response, 'Legacy Partner Vacancy')

        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session.save()
        other_company_response = self.client.get('/employer/jobs.php')
        self.assertEqual(other_company_response.status_code, 200)
        self.assertNotContains(other_company_response, 'New Partner Vacancy')

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

    def test_admin_cannot_post_for_new_company_without_employer_login_details(self):
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

        self.assertEqual(response.status_code, 200)
        self.assertEqual(
            response.context['error'],
            "For a new company, provide the contact's name, login email, and temporary password.",
        )
        self.assertFalse(Employer.objects.filter(company_name='Unregistered Partner Ltd.').exists())
        self.assertFalse(JobPosting.objects.filter(title='Partner Vacancy').exists())

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


class PostgreSQLRLSContextTests(TransactionTestCase):
    def test_session_role_is_reloaded_from_account_record(self):
        if connection.vendor != 'postgresql':
            self.skipTest('PostgreSQL row-level security is only available on PostgreSQL.')

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

    def test_forged_rls_identity_settings_are_rejected(self):
        if connection.vendor != 'postgresql':
            self.skipTest('PostgreSQL row-level security is only available on PostgreSQL.')

        applicant = User.objects.create(
            email='rls-context-applicant@example.com',
            password='not-a-real-account',
            role='applicant',
            status='active',
        )

        with transaction.atomic():
            with connection.cursor() as cursor:
                cursor.execute('SET LOCAL ROLE mic_app_rls')
                cursor.execute(
                    "SELECT set_config('app.session_key', 'rls-test-session', true)"
                )

            set_authenticated_rls_context(applicant)
            with connection.cursor() as cursor:
                cursor.execute(
                    """
                    SELECT public.mic_rls_context_valid(),
                           public.mic_rls_user_id(),
                           public.mic_rls_user_role()
                    """
                )
                valid, user_id, role = cursor.fetchone()

            self.assertTrue(valid)
            self.assertEqual(user_id, applicant.pk)
            self.assertEqual(role, 'applicant')

            with connection.cursor() as cursor:
                cursor.execute(
                    "SELECT set_config('app.user_id', %s, true), "
                    "set_config('app.user_role', 'admin', true)",
                    [str(applicant.pk + 1000000)],
                )
                cursor.execute(
                    """
                    SELECT public.mic_rls_context_valid(),
                           public.mic_rls_user_id(),
                           public.mic_rls_user_role(),
                           public.mic_rls_is_admin()
                    """
                )
                valid, user_id, role, is_admin = cursor.fetchone()

            self.assertFalse(valid)
            self.assertIsNone(user_id)
            self.assertEqual(role, '')
            self.assertFalse(is_admin)


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

    @patch('app.services.resume_storage.requests.request')
    def test_vercel_resume_upload_uses_private_supabase_bucket(self, storage_request):
        from app.services.resume_storage import (
            SUPABASE_RESUME_PREFIX,
            store_verified_resume,
        )

        storage_request.return_value.raise_for_status.return_value = None
        with tempfile.TemporaryDirectory() as temp_dir:
            source_path = Path(temp_dir) / 'resume.pdf'
            source_path.write_bytes(b'private test resume')
            with override_settings(
                SUPABASE_URL='https://storage.example.invalid',
                SUPABASE_SERVICE_ROLE_KEY='test-only-service-key',
                RESUME_STORAGE_BUCKET='private-resumes',
            ):
                with patch.dict(os.environ, {'VERCEL': '1'}):
                    reference = store_verified_resume(source_path, '.pdf')

        self.assertTrue(reference.startswith(SUPABASE_RESUME_PREFIX))
        self.assertTrue(reference.startswith('supabase://resumes/'))
        self.assertIn(
            '/storage/v1/object/private-resumes/resumes/',
            storage_request.call_args.args[1],
        )
