import datetime
import html
from django.core.mail import send_mail
from django.conf import settings

def send_contact_reply(to_email: str, to_name: str, subject: str, body_text: str, original_message: str = '') -> dict:
    """
    Sends a reply to a contact inquiry using Django's email backend.
    Exact HTML template matching PHP PHPMailer template.
    """
    original_block = ''
    if original_message and original_message.strip():
        safe_original = html.escape(original_message.strip()).replace('\n', '<br>')
        original_block = f"""
        <div style='margin-top:28px;padding:16px 20px;background:#f4f6f9;
                    border-left:4px solid #d4af55;border-radius:6px;font-size:13px;color:#555;'>
            <p style='margin:0 0 6px;font-weight:600;color:#888;text-transform:uppercase;letter-spacing:.5px;font-size:11px;'>
                Your original message
            </p>
            <p style='margin:0;line-height:1.6;'>{safe_original}</p>
        </div>"""

    safe_reply = html.escape(body_text.strip()).replace('\n', '<br>')
    year = datetime.date.today().year

    html_content = f"""<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:12px;overflow:hidden;
                    box-shadow:0 4px 20px rgba(0,0,0,.08);max-width:600px;width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#0a1628 0%,#1a2d4e 100%);padding:28px 36px;text-align:center;">
            <h1 style="margin:0;color:#d4af55;font-size:22px;font-weight:700;letter-spacing:1px;">MultiBiz Global</h1>
            <p style="margin:6px 0 0;color:rgba(255,255,255,.7);font-size:13px;">Connecting Talent with Opportunity</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px;">
            <p style="margin:0 0 16px;font-size:15px;color:#333;">Hello, <strong>{to_name}</strong>,</p>
            <p style="margin:0 0 20px;font-size:15px;color:#333;line-height:1.7;">Thank you for reaching out. Here is our response to your inquiry:</p>
            <div style="background:#f8f9fc;border-radius:8px;padding:20px 24px;border:1px solid #e8eaf0;font-size:15px;color:#333;line-height:1.8;">
              {safe_reply}
            </div>
            {original_block}
            <p style="margin:28px 0 0;font-size:13px;color:#666;">
              If you have further questions, simply reply to this e-mail or visit us at
              <a href="https://multibiz.global" style="color:#0056b3;">multibiz.global</a>.
            </p>
          </td>
        </tr>
        <tr>
          <td style="background:#f8f9fc;padding:20px 36px;text-align:center;border-top:1px solid #e8eaf0;">
            <p style="margin:0;font-size:12px;color:#999;">
              &copy; {year} MultiBiz Global &middot; inquiry@multibiz.global &middot; +63 917 544 1674
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>"""

    plain_message = body_text
    try:
        send_mail(
            subject=subject,
            message=plain_message,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=False
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[MultiBiz Mailer] Error sending email: {e}")
        return {'success': False, 'error': str(e)}


def send_otp_email(to_email: str, first_name: str, otp_code: str) -> dict:
    """
    Sends 6-digit registration verification OTP email.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_name = html.escape(first_name or 'User')

    html_content = f"""<!DOCTYPE html>
<html>
<head>
  <meta charset='UTF-8'>
  <style>
    body {{ font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 0; }}
    .container {{ max-width: 520px; margin: 40px auto; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }}
    .header {{ background: linear-gradient(135deg, #1a73e8, #0d47a1); padding: 30px 20px; text-align: center; }}
    .header h1 {{ color: #ffffff; margin: 0; font-size: 22px; }}
    .body {{ padding: 35px 30px; }}
    .body p {{ color: #444; font-size: 15px; line-height: 1.6; }}
    .otp-box {{ margin: 28px auto; text-align: center; }}
    .otp-code {{ display: inline-block; background: #f0f4ff; border: 2px dashed #1a73e8; border-radius: 10px; padding: 18px 36px; font-size: 42px; font-weight: 700; letter-spacing: 10px; color: #1a73e8; font-family: 'Courier New', monospace; }}
    .expiry {{ text-align: center; color: #888; font-size: 13px; margin-top: 10px; }}
    .footer {{ background: #f8f9fa; padding: 18px; text-align: center; color: #aaa; font-size: 12px; }}
    .warning {{ background: #fff8e1; border-left: 4px solid #ffc107; padding: 12px 16px; border-radius: 4px; margin-top: 20px; font-size: 13px; color: #666; }}
  </style>
</head>
<body>
  <div class='container'>
    <div class='header'>
      <h1>&#x2709;&#xFE0F; Email Verification</h1>
    </div>
    <div class='body'>
      <p>Hi <strong>{safe_name}</strong>,</p>
      <p>Thank you for registering with <strong>{site_name}</strong>. Use the verification code below to complete your registration:</p>
      <div class='otp-box'>
        <div class='otp-code'>{otp_code}</div>
      </div>
      <p class='expiry'>&#x23F1; This code expires in <strong>10 minutes</strong>.</p>
      <div class='warning'>
        &#x26A0;&#xFE0F; <strong>Never share this code.</strong> Our team will never ask for your verification code.
        If you did not request this, please ignore this email.
      </div>
    </div>
    <div class='footer'>
      &copy; {year} {site_name}. All rights reserved.
    </div>
  </div>
</body>
</html>"""

    subject = f"{otp_code} is your {site_name} verification code"
    plain_text = f"Hi {safe_name},\n\nYour verification code is: {otp_code}\n\nThis code expires in 10 minutes.\n\nMultiBiz Global"

    try:
        send_mail(
            subject=subject,
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=False
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[OTP Mailer] Error sending OTP email: {e}")
        return {'success': False, 'error': str(e)}


def send_welcome_email(to_email: str, first_name: str) -> dict:
    """Send a welcome message after a new applicant account is created."""
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_name = html.escape(first_name or 'Applicant')
    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;'>
    <div style='padding:28px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#1a2d4e);'>
      <h1 style='margin:0;color:#d4af55;font-size:22px;'>Welcome to MultiBiz</h1>
      <p style='margin:8px 0 0;color:#ffffff;font-size:13px;'>Connecting Talent with Opportunity</p>
    </div>
    <div style='padding:32px;color:#333333;line-height:1.7;'>
      <p>Hello <strong>{safe_name}</strong>,</p>
      <p>Welcome to <strong>{site_name}</strong>. Your applicant account has been created successfully.</p>
      <p>You can now complete your profile, explore available jobs, and apply for opportunities that match your skills.</p>
      <p style='margin-top:24px;'>We are glad to have you with us.</p>
      <p style='margin-bottom:0;'>The MultiBiz Team</p>
    </div>
    <div style='padding:18px 32px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""
    plain_text = (
        f"Hello {first_name or 'Applicant'},\n\n"
        f"Welcome to {site_name}. Your applicant account has been created successfully.\n\n"
        "You can now complete your profile, explore available jobs, and apply for opportunities that match your skills.\n\n"
        "The MultiBiz Team"
    )
    try:
        send_mail(
            subject=f'Welcome to {site_name}',
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=False,
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Welcome Mailer] Error sending email: {e}")
        return {'success': False, 'error': str(e)}


def send_employer_welcome_email(to_email: str, first_name: str, company_name: str) -> dict:
    """Send a welcome email after a new employer / corporate account is registered."""
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_name = html.escape(first_name or 'Hiring Manager')
    safe_company = html.escape(company_name or 'Your Company')
    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
    <div style='padding:28px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#1a2d4e);'>
      <h1 style='margin:0;color:#d4af55;font-size:22px;'>Welcome to MultiBiz Global</h1>
      <p style='margin:8px 0 0;color:#ffffff;font-size:13px;'>Corporate Talent & Recruitment Partner</p>
    </div>
    <div style='padding:32px;color:#333333;line-height:1.7;'>
      <p>Hello <strong>{safe_name}</strong>,</p>
      <p>Welcome to <strong>{site_name}</strong>. Your corporate employer account for <strong>{safe_company}</strong> has been created successfully.</p>
      <p>As an employer partner, you can now:</p>
      <ul style='color:#555;padding-left:20px;'>
        <li><strong>Submit Job Requests:</strong> Post recruitment requisitions with custom salary, skills, and urgency.</li>
        <li><strong>Review Candidate Applications:</strong> Access AI-scored, verified candidate profiles.</li>
        <li><strong>Manage Recruitment Pipeline:</strong> Schedule video/in-person interviews, send feedback, and extend offers.</li>
      </ul>
      <p style='margin-top:24px;'>Our recruitment specialists are ready to help you find the right talent.</p>
      <p style='margin-bottom:0;'>Best regards,<br><strong>The MultiBiz Enterprise Recruitment Team</strong></p>
    </div>
    <div style='padding:18px 32px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""
    plain_text = (
        f"Hello {first_name or 'Hiring Manager'},\n\n"
        f"Welcome to {site_name}. Your corporate employer account for {company_name or 'Your Company'} has been created successfully.\n\n"
        "You can now submit job requests, review AI-scored candidate profiles, and schedule interviews.\n\n"
        "The MultiBiz Enterprise Recruitment Team"
    )
    try:
        send_mail(
            subject=f'Welcome to {site_name} - Employer Account Created',
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=False,
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Employer Welcome Mailer] Error sending email: {e}")
        return {'success': False, 'error': str(e)}


def send_application_submitted_email(to_email: str, applicant_name: str, job_title: str, company_name: str) -> dict:
    """
    Sends confirmation email to applicant when their application is submitted and under review.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_name = html.escape(applicant_name or 'Applicant')
    safe_job = html.escape(job_title or 'Job Position')
    safe_company = html.escape(company_name or 'Employer')

    subject = f"Application Received: {job_title} is Under Review - MultiBiz"
    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
    <div style='padding:28px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#1a2d4e);'>
      <h1 style='margin:0;color:#d4af55;font-size:22px;'>MultiBiz Global</h1>
      <p style='margin:8px 0 0;color:#ffffff;font-size:13px;'>Connecting Talent with Opportunity</p>
    </div>
    <div style='padding:32px;color:#333333;line-height:1.7;'>
      <p style='font-size:16px;'>Hello <strong>{safe_name}</strong>,</p>
      <p>Thank you for applying for the position of <strong>{safe_job}</strong> at <strong>{safe_company}</strong>.</p>
      <div style='background:#e8f4fd;border-left:4px solid #1866a3;padding:16px 20px;border-radius:6px;margin:20px 0;'>
        <p style='margin:0;font-weight:700;color:#0d47a1;font-size:15px;'>Status: Application Under Review</p>
        <p style='margin:6px 0 0;color:#455a64;font-size:13px;'>Your resume and credentials have been received and are currently being reviewed by the hiring team.</p>
      </div>
      <p>We will notify you via email as soon as the employer updates your application status or schedules an interview.</p>
      <p style='margin-top:24px;'>You can also track your real-time status anytime on your <a href='http://127.0.0.1:8000/applicant/applications.php' style='color:#1866a3;font-weight:600;'>Applicant Dashboard</a>.</p>
      <p style='margin-top:24px;margin-bottom:0;'>Best regards,<br><strong>The MultiBiz Recruitment Team</strong></p>
    </div>
    <div style='padding:18px 32px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""

    plain_text = (
        f"Hello {applicant_name or 'Applicant'},\n\n"
        f"Thank you for applying for the position of {job_title} at {company_name}.\n\n"
        f"Status: Application Under Review\n"
        f"Your resume and credentials have been received and are currently being reviewed by the hiring team.\n\n"
        f"We will notify you as soon as the employer updates your application status.\n\n"
        f"Best regards,\nThe MultiBiz Recruitment Team"
    )

    try:
        sent_count = send_mail(
            subject=subject,
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=False
        )
        if sent_count != 1:
            return {'success': False, 'error': 'Email backend did not send a message.'}
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Application Submit Mailer] Error sending email: {e}")
        return {'success': False, 'error': str(e)}


def send_application_status_update_email(to_email: str, applicant_name: str, job_title: str, company_name: str, new_status: str, remarks: str = '', match_score: float = 0.0) -> dict:
    """
    Sends email notification to applicant when their application is Accepted/Approved, Rejected, Shortlisted, etc.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_name = html.escape(applicant_name or 'Applicant')
    safe_job = html.escape(job_title or 'Job Position')
    safe_company = html.escape(company_name or 'Employer')
    status_clean = (new_status or '').lower().strip()
    qualification_notice = status_clean in ['qualified', 'under_qualified', 'unclassified', 'not_qualified']

    remarks_block = ""
    if remarks and remarks.strip():
        safe_remarks = html.escape(remarks.strip()).replace('\n', '<br>')
        remarks_block = f"""
        <div style='margin-top:18px;padding:14px 18px;background:#f8f9fa;border-left:4px solid #6c757d;border-radius:6px;'>
          <strong style='color:#495057;font-size:13px;'>Employer Remarks:</strong>
          <p style='margin:4px 0 0;color:#333;font-size:13px;'>{safe_remarks}</p>
        </div>"""

    qualification_summary = ''
    if status_clean in ['accepted', 'approved', 'hired']:
        subject = f"🎉 Congratulations! Your Application for {job_title} was Approved/Accepted"
        status_title = "Application Approved & Accepted"
        status_bg = "#d4edda"
        status_border = "#28a745"
        status_color = "#155724"
        message_body = f"""
        <p style='font-size:15px;'>Congratulations! We are thrilled to inform you that your application for <strong>{safe_job}</strong> at <strong>{safe_company}</strong> has been <strong style='color:#28a745;'>APPROVED / ACCEPTED</strong>!</p>
        <p>The employer was impressed with your qualifications and profile. They will reach out to you directly with onboarding details and next steps.</p>
        """
    elif status_clean in ['rejected', 'declined', 'not_selected']:
        subject = f"Application Update: {job_title} at {company_name}"
        status_title = "Application Status: Not Selected"
        status_bg = "#f8d7da"
        status_border = "#dc3545"
        status_color = "#721c24"
        message_body = f"""
        <p style='font-size:15px;'>Thank you for taking the time to apply for the position of <strong>{safe_job}</strong> at <strong>{safe_company}</strong>.</p>
        <p>After careful consideration, the hiring team has decided to move forward with other candidates whose qualifications more closely match the role requirements at this time.</p>
        <p>We encourage you to explore other job openings on MultiBiz that match your skills and experience.</p>
        """
    elif status_clean in ['shortlisted']:
        subject = f"Good News! You have been Shortlisted for {job_title}"
        status_title = "Application Status: Shortlisted"
        status_bg = "#d1ecf1"
        status_border = "#17a2b8"
        status_color = "#0c5460"
        message_body = f"""
        <p style='font-size:15px;'>Great news! Your application for <strong>{safe_job}</strong> at <strong>{safe_company}</strong> has been <strong style='color:#17a2b8;'>Shortlisted</strong>.</p>
        <p>The employer has added you to their candidate shortlist and may reach out shortly to schedule an interview.</p>
        """
    elif status_clean in ['interviewed', 'scheduled']:
        subject = f"Interview Update: {job_title} at {company_name}"
        status_title = "Application Status: Interview Stage"
        status_bg = "#e2d9f3"
        status_border = "#6f42c1"
        status_color = "#381375"
        message_body = f"""
        <p style='font-size:15px;'>Your application for <strong>{safe_job}</strong> at <strong>{safe_company}</strong> has advanced to the <strong>Interview Stage</strong>.</p>
        <p>Please check your applicant dashboard for schedule and meeting details.</p>
        """
    elif qualification_notice:
        qualification_labels = {
            'qualified': 'Qualified',
            'under_qualified': 'Under-Qualified',
            'unclassified': 'Unclassified',
            'not_qualified': 'Not Qualified',
        }
        qualification_colors = {
            'qualified': ('#d4edda', '#28a745', '#155724'),
            'under_qualified': ('#fff3cd', '#ffc107', '#856404'),
            'unclassified': ('#e2e8f0', '#64748b', '#334155'),
            'not_qualified': ('#f8d7da', '#dc3545', '#721c24'),
        }
        status_title = f"Screening Result: {qualification_labels[status_clean]}"
        status_bg, status_border, status_color = qualification_colors[status_clean]
        subject = f"Application Screening Result: {qualification_labels[status_clean]} for {job_title}"
        score_text = f"{float(match_score):.0f}%"
        qualification_summary = (
            f"Screening result: {qualification_labels[status_clean]}\n"
            f"AI match score: {score_text}\n"
            "This is an initial qualifications screening, not a final hiring decision. "
            "The employer makes the final decision."
        )
        message_body = f"""
        <p style='font-size:15px;'>Your application for <strong>{safe_job}</strong> at <strong>{safe_company}</strong> has been screened.</p>
        <p><strong>Result: {qualification_labels[status_clean]}</strong><br>AI match score: <strong>{score_text}</strong></p>
        <p>This result reflects the initial qualifications screening only and is not a final hiring decision. The employer makes the final decision.</p>
        """
    else:
        subject = f"Application Status Update: {job_title} - {new_status.title()}"
        status_title = f"Application Status: {new_status.title()}"
        status_bg = "#f0f4ff"
        status_border = "#1866a3"
        status_color = "#0d47a1"
        message_body = f"""
        <p style='font-size:15px;'>Your application for <strong>{safe_job}</strong> at <strong>{safe_company}</strong> status has been updated to <strong>{new_status.title()}</strong>.</p>
        """

    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:600px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
    <div style='padding:28px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#1a2d4e);'>
      <h1 style='margin:0;color:#d4af55;font-size:22px;'>MultiBiz Global</h1>
      <p style='margin:8px 0 0;color:#ffffff;font-size:13px;'>Connecting Talent with Opportunity</p>
    </div>
    <div style='padding:32px;color:#333333;line-height:1.7;'>
      <p style='font-size:16px;'>Hello <strong>{safe_name}</strong>,</p>
      <div style='background:{status_bg};border-left:4px solid {status_border};padding:16px 20px;border-radius:6px;margin:20px 0;'>
        <p style='margin:0;font-weight:700;color:{status_color};font-size:15px;'>{status_title}</p>
        <p style='margin:4px 0 0;color:#333;font-size:13px;'>Role: <strong>{safe_job}</strong> | Employer: <strong>{safe_company}</strong></p>
      </div>
      {message_body}
      {remarks_block}
      {'' if qualification_notice else "<p style='margin-top:24px;'>You can view full details on your <a href='http://127.0.0.1:8000/applicant/applications.php' style='color:#1866a3;font-weight:600;'>Applications Page</a>.</p>"}
      <p style='margin-top:24px;margin-bottom:0;'>Best regards,<br><strong>The MultiBiz Recruitment Team</strong></p>
    </div>
    <div style='padding:18px 32px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""

    plain_text = (
        f"Hello {applicant_name or 'Applicant'},\n\n"
        f"Your application status for {job_title} at {company_name} is now: {status_title}.\n"
        f"{qualification_summary}\n\n"
        f"{remarks if remarks else ''}\n\n"
        f"Best regards,\nThe MultiBiz Recruitment Team"
    )

    try:
        sent_count = send_mail(
            subject=subject,
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=False
        )
        if sent_count != 1:
            return {'success': False, 'error': 'Email backend did not send a message.'}
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Status Update Mailer] Error sending email: {e}")
        return {'success': False, 'error': str(e)}


def send_talent_request_admin_notification(inquiry_id: int, company_name: str, contact_name: str, contact_email: str, job_title: str, job_specs: dict) -> dict:
    """
    Sends email notification to MultiBiz Admin when an employer submits a Talent/Job Request.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    admin_email = getattr(settings, 'EMAIL_HOST_USER', 'reccapinto8@gmail.com')

    safe_company = html.escape(company_name or 'Company')
    safe_contact = html.escape(contact_name or 'Hiring Manager')
    safe_title = html.escape(job_title or 'Position Needed')
    safe_email = html.escape(contact_email or '')
    
    desc = html.escape(job_specs.get('description', 'None provided')).replace('\n', '<br>')
    reqs = html.escape(job_specs.get('requirements', 'None specified')).replace('\n', '<br>')
    skills = html.escape(job_specs.get('skills_required', 'Not specified'))
    loc = html.escape(job_specs.get('location', 'Not specified'))
    emp_type = html.escape(job_specs.get('employment_type', 'Full-time').title())
    sal = html.escape(job_specs.get('salary_range', 'Negotiable'))
    urgency = html.escape(job_specs.get('urgency', 'Normal').title())

    subject = f"🔔 New Company Talent Request: {job_title} from {company_name}"
    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:620px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
    <div style='padding:26px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#1866a3);'>
      <h1 style='margin:0;color:#d4af55;font-size:22px;'>MultiBiz Admin Notification</h1>
      <p style='margin:6px 0 0;color:#ffffff;font-size:13px;'>New Company Talent / Job Posting Request</p>
    </div>
    <div style='padding:30px;color:#333;line-height:1.6;'>
      <div style='background:#f4f9ff;border-left:4px solid #1866a3;padding:16px 20px;border-radius:6px;margin-bottom:20px;'>
        <p style='margin:0;font-weight:700;color:#0a3d6b;font-size:16px;'>{safe_title}</p>
        <p style='margin:4px 0 0;color:#555;font-size:13px;'>Company: <strong>{safe_company}</strong> &bull; Contact: <strong>{safe_contact}</strong> ({safe_email})</p>
      </div>

      <table style='width:100%;border-collapse:collapse;margin-bottom:20px;font-size:13px;'>
        <tr><td style='padding:6px 0;color:#777;width:130px;'><strong>Employment Type:</strong></td><td style='padding:6px 0;color:#222;'>{emp_type}</td></tr>
        <tr><td style='padding:6px 0;color:#777;'><strong>Location:</strong></td><td style='padding:6px 0;color:#222;'>{loc}</td></tr>
        <tr><td style='padding:6px 0;color:#777;'><strong>Salary Range:</strong></td><td style='padding:6px 0;color:#222;'>{sal}</td></tr>
        <tr><td style='padding:6px 0;color:#777;'><strong>Urgency:</strong></td><td style='padding:6px 0;color:#d97706;font-weight:700;'>{urgency}</td></tr>
        <tr><td style='padding:6px 0;color:#777;'><strong>Key Skills:</strong></td><td style='padding:6px 0;color:#222;'>{skills}</td></tr>
      </table>

      <div style='margin-bottom:18px;'>
        <strong style='color:#0a3d6b;font-size:13px;display:block;margin-bottom:6px;'>Job Description:</strong>
        <div style='background:#f8f9fc;padding:14px;border-radius:6px;border:1px solid #e2e8f0;font-size:13px;color:#334155;'>
          {desc}
        </div>
      </div>

      <div style='margin-bottom:24px;'>
        <strong style='color:#0a3d6b;font-size:13px;display:block;margin-bottom:6px;'>Requirements:</strong>
        <div style='background:#f8f9fc;padding:14px;border-radius:6px;border:1px solid #e2e8f0;font-size:13px;color:#334155;'>
          {reqs}
        </div>
      </div>

      <div style='text-align:center;margin:28px 0 10px;'>
        <a href='http://127.0.0.1:8000/admin/messages.php?id={inquiry_id}' style='background:#1866a3;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block;'>
          Review &amp; Post Job in Admin
        </a>
      </div>
    </div>
    <div style='padding:16px 30px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""

    plain_text = f"New Talent Request from {company_name} for position: {job_title}\n\nReview at: http://127.0.0.1:8000/admin/messages.php?id={inquiry_id}"
    try:
        send_mail(
            subject=subject,
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[admin_email],
            html_message=html_content,
            fail_silently=True
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Talent Request Mailer] Error sending admin notification: {e}")
        return {'success': False, 'error': str(e)}


def send_talent_request_employer_confirmation(to_email: str, company_name: str, contact_name: str, job_title: str) -> dict:
    """
    Sends confirmation to employer that their talent request was received and is under review.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_company = html.escape(company_name or 'Company')
    safe_contact = html.escape(contact_name or 'Hiring Partner')
    safe_title = html.escape(job_title or 'Job Position')

    subject = f"Talent Request Received: {job_title} - MultiBiz Global"
    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:600px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
    <div style='padding:26px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#1a2d4e);'>
      <h1 style='margin:0;color:#d4af55;font-size:22px;'>MultiBiz Global</h1>
      <p style='margin:6px 0 0;color:#ffffff;font-size:13px;'>Connecting Talent with Opportunity</p>
    </div>
    <div style='padding:32px;color:#333;line-height:1.7;'>
      <p style='font-size:16px;'>Hello <strong>{safe_contact}</strong> ({safe_company}),</p>
      <p>Thank you for submitting your talent requirement for <strong>{safe_title}</strong>.</p>
      <div style='background:#f0fdf4;border-left:4px solid #16a34a;padding:16px 20px;border-radius:6px;margin:20px 0;'>
        <p style='margin:0;font-weight:700;color:#15803d;font-size:15px;'>Request Status: Under Admin Review</p>
        <p style='margin:4px 0 0;color:#4b5563;font-size:13px;'>Our team is reviewing the job specifications. Once reviewed, we will post the opening to the MultiBiz network and activate AI candidate matching.</p>
      </div>
      <p>You will receive an email notification as soon as the job is published live.</p>
      <p style='margin-top:24px;margin-bottom:0;'>Best regards,<br><strong>The MultiBiz Recruitment Operations Team</strong></p>
    </div>
    <div style='padding:16px 30px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""

    plain_text = f"Hello {contact_name},\n\nYour talent request for {job_title} has been received and is under review by MultiBiz Admin.\n\nBest regards,\nMultiBiz Team"
    try:
        send_mail(
            subject=subject,
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=True
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Talent Request Mailer] Error sending employer confirmation: {e}")
        return {'success': False, 'error': str(e)}


def send_job_posted_live_notification(to_email: str, company_name: str, contact_name: str, job_title: str, job_id: int, location: str = '', salary_range: str = '') -> dict:
    """
    Sends notification to employer when admin reviews, approves, and posts their job live.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_company = html.escape(company_name or 'Company')
    safe_contact = html.escape(contact_name or 'Hiring Partner')
    safe_title = html.escape(job_title or 'Job Position')
    safe_loc = html.escape(location or 'MultiBiz Partner Locations')
    safe_sal = html.escape(salary_range or 'Competitive')

    subject = f"🎉 Job Approved & Live: {job_title} is Now Published on MultiBiz!"
    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:600px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
    <div style='padding:26px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#1866a3);'>
      <h1 style='margin:0;color:#d4af55;font-size:22px;'>MultiBiz Global</h1>
      <p style='margin:6px 0 0;color:#ffffff;font-size:13px;'>Connecting Talent with Opportunity</p>
    </div>
    <div style='padding:32px;color:#333;line-height:1.7;'>
      <p style='font-size:16px;'>Hello <strong>{safe_contact}</strong> ({safe_company}),</p>
      <p>Great news! Your requested job posting has been <strong>reviewed, approved, and posted live</strong> on the MultiBiz platform.</p>
      
      <div style='background:#f0fdf4;border-left:4px solid #16a34a;padding:16px 20px;border-radius:6px;margin:20px 0;'>
        <p style='margin:0;font-weight:700;color:#15803d;font-size:16px;'>{safe_title}</p>
        <p style='margin:4px 0 0;color:#4b5563;font-size:13px;'>Job ID: <strong>#{job_id}</strong> &bull; Location: <strong>{safe_loc}</strong> &bull; Salary: <strong>{safe_sal}</strong></p>
      </div>

      <p>Applicants can now discover and apply for this opening. You can monitor incoming applicants and review AI candidate rankings directly from your Employer Portal.</p>

      <div style='text-align:center;margin:28px 0 10px;'>
        <a href='http://127.0.0.1:8000/employer/jobs.php' style='background:#1866a3;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block;'>
          View Job in Employer Portal
        </a>
      </div>

      <p style='margin-top:24px;margin-bottom:0;'>Best regards,<br><strong>The MultiBiz Operations Team</strong></p>
    </div>
    <div style='padding:16px 30px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""

    plain_text = f"Hello {contact_name},\n\nYour job {job_title} has been reviewed and posted live by MultiBiz Admin!\n\nView at: http://127.0.0.1:8000/employer/jobs.php\n\nBest regards,\nMultiBiz Team"
    try:
        send_mail(
            subject=subject,
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=True
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Job Live Mailer] Error sending live notification: {e}")
        return {'success': False, 'error': str(e)}


def send_interview_scheduled_email(
    to_email: str,
    applicant_name: str,
    company_name: str,
    job_title: str,
    interview_date: str,
    interview_time: str,
    interview_type: str = 'Online',
    location_or_link: str = '',
    interviewer_name: str = '',
    instructions: str = '',
    additional_notes: str = ''
) -> dict:
    """
    Sends automated interview schedule email to the applicant containing all required details.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_applicant = html.escape(applicant_name or 'Candidate')
    safe_company = html.escape(company_name or 'Hiring Partner')
    safe_title = html.escape(job_title or 'Job Position')
    safe_date = html.escape(str(interview_date))
    safe_time = html.escape(str(interview_time))
    safe_type = html.escape(interview_type or 'Online')
    safe_loc_link = html.escape(location_or_link or 'Details to follow')
    safe_interviewer = html.escape(interviewer_name or 'Recruitment Team')
    safe_instructions = html.escape(instructions or 'Please ensure you are prepared 10 minutes prior to the scheduled time.').replace('\n', '<br>')
    safe_notes = html.escape(additional_notes or '').replace('\n', '<br>') if additional_notes else ''

    subject = f"📅 Interview Invitation: {safe_title} at {safe_company}"
    
    notes_block = ''
    if safe_notes:
        notes_block = f"""
        <div style="margin-top:16px;padding:12px 16px;background:#f8fafc;border-left:3px solid #3b82f6;border-radius:6px;font-size:13px;color:#475569;">
            <strong style="color:#1e293b;">Additional Employer Notes:</strong><br>{safe_notes}
        </div>
        """

    loc_link_html = safe_loc_link
    if 'http://' in safe_loc_link or 'https://' in safe_loc_link:
        loc_link_html = f'<a href="{safe_loc_link}" target="_blank" style="color:#0284c7;font-weight:700;word-break:break-all;">{safe_loc_link}</a>'

    html_content = f"""<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f0f2f5;font-family:'Segoe UI',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f2f5;padding:32px 0;">
    <tr><td align="center">
      <table width="620" cellpadding="0" cellspacing="0"
             style="background:#ffffff;border-radius:14px;overflow:hidden;
                    box-shadow:0 6px 24px rgba(0,0,0,.09);max-width:620px;width:100%;">
        <tr>
          <td style="background:linear-gradient(135deg,#09203f 0%,#1e40af 60%,#0284c7 100%);padding:30px 36px;text-align:center;">
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;">Interview Scheduled</h1>
            <p style="margin:6px 0 0;color:rgba(255,255,255,.85);font-size:13px;">{safe_company} &bull; MultiBiz Talent Network</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px;">
            <p style="margin:0 0 16px;font-size:15px;color:#1e293b;">Hello <strong>{safe_applicant}</strong>,</p>
            <p style="margin:0 0 20px;font-size:14.5px;color:#334155;line-height:1.7;">
              Congratulations! <strong>{safe_company}</strong> has reviewed your application for <strong>{safe_title}</strong> and scheduled an interview with you.
            </p>
            
            <!-- Details Card -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:20px;margin-bottom:22px;">
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:13.5px;color:#64748b;width:38%;">Company / Employer:</td>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:14px;color:#0f172a;font-weight:700;">{safe_company}</td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:13.5px;color:#64748b;">Job Position:</td>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:14px;color:#0284c7;font-weight:700;">{safe_title}</td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:13.5px;color:#64748b;">Interview Date:</td>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:14px;color:#0f172a;font-weight:700;">📅 {safe_date}</td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:13.5px;color:#64748b;">Interview Time:</td>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:14px;color:#0f172a;font-weight:700;">⏰ {safe_time}</td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:13.5px;color:#64748b;">Interview Type:</td>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:14px;color:#0f172a;font-weight:600;">{safe_type}</td>
              </tr>
              <tr>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:13.5px;color:#64748b;">Location / Meeting Link:</td>
                <td style="padding:8px 0;border-bottom:1px solid #edf2f7;font-size:13.5px;color:#0f172a;">{loc_link_html}</td>
              </tr>
              <tr>
                <td style="padding:8px 0;font-size:13.5px;color:#64748b;">Interviewer / Contact:</td>
                <td style="padding:8px 0;font-size:14px;color:#0f172a;font-weight:600;">{safe_interviewer}</td>
              </tr>
            </table>

            <!-- Instructions -->
            <div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:16px 20px;border-radius:6px;margin-bottom:20px;">
              <p style="margin:0 0 4px;font-size:13px;font-weight:700;color:#1d4ed8;text-transform:uppercase;letter-spacing:.5px;">Instructions for Candidate:</p>
              <p style="margin:0;font-size:13.5px;color:#1e3a8a;line-height:1.6;">{safe_instructions}</p>
            </div>

            {notes_block}

            <p style="margin:24px 0 0;font-size:13px;color:#64748b;line-height:1.6;">
              You can also view this interview schedule and manage your application anytime inside your <a href="http://127.0.0.1:8000/applicant/dashboard/" style="color:#0284c7;font-weight:600;">Applicant Dashboard</a>.
            </p>

            <p style="margin-top:24px;margin-bottom:0;color:#334155;font-size:14px;">Best of luck with your interview!<br><strong>The MultiBiz Talent Team</strong></p>
          </td>
        </tr>
        <tr>
          <td style="background:#f8fafc;padding:20px 36px;text-align:center;border-top:1px solid #e2e8f0;">
            <p style="margin:0;font-size:12px;color:#94a3b8;">
              &copy; {year} {site_name}. All rights reserved.
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>"""

    plain_message = f"""Hello {applicant_name},

You have been invited for an interview with {company_name} for the position of {job_title}.

Interview Details:
- Date: {interview_date}
- Time: {interview_time}
- Type: {interview_type}
- Location / Link: {location_or_link}
- Interviewer: {interviewer_name}
- Instructions: {instructions}

Best regards,
MultiBiz Team"""

    try:
        send_mail(
            subject=subject,
            message=plain_message,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=True
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Interview Mailer] Error: {e}")
        return {'success': False, 'error': str(e)}


def send_job_request_rejected_notification(to_email: str, company_name: str, contact_name: str, job_title: str, rejection_reason: str = '') -> dict:
    """
    Sends notification to employer when admin reviews and rejects their job request with reason.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_company = html.escape(company_name or 'Company')
    safe_contact = html.escape(contact_name or 'Hiring Partner')
    safe_title = html.escape(job_title or 'Job Position')
    safe_reason = html.escape(rejection_reason or 'Specification adjustment required.').replace('\n', '<br>')

    subject = f"Job Request Status Update: {job_title} - MultiBiz Global"
    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:600px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
    <div style='padding:26px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#991b1b);'>
      <h1 style='margin:0;color:#ffffff;font-size:22px;'>Job Request Update</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,.8);font-size:13px;'>MultiBiz Recruitment Operations</p>
    </div>
    <div style='padding:32px;color:#333;line-height:1.7;'>
      <p style='font-size:16px;'>Hello <strong>{safe_contact}</strong> ({safe_company}),</p>
      <p>Thank you for submitting your job request for <strong>{safe_title}</strong>.</p>
      
      <div style='background:#fef2f2;border-left:4px solid #ef4444;padding:16px 20px;border-radius:6px;margin:20px 0;'>
        <p style='margin:0;font-weight:700;color:#b91c1c;font-size:15px;'>Request Status: Rejected / Needs Revision</p>
        <p style='margin:8px 0 0;color:#374151;font-size:13.5px;line-height:1.6;'>
          <strong>Reason / Feedback from Admin:</strong><br>
          {safe_reason}
        </p>
      </div>

      <p style="font-size:13.5px;color:#4b5563;">You can review, revise, and resubmit this job request from your Employer Portal anytime.</p>

      <p style='margin-top:24px;margin-bottom:0;'>Best regards,<br><strong>The MultiBiz Recruitment Team</strong></p>
    </div>
    <div style='padding:16px 30px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""

    plain_text = f"Hello {contact_name},\n\nYour job request for {job_title} was reviewed by Admin and requires revisions.\nReason: {rejection_reason}\n\nBest regards,\nMultiBiz Team"
    try:
        send_mail(
            subject=subject,
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=True
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Job Rejection Mailer] Error: {e}")
        return {'success': False, 'error': str(e)}


def send_applicant_forwarded_to_employer_email(to_email: str, company_name: str, applicant_name: str, job_title: str, match_score: float = 0.0, admin_notes: str = '') -> dict:
    """
    Sends notification to employer when admin forwards a candidate to them.
    """
    site_name = 'MULTIBIZ INTERNATIONAL CORPORATION'
    year = datetime.date.today().year
    safe_company = html.escape(company_name or 'Company')
    safe_applicant = html.escape(applicant_name or 'Candidate')
    safe_title = html.escape(job_title or 'Job Position')
    safe_notes = html.escape(admin_notes or '').replace('\n', '<br>') if admin_notes else ''

    subject = f"🌟 New Candidate Forwarded: {applicant_name} for {job_title}"
    
    notes_html = ''
    if safe_notes:
        notes_html = f"""
        <div style="margin-top:16px;padding:12px 16px;background:#f0fdf4;border-left:3px solid #16a34a;border-radius:6px;font-size:13px;color:#15803d;">
            <strong>Admin Notes:</strong><br>{safe_notes}
        </div>
        """

    html_content = f"""<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f0f2f5;font-family:Arial,sans-serif;'>
  <div style='max-width:600px;margin:30px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);'>
    <div style='padding:26px 32px;text-align:center;background:linear-gradient(135deg,#0a1628,#0284c7);'>
      <h1 style='margin:0;color:#ffffff;font-size:22px;'>New Candidate Received</h1>
      <p style='margin:6px 0 0;color:rgba(255,255,255,.85);font-size:13px;'>MultiBiz Recruitment Operations</p>
    </div>
    <div style='padding:32px;color:#333;line-height:1.7;'>
      <p style='font-size:16px;'>Hello <strong>{safe_company} Team</strong>,</p>
      <p>MultiBiz Admin has forwarded a candidate application for your review:</p>
      
      <div style='background:#f8fafc;border:1px solid #e2e8f0;padding:18px 22px;border-radius:8px;margin:20px 0;'>
        <p style='margin:0;font-weight:700;color:#0f172a;font-size:16px;'>👤 {safe_applicant}</p>
        <p style='margin:4px 0 0;color:#0284c7;font-size:13.5px;font-weight:600;'>Position: {safe_title}</p>
        <p style='margin:4px 0 0;color:#64748b;font-size:13px;'>AI Match Score: <strong>{match_score:.0f}%</strong></p>
        {notes_html}
      </div>

      <p style="font-size:13.5px;color:#4b5563;">You can now review their complete resume dossier, mark them as Qualified/Not Qualified, or schedule an interview directly from your Employer Portal.</p>

      <div style='text-align:center;margin:26px 0 10px;'>
        <a href='http://127.0.0.1:8000/employer/candidates/' style='background:#0284c7;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:700;font-size:14px;display:inline-block;'>
          Review Candidate in Portal
        </a>
      </div>

      <p style='margin-top:24px;margin-bottom:0;'>Best regards,<br><strong>The MultiBiz Recruitment Team</strong></p>
    </div>
    <div style='padding:16px 30px;text-align:center;background:#f8f9fc;border-top:1px solid #e8eaf0;color:#999;font-size:12px;'>&copy; {year} {site_name}. All rights reserved.</div>
  </div>
</body>
</html>"""

    plain_text = f"Hello {company_name},\n\nMultiBiz Admin has forwarded candidate {applicant_name}'s application for your job opening: {job_title}.\n\nPlease review their application in your Employer Portal.\n\nBest regards,\nMultiBiz Team"
    try:
        send_mail(
            subject=subject,
            message=plain_text,
            from_email=settings.DEFAULT_FROM_EMAIL,
            recipient_list=[to_email],
            html_message=html_content,
            fail_silently=True
        )
        return {'success': True, 'error': None}
    except Exception as e:
        print(f"[Candidate Forwarded Mailer] Error: {e}")
        return {'success': False, 'error': str(e)}
