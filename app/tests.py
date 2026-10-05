import json
import os
import sys
import tempfile
import types
from decimal import Decimal
from pathlib import Path
from unittest.mock import Mock, patch
from django.test import TestCase, Client, override_settings
from django.core import mail
from django.urls import reverse
from app.models import (
    User, Applicant, Employer, JobPosting, Application,
    Qualification, Skill, ContactInquiry, Message, AuditTrail,
    Notification, CmsHeroSlide, CmsBrand, CmsTestimonial
)
from app.auth_utils import verify_password, hash_password
from app.services.mailer import send_application_status_update_email
from app.services.ml_job_matching import (
    MATCH_CLASSES,
    evaluate_match_model,
    train_match_classifier,
)
from app.services.qualification import classify_match_score

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
            (30, 'unclassified'),
            (39.99, 'unclassified'),
            (40, 'under_qualified'),
            (60, 'under_qualified'),
            (60.01, 'qualified'),
        ]
        for score, expected_status in expected_statuses:
            with self.subTest(score=score):
                self.assertEqual(classify_match_score(score), expected_status)

    def test_admin_dashboard_score_distribution_uses_qualification_bands(self):
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
        self.assertEqual(chart_data['match_tier_data'], [1, 1, 2, 1, 1])

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

            fake_discovery = types.ModuleType('googleapiclient.discovery')
            fake_http = types.ModuleType('googleapiclient.http')
            fake_service = Mock()
            fake_service.files.return_value.create.return_value.execute.return_value = {
                'id': 'file-123', 'name': 'demo.xlsx', 'webViewLink': 'https://example.com'
            }
            fake_discovery.build = Mock(return_value=fake_service)
            fake_http.MediaIoBaseUpload = Mock()

            modules = {
                'google': fake_google,
                'google.oauth2': fake_oauth2,
                'google.oauth2.credentials': fake_credentials_module,
                'google.auth': fake_auth,
                'google.auth.transport': fake_transport,
                'google.auth.transport.requests': fake_requests,
                'googleapiclient.discovery': fake_discovery,
                'googleapiclient.http': fake_http,
            }
            from app.services import google_drive_service
            with patch.dict(sys.modules, modules), patch.object(
                google_drive_service.settings, 'BASE_DIR', Path(temp_dir)
            ), patch.object(
                google_drive_service, '_find_oauth_client_secret', return_value=client_secret_path
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

    def test_tfidf_svm_match_model_uses_three_classes_and_holdout_metrics(self):
        samples = [
            {'text': f'{label} match role {index} skill experience', 'label': label}
            for label in MATCH_CLASSES
            for index in range(4)
        ]

        model, status = train_match_classifier(samples)
        evaluation = evaluate_match_model(samples)

        self.assertTrue(status['ready'])
        self.assertEqual(tuple(model.named_steps['svm'].classes_), tuple(sorted(MATCH_CLASSES)))
        self.assertIn('tfidf', model.named_steps)
        self.assertEqual(set(model.predict(['high match role skill'])), {'high'})
        self.assertTrue(evaluation['ready'], evaluation)
        self.assertEqual(len(evaluation['confusion_rows']), 3)
        self.assertTrue(all(len(row['cells']) == 3 for row in evaluation['confusion_rows']))
        self.assertEqual(
            sum(cell['count'] for row in evaluation['confusion_rows'] for cell in row['cells']),
            evaluation['test_count'],
        )
        matrix = [
            [cell['count'] for cell in row['cells']]
            for row in evaluation['confusion_rows']
        ]
        total = sum(sum(row) for row in matrix)
        true_positives = [matrix[index][index] for index in range(3)]
        expected_accuracy = sum(true_positives) / total
        expected_precision = sum(
            true_positives[index] / sum(row[index] for row in matrix)
            if sum(row[index] for row in matrix) else 0
            for index in range(3)
        ) / 3
        expected_recall = sum(
            true_positives[index] / sum(matrix[index])
            for index in range(3)
        ) / 3
        expected_f1 = sum(
            2 * (
                (true_positives[index] / sum(row[index] for row in matrix)
                 if sum(row[index] for row in matrix) else 0)
                * (true_positives[index] / sum(matrix[index]))
            ) / (
                (true_positives[index] / sum(row[index] for row in matrix)
                 if sum(row[index] for row in matrix) else 0)
                + (true_positives[index] / sum(matrix[index]))
            )
            if (
                (true_positives[index] / sum(row[index] for row in matrix)
                 if sum(row[index] for row in matrix) else 0)
                + (true_positives[index] / sum(matrix[index]))
            ) else 0
            for index in range(3)
        ) / 3
        expected_chance = sum(
            sum(matrix[index]) * sum(row[index] for row in matrix)
            for index in range(3)
        ) / total ** 2
        expected_kappa = (
            (expected_accuracy - expected_chance) / (1 - expected_chance)
            if expected_chance < 1 else 1
        )
        self.assertAlmostEqual(evaluation['accuracy'], expected_accuracy)
        self.assertAlmostEqual(evaluation['precision'], expected_precision)
        self.assertAlmostEqual(evaluation['recall'], expected_recall)
        self.assertAlmostEqual(evaluation['f1'], expected_f1)
        self.assertAlmostEqual(evaluation['kappa'], expected_kappa)
        for metric in ('accuracy', 'precision', 'recall', 'f1', 'kappa'):
            self.assertIn(metric, evaluation)
        self.assertEqual(evaluation['test_count'], 6)

    def test_tfidf_svm_waits_for_reviewed_examples_in_all_three_classes(self):
        samples = [
            {'text': f'{label} match role {index}', 'label': label}
            for label, count in [('high', 2), ('medium', 2), ('low', 1)]
            for index in range(count)
        ]

        model, status = train_match_classifier(samples)

        self.assertIsNone(model)
        self.assertFalse(status['ready'])
        self.assertIn('at least two admin-reviewed examples each', status['reason'])

    def test_job_list_adds_svm_class_without_changing_numeric_match_score(self):
        reviewed_labels = [
            ('qualified', 'python django senior developer'),
            ('under_qualified', 'training internship junior'),
            ('not_qualified', 'beginner unrelated background'),
        ]
        for label, skills in reviewed_labels:
            for index in range(2):
                user = User.objects.create(
                    email=f'match-{label}-{index}@example.com',
                    password='test-password',
                    role='applicant',
                    status='active',
                )
                applicant = Applicant.objects.create(
                    user=user,
                    skills=skills,
                    qualifications='Information Technology',
                    experience_years=index,
                )
                Application.objects.create(
                    job=self.job,
                    applicant=applicant,
                    admin_qualification=label,
                )

        session = self.client.session
        session['user_id'] = self.applicant_user.user_id
        session['role'] = 'applicant'
        session.save()

        response = self.client.get('/applicant/jobs.php')

        self.assertEqual(response.status_code, 200)
        self.assertTrue(response.context['match_model_status']['ready'])
        self.assertIn(response.context['jobs'][0]['ml_match_class'], MATCH_CLASSES)
        self.assertEqual(response.context['jobs'][0]['match_score'], 0)
        self.assertContains(response, 'SVM ')

    def test_admin_analytics_renders_svm_evaluation_status(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/analytics.php')

        self.assertEqual(response.status_code, 200)
        self.assertFalse(response.context['match_model_evaluation']['ready'])
        self.assertContains(response, 'NLP + TF-IDF + SVM Match Evaluation')
        self.assertContains(response, 'at least two admin-reviewed examples each')

    def test_admin_analytics_renders_three_by_three_heatmap(self):
        for label, skills in [
            ('qualified', 'python django senior developer'),
            ('under_qualified', 'training internship junior'),
            ('not_qualified', 'beginner unrelated background'),
        ]:
            for index in range(2):
                user = User.objects.create(
                    email=f'analytics-{label}-{index}@example.com',
                    password='test-password',
                    role='applicant',
                    status='active',
                )
                applicant = Applicant.objects.create(
                    user=user,
                    skills=skills,
                    qualifications='Information Technology',
                    experience_years=index,
                )
                Application.objects.create(
                    job=self.job,
                    applicant=applicant,
                    admin_qualification=label,
                )


        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get('/admin/analytics.php')

        evaluation = response.context['match_model_evaluation']
        self.assertEqual(response.status_code, 200)
        self.assertTrue(evaluation['ready'], evaluation)
        self.assertEqual(len(evaluation['confusion_rows']), 3)
        self.assertTrue(all(len(row['cells']) == 3 for row in evaluation['confusion_rows']))
        self.assertContains(response, "Cohen's Kappa")
        self.assertContains(response, 'background-color:rgba(13,110,253,')

    def test_admin_job_candidates_page_renders(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.get(f'/admin/jobs/{self.job.job_id}/candidates/')

        self.assertEqual(response.status_code, 200)
        self.assertContains(response, 'No Candidates Found')
        self.assertContains(response, self.job.title)
        self.assertContains(response, 'AI Match &gt; 60%')
        self.assertContains(response, 'Unclassified Only')

    @override_settings(EMAIL_BACKEND='django.core.mail.backends.locmem.EmailBackend')
    def test_admin_dispatch_notice_route_is_rendered_and_sends_notice(self):
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

        candidates_response = self.client.get(f'/admin/jobs/{self.job.job_id}/candidates/')

        self.assertEqual(candidates_response.status_code, 200)
        self.assertContains(
            candidates_response,
            f'data-notification-url="/admin/notify_applicant/{application.application_id}/"',
        )

        notice_response = self.client.post(f'/admin/notify_applicant/{application.application_id}/')

        self.assertRedirects(
            notice_response,
            f'/admin/jobs/{self.job.job_id}/candidates/?notified=email_sent',
            fetch_redirect_response=False,
        )
        application.refresh_from_db()
        self.assertEqual(application.status, 'reviewed')
        notice = Notification.objects.get(user=self.applicant_user)
        self.assertIn('Qualified', notice.title)
        self.assertIn('88%', notice.message)
        self.assertEqual(len(mail.outbox), 1)
        self.assertEqual(mail.outbox[0].to, [self.applicant_user.email])
        self.assertIn('Qualified', mail.outbox[0].body)
        self.assertIn('88%', mail.outbox[0].body)
        self.assertNotIn('Applications Page', mail.outbox[0].alternatives[0][0])

    @override_settings(EMAIL_BACKEND='django.core.mail.backends.locmem.EmailBackend')
    def test_admin_dispatch_notice_email_includes_each_qualification_result(self):
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        expected_results = [
            (Decimal('75.00'), 'Qualified'),
            (Decimal('60.00'), 'Under-Qualified'),
            (Decimal('35.00'), 'Unclassified'),
            (Decimal('29.00'), 'Not Qualified'),
        ]
        for score, expected_result in expected_results:
            with self.subTest(score=score):
                application = Application.objects.create(
                    job=self.job,
                    applicant=self.applicant,
                    match_score=score,
                )

                response = self.client.post(
                    f'/admin/notify_applicant/{application.application_id}/',
                )

                self.assertEqual(response.status_code, 302)
                sent_email = mail.outbox[-1]
                self.assertIn(expected_result, sent_email.subject)
                self.assertIn(expected_result, sent_email.body)
                self.assertNotIn('Applications Page', sent_email.alternatives[0][0])

    @patch('app.services.mailer.send_mail', side_effect=OSError('SMTP unavailable'))
    def test_admin_dispatch_notice_reports_email_delivery_failure(self, _send_mail):
        application = Application.objects.create(
            job=self.job,
            applicant=self.applicant,
            status='pending',
        )
        session = self.client.session
        session['user_id'] = self.admin_user.user_id
        session['role'] = 'admin'
        session.save()

        response = self.client.post(f'/admin/notify_applicant/{application.application_id}/')

        self.assertRedirects(
            response,
            f'/admin/jobs/{self.job.job_id}/candidates/?notified=email_failed',
            fetch_redirect_response=False,
        )
        self.assertTrue(Notification.objects.filter(user=self.applicant_user).exists())
        failure_page = self.client.get(response.url)
        self.assertContains(failure_page, 'Email was not sent.')

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
        self.assertEqual(forward_response.json()['count'], 1)
        application.refresh_from_db()
        self.assertTrue(application.forwarded_to_employer)
        self.assertTrue(Notification.objects.filter(
            user=self.employer_user,
            message__contains='Job Seeker',
        ).exists())

        session = self.client.session
        session['user_id'] = self.employer_user.user_id
        session['role'] = 'employer'
        session.save()

        candidates_response = self.client.get('/employer/candidates.php')

        self.assertEqual(candidates_response.status_code, 200)
        self.assertContains(candidates_response, 'Job Seeker')
        self.assertContains(candidates_response, self.job.title)
        self.assertContains(candidates_response, 'Review Dossier')
        self.assertEqual(
            candidates_response.context['applications'][0].application_id,
            application.application_id,
        )

        application.employer_status = 'for_interview'
        application.save(update_fields=['employer_status'])

        interview_response = self.client.get('/employer/candidates.php?tab=interview')

        self.assertEqual(interview_response.status_code, 200)
        self.assertContains(interview_response, 'Job Seeker')
        self.assertEqual(interview_response.context['current_tab'], 'interview')
        self.assertEqual(
            interview_response.context['applications'][0].application_id,
            application.application_id,
        )

    def test_batch_forward_unclassified_includes_only_30_to_under_40_scores(self):
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

        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()['count'], 2)
        for score, application in applications_by_score.items():
            application.refresh_from_db()
            self.assertEqual(
                application.forwarded_to_employer,
                Decimal('30.00') <= score < Decimal('40.00'),
                msg=f'Unexpected forwarding result for score {score}',
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
