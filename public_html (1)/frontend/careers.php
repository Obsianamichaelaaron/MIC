<!DOCTYPE html>
<html lang="en">
<head>
    <title>Careers - MULTIBIZ INTERNATIONAL CORPORATION</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="keywords" content="MULTIBIZ INTERNATIONAL CORPORATION, Careers, Jobs">
    <meta name="description" content="Join the MULTIBIZ team – explore career opportunities in managed print, document management, and system integration.">
    <link rel="shortcut icon" href="2024/favicon.png" type="image/x-icon">
    <link rel="apple-touch-icon" href="2024/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ============================================================
           DESIGN SYSTEM — LUXURY CORPORATE (synced with services.php)
        ============================================================ */
        :root {
            --navy:        #0a1628;
            --navy-mid:    #0f2040;
            --blue:        #1a4fa0;
            --blue-light:  #2563c8;
            --gold:        #b8973a;
            --gold-light:  #d4af55;
            --cream:       #f7f5f0;
            --warm-white:  #fafaf8;
            --gray-100:    #f0eff0;
            --gray-200:    #e4e2e8;
            --gray-500:    #8a8691;
            --gray-700:    #4a4752;
            --dark:        #18151f;

            --font-display: 'Playfair Display', Georgia, serif;
            --font-body:    'DM Sans', -apple-system, sans-serif;

            --shadow-sm: 0 2px 12px rgba(10,22,40,0.07);
            --shadow-md: 0 8px 32px rgba(10,22,40,0.11);
            --shadow-lg: 0 20px 56px rgba(10,22,40,0.16);
            --shadow-xl: 0 32px 80px rgba(10,22,40,0.22);
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
            --radius-sm: 6px; --radius-md: 12px; --radius-lg: 20px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; overflow-x: hidden; }
        body {
            font-family: var(--font-body); color: var(--dark);
            background: var(--warm-white); overflow-x: hidden;
            line-height: 1.6; -webkit-font-smoothing: antialiased;
        }

        /* ── HEADER (identical to services.php) ── */
        header { position: fixed; top: 0; left: 0; width: 100%; z-index: 1000; }
        .header-container {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 clamp(1rem,5%,4rem); height: 76px;
            background: rgba(10,22,40,0.97);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(184,151,58,0.18);
            transition: all 0.3s ease;
        }
        .logo img { height: clamp(34px,6vw,42px); max-width: 100%; }
        .nav-toggle { display: none; background: none; border: none; color: white; font-size: clamp(18px,5vw,22px); cursor: pointer; padding: 8px; }
        .nav-menu { display: flex; align-items: center; }
        .nav-list { display: flex; list-style: none; align-items: center; gap: 0.15rem; }
        .nav-item { position: relative; }
        .nav-link {
            text-decoration: none; color: rgba(255,255,255,0.72);
            font-weight: 500; font-size: clamp(13px,2vw,15px); letter-spacing: 0.04em;
            padding: 0.5rem 0.85rem; display: flex; align-items: center; gap: 0.3rem;
            border-radius: var(--radius-sm); transition: all 0.25s ease; white-space: nowrap;
        }
        .nav-link i { font-size: 0.6rem; opacity: 0.55; transition: transform 0.3s ease; }
        .nav-link:hover { color: white; background: rgba(255,255,255,0.07); }
        .nav-link.active { color: var(--gold-light); }
        .nav-item:hover .nav-link i { transform: rotate(180deg); }

        .dropdown {
            position: absolute; top: calc(100% + 6px); left: 0; min-width: 240px;
            background: var(--navy); border: 1px solid rgba(184,151,58,0.2);
            border-radius: var(--radius-md); box-shadow: var(--shadow-xl);
            opacity: 0; visibility: hidden; transform: translateY(-6px);
            transition: all 0.25s var(--ease-out); z-index: 200; overflow: hidden;
        }
        .nav-item:hover .dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { border-bottom: 1px solid rgba(255,255,255,0.05); }
        .dropdown-link {
            display: block; padding: 0.65rem 1.2rem;
            color: rgba(255,255,255,0.65); text-decoration: none;
            font-size: 13px; transition: all 0.2s ease;
        }
        .dropdown-link:hover { color: var(--gold-light); background: rgba(184,151,58,0.07); padding-left: 1.5rem; }

        .login-register-btn {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%) !important;
            color: var(--navy) !important; font-weight: 700 !important;
            padding: 0.45rem 1.2rem !important; border-radius: 100px !important;
            font-size: 13px !important; box-shadow: 0 4px 16px rgba(184,151,58,0.3);
            transition: all 0.3s ease !important;
        }
        .login-register-btn:hover { transform: translateY(-2px) !important; box-shadow: 0 6px 24px rgba(184,151,58,0.45) !important; }

        /* ── CAREERS HERO (inspired by services hero) ── */
        .careers-hero {
            height: clamp(44vh,56vh,65vh);
            position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center; text-align: center;
            color: white; margin-top: 76px; min-height: 320px;
            background: var(--navy-mid);
        }
        .careers-hero::before {
            content: ''; position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 70% 35%, rgba(26,79,160,0.6) 0%, transparent 70%),
                radial-gradient(ellipse 55% 45% at 15% 75%, rgba(184,151,58,0.2) 0%, transparent 60%);
        }
        .careers-hero-overlay {
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.018'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .careers-hero-overlay::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 100%; height: 130px;
            background: linear-gradient(to top, var(--warm-white), transparent);
        }
        .careers-hero-content {
            max-width: min(820px,90vw); padding: 0 clamp(1rem,5%,2rem);
            z-index: 1; position: relative;
        }
        .careers-hero-eyebrow {
            display: inline-flex; align-items: center; gap: 0.7rem;
            font-size: 0.68rem; font-weight: 600; letter-spacing: 0.22em;
            text-transform: uppercase; color: var(--gold-light); margin-bottom: 1.4rem;
            animation: fadeUp 0.7s var(--ease-out) 0.1s both;
        }
        .careers-hero-eyebrow::before, .careers-hero-eyebrow::after { content: ''; height: 1px; background: var(--gold-light); opacity: 0.6; width: 36px; }
        .careers-hero-title {
            font-family: var(--font-display);
            font-size: clamp(2.2rem,7vw,4rem); font-weight: 600; line-height: 1.1;
            margin-bottom: 1.25rem; animation: fadeUp 0.8s var(--ease-out) 0.25s both;
        }
        .careers-hero-title em { font-style: italic; color: var(--gold-light); }
        .careers-hero-subtitle {
            font-size: clamp(0.95rem,3vw,1.2rem); color: rgba(255,255,255,0.65);
            font-weight: 300; line-height: 1.65; max-width: 600px; margin: 0 auto;
            animation: fadeUp 0.8s var(--ease-out) 0.4s both;
        }

        /* ── SECTION COMMON (like services sections) ── */
        .section {
            padding: clamp(4.5rem,9vw,7rem) clamp(1.25rem,5%,5%);
            border-top: 1px solid rgba(10,22,40,0.07);
        }
        .section-alt { background: linear-gradient(175deg, #eef0f6 0%, #e7eaf3 100%); }

        .section-container {
            max-width: min(1200px,96%); margin: 0 auto;
        }

        .section-label {
            display: inline-flex; align-items: center; gap: 0.55rem;
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.18em;
            text-transform: uppercase; color: var(--blue); margin-bottom: 0.7rem;
        }
        .section-label::before { content: ''; width: 22px; height: 2px; background: var(--gold); border-radius: 2px; }

        .section-title {
            font-family: var(--font-display);
            font-size: clamp(1.7rem,5vw,2.5rem); color: var(--navy);
            margin-bottom: clamp(1.25rem,4vw,1.75rem);
            position: relative; padding-bottom: clamp(0.8rem,2vw,1.1rem);
            line-height: 1.2; font-weight: 600;
        }
        .section-title::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 52px; height: 3px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            border-radius: 3px;
        }
        .section-subtitle {
            font-size: clamp(0.88rem,2.8vw,1rem); line-height: 1.8;
            color: var(--gray-700); margin-bottom: clamp(1rem,3vw,1.5rem);
            max-width: 720px;
        }
        .section-center { text-align: center; }
        .section-center .section-label { justify-content: center; }
        .section-center .section-title::after { left: 50%; transform: translateX(-50%); }
        .section-center .section-subtitle { margin-left: auto; margin-right: auto; }

        /* ── WORK WITH US (flex like service-container) ── */
        .work-grid {
            display: flex; align-items: center;
            gap: clamp(2.5rem,6vw,5rem);
            max-width: min(1200px,96%); margin: 0 auto; flex-wrap: wrap;
        }
        .work-content { flex: 1 1 min(480px,100%); min-width: min(280px,100%); }
        .work-image { flex: 1 1 min(420px,100%); text-align: center; min-width: min(280px,100%); }
        .work-image img {
            max-width: 100%; border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            transition: transform 0.6s var(--ease-out);
        }
        .work-image img:hover { transform: scale(1.025); }

        .benefits-list { margin: 1.5rem 0; }
        .benefit-item {
            display: flex; align-items: center; gap: 0.65rem;
            padding: clamp(0.7rem,2vw,0.9rem) clamp(0.85rem,2vw,1.1rem);
            background: white; border-radius: var(--radius-sm);
            border: 1px solid var(--gray-200); border-left: 3px solid var(--gold);
            transition: all 0.25s var(--ease-out);
            margin-bottom: 0.75rem;
        }
        .benefit-item:hover { transform: translateX(4px); box-shadow: var(--shadow-sm); border-left-color: var(--blue); }
        .benefit-item i { color: var(--blue); font-size: 0.9rem; flex-shrink: 0; }
        .benefit-item span { font-weight: 500; font-size: clamp(0.82rem,2.5vw,0.9rem); color: var(--navy); }

        /* ── SEARCH FORM (elevated) ── */
        .search-section {
            background: var(--cream);
            padding: 3rem 0;
        }
        .search-container {
            max-width: 1000px; margin: 0 auto; padding: 0 1.5rem;
        }
        .search-form {
            display: grid; grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem; background: white; padding: 2rem;
            border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
        }
        .form-input, .form-select {
            width: 100%; padding: 0.85rem 1.1rem;
            border: 1.5px solid var(--gray-200); border-radius: var(--radius-sm);
            font-family: var(--font-body); font-size: 0.9rem;
            background: var(--warm-white); transition: all 0.25s ease;
            outline: none;
        }
        .form-input:focus, .form-select:focus {
            border-color: var(--blue); background: white;
            box-shadow: 0 0 0 3px rgba(26,79,160,0.08);
        }
        .btn-search {
            padding: 0.85rem 2rem; background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%);
            color: white; border: none; border-radius: 100px;
            font-weight: 600; letter-spacing: 0.06em; cursor: pointer;
            display: inline-flex; align-items: center; gap: 0.5rem;
            transition: all 0.3s ease; white-space: nowrap;
        }
        .btn-search:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(10,22,40,0.25); }

        .job-count {
            background: white; padding: 1rem 2rem; border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm); margin: 2rem auto 0;
            max-width: 300px; text-align: center; color: var(--gray-700);
        }
        .job-count strong { color: var(--blue); font-size: 1.2rem; margin-right: 0.25rem; }

        /* ── JOBS GRID (cards with service style) ── */
        .jobs-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.5rem; max-width: 1400px; margin: 0 auto;
        }
        .job-card {
            background: white; border: 1px solid var(--gray-100);
            border-radius: var(--radius-lg); padding: 2rem;
            transition: all 0.35s var(--ease-out); position: relative; overflow: hidden;
        }
        .job-card::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 100%; height: 3px;
            background: linear-gradient(90deg, var(--blue), var(--gold));
            transform: scaleX(0); transform-origin: left;
            transition: transform 0.4s var(--ease-out);
        }
        .job-card:hover {
            transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: transparent;
        }
        .job-card:hover::after { transform: scaleX(1); }

        .job-header { margin-bottom: 1.25rem; }
        .job-title {
            font-family: var(--font-display); font-size: 1.3rem; font-weight: 600;
            color: var(--navy); margin-bottom: 0.4rem;
        }
        .job-company { font-size: 0.9rem; color: var(--gray-500); display: flex; align-items: center; gap: 0.4rem; }
        .job-company i { color: var(--blue); }

        .job-meta { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.25rem; }
        .job-meta-item {
            display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem;
            color: var(--gray-700); background: var(--cream); padding: 0.35rem 1rem;
            border-radius: 100px;
        }
        .job-meta-item i { color: var(--blue); font-size: 0.75rem; }

        .job-description {
            font-size: 0.9rem; color: var(--gray-700); line-height: 1.7;
            margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 3;
            -webkit-box-orient: vertical; overflow: hidden;
        }

        .job-skills { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; }
        .skill-tag {
            background: rgba(26,79,160,0.08); color: var(--blue);
            padding: 0.35rem 0.8rem; border-radius: 100px; font-size: 0.78rem; font-weight: 500;
        }

        .job-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding-top: 1.25rem; border-top: 1px solid var(--gray-100);
        }
        .job-salary {
            font-weight: 600; color: #22a060; font-size: 1rem;
            display: flex; align-items: center; gap: 0.4rem;
        }
        .job-salary i { color: var(--gold); }
        .job-buttons { display: flex; gap: 0.75rem; }

        .btn-view, .btn-apply {
            padding: 0.5rem 1.25rem; font-size: 0.8rem; font-weight: 600;
            border-radius: 100px; text-decoration: none; display: inline-flex;
            align-items: center; gap: 0.4rem; transition: all 0.25s ease;
        }
        .btn-view {
            border: 1.5px solid var(--navy); color: var(--navy);
        }
        .btn-view:hover { background: var(--navy); color: white; }
        .btn-apply {
            background: linear-gradient(135deg, #1a7a4a, #22a060); color: white;
        }
        .btn-apply:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,122,74,0.35); }

        .no-jobs {
            text-align: center; padding: 4rem 2rem; background: white;
            border-radius: var(--radius-lg); box-shadow: var(--shadow-sm);
            max-width: 600px; margin: 0 auto;
        }
        .no-jobs i { font-size: 3.5rem; color: var(--gray-200); margin-bottom: 1rem; }
        .no-jobs h3 { font-size: 1.5rem; color: var(--navy); }

        /* ── CTA SECTION (exactly like services.php) ── */
        .cta-section {
            background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%);
            color: white; padding: clamp(4rem,9vw,6.5rem) clamp(1.25rem,5%,5%);
            text-align: center; position: relative; overflow: hidden;
        }
        .cta-section::before {
            content: ''; position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .cta-container { max-width: 760px; margin: 0 auto; position: relative; z-index: 1; }
        .cta-eyebrow {
            display: inline-flex; align-items: center; gap: 0.6rem;
            font-size: 0.68rem; font-weight: 600; letter-spacing: 0.2em;
            text-transform: uppercase; color: var(--gold-light); margin-bottom: 1.25rem;
        }
        .cta-eyebrow::before, .cta-eyebrow::after { content: ''; width: 28px; height: 1px; background: var(--gold-light); opacity: 0.6; }
        .cta-title {
            font-family: var(--font-display); font-size: clamp(1.8rem,6vw,3rem);
            font-weight: 600; margin-bottom: 1.25rem; line-height: 1.15; color: white;
        }
        .cta-title em { font-style: italic; color: var(--gold-light); }
        .cta-description {
            font-size: clamp(0.95rem,3vw,1.15rem); opacity: 0.78;
            margin-bottom: clamp(2rem,5vw,3rem); line-height: 1.7;
        }
        .cta-buttons { display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap; }
        .cta-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.9rem 2rem; border-radius: 100px; font-weight: 600;
            font-size: clamp(0.82rem,2.8vw,0.92rem); letter-spacing: 0.05em;
            text-decoration: none; transition: all 0.3s var(--ease-out);
        }
        .cta-btn-primary { background: white; color: var(--navy); box-shadow: 0 6px 20px rgba(0,0,0,0.2); }
        .cta-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(0,0,0,0.3); }
        .cta-btn-outline { background: transparent; color: white; border: 2px solid rgba(255,255,255,0.55); }
        .cta-btn-outline:hover { background: rgba(255,255,255,0.1); border-color: white; transform: translateY(-2px); }

        /* ── FOOTER (same as services) ── */
        footer {
            background: var(--dark); color: white;
            padding: clamp(3rem,8vw,5.5rem) clamp(1.25rem,5%,4rem) 0;
        }
        .footer-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(min(220px,100%),1fr));
            gap: clamp(1.5rem,4vw,3rem); max-width: 1200px; margin: 0 auto clamp(2rem,5vw,3.5rem);
        }
        .footer-col h3 {
            font-size: 0.73rem; font-weight: 700; letter-spacing: 0.14em;
            text-transform: uppercase; color: var(--gold-light);
            margin-bottom: 1.25rem; padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .footer-col p { font-size: clamp(0.82rem,2.5vw,0.88rem); color: rgba(255,255,255,0.45); line-height: 1.8; margin-bottom: 1.25rem; }
        .footer-social { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .social-icon {
            width: 36px; height: 36px; border-radius: 50%;
            background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1);
            display: flex; align-items: center; justify-content: center;
            color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.85rem;
            transition: all 0.25s ease;
        }
        .social-icon:hover { background: var(--gold); border-color: var(--gold); color: var(--navy); transform: translateY(-2px); }
        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 0.6rem; }
        .footer-links a {
            color: rgba(255,255,255,0.45); text-decoration: none;
            font-size: clamp(0.82rem,2.5vw,0.87rem); transition: all 0.2s ease;
            display: inline-flex; align-items: center; gap: 0.4rem;
        }
        .footer-links a:hover { color: white; padding-left: 4px; }
        .footer-links li:not(:has(a)) {
            color: rgba(255,255,255,0.45); font-size: clamp(0.82rem,2.5vw,0.87rem);
            display: flex; align-items: flex-start; gap: 0.5rem; line-height: 1.6;
        }
        .footer-links li i { color: var(--gold); margin-top: 2px; flex-shrink: 0; }
        .footer-bottom { padding: 1.5rem clamp(1.25rem,5%,4rem); border-top: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .footer-bottom p { font-size: 0.78rem; color: rgba(255,255,255,0.26); }

        /* ── ANIMATIONS ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-on-scroll {
            opacity: 0; transform: translateY(20px);
            transition: all 0.7s var(--ease-out);
        }
        .animate-on-scroll.visible { opacity: 1; transform: translateY(0); }

        /* ── RESPONSIVE (aligned with services.php) ── */
        @media (max-width: 992px) {
            .nav-toggle { display: block; }
            .nav-menu {
                position: fixed; top: 76px; left: -100%;
                width: 100%; height: calc(100vh - 76px);
                background: var(--navy); flex-direction: column;
                align-items: stretch; padding: 1.5rem;
                transition: left 0.4s var(--ease-out); overflow-y: auto;
            }
            .nav-menu.active { left: 0; }
            .nav-list { flex-direction: column; width: 100%; gap: 0; }
            .nav-item { width: 100%; margin-bottom: 0.4rem; }
            .nav-link { padding: 0.75rem 1rem; font-size: 1rem; }
            .dropdown { position: static; opacity: 1; visibility: visible; transform: none; box-shadow: none; border: none; background: rgba(255,255,255,0.05); border-radius: var(--radius-sm); margin-top: 0.25rem; display: none; }
            .nav-item.open .dropdown { display: block; }
            .nav-item.open .nav-link i { transform: rotate(180deg) !important; }

            .work-grid { flex-direction: column; }
            .search-form { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 768px) {
            .jobs-grid { grid-template-columns: 1fr; }
            .job-footer { flex-direction: column; align-items: flex-start; gap: 1rem; }
            .job-buttons { width: 100%; }
            .btn-view, .btn-apply { flex: 1; text-align: center; justify-content: center; }
            .footer-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 576px) {
            .header-container { padding: 0 1rem; }
            .cta-buttons { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>

<!-- ─── HEADER (exactly as services.php) ─── -->
<header id="mainHeader">
    <div class="header-container">
        <div class="logo">
            <a href="index.php"><img src="images/mbLogo.png" alt="MULTIBIZ INTERNATIONAL CORPORATION"></a>
        </div>
        <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation">
            <i class="fas fa-bars"></i>
        </button>
        <nav class="nav-menu" id="navMenu">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="about.php" class="nav-link">About <i class="fas fa-chevron-down"></i></a>
                    <div class="dropdown">
                        <div class="dropdown-item"><a href="about.php" class="dropdown-link">Who We Are</a></div>
                        <div class="dropdown-item"><a href="about.php#vision" class="dropdown-link">Vision, Mission, Values</a></div>
                        <div class="dropdown-item"><a href="about.php#whereweare" class="dropdown-link">Where We Are</a></div>
                        <div class="dropdown-item"><a href="about.php#industries" class="dropdown-link">Industries We Serve</a></div>
                        <div class="dropdown-item"><a href="about.php#certifications" class="dropdown-link">Certifications</a></div>
                        <div class="dropdown-item"><a href="about.php#awards" class="dropdown-link">Awards</a></div>
                        <div class="dropdown-item"><a href="about.php#history" class="dropdown-link">History</a></div>
                        <div class="dropdown-item"><a href="about.php#storiesarchive" class="dropdown-link">Stories Archive</a></div>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="services.php" class="nav-link">Services <i class="fas fa-chevron-down"></i></a>
                    <div class="dropdown">
                        <div class="dropdown-item"><a href="services.php" class="dropdown-link">Managed Print Services (MPS)</a></div>
                        <div class="dropdown-item"><a href="services.php#managedit" class="dropdown-link">Document Management System</a></div>
                        <div class="dropdown-item"><a href="services.php#managedpc" class="dropdown-link">Document Scanner Leasing</a></div>
                        <div class="dropdown-item"><a href="services.php#managedhris" class="dropdown-link">System Integration</a></div>
                    </div>
                </li>
                <li class="nav-item">
                    <a href="careers.php" class="nav-link active">Careers</a>
                </li>
                <li class="nav-item">
                    <a href="loginregister.php" class="nav-link login-register-btn">Login</a>
                </li>
            </ul>
        </nav>
    </div>
</header>

<!-- ─── CAREERS HERO (inspired by services hero) ─── -->
<div class="careers-hero">
    <div class="careers-hero-overlay"></div>
    <div class="careers-hero-content">
        <span class="careers-hero-eyebrow">Join Our Team</span>
        <h1 class="careers-hero-title">Build Your Future <em>With Us</em></h1>
        <p class="careers-hero-subtitle">Discover exciting career opportunities and become part of a dynamic team that's shaping the future of business solutions in the Philippines.</p>
    </div>
</div>

<!-- ─── WORK WITH US SECTION (like service sections) ─── -->
<section class="section">
    <div class="work-grid">
        <div class="work-content animate-on-scroll">
            <span class="section-label">Work With Us</span>
            <h2 class="section-title">Why Join MULTIBIZ?</h2>
            <p class="section-subtitle">At MULTIBIZ INTERNATIONAL CORPORATION, we are your Managed Print Services expert, dedicated to delivering innovative, practical, and quality solutions. For over two decades, we have empowered businesses and fostered sustainable growth for our partners.</p>
            <p class="section-subtitle">We believe in nurturing talent and providing an environment where professionals can thrive, grow, and make a meaningful impact.</p>
            <div class="benefits-list">
                <div class="benefit-item"><i class="fas fa-chart-line"></i><span>Career growth and development opportunities</span></div>
                <div class="benefit-item"><i class="fas fa-hand-holding-heart"></i><span>Competitive compensation and benefits package</span></div>
                <div class="benefit-item"><i class="fas fa-users"></i><span>Collaborative and supportive work environment</span></div>
                <div class="benefit-item"><i class="fas fa-clock"></i><span>Work-life balance and flexible arrangements</span></div>
            </div>
        </div>
        <div class="work-image animate-on-scroll">
            <img src="images/applynow.png" alt="MULTIBIZ team">
        </div>
    </div>
</section>

<!-- ─── SEARCH SECTION (clean corporate) ─── -->
<section class="search-section">
    <div class="search-container">
        <div class="section-center" style="margin-bottom: 2rem;">
            <span class="section-label">Find Your Opportunity</span>
            <h2 class="section-title">Search Open Positions</h2>
        </div>

        <form method="GET" class="search-form animate-on-scroll">
            <div class="form-group">
                <input type="text" name="search" class="form-input" placeholder="Search jobs, keywords..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
            </div>
            <div class="form-group">
                <input type="text" name="location" class="form-input" placeholder="Location" value="<?php echo isset($_GET['location']) ? htmlspecialchars($_GET['location']) : ''; ?>">
            </div>
            <div class="form-group">
                <select name="employment_type" class="form-select">
                    <option value="">All Employment Types</option>
                    <option value="full-time" <?php echo (isset($_GET['employment_type']) && $_GET['employment_type']=='full-time')?'selected':''; ?>>Full-time</option>
                    <option value="part-time" <?php echo (isset($_GET['employment_type']) && $_GET['employment_type']=='part-time')?'selected':''; ?>>Part-time</option>
                    <option value="contract" <?php echo (isset($_GET['employment_type']) && $_GET['employment_type']=='contract')?'selected':''; ?>>Contract</option>
                    <option value="internship" <?php echo (isset($_GET['employment_type']) && $_GET['employment_type']=='internship')?'selected':''; ?>>Internship</option>
                </select>
            </div>
            <button type="submit" class="btn-search"><i class="fas fa-search"></i> Search Jobs</button>
        </form>

        <!-- static job count example (replace with dynamic if needed) -->
        <div class="job-count animate-on-scroll"><strong>6</strong> jobs found</div>
    </div>
</section>

<!-- ─── JOBS GRID SECTION (cards with service flair) ─── -->
<section class="section">
    <div class="section-center" style="margin-bottom: 3rem;">
        <span class="section-label">Current Opportunities</span>
        <h2 class="section-title">Featured Job Openings</h2>
    </div>

    <div class="jobs-grid">
        <!-- Job Card 1 -->
        <div class="job-card animate-on-scroll">
            <div class="job-header">
                <h3 class="job-title">Managed Print Specialist</h3>
                <p class="job-company"><i class="fas fa-building"></i> MULTIBIZ INTERNATIONAL</p>
            </div>
            <div class="job-meta">
                <div class="job-meta-item"><i class="fas fa-map-marker-alt"></i> Pasig City</div>
                <div class="job-meta-item"><i class="fas fa-briefcase"></i> Full-time</div>
                <div class="job-meta-item"><i class="fas fa-clock"></i> Posted Mar 10, 2025</div>
            </div>
            <p class="job-description">Manage client print infrastructure, optimize device fleets, and implement MPS solutions. Provide technical support and maintain consumables inventory.</p>
            <div class="job-skills">
                <span class="skill-tag">MPS</span><span class="skill-tag">Printers</span><span class="skill-tag">Canon</span><span class="skill-tag">Ricoh</span>
            </div>
            <div class="job-footer">
                <div class="job-salary"><i class="fas fa-money-bill-wave"></i> ₱25K–₱35K</div>
                <div class="job-buttons">
                    <a href="#" class="btn-view"><i class="fas fa-info-circle"></i> Details</a>
                    <a href="loginregister.php" class="btn-apply"><i class="fas fa-paper-plane"></i> Apply</a>
                </div>
            </div>
        </div>

        <!-- Job Card 2 -->
        <div class="job-card animate-on-scroll">
            <div class="job-header">
                <h3 class="job-title">Document Management Consultant</h3>
                <p class="job-company"><i class="fas fa-building"></i> MULTIBIZ INTERNATIONAL</p>
            </div>
            <div class="job-meta">
                <div class="job-meta-item"><i class="fas fa-map-marker-alt"></i> Makati City</div>
                <div class="job-meta-item"><i class="fas fa-briefcase"></i> Full-time</div>
                <div class="job-meta-item"><i class="fas fa-clock"></i> Posted Mar 5, 2025</div>
            </div>
            <p class="job-description">Lead DMS implementations, analyze client workflows, configure document repositories, and train users. Experience with OnBase or SharePoint a plus.</p>
            <div class="job-skills">
                <span class="skill-tag">DMS</span><span class="skill-tag">OnBase</span><span class="skill-tag">SharePoint</span><span class="skill-tag">Scanning</span>
            </div>
            <div class="job-footer">
                <div class="job-salary"><i class="fas fa-money-bill-wave"></i> ₱30K–₱45K</div>
                <div class="job-buttons">
                    <a href="#" class="btn-view"><i class="fas fa-info-circle"></i> Details</a>
                    <a href="loginregister.php" class="btn-apply"><i class="fas fa-paper-plane"></i> Apply</a>
                </div>
            </div>
        </div>

        <!-- Job Card 3 -->
        <div class="job-card animate-on-scroll">
            <div class="job-header">
                <h3 class="job-title">Scanner Leasing Account Executive</h3>
                <p class="job-company"><i class="fas fa-building"></i> MULTIBIZ INTERNATIONAL</p>
            </div>
            <div class="job-meta">
                <div class="job-meta-item"><i class="fas fa-map-marker-alt"></i> Quezon City</div>
                <div class="job-meta-item"><i class="fas fa-briefcase"></i> Full-time</div>
                <div class="job-meta-item"><i class="fas fa-clock"></i> Posted Feb 28, 2025</div>
            </div>
            <p class="job-description">Promote scanner leasing programs, build client relationships, achieve sales targets, and coordinate with technical teams for smooth onboarding.</p>
            <div class="job-skills">
                <span class="skill-tag">Sales</span><span class="skill-tag">Leasing</span><span class="skill-tag">Document Scanners</span>
            </div>
            <div class="job-footer">
                <div class="job-salary"><i class="fas fa-money-bill-wave"></i> Competitive + Commission</div>
                <div class="job-buttons">
                    <a href="#" class="btn-view"><i class="fas fa-info-circle"></i> Details</a>
                    <a href="loginregister.php" class="btn-apply"><i class="fas fa-paper-plane"></i> Apply</a>
                </div>
            </div>
        </div>

        <!-- Job Card 4 -->
        <div class="job-card animate-on-scroll">
            <div class="job-header">
                <h3 class="job-title">System Integration Engineer</h3>
                <p class="job-company"><i class="fas fa-building"></i> MULTIBIZ INTERNATIONAL</p>
            </div>
            <div class="job-meta">
                <div class="job-meta-item"><i class="fas fa-map-marker-alt"></i> Taguig City</div>
                <div class="job-meta-item"><i class="fas fa-briefcase"></i> Contract</div>
                <div class="job-meta-item"><i class="fas fa-clock"></i> Posted Feb 20, 2025</div>
            </div>
            <p class="job-description">Design and implement integration solutions between ERP, HRIS, and other platforms. Develop APIs and middleware to streamline operations.</p>
            <div class="job-skills">
                <span class="skill-tag">API</span><span class="skill-tag">Middleware</span><span class="skill-tag">Cloud</span><span class="skill-tag">Python</span>
            </div>
            <div class="job-footer">
                <div class="job-salary"><i class="fas fa-money-bill-wave"></i> ₱40K–₱60K</div>
                <div class="job-buttons">
                    <a href="#" class="btn-view"><i class="fas fa-info-circle"></i> Details</a>
                    <a href="loginregister.php" class="btn-apply"><i class="fas fa-paper-plane"></i> Apply</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ─── CTA SECTION (exact copy from services.php) ─── -->
<section class="cta-section">
    <div class="cta-container">
        <span class="cta-eyebrow">Ready to Start?</span>
        <h2 class="cta-title">Don't See the Right Fit?</h2>
        <p class="cta-description">We're always looking for talented individuals to join our team. Send us your resume and we'll keep you in mind for future opportunities.</p>
        <div class="cta-buttons">
            <a href="contactus.php" class="cta-btn cta-btn-primary"><i class="fas fa-paper-plane"></i> Send Resume</a>
            <a href="about.php" class="cta-btn cta-btn-outline">Learn About Us <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- ─── FOOTER (identical to services.php) ─── -->
<footer>
    <div class="footer-grid">
        <div class="footer-col">
            <h3>MULTIBIZ INTERNATIONAL</h3>
            <p>Your trusted managed services partner. Delivering innovative solutions that drive efficiency, reduce costs, and enhance productivity.</p>
            <div class="footer-social">
                <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
        <div class="footer-col">
            <h3>Quick Links</h3>
            <ul class="footer-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="about.php">About Us</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="careers.php">Careers</a></li>
                <li><a href="contactus.php">Contact Us</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h3>Services</h3>
            <ul class="footer-links">
                <li><a href="services.php">Managed Print Services</a></li>
                <li><a href="services.php#managedit">Document Management System</a></li>
                <li><a href="services.php#managedpc">Document Scanner Leasing</a></li>
                <li><a href="services.php#managedhris">System Integration</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h3>Contact Info</h3>
            <ul class="footer-links">
                <li><i class="fas fa-globe"></i> https://multibiz.global</li>
                <li><i class="fas fa-phone"></i> +63 917 544 1674</li>
                <li><i class="fas fa-envelope"></i> inquiry@multibiz.global</li>
                <li><i class="fab fa-facebook-f"></i> Multibiz International Corporation</li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; <span id="yr"></span> MULTIBIZ INTERNATIONAL CORPORATION. All Rights Reserved.</p>
    </div>
</footer>

<script>
    document.getElementById('yr').textContent = new Date().getFullYear();

    // Header scroll effect
    const header = document.getElementById('mainHeader');
    window.addEventListener('scroll', () => header.classList.toggle('scrolled', window.scrollY > 20));

    // Mobile nav toggle
    const navToggle = document.getElementById('navToggle');
    const navMenu = document.getElementById('navMenu');
    navToggle.addEventListener('click', () => {
        const active = navMenu.classList.toggle('active');
        navToggle.innerHTML = active ? '<i class="fas fa-times"></i>' : '<i class="fas fa-bars"></i>';
    });

    // Mobile dropdowns
    document.querySelectorAll('.nav-link .fa-chevron-down').forEach(icon => {
        icon.closest('.nav-link').addEventListener('click', function(e) {
            if (window.innerWidth <= 992) {
                e.preventDefault();
                const parent = this.closest('.nav-item');
                const wasOpen = parent.classList.contains('open');
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('open'));
                if (!wasOpen) parent.classList.add('open');
            }
        });
    });

    // Scroll animations
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, i) => {
            if (entry.isIntersecting) {
                setTimeout(() => entry.target.classList.add('visible'), i * 60);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));
</script>

</body>
</html>