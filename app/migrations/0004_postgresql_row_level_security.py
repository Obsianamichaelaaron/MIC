from django.db import migrations


FORWARD_SQL = r"""
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'mic_app_rls') THEN
        CREATE ROLE mic_app_rls NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
    END IF;
END
$$;

GRANT mic_app_rls TO CURRENT_USER;
GRANT USAGE ON SCHEMA public TO mic_app_rls;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO mic_app_rls;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO mic_app_rls;

CREATE OR REPLACE FUNCTION public.mic_rls_user_id()
RETURNS integer
LANGUAGE sql
STABLE
AS $$
    SELECT NULLIF(current_setting('app.user_id', true), '')::integer
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_user_role()
RETURNS text
LANGUAGE sql
STABLE
AS $$
    SELECT COALESCE(current_setting('app.user_role', true), '')
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_is_admin()
RETURNS boolean
LANGUAGE sql
STABLE
AS $$
    SELECT public.mic_rls_user_role() = 'admin'
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_is_registration()
RETURNS boolean
LANGUAGE sql
STABLE
AS $$
    SELECT COALESCE(current_setting('app.registration', true), '') = 'true'
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_owns_employer(target_employer_id integer)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.employers e
        WHERE e.employer_id = target_employer_id
          AND e.user_id = public.mic_rls_user_id()
    )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_owns_job(target_job_id integer)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT EXISTS (
        SELECT 1
        FROM public.job_postings j
        JOIN public.employers e ON e.employer_id = j.employer_id
        WHERE j.job_id = target_job_id
          AND e.user_id = public.mic_rls_user_id()
    )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_can_access_application(target_application_id integer)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT public.mic_rls_is_admin() OR EXISTS (
        SELECT 1
        FROM public.applications a
        JOIN public.applicants ap ON ap.applicant_id = a.applicant_id
        JOIN public.job_postings j ON j.job_id = a.job_id
        JOIN public.employers e ON e.employer_id = j.employer_id
        WHERE a.application_id = target_application_id
          AND (
              ap.user_id = public.mic_rls_user_id()
              OR (
                  e.user_id = public.mic_rls_user_id()
                  AND a.forwarded_to_employer
              )
          )
    )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_can_access_applicant(target_applicant_id integer)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = target_applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
        OR EXISTS (
            SELECT 1
            FROM public.applications a
            JOIN public.job_postings j ON j.job_id = a.job_id
            JOIN public.employers e ON e.employer_id = j.employer_id
            WHERE a.applicant_id = target_applicant_id
              AND e.user_id = public.mic_rls_user_id()
              AND a.forwarded_to_employer
        )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_user_is_visible(target_user_id integer)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT public.mic_rls_is_admin()
        OR target_user_id = public.mic_rls_user_id()
        OR (
            public.mic_rls_user_id() IS NOT NULL
            AND EXISTS (
                SELECT 1 FROM public.users u
                WHERE u.user_id = target_user_id AND u.role = 'admin'
            )
        )
        OR EXISTS (
            SELECT 1 FROM public.messages m
            WHERE (m.sender_id = public.mic_rls_user_id() AND m.receiver_id = target_user_id)
               OR (m.receiver_id = public.mic_rls_user_id() AND m.sender_id = target_user_id)
        )
        OR EXISTS (
            SELECT 1
            FROM public.applications a
            JOIN public.job_postings j ON j.job_id = a.job_id
            JOIN public.employers e ON e.employer_id = j.employer_id
            JOIN public.applicants ap ON ap.applicant_id = a.applicant_id
            WHERE a.forwarded_to_employer
              AND (
                  (e.user_id = public.mic_rls_user_id() AND ap.user_id = target_user_id)
                  OR (ap.user_id = public.mic_rls_user_id() AND e.user_id = target_user_id)
              )
        )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_public_employer(target_employer_id integer)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.job_postings j
        WHERE j.employer_id = target_employer_id AND j.status = 'active'
    )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_public_job(target_job_id integer)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.job_postings j
        WHERE j.job_id = target_job_id AND j.status = 'active'
    )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_public_cms_section(target_section_id integer)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.cms_sections s WHERE s.id = target_section_id
    )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_email_exists(target_email text)
RETURNS boolean
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT EXISTS (
        SELECT 1 FROM public.users u WHERE lower(u.email) = lower(target_email)
    )
$$;

CREATE OR REPLACE FUNCTION public.mic_rls_authenticate_user(target_email text)
RETURNS TABLE (
    user_id integer,
    email varchar,
    password varchar,
    role varchar,
    status varchar,
    first_name varchar,
    last_name varchar
)
LANGUAGE sql
STABLE
SECURITY DEFINER
SET search_path = pg_catalog, public
AS $$
    SELECT u.user_id, u.email, u.password, u.role, u.status, u.first_name, u.last_name
    FROM public.users u
    WHERE lower(u.email) = lower(target_email)
    ORDER BY u.user_id
    LIMIT 1
$$;

REVOKE ALL ON FUNCTION public.mic_rls_user_id() FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_user_role() FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_is_admin() FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_is_registration() FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_owns_employer(integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_owns_job(integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_can_access_application(integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_can_access_applicant(integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_user_is_visible(integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_public_employer(integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_public_job(integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_public_cms_section(integer) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_email_exists(text) FROM PUBLIC;
REVOKE ALL ON FUNCTION public.mic_rls_authenticate_user(text) FROM PUBLIC;

GRANT EXECUTE ON FUNCTION public.mic_rls_user_id() TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_user_role() TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_is_admin() TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_is_registration() TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_owns_employer(integer) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_owns_job(integer) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_can_access_application(integer) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_can_access_applicant(integer) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_user_is_visible(integer) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_public_employer(integer) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_public_job(integer) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_public_cms_section(integer) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_email_exists(text) TO mic_app_rls;
GRANT EXECUTE ON FUNCTION public.mic_rls_authenticate_user(text) TO mic_app_rls;

DO $$
DECLARE
    table_name text;
BEGIN
    FOREACH table_name IN ARRAY ARRAY[
        'applicants', 'applications', 'audit_trail', 'auth_group',
        'auth_group_permissions', 'auth_permission', 'auth_user',
        'auth_user_groups', 'auth_user_user_permissions', 'candidate_feedback',
        'candidate_ml_features', 'candidate_recommendations', 'chatbot_answers',
        'chatbot_recommendations', 'cms_brands', 'cms_content', 'cms_hero_slides',
        'cms_news', 'cms_sections', 'cms_testimonials', 'contact_inquiries',
        'contact_replies', 'django_admin_log', 'django_content_type',
        'django_migrations', 'django_session', 'employers', 'feedback',
        'interview_schedules', 'job_postings', 'job_qualification_mapping',
        'job_recommendations', 'messages', 'ml_application_screening',
        'ml_feature_importance', 'ml_model_performance', 'notifications',
        'qualifications', 'resume_analysis', 'saved_jobs', 'skills', 'users'
    ] LOOP
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', table_name);
    END LOOP;
END
$$;

CREATE POLICY mic_rls_users_select ON public.users
    FOR SELECT USING (public.mic_rls_user_is_visible(user_id));
CREATE POLICY mic_rls_users_insert ON public.users
    FOR INSERT WITH CHECK (
        public.mic_rls_is_admin()
        OR (
            public.mic_rls_is_registration()
            AND role IN ('applicant', 'employer')
            AND status = 'active'
            AND NOT created_by_admin
        )
    );
CREATE POLICY mic_rls_users_update ON public.users
    FOR UPDATE
    USING (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id())
    WITH CHECK (
        public.mic_rls_is_admin()
        OR (user_id = public.mic_rls_user_id() AND role IN ('applicant', 'employer'))
    );
CREATE POLICY mic_rls_users_delete ON public.users
    FOR DELETE USING (public.mic_rls_is_admin());

CREATE POLICY mic_rls_applicants_select ON public.applicants
    FOR SELECT USING (public.mic_rls_can_access_applicant(applicant_id));
CREATE POLICY mic_rls_applicants_insert ON public.applicants
    FOR INSERT WITH CHECK (
        public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id()
    );
CREATE POLICY mic_rls_applicants_update ON public.applicants
    FOR UPDATE
    USING (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id())
    WITH CHECK (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id());
CREATE POLICY mic_rls_applicants_delete ON public.applicants
    FOR DELETE USING (public.mic_rls_is_admin());

CREATE POLICY mic_rls_employers_select ON public.employers
    FOR SELECT USING (
        public.mic_rls_is_admin()
        OR user_id = public.mic_rls_user_id()
        OR public.mic_rls_public_employer(employer_id)
    );
CREATE POLICY mic_rls_employers_insert ON public.employers
    FOR INSERT WITH CHECK (
        public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id()
    );
CREATE POLICY mic_rls_employers_update ON public.employers
    FOR UPDATE
    USING (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id())
    WITH CHECK (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id());
CREATE POLICY mic_rls_employers_delete ON public.employers
    FOR DELETE USING (public.mic_rls_is_admin());

CREATE POLICY mic_rls_jobs_select ON public.job_postings
    FOR SELECT USING (
        public.mic_rls_is_admin()
        OR public.mic_rls_owns_employer(employer_id)
        OR status = 'active'
    );
CREATE POLICY mic_rls_jobs_insert ON public.job_postings
    FOR INSERT WITH CHECK (
        public.mic_rls_is_admin() OR public.mic_rls_owns_employer(employer_id)
    );
CREATE POLICY mic_rls_jobs_update ON public.job_postings
    FOR UPDATE
    USING (public.mic_rls_is_admin() OR public.mic_rls_owns_employer(employer_id))
    WITH CHECK (public.mic_rls_is_admin() OR public.mic_rls_owns_employer(employer_id));
CREATE POLICY mic_rls_jobs_delete ON public.job_postings
    FOR DELETE USING (public.mic_rls_is_admin());

CREATE POLICY mic_rls_job_qualifications_select ON public.job_qualification_mapping
    FOR SELECT USING (
        public.mic_rls_is_admin()
        OR public.mic_rls_owns_job(job_id)
        OR public.mic_rls_public_job(job_id)
    );
CREATE POLICY mic_rls_job_qualifications_insert ON public.job_qualification_mapping
    FOR INSERT WITH CHECK (
        public.mic_rls_is_admin() OR public.mic_rls_owns_job(job_id)
    );
CREATE POLICY mic_rls_job_qualifications_update ON public.job_qualification_mapping
    FOR UPDATE
    USING (public.mic_rls_is_admin() OR public.mic_rls_owns_job(job_id))
    WITH CHECK (public.mic_rls_is_admin() OR public.mic_rls_owns_job(job_id));
CREATE POLICY mic_rls_job_qualifications_delete ON public.job_qualification_mapping
    FOR DELETE USING (public.mic_rls_is_admin() OR public.mic_rls_owns_job(job_id));

CREATE POLICY mic_rls_applications_select ON public.applications
    FOR SELECT USING (public.mic_rls_can_access_application(application_id));
CREATE POLICY mic_rls_applications_insert ON public.applications
    FOR INSERT WITH CHECK (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = applications.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    );
CREATE POLICY mic_rls_applications_update ON public.applications
    FOR UPDATE
    USING (public.mic_rls_can_access_application(application_id))
    WITH CHECK (public.mic_rls_can_access_application(application_id));
CREATE POLICY mic_rls_applications_delete ON public.applications
    FOR DELETE USING (public.mic_rls_is_admin());

CREATE POLICY mic_rls_saved_jobs_all ON public.saved_jobs
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = saved_jobs.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = saved_jobs.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    );

CREATE POLICY mic_rls_messages_all ON public.messages
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR sender_id = public.mic_rls_user_id()
        OR receiver_id = public.mic_rls_user_id()
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR sender_id = public.mic_rls_user_id()
    );

CREATE POLICY mic_rls_notifications_all ON public.notifications
    FOR ALL
    USING (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id())
    WITH CHECK (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id());

CREATE POLICY mic_rls_interviews_all ON public.interview_schedules
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR public.mic_rls_owns_employer(employer_id)
        OR public.mic_rls_can_access_application(application_id)
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR public.mic_rls_owns_employer(employer_id)
    );

CREATE POLICY mic_rls_feedback_all ON public.feedback
    FOR ALL
    USING (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id())
    WITH CHECK (public.mic_rls_is_admin() OR user_id = public.mic_rls_user_id());

CREATE POLICY mic_rls_candidate_feedback_all ON public.candidate_feedback
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR applicant_id IN (
            SELECT ap.applicant_id FROM public.applicants ap
            WHERE ap.user_id = public.mic_rls_user_id()
        )
        OR public.mic_rls_owns_employer(employer_id)
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR applicant_id IN (
            SELECT ap.applicant_id FROM public.applicants ap
            WHERE ap.user_id = public.mic_rls_user_id()
        )
        OR public.mic_rls_owns_employer(employer_id)
    );

CREATE POLICY mic_rls_job_recommendations_all ON public.job_recommendations
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = job_recommendations.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = job_recommendations.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    );

CREATE POLICY mic_rls_candidate_recommendations_all ON public.candidate_recommendations
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR public.mic_rls_owns_employer(employer_id)
        OR public.mic_rls_can_access_applicant(applicant_id)
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR public.mic_rls_owns_employer(employer_id)
    );

CREATE POLICY mic_rls_resume_analysis_all ON public.resume_analysis
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR public.mic_rls_can_access_applicant(applicant_id)
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = resume_analysis.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    );

CREATE POLICY mic_rls_chatbot_answers_all ON public.chatbot_answers
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = chatbot_answers.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = chatbot_answers.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    );

CREATE POLICY mic_rls_chatbot_recommendations_all ON public.chatbot_recommendations
    FOR ALL
    USING (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = chatbot_recommendations.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    )
    WITH CHECK (
        public.mic_rls_is_admin()
        OR EXISTS (
            SELECT 1 FROM public.applicants ap
            WHERE ap.applicant_id = chatbot_recommendations.applicant_id
              AND ap.user_id = public.mic_rls_user_id()
        )
    );

CREATE POLICY mic_rls_contact_inquiries_select ON public.contact_inquiries
    FOR SELECT USING (public.mic_rls_is_admin());
CREATE POLICY mic_rls_contact_inquiries_insert ON public.contact_inquiries
    FOR INSERT WITH CHECK (true);
CREATE POLICY mic_rls_contact_inquiries_update ON public.contact_inquiries
    FOR UPDATE
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_contact_inquiries_delete ON public.contact_inquiries
    FOR DELETE USING (public.mic_rls_is_admin());

CREATE POLICY mic_rls_contact_replies_all ON public.contact_replies
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_audit_trail_all ON public.audit_trail
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_qualifications_select ON public.qualifications
    FOR SELECT USING (public.mic_rls_is_admin() OR status = 'active');
CREATE POLICY mic_rls_qualifications_write ON public.qualifications
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_skills_select ON public.skills
    FOR SELECT USING (public.mic_rls_is_admin() OR status = 'active');
CREATE POLICY mic_rls_skills_write ON public.skills
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_job_ml_features_all ON public.candidate_ml_features
    FOR ALL
    USING (public.mic_rls_can_access_application(application_id))
    WITH CHECK (public.mic_rls_can_access_application(application_id));

CREATE POLICY mic_rls_application_screening_all ON public.ml_application_screening
    FOR ALL
    USING (public.mic_rls_can_access_application(application_id))
    WITH CHECK (public.mic_rls_can_access_application(application_id));

CREATE POLICY mic_rls_ml_feature_importance_all ON public.ml_feature_importance
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_ml_model_performance_all ON public.ml_model_performance
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_cms_sections_select ON public.cms_sections
    FOR SELECT USING (true);
CREATE POLICY mic_rls_cms_sections_write ON public.cms_sections
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_cms_content_select ON public.cms_content
    FOR SELECT USING (
        public.mic_rls_is_admin()
        OR (is_active AND public.mic_rls_public_cms_section(section_id))
    );
CREATE POLICY mic_rls_cms_content_write ON public.cms_content
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_cms_hero_slides_select ON public.cms_hero_slides
    FOR SELECT USING (public.mic_rls_is_admin() OR is_active);
CREATE POLICY mic_rls_cms_hero_slides_write ON public.cms_hero_slides
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_cms_testimonials_select ON public.cms_testimonials
    FOR SELECT USING (public.mic_rls_is_admin() OR is_active);
CREATE POLICY mic_rls_cms_testimonials_write ON public.cms_testimonials
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_cms_brands_select ON public.cms_brands
    FOR SELECT USING (public.mic_rls_is_admin() OR is_active);
CREATE POLICY mic_rls_cms_brands_write ON public.cms_brands
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_cms_news_select ON public.cms_news
    FOR SELECT USING (public.mic_rls_is_admin() OR is_active);
CREATE POLICY mic_rls_cms_news_write ON public.cms_news
    FOR ALL
    USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());

CREATE POLICY mic_rls_django_session_select ON public.django_session
    FOR SELECT USING (
        session_key = NULLIF(current_setting('app.session_key', true), '')
    );
CREATE POLICY mic_rls_django_session_insert ON public.django_session
    FOR INSERT WITH CHECK (true);
CREATE POLICY mic_rls_django_session_update ON public.django_session
    FOR UPDATE
    USING (session_key = NULLIF(current_setting('app.session_key', true), ''))
    WITH CHECK (session_key = NULLIF(current_setting('app.session_key', true), ''));
CREATE POLICY mic_rls_django_session_delete ON public.django_session
    FOR DELETE USING (
        session_key = NULLIF(current_setting('app.session_key', true), '')
    );

CREATE POLICY mic_rls_auth_group_all ON public.auth_group
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_auth_group_permissions_all ON public.auth_group_permissions
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_auth_permission_all ON public.auth_permission
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_auth_user_all ON public.auth_user
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_auth_user_groups_all ON public.auth_user_groups
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_auth_user_permissions_all ON public.auth_user_user_permissions
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_django_admin_log_all ON public.django_admin_log
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_django_content_type_all ON public.django_content_type
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
CREATE POLICY mic_rls_django_migrations_all ON public.django_migrations
    FOR ALL USING (public.mic_rls_is_admin())
    WITH CHECK (public.mic_rls_is_admin());
"""


REVERSE_SQL = r"""
DO $$
DECLARE
    table_name text;
BEGIN
    FOREACH table_name IN ARRAY ARRAY[
        'applicants', 'applications', 'audit_trail', 'auth_group',
        'auth_group_permissions', 'auth_permission', 'auth_user',
        'auth_user_groups', 'auth_user_user_permissions', 'candidate_feedback',
        'candidate_ml_features', 'candidate_recommendations', 'chatbot_answers',
        'chatbot_recommendations', 'cms_brands', 'cms_content', 'cms_hero_slides',
        'cms_news', 'cms_sections', 'cms_testimonials', 'contact_inquiries',
        'contact_replies', 'django_admin_log', 'django_content_type',
        'django_migrations', 'django_session', 'employers', 'feedback',
        'interview_schedules', 'job_postings', 'job_qualification_mapping',
        'job_recommendations', 'messages', 'ml_application_screening',
        'ml_feature_importance', 'ml_model_performance', 'notifications',
        'qualifications', 'resume_analysis', 'saved_jobs', 'skills', 'users'
    ] LOOP
        EXECUTE format('ALTER TABLE public.%I DISABLE ROW LEVEL SECURITY', table_name);
    END LOOP;
END
$$;

DROP POLICY IF EXISTS mic_rls_users_select ON public.users;
DROP POLICY IF EXISTS mic_rls_users_insert ON public.users;
DROP POLICY IF EXISTS mic_rls_users_update ON public.users;
DROP POLICY IF EXISTS mic_rls_users_delete ON public.users;
DROP POLICY IF EXISTS mic_rls_applicants_select ON public.applicants;
DROP POLICY IF EXISTS mic_rls_applicants_insert ON public.applicants;
DROP POLICY IF EXISTS mic_rls_applicants_update ON public.applicants;
DROP POLICY IF EXISTS mic_rls_applicants_delete ON public.applicants;
DROP POLICY IF EXISTS mic_rls_employers_select ON public.employers;
DROP POLICY IF EXISTS mic_rls_employers_insert ON public.employers;
DROP POLICY IF EXISTS mic_rls_employers_update ON public.employers;
DROP POLICY IF EXISTS mic_rls_employers_delete ON public.employers;
DROP POLICY IF EXISTS mic_rls_jobs_select ON public.job_postings;
DROP POLICY IF EXISTS mic_rls_jobs_insert ON public.job_postings;
DROP POLICY IF EXISTS mic_rls_jobs_update ON public.job_postings;
DROP POLICY IF EXISTS mic_rls_jobs_delete ON public.job_postings;
DROP POLICY IF EXISTS mic_rls_job_qualifications_select ON public.job_qualification_mapping;
DROP POLICY IF EXISTS mic_rls_job_qualifications_insert ON public.job_qualification_mapping;
DROP POLICY IF EXISTS mic_rls_job_qualifications_update ON public.job_qualification_mapping;
DROP POLICY IF EXISTS mic_rls_job_qualifications_delete ON public.job_qualification_mapping;
DROP POLICY IF EXISTS mic_rls_applications_select ON public.applications;
DROP POLICY IF EXISTS mic_rls_applications_insert ON public.applications;
DROP POLICY IF EXISTS mic_rls_applications_update ON public.applications;
DROP POLICY IF EXISTS mic_rls_applications_delete ON public.applications;
DROP POLICY IF EXISTS mic_rls_saved_jobs_all ON public.saved_jobs;
DROP POLICY IF EXISTS mic_rls_messages_all ON public.messages;
DROP POLICY IF EXISTS mic_rls_notifications_all ON public.notifications;
DROP POLICY IF EXISTS mic_rls_interviews_all ON public.interview_schedules;
DROP POLICY IF EXISTS mic_rls_feedback_all ON public.feedback;
DROP POLICY IF EXISTS mic_rls_candidate_feedback_all ON public.candidate_feedback;
DROP POLICY IF EXISTS mic_rls_job_recommendations_all ON public.job_recommendations;
DROP POLICY IF EXISTS mic_rls_candidate_recommendations_all ON public.candidate_recommendations;
DROP POLICY IF EXISTS mic_rls_resume_analysis_all ON public.resume_analysis;
DROP POLICY IF EXISTS mic_rls_chatbot_answers_all ON public.chatbot_answers;
DROP POLICY IF EXISTS mic_rls_chatbot_recommendations_all ON public.chatbot_recommendations;
DROP POLICY IF EXISTS mic_rls_contact_inquiries_select ON public.contact_inquiries;
DROP POLICY IF EXISTS mic_rls_contact_inquiries_insert ON public.contact_inquiries;
DROP POLICY IF EXISTS mic_rls_contact_inquiries_update ON public.contact_inquiries;
DROP POLICY IF EXISTS mic_rls_contact_inquiries_delete ON public.contact_inquiries;
DROP POLICY IF EXISTS mic_rls_contact_replies_all ON public.contact_replies;
DROP POLICY IF EXISTS mic_rls_audit_trail_all ON public.audit_trail;
DROP POLICY IF EXISTS mic_rls_qualifications_select ON public.qualifications;
DROP POLICY IF EXISTS mic_rls_qualifications_write ON public.qualifications;
DROP POLICY IF EXISTS mic_rls_skills_select ON public.skills;
DROP POLICY IF EXISTS mic_rls_skills_write ON public.skills;
DROP POLICY IF EXISTS mic_rls_job_ml_features_all ON public.candidate_ml_features;
DROP POLICY IF EXISTS mic_rls_application_screening_all ON public.ml_application_screening;
DROP POLICY IF EXISTS mic_rls_ml_feature_importance_all ON public.ml_feature_importance;
DROP POLICY IF EXISTS mic_rls_ml_model_performance_all ON public.ml_model_performance;
DROP POLICY IF EXISTS mic_rls_cms_sections_select ON public.cms_sections;
DROP POLICY IF EXISTS mic_rls_cms_sections_write ON public.cms_sections;
DROP POLICY IF EXISTS mic_rls_cms_content_select ON public.cms_content;
DROP POLICY IF EXISTS mic_rls_cms_content_write ON public.cms_content;
DROP POLICY IF EXISTS mic_rls_cms_hero_slides_select ON public.cms_hero_slides;
DROP POLICY IF EXISTS mic_rls_cms_hero_slides_write ON public.cms_hero_slides;
DROP POLICY IF EXISTS mic_rls_cms_testimonials_select ON public.cms_testimonials;
DROP POLICY IF EXISTS mic_rls_cms_testimonials_write ON public.cms_testimonials;
DROP POLICY IF EXISTS mic_rls_cms_brands_select ON public.cms_brands;
DROP POLICY IF EXISTS mic_rls_cms_brands_write ON public.cms_brands;
DROP POLICY IF EXISTS mic_rls_cms_news_select ON public.cms_news;
DROP POLICY IF EXISTS mic_rls_cms_news_write ON public.cms_news;
DROP POLICY IF EXISTS mic_rls_django_session_select ON public.django_session;
DROP POLICY IF EXISTS mic_rls_django_session_insert ON public.django_session;
DROP POLICY IF EXISTS mic_rls_django_session_update ON public.django_session;
DROP POLICY IF EXISTS mic_rls_django_session_delete ON public.django_session;
DROP POLICY IF EXISTS mic_rls_auth_group_all ON public.auth_group;
DROP POLICY IF EXISTS mic_rls_auth_group_permissions_all ON public.auth_group_permissions;
DROP POLICY IF EXISTS mic_rls_auth_permission_all ON public.auth_permission;
DROP POLICY IF EXISTS mic_rls_auth_user_all ON public.auth_user;
DROP POLICY IF EXISTS mic_rls_auth_user_groups_all ON public.auth_user_groups;
DROP POLICY IF EXISTS mic_rls_auth_user_permissions_all ON public.auth_user_user_permissions;
DROP POLICY IF EXISTS mic_rls_django_admin_log_all ON public.django_admin_log;
DROP POLICY IF EXISTS mic_rls_django_content_type_all ON public.django_content_type;
DROP POLICY IF EXISTS mic_rls_django_migrations_all ON public.django_migrations;

DROP FUNCTION IF EXISTS public.mic_rls_authenticate_user(text);
DROP FUNCTION IF EXISTS public.mic_rls_email_exists(text);
DROP FUNCTION IF EXISTS public.mic_rls_public_cms_section(integer);
DROP FUNCTION IF EXISTS public.mic_rls_public_job(integer);
DROP FUNCTION IF EXISTS public.mic_rls_public_employer(integer);
DROP FUNCTION IF EXISTS public.mic_rls_user_is_visible(integer);
DROP FUNCTION IF EXISTS public.mic_rls_can_access_applicant(integer);
DROP FUNCTION IF EXISTS public.mic_rls_can_access_application(integer);
DROP FUNCTION IF EXISTS public.mic_rls_owns_job(integer);
DROP FUNCTION IF EXISTS public.mic_rls_owns_employer(integer);
DROP FUNCTION IF EXISTS public.mic_rls_is_registration();
DROP FUNCTION IF EXISTS public.mic_rls_is_admin();
DROP FUNCTION IF EXISTS public.mic_rls_user_role();
DROP FUNCTION IF EXISTS public.mic_rls_user_id();

REVOKE mic_app_rls FROM CURRENT_USER;
"""


def enable_postgresql_rls(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(FORWARD_SQL)


def disable_postgresql_rls(apps, schema_editor):
    if schema_editor.connection.vendor == 'postgresql':
        with schema_editor.connection.cursor() as cursor:
            cursor.execute(REVERSE_SQL)


class Migration(migrations.Migration):
    atomic = True

    dependencies = [
        ('app', '0003_application_admin_notes_and_more'),
    ]

    operations = [
        migrations.RunPython(enable_postgresql_rls, disable_postgresql_rls),
    ]
