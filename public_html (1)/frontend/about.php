<!DOCTYPE html>
<html lang="en">
<head>
    <title>About Us - MULTIBIZ INTERNATIONAL CORPORATION</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="keywords" content="MULTIBIZ INTERNATIONAL CORPORATION, About Us, Company History, Vision, Mission">
    <meta name="description" content="Learn about MULTIBIZ INTERNATIONAL CORPORATION - Your trusted managed services partner since 2002">
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
        header.scrolled .header-container { box-shadow: var(--shadow-lg); }
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

        /* ── ABOUT HERO (inspired by services hero) ── */
        .about-hero {
            height: clamp(44vh,56vh,65vh);
            position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center; text-align: center;
            color: white; margin-top: 76px; min-height: 320px;
            background: var(--navy-mid);
        }
        .about-hero::before {
            content: ''; position: absolute; inset: 0;
            background:
                radial-gradient(ellipse 70% 60% at 70% 35%, rgba(26,79,160,0.6) 0%, transparent 70%),
                radial-gradient(ellipse 55% 45% at 15% 75%, rgba(184,151,58,0.2) 0%, transparent 60%);
        }
        .about-hero-overlay {
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none'%3E%3Cg fill='%23ffffff' fill-opacity='0.018'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .about-hero-overlay::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 100%; height: 130px;
            background: linear-gradient(to top, var(--warm-white), transparent);
        }
        .about-hero-content {
            max-width: min(820px,90vw); padding: 0 clamp(1rem,5%,2rem);
            z-index: 1; position: relative;
        }
        .about-hero-eyebrow {
            display: inline-flex; align-items: center; gap: 0.7rem;
            font-size: 0.68rem; font-weight: 600; letter-spacing: 0.22em;
            text-transform: uppercase; color: var(--gold-light); margin-bottom: 1.4rem;
            animation: fadeUp 0.7s var(--ease-out) 0.1s both;
        }
        .about-hero-eyebrow::before, .about-hero-eyebrow::after { content: ''; height: 1px; background: var(--gold-light); opacity: 0.6; width: 36px; }
        .about-hero-title {
            font-family: var(--font-display);
            font-size: clamp(2.2rem,7vw,4rem); font-weight: 600; line-height: 1.1;
            margin-bottom: 1.25rem; animation: fadeUp 0.8s var(--ease-out) 0.25s both;
        }
        .about-hero-title em { font-style: italic; color: var(--gold-light); }
        .about-hero-subtitle {
            font-size: clamp(0.95rem,3vw,1.2rem); color: rgba(255,255,255,0.65);
            font-weight: 300; line-height: 1.65; max-width: 600px; margin: 0 auto;
            animation: fadeUp 0.8s var(--ease-out) 0.4s both;
        }

        /* ── ABOUT SUB-NAV (like services-nav) ── */
        .about-nav {
            background: white; border-bottom: 1px solid var(--gray-200);
            position: sticky; top: 76px; z-index: 100;
            box-shadow: 0 2px 16px rgba(10,22,40,0.07);
            overflow-x: auto; scrollbar-width: none;
        }
        .about-nav::-webkit-scrollbar { display: none; }
        .about-nav-container { max-width: 1200px; margin: 0 auto; padding: 0 clamp(1rem,3%,2rem); }
        .about-nav-list { display: flex; list-style: none; white-space: nowrap; }
        .about-nav-link {
            text-decoration: none; color: var(--gray-500); font-weight: 500;
            padding: 1.1rem clamp(0.85rem,2vw,1.35rem); display: block;
            font-size: clamp(0.75rem,2vw,0.83rem); letter-spacing: 0.03em;
            white-space: nowrap; border-bottom: 2px solid transparent;
            transition: all 0.25s ease;
        }
        .about-nav-link:hover { color: var(--navy); border-bottom-color: var(--gold); background: rgba(184,151,58,0.04); }

        /* ── ABOUT SECTIONS (like service sections) ── */
        .about-section {
            padding: clamp(4.5rem,9vw,7rem) clamp(1.25rem,5%,5%);
            border-top: 1px solid rgba(10,22,40,0.07); overflow: hidden;
        }
        .about-section-alt { background: linear-gradient(175deg, #eef0f6 0%, #e7eaf3 100%); }

        .about-container {
            display: flex; align-items: center;
            gap: clamp(2.5rem,6vw,5rem);
            max-width: min(1200px,96%); margin: 0 auto; flex-wrap: wrap;
        }
        .about-content { flex: 1 1 min(480px,100%); min-width: min(280px,100%); }
        .about-image { flex: 1 1 min(420px,100%); text-align: center; min-width: min(280px,100%); }
        .about-image img {
            max-width: 100%; border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            transition: transform 0.6s var(--ease-out);
        }
        .about-image img:hover { transform: scale(1.025); }

        /* Section eyebrow */
        .section-eyebrow {
            display: inline-flex; align-items: center; gap: 0.55rem;
            font-size: 0.68rem; font-weight: 700; letter-spacing: 0.18em;
            text-transform: uppercase; color: var(--blue); margin-bottom: 0.7rem;
        }
        .section-eyebrow::before { content: ''; width: 22px; height: 2px; background: var(--gold); border-radius: 2px; }

        .about-title {
            font-family: var(--font-display);
            font-size: clamp(1.7rem,5vw,2.5rem); color: var(--navy);
            margin-bottom: clamp(1.25rem,4vw,1.75rem);
            position: relative; padding-bottom: clamp(0.8rem,2vw,1.1rem);
            line-height: 1.2; font-weight: 600;
        }
        .about-title::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 52px; height: 3px;
            background: linear-gradient(90deg, var(--gold), var(--gold-light));
            border-radius: 3px;
        }
        .about-description {
            font-size: clamp(0.88rem,2.8vw,1rem); line-height: 1.8;
            color: var(--gray-700); margin-bottom: clamp(1rem,3vw,1.5rem);
            text-align: justify; hyphens: auto;
        }

        /* ── STATS CARDS ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(140px,100%),1fr));
            gap: clamp(0.75rem,2vw,1rem);
            margin-top: clamp(1.5rem,4vw,2rem);
        }
        .stat-card {
            text-align: center; padding: clamp(1rem,3vw,1.5rem);
            background: white; border-radius: var(--radius-md);
            border: 1px solid var(--gray-200); border-top: 3px solid var(--gold);
            transition: all 0.3s var(--ease-out);
        }
        .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        .stat-number {
            font-family: var(--font-display);
            font-size: clamp(1.8rem,5vw,2.2rem); font-weight: 700;
            color: var(--navy); line-height: 1; margin-bottom: 0.3rem;
        }
        .stat-label {
            font-size: clamp(0.7rem,2.5vw,0.8rem); color: var(--gray-500);
            font-weight: 500; letter-spacing: 0.02em;
        }

        /* ── VMV CARDS ── */
        .vmv-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(260px,100%),1fr));
            gap: clamp(1.25rem,3vw,1.75rem);
            margin-top: clamp(1rem,3vw,1.5rem);
        }
        .vmv-card {
            text-align: center; padding: clamp(1.5rem,4vw,2rem);
            background: white; border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200); position: relative;
            overflow: hidden; transition: all 0.35s var(--ease-out);
        }
        .vmv-card::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0;
            height: 3px; background: linear-gradient(90deg, var(--blue), var(--gold));
        }
        .vmv-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }
        .vmv-icon {
            width: clamp(56px,10vw,64px); height: clamp(56px,10vw,64px);
            background: linear-gradient(135deg, var(--navy), var(--blue));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto clamp(1rem,2.5vw,1.25rem);
            color: var(--gold-light); font-size: clamp(1.25rem,3.5vw,1.5rem);
        }
        .vmv-card h3 {
            font-family: var(--font-display);
            font-size: clamp(1.1rem,3vw,1.25rem); font-weight: 600;
            margin-bottom: 0.75rem; color: var(--navy);
        }
        .vmv-card p { color: var(--gray-500); font-size: clamp(0.85rem,2.5vw,0.9rem); line-height: 1.7; }

        /* ── VALUES GRID ── */
        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(140px,100%),1fr));
            gap: clamp(0.75rem,2vw,1rem);
            margin-top: clamp(1.5rem,4vw,2rem);
        }
        .value-item {
            text-align: center; padding: clamp(1rem,3vw,1.25rem);
            background: white; border-radius: var(--radius-md);
            border: 1px solid var(--gray-200); border-left: 3px solid var(--gold);
            transition: all 0.3s var(--ease-out);
        }
        .value-item:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-left-color: var(--blue); }
        .value-item i {
            font-size: clamp(1.25rem,4vw,1.5rem); color: var(--blue);
            margin-bottom: 0.5rem; display: block;
        }
        .value-item h4 {
            font-family: var(--font-display);
            font-size: clamp(0.9rem,2.8vw,1rem); font-weight: 600;
            margin-bottom: 0.25rem; color: var(--navy);
        }
        .value-item p { color: var(--gray-500); font-size: clamp(0.75rem,2.5vw,0.82rem); line-height: 1.5; }

        /* ── LOCATION INFO ── */
        .location-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(200px,100%),1fr));
            gap: clamp(0.75rem,2vw,1rem);
            margin-top: clamp(1.5rem,4vw,2rem);
        }
        .location-card {
            padding: clamp(1.25rem,3vw,1.5rem); background: white;
            border-radius: var(--radius-md); border: 1px solid var(--gray-200);
            border-left: 3px solid var(--gold); transition: all 0.3s var(--ease-out);
        }
        .location-card:hover { transform: translateX(4px); box-shadow: var(--shadow-md); }
        .location-card h3 {
            font-size: clamp(0.9rem,2.8vw,1rem); font-weight: 600;
            color: var(--navy); margin-bottom: 0.5rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .location-card h3 i { color: var(--blue); font-size: 0.9rem; }
        .location-card p { color: var(--gray-700); font-size: clamp(0.82rem,2.5vw,0.88rem); line-height: 1.6; }

        /* ── INDUSTRIES GRID ── */
        .industries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(150px,100%),1fr));
            gap: clamp(0.75rem,2vw,1rem);
            margin-top: clamp(1.5rem,4vw,2rem);
        }
        .industry-card {
            text-align: center; padding: clamp(1rem,3vw,1.25rem);
            background: white; border-radius: var(--radius-md);
            border: 1px solid var(--gray-200); transition: all 0.3s var(--ease-out);
            position: relative; overflow: hidden;
        }
        .industry-card::after {
            content: ''; position: absolute; bottom: 0; left: 0;
            width: 100%; height: 2px; background: linear-gradient(90deg, var(--blue), var(--gold));
            transform: scaleX(0); transition: transform 0.35s var(--ease-out);
        }
        .industry-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
        .industry-card:hover::after { transform: scaleX(1); }
        .industry-card i {
            font-size: clamp(1.5rem,5vw,2rem); color: var(--blue);
            margin-bottom: 0.5rem; transition: color 0.3s ease;
        }
        .industry-card:hover i { color: var(--gold); }
        .industry-card h4 {
            font-family: var(--font-display);
            font-size: clamp(0.8rem,2.8vw,0.9rem); font-weight: 600;
            color: var(--navy); line-height: 1.3;
        }

        /* ── CERTIFICATIONS GRID ── */
        .certs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(200px,100%),1fr));
            gap: clamp(1rem,3vw,1.5rem);
            margin-top: clamp(1.5rem,4vw,2rem);
        }
        .cert-card {
            text-align: center; padding: clamp(1.25rem,4vw,1.75rem);
            background: white; border-radius: var(--radius-lg);
            border: 1px solid var(--gray-200); transition: all 0.35s var(--ease-out);
        }
        .cert-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: rgba(184,151,58,0.3); }
        .cert-icon {
            width: clamp(56px,12vw,64px); height: clamp(56px,12vw,64px);
            background: linear-gradient(135deg, var(--navy), var(--blue));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto clamp(1rem,2.5vw,1.25rem);
            color: var(--gold-light); font-size: clamp(1.25rem,4vw,1.5rem);
        }
        .cert-card h4 {
            font-family: var(--font-display);
            font-size: clamp(0.9rem,3vw,1rem); font-weight: 600;
            margin-bottom: 0.5rem; color: var(--navy);
        }
        .cert-card p { color: var(--gray-500); font-size: clamp(0.78rem,2.5vw,0.85rem); line-height: 1.5; }

        /* ── AWARDS TIMELINE ── */
        .awards-timeline {
            margin-top: clamp(1.5rem,4vw,2rem);
            position: relative;
        }
        .awards-timeline::before {
            content: ''; position: absolute; left: 28px; top: 0; bottom: 0;
            width: 2px; background: linear-gradient(to bottom, var(--blue), var(--gold));
        }
        .award-item {
            display: flex; margin-bottom: clamp(1.25rem,4vw,1.75rem);
            position: relative; gap: clamp(1rem,3vw,1.5rem);
            flex-wrap: wrap;
        }
        .award-year {
            width: 56px; height: 56px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: var(--gold-light); font-weight: 700; font-size: 0.8rem;
            border: 2px solid rgba(184,151,58,0.3); z-index: 2;
        }
        .award-content {
            flex: 1 1 min(280px,100%); padding: clamp(1rem,3vw,1.25rem);
            background: white; border-radius: var(--radius-md);
            border: 1px solid var(--gray-200); border-left: 3px solid var(--gold);
            transition: all 0.3s var(--ease-out);
        }
        .award-content:hover { transform: translateX(4px); box-shadow: var(--shadow-md); }
        .award-content h4 {
            font-family: var(--font-display);
            font-size: clamp(0.95rem,3vw,1.05rem); font-weight: 600;
            margin-bottom: 0.25rem; color: var(--navy);
        }
        .award-content p { color: var(--gray-500); font-size: clamp(0.8rem,2.5vw,0.85rem); line-height: 1.6; margin: 0; }

        /* ── HISTORY TIMELINE ── */
        .history-timeline { margin-top: clamp(1.5rem,4vw,2rem); }
        .history-item {
            display: flex; align-items: flex-start; margin-bottom: clamp(1.25rem,4vw,1.5rem);
            padding: clamp(1.25rem,3vw,1.5rem); background: white;
            border-radius: var(--radius-md); border: 1px solid var(--gray-200);
            transition: all 0.3s var(--ease-out); gap: clamp(1rem,3vw,1.5rem);
            flex-wrap: wrap;
        }
        .history-item:hover { transform: translateX(8px); box-shadow: var(--shadow-md); border-color: rgba(184,151,58,0.2); }
        .history-year {
            width: 70px; height: 70px; flex-shrink: 0;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            color: var(--gold-light); font-family: var(--font-display);
            font-weight: 700; font-size: 0.9rem;
        }
        .history-content { flex: 1 1 min(280px,100%); }
        .history-content h4 {
            font-family: var(--font-display);
            font-size: clamp(1rem,3.5vw,1.1rem); font-weight: 600;
            margin-bottom: 0.4rem; color: var(--navy);
        }
        .history-content p { color: var(--gray-500); font-size: clamp(0.82rem,2.5vw,0.88rem); line-height: 1.7; margin: 0; }

        /* ── STORIES GRID ── */
        .stories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(min(260px,100%),1fr));
            gap: clamp(1rem,3vw,1.5rem);
            margin-top: clamp(1.5rem,4vw,2rem);
        }
        .story-card {
            background: white; border-radius: var(--radius-lg);
            overflow: hidden; box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200); transition: all 0.35s var(--ease-out);
        }
        .story-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-lg); }
        .story-image {
            height: 180px; overflow: hidden; position: relative;
        }
        .story-image::after {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(to top, rgba(10,22,40,0.3), transparent);
        }
        .story-image img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .story-card:hover .story-image img { transform: scale(1.06); }
        .story-content { padding: clamp(1.25rem,3vw,1.5rem); }
        .story-content h4 {
            font-family: var(--font-display);
            font-size: clamp(0.95rem,3vw,1.05rem); font-weight: 600;
            margin-bottom: 0.6rem; color: var(--navy);
        }
        .story-content p {
            color: var(--gray-500); font-size: clamp(0.8rem,2.5vw,0.85rem);
            line-height: 1.6; margin-bottom: 0.75rem;
        }
        .story-link {
            color: var(--blue); text-decoration: none; font-weight: 600;
            font-size: 0.8rem; display: inline-flex; align-items: center;
            gap: 0.4rem; transition: all 0.25s ease;
        }
        .story-link:hover { gap: 0.65rem; color: var(--gold); }

        .about-cta { text-align: center; margin-top: clamp(2rem,5vw,3rem); }

        /* ── CTA BUTTON (like service-btn) ── */
        .service-btn {
            display: inline-flex; align-items: center; gap: 0.5rem;
            padding: 0.8rem 1.9rem; border-radius: 100px;
            text-decoration: none; font-weight: 600;
            font-size: clamp(0.82rem,2.8vw,0.9rem); letter-spacing: 0.05em;
            transition: all 0.3s var(--ease-out); cursor: pointer; border: none;
        }
        .service-btn-primary {
            background: linear-gradient(135deg, var(--navy), var(--blue));
            color: white; box-shadow: 0 6px 20px rgba(10,22,40,0.25);
        }
        .service-btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(10,22,40,0.35); }

        /* ── CTA SECTION (same as services.php) ── */
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
        .about-section-alt .about-image .animate-on-scroll { transform: translateX(-18px); }
        .about-section-alt .about-image .animate-on-scroll.visible { transform: translateX(0); }

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

            .about-container { flex-direction: column; max-width: min(860px,95%); }
            .about-section-alt .about-container { flex-direction: column-reverse; }
            .stats-grid { grid-template-columns: repeat(2,1fr); }
            .vmv-grid { grid-template-columns: 1fr; }
            .awards-timeline::before { left: 50%; transform: translateX(-50%); }
            .award-item { flex-direction: column; align-items: center; text-align: center; }
            .award-year { margin-bottom: 0.5rem; }
            .history-item { flex-direction: column; text-align: center; }
            .history-year { margin: 0 auto 1rem; }
        }
        @media (max-width: 576px) {
            .header-container { padding: 0 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .values-grid { grid-template-columns: 1fr; }
            .industries-grid { grid-template-columns: repeat(2,1fr); }
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
                    <a href="about.php" class="nav-link active">About <i class="fas fa-chevron-down"></i></a>
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
                    <a href="careers.php" class="nav-link">Careers</a>
                </li>
                <li class="nav-item">
                    <a href="loginregister.php" class="nav-link login-register-btn">Login</a>
                </li>
            </ul>
        </nav>
    </div>
</header>

<!-- ─── ABOUT HERO (inspired by services hero) ─── -->
<div class="about-hero">
    <div class="about-hero-overlay"></div>
    <div class="about-hero-content">
        <span class="about-hero-eyebrow">Trusted Since 2002</span>
        <h1 class="about-hero-title">About <em>MULTIBIZ</em></h1>
        <p class="about-hero-subtitle">Two decades of integrated solutions, innovation, and unwavering commitment to our clients.</p>
    </div>
</div>

<!-- ─── ABOUT SUB-NAV (like services-nav) ─── -->
<nav class="about-nav">
    <div class="about-nav-container">
        <ul class="about-nav-list">
            <li><a href="#whoweare" class="about-nav-link">Who We Are</a></li>
            <li><a href="#vision" class="about-nav-link">Vision & Mission</a></li>
            <li><a href="#whereweare" class="about-nav-link">Where We Are</a></li>
            <li><a href="#industries" class="about-nav-link">Industries</a></li>
            <li><a href="#certifications" class="about-nav-link">Certifications</a></li>
            <li><a href="#awards" class="about-nav-link">Awards</a></li>
            <li><a href="#history" class="about-nav-link">History</a></li>
            <li><a href="#storiesarchive" class="about-nav-link">Stories</a></li>
        </ul>
    </div>
</nav>

<!-- ─── WHO WE ARE ─── -->
<section id="whoweare" class="about-section">
    <div class="about-container">
        <div class="about-content animate-on-scroll">
            <span class="section-eyebrow">Our Company</span>
            <h2 class="about-title">Who We Are</h2>
            <p class="about-description">
                Multibiz International Corporation is a leader in providing integrated IT and Managed Print Services (MPS). Since 2002, we have been dedicated to delivering innovative, practical, and quality solutions. We empower businesses by allowing them to focus on their core operations while we manage their printing and document management needs. We are a multi-brand company, offering tailored solutions from a wide array of technology partners.
            </p>
            <p class="about-description">
                Our commitment to excellence and customer satisfaction has made us the preferred choice for businesses across various industries. We pride ourselves on delivering comprehensive solutions that drive efficiency, reduce costs, and enhance productivity.
            </p>

            <div class="stats-grid">
                <div class="stat-card"><div class="stat-number">22+</div><div class="stat-label">Years of Experience</div></div>
                <div class="stat-card"><div class="stat-number">500+</div><div class="stat-label">Happy Clients</div></div>
                <div class="stat-card"><div class="stat-number">95%</div><div class="stat-label">Efficiency Rate</div></div>
                <div class="stat-card"><div class="stat-number">3K+</div><div class="stat-label">Units Managed</div></div>
            </div>
        </div>

        <div class="about-image animate-on-scroll">
            <img src="images/picture3.png" alt="MULTIBIZ Team">
        </div>
    </div>
</section>

<!-- ─── VISION, MISSION, VALUES ─── -->
<section id="vision" class="about-section about-section-alt">
    <div class="about-container">
        <div class="about-image animate-on-scroll">
            <img src="images/picture2.png" alt="Our Vision and Mission">
        </div>

        <div class="about-content animate-on-scroll">
            <span class="section-eyebrow">Our Foundation</span>
            <h2 class="about-title">Vision, Mission & Values</h2>

            <div class="vmv-grid">
                <div class="vmv-card">
                    <div class="vmv-icon"><i class="fas fa-eye"></i></div>
                    <h3>Our Vision</h3>
                    <p>To be the leading and most trusted business partner in providing innovative solutions for business growth and sustainability.</p>
                </div>
                <div class="vmv-card">
                    <div class="vmv-icon"><i class="fas fa-bullseye"></i></div>
                    <h3>Our Mission</h3>
                    <p>We are committed to provide excellent service and solutions to our customers through our competent and passionate team.</p>
                </div>
            </div>

            <h3 class="section-eyebrow" style="margin-top:1.5rem;">Our Core Values</h3>
            <div class="values-grid">
                <div class="value-item"><i class="fas fa-shield-alt"></i><h4>Integrity</h4><p>Do what is right</p></div>
                <div class="value-item"><i class="fas fa-star"></i><h4>Excellence</h4><p>Strive to be best</p></div>
                <div class="value-item"><i class="fas fa-users"></i><h4>Teamwork</h4><p>Together we achieve</p></div>
                <div class="value-item"><i class="fas fa-lightbulb"></i><h4>Innovation</h4><p>Embrace change</p></div>
                <div class="value-item"><i class="fas fa-handshake"></i><h4>Customer Focus</h4><p>Clients first</p></div>
            </div>
        </div>
    </div>
</section>

<!-- ─── WHERE WE ARE ─── -->
<section id="whereweare" class="about-section">
    <div class="about-container">
        <div class="about-content animate-on-scroll">
            <span class="section-eyebrow">Our Presence</span>
            <h2 class="about-title">Where We Are</h2>
            <p class="about-description">
                Multibiz International Corporation is headquartered in the Philippines, with offices in key business districts to serve clients nationwide.
            </p>

            <div class="location-grid">
                <div class="location-card"><h3><i class="fas fa-map-marker-alt"></i> Main Office</h3><p>Unit 500A, 5th Floor VGP Center, Ayala Avenue, Makati City</p></div>
                <div class="location-card"><h3><i class="fas fa-phone"></i> Contact</h3><p>+63 917 544 1674<br>0995-653-2071</p></div>
                <div class="location-card"><h3><i class="fas fa-envelope"></i> Email</h3><p>inquiry@multibiz.global<br>www.multibiz.global</p></div>
                <div class="location-card"><h3><i class="fas fa-clock"></i> Hours</h3><p>Mon–Fri: 8AM–6PM<br>Sat: 9AM–1PM</p></div>
            </div>
        </div>

        <div class="about-image animate-on-scroll">
            <img src="images/map.png" alt="Our Location">
        </div>
    </div>
</section>

<!-- ─── INDUSTRIES WE SERVE ─── -->
<section id="industries" class="about-section about-section-alt">
    <div class="about-container">
        <div class="about-image animate-on-scroll">
            <img src="images/picture1.png" alt="Industries We Serve">
        </div>

        <div class="about-content animate-on-scroll">
            <span class="section-eyebrow">Sectors We Serve</span>
            <h2 class="about-title">Industries We Serve</h2>
            <p class="about-description">
                Our diverse client base spans multiple industries, each with unique challenges. We tailor our solutions to meet specific sector needs.
            </p>

            <div class="industries-grid">
                <div class="industry-card"><i class="fas fa-hospital"></i><h4>Healthcare</h4></div>
                <div class="industry-card"><i class="fas fa-university"></i><h4>Banking & Finance</h4></div>
                <div class="industry-card"><i class="fas fa-graduation-cap"></i><h4>Education</h4></div>
                <div class="industry-card"><i class="fas fa-industry"></i><h4>Manufacturing</h4></div>
                <div class="industry-card"><i class="fas fa-shopping-cart"></i><h4>Retail</h4></div>
                <div class="industry-card"><i class="fas fa-building"></i><h4>Government</h4></div>
                <div class="industry-card"><i class="fas fa-house"></i><h4>Real Estate</h4></div>
                <div class="industry-card"><i class="fas fa-utensils"></i><h4>Food & Beverage</h4></div>
                <div class="industry-card"><i class="fas fa-cart-flatbed-suitcase"></i><h4>Hospitality</h4></div>
                <div class="industry-card"><i class="fas fa-car"></i><h4>Automotive</h4></div>
            </div>
        </div>
    </div>
</section>

<!-- ─── CERTIFICATIONS ─── -->
<section id="certifications" class="about-section">
    <div class="about-container">
        <div class="about-content animate-on-scroll">
            <span class="section-eyebrow">Trust & Quality</span>
            <h2 class="about-title">Certifications & Accreditations</h2>
            <p class="about-description">
                Our commitment to quality is demonstrated through certifications and partnerships with leading technology providers.
            </p>

            <div class="certs-grid">
                <div class="cert-card"><div class="cert-icon"><i class="fab fa-microsoft"></i></div><h4>Zhuhai i-AICON</h4><p>Exclusive Partner since 2015</p></div>
                <div class="cert-card"><div class="cert-icon"><i class="fas fa-print"></i></div><h4>Multi-Brand Partner</h4><p>Canon, Ricoh, Epson, Brother</p></div>
                <div class="cert-card"><div class="cert-icon"><i class="fas fa-shield-alt"></i></div><h4>ISO 9001:2015</h4><p>Quality Management</p></div>
                <div class="cert-card"><div class="cert-icon"><i class="fas fa-network-wired"></i></div><h4>Cisco Premier</h4><p>Networking Solutions</p></div>
            </div>
        </div>
    </div>
</section>

<!-- ─── AWARDS & RECOGNITION ─── -->
<section id="awards" class="about-section about-section-alt">
    <div class="about-container">
        <div class="about-content animate-on-scroll">
            <span class="section-eyebrow">Milestones</span>
            <h2 class="about-title">Awards & Recognition</h2>
            <p class="about-description">
                Our dedication to excellence has been recognized through numerous awards and industry accolades.
            </p>

            <div class="awards-timeline">
                <div class="award-item"><div class="award-year">2002</div><div class="award-content"><h4>Company Foundation</h4><p>Multibiz International Corp. established.</p></div></div>
                <div class="award-item"><div class="award-year">2010</div><div class="award-content"><h4>MPS Launch</h4><p>Shift to Managed Print Services.</p></div></div>
                <div class="award-item"><div class="award-year">2015</div><div class="award-content"><h4>i-AICON Partnership</h4><p>Exclusive Philippine partner.</p></div></div>
                <div class="award-item"><div class="award-year">2024</div><div class="award-content"><h4>Excellence Award</h4><p>95% efficiency, 500+ clients.</p></div></div>
            </div>
        </div>
    </div>
</section>

<!-- ─── HISTORY ─── -->
<section id="history" class="about-section">
    <div class="about-container">
        <div class="about-image animate-on-scroll">
            <img src="images/picture3.png" alt="Our History">
        </div>

        <div class="about-content animate-on-scroll">
            <span class="section-eyebrow">Our Journey</span>
            <h2 class="about-title">Our History</h2>
            <div class="history-timeline">
                <div class="history-item"><div class="history-year">1990</div><div class="history-content"><h4>Company Foundation</h4><p>MULTIBIZ established with focus on office equipment.</p></div></div>
                <div class="history-item"><div class="history-year">1998</div><div class="history-content"><h4>Digital Transformation</h4><p>Expanded into IT services.</p></div></div>
                <div class="history-item"><div class="history-year">2005</div><div class="history-content"><h4>Managed Services</h4><p>Introduced MPS for enterprises.</p></div></div>
                <div class="history-item"><div class="history-year">2015</div><div class="history-content"><h4>Regional Expansion</h4><p>Strategic partnerships formed.</p></div></div>
                <div class="history-item"><div class="history-year">2023</div><div class="history-content"><h4>Innovation Center</h4><p>Opened state-of-the-art facility.</p></div></div>
            </div>
        </div>
    </div>
</section>

<!-- ─── STORIES ARCHIVE ─── -->
<section id="storiesarchive" class="about-section about-section-alt">
    <div class="about-container">
        <div class="about-content animate-on-scroll">
            <span class="section-eyebrow">Client Impact</span>
            <h2 class="about-title">Success Stories Archive</h2>
            <p class="about-description">
                Discover how we've helped organizations transform their operations through innovative solutions.
            </p>

            <div class="stories-grid">
                <div class="story-card"><div class="story-image"><img src="https://placehold.co/400x250/0a1628/d4af55?text=Healthcare" alt="Healthcare"></div><div class="story-content"><h4>Medical Records Management</h4><p>60% reduction in processing time.</p><a href="#" class="story-link">Read Story <i class="fas fa-arrow-right"></i></a></div></div>
                <div class="story-card"><div class="story-image"><img src="https://placehold.co/400x250/1a4fa0/ffffff?text=Banking" alt="Banking"></div><div class="story-content"><h4>Banking Transformation</h4><p>Secure document management.</p><a href="#" class="story-link">Read Story <i class="fas fa-arrow-right"></i></a></div></div>
                <div class="story-card"><div class="story-image"><img src="https://placehold.co/400x250/18151f/b8973a?text=Education" alt="Education"></div><div class="story-content"><h4>Campus Modernization</h4><p>Unified print services for university.</p><a href="#" class="story-link">Read Story <i class="fas fa-arrow-right"></i></a></div></div>
            </div>

            <div class="about-cta">
                <a href="contactus.php" class="service-btn service-btn-primary">Share Your Story <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
    </div>
</section>

<!-- ─── CTA SECTION (exact copy from services.php) ─── -->
<section class="cta-section">
    <div class="cta-container">
        <span class="cta-eyebrow">Let's Work Together</span>
        <h2 class="cta-title">Ready to <em>Elevate</em> Your Operations?</h2>
        <p class="cta-description">Contact our team today to discover which solution is right for your organization. We'll craft a tailored package that fits your needs and budget.</p>
        <div class="cta-buttons">
            <a href="contactus.php" class="cta-btn cta-btn-primary"><i class="fas fa-envelope"></i> Get in Touch</a>
            <a href="services.php" class="cta-btn cta-btn-outline">Explore Services</a>
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
                setTimeout(() => entry.target.classList.add('visible'), i * 80);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.animate-on-scroll').forEach(el => observer.observe(el));

    // Active sub-nav highlight
    const sections = document.querySelectorAll('section[id]');
    const subLinks = document.querySelectorAll('.about-nav-link');
    function highlightNav() {
        let cur = '';
        sections.forEach(s => { if (window.scrollY >= s.offsetTop - 160) cur = s.id; });
        subLinks.forEach(l => {
            const active = l.getAttribute('href') === '#' + cur;
            l.style.borderBottomColor = active ? 'var(--gold)' : 'transparent';
            l.style.color = active ? 'var(--navy)' : '';
            l.style.background = active ? 'rgba(184,151,58,0.04)' : '';
        });
    }
    window.addEventListener('scroll', highlightNav);
    highlightNav();
</script>

</body>
</html>