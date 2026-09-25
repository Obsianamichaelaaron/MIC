<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include your config files
require_once __DIR__ . '/includes/config/database.php';
require_once __DIR__ . '/includes/config/session.php';

// Get database connection using YOUR function
$conn = getDBConnection();

// Fetch CMS data for the homepage
function getCMSContent($section_key, $field_key, $default = '') {
    global $conn;

    if (!$conn) {
        return $default;
    }

    try {
        $stmt = $conn->prepare("SELECT field_value FROM cms_content WHERE section_id = (SELECT id FROM cms_sections WHERE section_key = ?) AND field_key = ?");
        $stmt->bind_param("ss", $section_key, $field_key);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['field_value'];
        }
        return $default;
    } catch (Exception $e) {
        return $default;
    }
}

// Fetch hero slides
$hero_slides = [];
if ($conn) {
    try {
        $result = $conn->query("SELECT * FROM cms_hero_slides WHERE is_active = 1 ORDER BY sort_order ASC");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $hero_slides[] = $row;
            }
        }
    } catch (Exception $e) {
        $hero_slides = [];
    }
}

// If no hero slides in CMS, use defaults
if (empty($hero_slides)) {
    $hero_slides = [
        ['title' => 'Integrated Business<br><em>Solutions</em> That Scale', 'subtitle' => 'Connect with opportunities that match your skills. Partner with the Philippines\' leading managed services corporation.', 'button_text' => 'Browse Jobs', 'button_link' => 'careers.php', 'image_path' => 'images/picture1.png'],
        ['title' => 'Global <em>Partnerships</em>', 'subtitle' => 'Building bridges between talent and opportunity across the Philippines and beyond.', 'button_text' => 'Learn More', 'button_link' => 'about.php', 'image_path' => 'images/picture2.png'],
        ['title' => 'Digital <em>Transformation</em>', 'subtitle' => 'Empowering businesses with cutting-edge managed services and solutions.', 'button_text' => 'Our Services', 'button_link' => 'services.php', 'image_path' => 'images/picture3.png']
    ];
}

// Fetch stats
$stats_years = getCMSContent('stats', 'years', '22+');
$stats_clients = getCMSContent('stats', 'clients', '500+');
$stats_efficiency = getCMSContent('stats', 'efficiency', '95%');
$stats_units = getCMSContent('stats', 'units', '3K+');

// Fetch testimonials
$testimonials = [];
if ($conn) {
    try {
        $result = $conn->query("SELECT * FROM cms_testimonials WHERE is_active = 1 ORDER BY sort_order ASC LIMIT 5");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $testimonials[] = $row;
            }
        }
    } catch (Exception $e) {
        $testimonials = [];
    }
}

if (empty($testimonials)) {
    $testimonials = [
        ['author_name' => 'John Smith', 'author_role' => 'CEO', 'company' => 'Tech Solutions Inc.', 'content' => 'MULTIBIZ INTERNATIONAL CORPORATION transformed our document management system. Their managed print services have saved us time and reduced our costs significantly. The team is professional and always available when we need support.'],
        ['author_name' => 'Sarah Johnson', 'author_role' => 'CTO', 'company' => 'Global Enterprises', 'content' => 'The IT solutions provided by MULTIBIZ INTERNATIONAL CORPORATION have been exceptional. They helped us streamline our operations and implement systems that have increased our productivity by 40%. Highly recommended!'],
        ['author_name' => 'Michael Brown', 'author_role' => 'Operations Director', 'company' => 'Retail Corp', 'content' => 'We\'ve been working with MULTIBIZ INTERNATIONAL CORPORATION for over 10 years and their service has always been top-notch. Their team understands our business needs and provides solutions that help us grow.']
    ];
}

// Fetch news
$news_articles = [];
if ($conn) {
    try {
        $result = $conn->query("SELECT * FROM cms_news WHERE is_active = 1 ORDER BY news_date DESC LIMIT 6");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $news_articles[] = $row;
            }
        }
    } catch (Exception $e) {
        $news_articles = [];
    }
}

// ============================================================
// PARTNER BRANDS — load from DB, seed hardcoded defaults if empty
// ============================================================
$hardcoded_brands = [
    ['brand_name' => 'Lungsod ng Bacoor',       'brand_description' => 'Building progress together.',          'brand_overlay_title' => 'Lungsod ng Bacoor',       'brand_overlay_description' => 'Empowering local communities.',      'brand_logo' => 'images/b1.png',     'brand_category' => 'gov', 'sort_order' => 1],
    ['brand_name' => 'Lungsod ng Imus',          'brand_description' => 'Innovative city, empowered citizens.', 'brand_overlay_title' => 'Lungsod ng Imus',          'brand_overlay_description' => 'Advancing sustainable growth.',      'brand_logo' => 'images/b1.png',     'brand_category' => 'gov', 'sort_order' => 2],
    ['brand_name' => 'City of Santa Rosa',       'brand_description' => 'The Lion City of the South.',         'brand_overlay_title' => 'City of Santa Rosa',       'brand_overlay_description' => 'Driving innovation and excellence.', 'brand_logo' => 'images/b3.png',     'brand_category' => 'gov', 'sort_order' => 3],
    ['brand_name' => 'City of Trece Martires',   'brand_description' => 'Unity in progress.',                  'brand_overlay_title' => 'City of Trece Martires',   'brand_overlay_description' => 'Committed to public service.',       'brand_logo' => 'images/b4.png',     'brand_category' => 'gov', 'sort_order' => 4],
    ['brand_name' => 'City of General Trias',    'brand_description' => 'Championing development.',            'brand_overlay_title' => 'City of General Trias',    'brand_overlay_description' => 'Creating opportunities for all.',    'brand_logo' => 'images/b5.png',     'brand_category' => 'gov', 'sort_order' => 5],
    ['brand_name' => 'City of San Pedro',        'brand_description' => 'Gateway to Laguna.',                  'brand_overlay_title' => 'City of San Pedro',        'brand_overlay_description' => 'People-centered governance.',        'brand_logo' => 'images/b6.png',     'brand_category' => 'gov', 'sort_order' => 6],
    ['brand_name' => 'Lalawigan ng Laguna',      'brand_description' => 'Heart of Calabarzon.',                'brand_overlay_title' => 'Lalawigan ng Laguna',      'brand_overlay_description' => 'Promoting inclusive prosperity.',    'brand_logo' => 'images/b8.png',     'brand_category' => 'gov', 'sort_order' => 7],
    ['brand_name' => 'Lalawigan ng Batangas',    'brand_description' => 'Heart of Calabarzon.',                'brand_overlay_title' => 'Lalawigan ng Batangas',    'brand_overlay_description' => 'Promoting inclusive prosperity.',    'brand_logo' => 'images/bnine.png',  'brand_category' => 'gov', 'sort_order' => 8],
    ['brand_name' => 'Globe myBusiness',         'brand_description' => 'Empowering Filipino entrepreneurs.',  'brand_overlay_title' => 'Globe myBusiness',         'brand_overlay_description' => 'Connecting business, powering success.','brand_logo' => 'images/b11.png',  'brand_category' => 'cor', 'sort_order' => 9],
    ['brand_name' => 'Asia Brewery Inc.',        'brand_description' => 'Refreshing the nation.',              'brand_overlay_title' => 'Asia Brewery Inc.',        'brand_overlay_description' => 'Committed to quality and innovation.','brand_logo' => 'images/b12.jpg',  'brand_category' => 'cor', 'sort_order' => 10],
    ['brand_name' => 'AFFI Entrepreneurs',       'brand_description' => 'Fueling business dreams.',            'brand_overlay_title' => 'AFFI Entrepreneurs',       'brand_overlay_description' => 'Together, we grow stronger.',        'brand_logo' => 'images/b13.png',    'brand_category' => 'cor', 'sort_order' => 11],
    ['brand_name' => 'Metrobank',                'brand_description' => 'You\'re in good hands.',              'brand_overlay_title' => 'Metrobank',                'brand_overlay_description' => 'Meaningful banking for Filipinos.',  'brand_logo' => 'images/b14.png',    'brand_category' => 'cor', 'sort_order' => 12],
    ['brand_name' => 'CARD SME Bank',            'brand_description' => 'Financing your future.',              'brand_overlay_title' => 'CARD SME Bank',            'brand_overlay_description' => 'Helping small dreams grow big.',     'brand_logo' => 'images/b15.png',    'brand_category' => 'cor', 'sort_order' => 13],
    ['brand_name' => 'Mitsubishi Motors',        'brand_description' => 'Drive your ambition.',                'brand_overlay_title' => 'Mitsubishi Motors',        'brand_overlay_description' => 'Innovation in motion.',              'brand_logo' => 'images/b17.jpg',    'brand_category' => 'cor', 'sort_order' => 14],
    ['brand_name' => 'Hyundai',                  'brand_description' => 'Progress for humanity.',              'brand_overlay_title' => 'Hyundai',                  'brand_overlay_description' => 'New thinking, new possibilities.',   'brand_logo' => 'images/b18.jpg',    'brand_category' => 'cor', 'sort_order' => 15],
    ['brand_name' => 'CITIMOTORS INC.',          'brand_description' => 'Driven by trust.',                    'brand_overlay_title' => 'CITIMOTORS INC.',          'brand_overlay_description' => 'Your road to reliability.',          'brand_logo' => 'images/b1nine.jpg', 'brand_category' => 'cor', 'sort_order' => 16],
    ['brand_name' => 'PrimeWater',               'brand_description' => 'Clean water, better life.',           'brand_overlay_title' => 'PrimeWater',               'brand_overlay_description' => 'Sustaining communities nationwide.', 'brand_logo' => 'images/b20.png',    'brand_category' => 'cor', 'sort_order' => 17],
    ['brand_name' => 'IMI',                      'brand_description' => 'Engineering a smarter world.',        'brand_overlay_title' => 'IMI',                      'brand_overlay_description' => 'Innovating for the future.',         'brand_logo' => 'images/b21.jpg',    'brand_category' => 'cor', 'sort_order' => 18],
    ['brand_name' => 'Concentrix',               'brand_description' => 'Designing better human experiences.', 'brand_overlay_title' => 'Concentrix',               'brand_overlay_description' => 'People. Passion. Performance.',      'brand_logo' => 'images/b23.png',    'brand_category' => 'cor', 'sort_order' => 19],
    ['brand_name' => 'Convergys',                'brand_description' => 'Empowering people, powering business.','brand_overlay_title' => 'Convergys',              'brand_overlay_description' => 'Delivering customer excellence.',    'brand_logo' => 'images/b24.png',    'brand_category' => 'cor', 'sort_order' => 20],
    ['brand_name' => 'Jollibee',                 'brand_description' => 'Bida ang saya!',                      'brand_overlay_title' => 'Jollibee',                 'brand_overlay_description' => 'Bringing joy to every Filipino.',    'brand_logo' => 'images/b25.png',    'brand_category' => 'foo', 'sort_order' => 21],
    ['brand_name' => 'Days Hotel',               'brand_description' => 'Stay comfortable, stay inspired.',    'brand_overlay_title' => 'Days Hotel',               'brand_overlay_description' => 'Your home away from home.',          'brand_logo' => 'images/b26.jpg',    'brand_category' => 'foo', 'sort_order' => 22],
    ['brand_name' => 'Manila Ocean Park',        'brand_description' => 'Dive into discovery.',                'brand_overlay_title' => 'Manila Ocean Park',        'brand_overlay_description' => 'Where fun meets the ocean.',         'brand_logo' => 'images/b28.jpg',    'brand_category' => 'foo', 'sort_order' => 23],
    ['brand_name' => 'STI',                      'brand_description' => 'Education for real life.',             'brand_overlay_title' => 'STI',                      'brand_overlay_description' => 'Driven by technology and excellence.','brand_logo' => 'images/b2nine.jpg','brand_category' => 'edu', 'sort_order' => 24],
    ['brand_name' => 'Department of Agriculture','brand_description' => 'Masaganang ani, mataas na kita.',     'brand_overlay_title' => 'Department of Agriculture','brand_overlay_description' => 'Securing food for every Filipino.',  'brand_logo' => 'images/b30.png',    'brand_category' => 'gov', 'sort_order' => 25],
    ['brand_name' => 'DPWH',                     'brand_description' => 'Building better roads.',              'brand_overlay_title' => 'DPWH',                     'brand_overlay_description' => 'Connecting communities nationwide.', 'brand_logo' => 'images/b32.png',    'brand_category' => 'gov', 'sort_order' => 26],
    ['brand_name' => 'City of Dasmariñas',       'brand_description' => 'The university city.',                'brand_overlay_title' => 'City of Dasmariñas',       'brand_overlay_description' => 'Home of education and progress.',    'brand_logo' => 'images/b6.jpg',     'brand_category' => 'gov', 'sort_order' => 27],
];

$partner_brands = [];
if ($conn) {
    try {
        // Check if cms_brands table exists and has rows
        $check = $conn->query("SELECT COUNT(*) AS cnt FROM cms_brands");
        $row   = $check ? $check->fetch_assoc() : ['cnt' => 0];

        if ((int)$row['cnt'] === 0) {
            // ── Seed the hardcoded brands into the DB so CMS can manage them ──
            $insert = $conn->prepare(
                "INSERT INTO cms_brands
                 (brand_name, brand_description, brand_overlay_title, brand_overlay_description, brand_logo, brand_category, sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)"
            );
            foreach ($hardcoded_brands as $b) {
                $insert->bind_param(
                    "ssssssi",
                    $b['brand_name'], $b['brand_description'],
                    $b['brand_overlay_title'], $b['brand_overlay_description'],
                    $b['brand_logo'], $b['brand_category'], $b['sort_order']
                );
                $insert->execute();
            }
        }

        // Now read from the DB (always)
        $result = $conn->query("SELECT * FROM cms_brands WHERE is_active = 1 ORDER BY sort_order ASC");
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $partner_brands[] = $row;
            }
        }
    } catch (Exception $e) {
        // If anything fails (e.g. table doesn't exist yet), fall back to hardcoded array
        $partner_brands = $hardcoded_brands;
    }
}
// If still empty for any reason, use hardcoded as display fallback
if (empty($partner_brands)) {
    $partner_brands = $hardcoded_brands;
}

// Get section titles
$partners_title       = getCMSContent('partners_title',       'title', 'Our Partner Brands');
$testimonials_title   = getCMSContent('testimonials_title',   'title', 'What Our Clients Say');
$featured_jobs_title  = getCMSContent('featured_jobs_title',  'title', 'Featured Job Openings');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title>MULTIBIZ INTERNATIONAL CORPORATION</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="author" content="" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
    <meta name="keywords" content="MULTIBIZ INTERNATIONAL CORPORATION" />
    <meta name="description" content="MULTIBIZ INTERNATIONAL CORPORATION - Your Career Matching Platform">

    <link rel="shortcut icon" href="2024/favicon.png" type="image/x-icon" />
    <link rel="apple-touch-icon" href="2024/favicon.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="js/script.js"></script>
    <script src="js/landingPage.js"></script>

    <style>
        /* ============================================================
           DESIGN SYSTEM — LUXURY CORPORATE
        ============================================================ */
        :root {
            --navy:       #0a1628;
            --navy-mid:   #0f2040;
            --blue:       #1a4fa0;
            --blue-light: #2563c8;
            --gold:       #b8973a;
            --gold-light: #d4af55;
            --cream:      #f7f5f0;
            --warm-white: #fafaf8;
            --gray-100:   #f0eff0;
            --gray-300:   #d1cfd4;
            --gray-500:   #8a8691;
            --gray-700:   #4a4752;
            --dark:       #18151f;

            --font-display: 'Playfair Display', Georgia, serif;
            --font-body:    'DM Sans', -apple-system, sans-serif;

            --shadow-sm: 0 2px 8px rgba(10,22,40,0.06);
            --shadow-md: 0 8px 32px rgba(10,22,40,0.10);
            --shadow-lg: 0 20px 60px rgba(10,22,40,0.16);
            --shadow-xl: 0 32px 80px rgba(10,22,40,0.22);

            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
            --ease-in-out: cubic-bezier(0.65, 0, 0.35, 1);

            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-xl: 32px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; overflow-x: hidden; }
        body {
            font-family: var(--font-body);
            color: var(--dark);
            background: var(--warm-white);
            overflow-x: hidden;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ============================================================
           HEADER / NAV
        ============================================================ */
        header {
            position: fixed;
            top: 0; left: 0; width: 100%;
            z-index: 1000;
            transition: all 0.4s var(--ease-out);
        }

        .header-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 clamp(1.5rem, 5%, 4rem);
            height: 76px;
            background: rgba(10, 22, 40, 0.96);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(184, 151, 58, 0.15);
        }

        .logo { display: flex; align-items: center; text-decoration: none; }
        .logo img { height: 38px; filter: brightness(1.05); }

        .nav-toggle { display: none; background: none; border: none; color: white; font-size: 1.4rem; cursor: pointer; padding: 8px; }

        .nav-menu { display: flex; align-items: center; }

        .nav-list {
            display: flex;
            list-style: none;
            align-items: center;
            gap: 0.25rem;
        }

        .nav-item { position: relative; }

        .nav-link {
            text-decoration: none;
            color: rgba(255,255,255,0.78);
            font-family: var(--font-body);
            font-weight: 500;
            font-size: 0.875rem;
            letter-spacing: 0.04em;
            padding: 0.55rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
            border-radius: var(--radius-sm);
            transition: all 0.25s ease;
            white-space: nowrap;
        }
        .nav-link i { font-size: 0.7rem; opacity: 0.6; transition: transform 0.3s ease; }
        .nav-item:hover .nav-link,
        .nav-link.active { color: white; background: rgba(255,255,255,0.08); }
        .nav-item:hover .nav-link i { transform: rotate(180deg); }
        .nav-link.active { color: var(--gold-light); }

        .dropdown {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            min-width: 220px;
            background: var(--navy);
            border: 1px solid rgba(184,151,58,0.2);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-xl);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-8px);
            transition: all 0.25s var(--ease-out);
            z-index: 200;
            overflow: hidden;
        }
        .nav-item:hover .dropdown { opacity: 1; visibility: visible; transform: translateY(0); }
        .dropdown-item { border-bottom: 1px solid rgba(255,255,255,0.06); }
        .dropdown-item:last-child { border-bottom: none; }
        .dropdown-link {
            display: block;
            padding: 0.75rem 1.25rem;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            font-size: 0.84rem;
            font-weight: 400;
            letter-spacing: 0.02em;
            transition: all 0.2s ease;
        }
        .dropdown-link:hover {
            color: var(--gold-light);
            background: rgba(184,151,58,0.08);
            padding-left: 1.5rem;
        }

        .login-register-btn {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%) !important;
            color: var(--navy) !important;
            font-weight: 600 !important;
            padding: 0.55rem 1.4rem !important;
            border-radius: 100px !important;
            letter-spacing: 0.05em;
            font-size: 0.82rem !important;
            box-shadow: 0 4px 16px rgba(184,151,58,0.35);
            transition: all 0.3s ease !important;
        }
        .login-register-btn:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 6px 24px rgba(184,151,58,0.45) !important;
            background: rgba(255,255,255,0.08) !important;
        }

        /* ============================================================
           HERO
        ============================================================ */
        .hero {
            position: relative;
            height: 100vh;
            min-height: 640px;
            max-height: 900px;
            overflow: hidden;
        }

        .hero-background { position: absolute; inset: 0; }
        .hero-image {
            position: absolute; inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.2s ease;
        }
        .hero-image.active { opacity: 1; }

        .hero-overlay {
            position: absolute; inset: 0;
            background: linear-gradient(
                135deg,
                rgba(10,22,40,0.88) 0%,
                rgba(10,22,40,0.55) 50%,
                rgba(10,22,40,0.35) 100%
            );
            z-index: 1;
        }

        .hero-overlay::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0;
            width: 100%; height: 140px;
            background: linear-gradient(to top, var(--warm-white), transparent);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0 clamp(1.5rem, 8%, 8rem);
            padding-top: 76px;
            max-width: 900px;
        }

        .hero-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--gold-light);
            margin-bottom: 1.5rem;
            animation: fadeUp 0.8s var(--ease-out) 0.2s both;
        }
        .hero-eyebrow::before {
            content: '';
            width: 40px; height: 1px;
            background: var(--gold-light);
        }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(2.6rem, 6vw, 5rem);
            font-weight: 600;
            color: white;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            animation: fadeUp 0.8s var(--ease-out) 0.35s both;
        }
        .hero-title em { font-style: italic; color: var(--gold-light); }

        .hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.2rem);
            color: rgba(255,255,255,0.72);
            font-weight: 300;
            max-width: 520px;
            line-height: 1.7;
            margin-bottom: 2.5rem;
            animation: fadeUp 0.8s var(--ease-out) 0.5s both;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            animation: fadeUp 0.8s var(--ease-out) 0.65s both;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.85rem 2rem;
            border-radius: 100px;
            font-family: var(--font-body);
            font-weight: 600;
            font-size: 0.88rem;
            letter-spacing: 0.05em;
            text-decoration: none;
            transition: all 0.3s var(--ease-out);
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
            color: var(--navy);
            box-shadow: 0 8px 24px rgba(184,151,58,0.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 12px 32px rgba(184,151,58,0.55); }
        .btn-outline {
            background: transparent;
            color: white;
            border: 1.5px solid rgba(255,255,255,0.45);
            backdrop-filter: blur(8px);
        }
        .btn-outline:hover { background: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.7); transform: translateY(-2px); }

        .hero-nav {
            position: absolute;
            z-index: 3;
            top: 50%;
            transform: translateY(-50%);
            width: 48px; height: 48px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 50%;
            color: white;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.9rem;
            transition: all 0.25s ease;
            backdrop-filter: blur(8px);
        }
        .hero-nav:hover { background: rgba(255,255,255,0.2); }
        .hero-nav.prev { left: 2rem; }
        .hero-nav.next { right: 2rem; }

        .hero-indicators {
            position: absolute;
            bottom: 5rem;
            left: clamp(1.5rem, 8%, 8rem);
            z-index: 3;
            display: flex;
            gap: 0.5rem;
        }
        .hero-indicator {
            width: 32px; height: 2px;
            background: rgba(255,255,255,0.3);
            border: none; cursor: pointer;
            transition: all 0.3s ease;
            border-radius: 2px;
        }
        .hero-indicator.active { background: var(--gold-light); width: 56px; }

        /* ============================================================
           SECTION COMMON
        ============================================================ */
        .section {
            padding: clamp(5rem, 10vw, 9rem) clamp(1.5rem, 5%, 4rem);
        }

        .section-label {
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--blue);
            margin-bottom: 1rem;
        }
        .section-label::before { content: ''; width: 28px; height: 2px; background: var(--gold); }

        .section-title {
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 3.2rem);
            font-weight: 600;
            color: var(--navy);
            line-height: 1.15;
            margin-bottom: 1.25rem;
        }

        .section-subtitle {
            font-size: 1rem;
            color: var(--gray-500);
            font-weight: 300;
            line-height: 1.8;
            max-width: 560px;
            margin-bottom: 3rem;
        }

        /* ============================================================
           STATS BAR
        ============================================================ */
        .stats-bar {
            background: var(--navy);
            padding: 2.5rem clamp(1.5rem, 5%, 4rem);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 2rem;
            border-bottom: 1px solid rgba(184,151,58,0.2);
        }

        .stat-item { text-align: center; position: relative; }
        .stat-item:not(:last-child)::after {
            content: '';
            position: absolute;
            right: -1rem; top: 50%;
            transform: translateY(-50%);
            height: 40px; width: 1px;
            background: rgba(255,255,255,0.1);
        }

        .stat-number { font-family: var(--font-display); font-size: 2.4rem; font-weight: 600; color: var(--gold-light); line-height: 1; display: block; }
        .stat-label { font-size: 0.78rem; color: rgba(255,255,255,0.5); letter-spacing: 0.1em; text-transform: uppercase; font-weight: 500; margin-top: 0.4rem; }

        /* ============================================================
           PARTNER BRANDS
        ============================================================ */
        .brands-section { background: var(--cream); }

        .brands-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 2rem;
            flex-wrap: wrap;
            margin-bottom: 2.5rem;
        }

        .brands-filter { display: flex; gap: 0.5rem; flex-wrap: wrap; }

        .filter-btn {
            padding: 0.55rem 1.2rem;
            border: 1.5px solid var(--gray-300);
            background: white;
            border-radius: 100px;
            font-family: var(--font-body);
            font-size: 0.82rem;
            font-weight: 500;
            color: var(--gray-700);
            cursor: pointer;
            transition: all 0.25s ease;
            letter-spacing: 0.02em;
        }
        .filter-btn:hover, .filter-btn.active {
            background: var(--navy);
            border-color: var(--navy);
            color: white;
            box-shadow: 0 4px 14px rgba(10,22,40,0.18);
        }

        .brands-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 1rem;
        }

        .brand-card {
            background: white;
            border: 1px solid var(--gray-100);
            border-radius: var(--radius-md);
            padding: 1.75rem 1.25rem;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: all 0.35s var(--ease-out);
            text-align: center;
        }
        .brand-card::before {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            opacity: 0;
            transition: opacity 0.35s ease;
        }
        .brand-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); border-color: transparent; }
        .brand-card:hover::before { opacity: 1; }
        .brand-card:hover .brand-content h3,
        .brand-card:hover .brand-content p { color: rgba(255,255,255,0.9); }
        .brand-card:hover .brand-logo img { filter: brightness(10); }

        .brand-logo { position: relative; margin-bottom: 0.9rem; display: flex; align-items: center; justify-content: center; }
        .brand-logo img { height: 56px; object-fit: contain; transition: filter 0.35s ease; }

        .brand-content { position: relative; }
        .brand-content h3 { font-size: 0.8rem; font-weight: 600; color: var(--navy); margin-bottom: 0.25rem; transition: color 0.35s ease; line-height: 1.3; }
        .brand-content p { font-size: 0.72rem; color: var(--gray-500); transition: color 0.35s ease; }

        /* ============================================================
           MODAL
        ============================================================ */
        .brand-modal {
            display: none;
            position: fixed; inset: 0;
            background: rgba(10,22,40,0.75);
            backdrop-filter: blur(8px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        .brand-modal.active { display: flex; }
        .modal-content { background: white; border-radius: var(--radius-xl); overflow: hidden; width: min(560px, 92vw); box-shadow: var(--shadow-xl); }
        .modal-header { background: var(--navy); padding: 1.75rem 2rem; display: flex; align-items: center; justify-content: space-between; }
        .modal-header h2 { color: white; font-family: var(--font-display); font-size: 1.4rem; }
        .modal-close { background: none; border: none; color: rgba(255,255,255,0.5); font-size: 1.5rem; cursor: pointer; line-height: 1; transition: color 0.2s; }
        .modal-close:hover { color: white; }
        .modal-body { padding: 2rem; }

        /* ============================================================
           TESTIMONIALS
        ============================================================ */
        .testimonials {
            background: linear-gradient(160deg, var(--navy) 0%, var(--navy-mid) 100%);
            position: relative; overflow: hidden;
        }
        .testimonials::before {
            content: '"';
            position: absolute; top: -2rem; left: 3rem;
            font-family: var(--font-display); font-size: 20rem;
            color: rgba(255,255,255,0.025); line-height: 1; pointer-events: none;
        }

        .testimonials .section-label { color: var(--gold-light); }
        .testimonials .section-label::before { background: var(--gold-light); }
        .testimonials .section-title { color: white; }

        .testimonial-slider { position: relative; overflow: hidden; }
        .testimonial-item { display: none; padding: 3rem; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: var(--radius-xl); position: relative; }
        .testimonial-item.active { display: block; animation: fadeIn 0.5s ease; }
        .testimonial-item::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; background: linear-gradient(to bottom, var(--gold), var(--gold-light)); border-radius: 4px 0 0 4px; }

        .testimonial-text { font-family: var(--font-display); font-size: clamp(1.05rem, 2vw, 1.3rem); color: rgba(255,255,255,0.88); line-height: 1.7; font-style: italic; margin-bottom: 2rem; }
        .testimonial-author { font-weight: 700; font-size: 1rem; color: var(--gold-light); letter-spacing: 0.05em; }
        .testimonial-role { font-size: 0.84rem; color: rgba(255,255,255,0.45); margin-top: 0.25rem; }

        .testimonial-controls { display: flex; gap: 0.5rem; margin-top: 2rem; }
        .testimonial-dot { width: 32px; height: 3px; background: rgba(255,255,255,0.2); border-radius: 3px; cursor: pointer; transition: all 0.3s ease; border: none; }
        .testimonial-dot.active { background: var(--gold-light); width: 56px; }

        /* ============================================================
           CONTACT
        ============================================================ */
        .contact-section { background: var(--warm-white); }

        .contact-grid { display: grid; grid-template-columns: 1fr 1.4fr; gap: 5rem; align-items: start; max-width: 1100px; margin: 0 auto; }

        .contact-info-items { margin-top: 2rem; display: flex; flex-direction: column; gap: 1.25rem; }
        .contact-info-item { display: flex; align-items: center; gap: 1rem; }
        .contact-info-icon { width: 44px; height: 44px; background: var(--navy); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--gold-light); font-size: 0.9rem; flex-shrink: 0; }
        .contact-info-text { font-size: 0.9rem; color: var(--gray-700); }

        .contact-form { background: white; border: 1px solid var(--gray-100); border-radius: var(--radius-xl); padding: 2.75rem; box-shadow: var(--shadow-md); }

        .form-group { margin-bottom: 1.4rem; }
        .form-label { display: block; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--gray-700); margin-bottom: 0.5rem; }
        .form-input, .form-textarea { width: 100%; padding: 0.85rem 1.1rem; border: 1.5px solid var(--gray-300); border-radius: var(--radius-sm); font-family: var(--font-body); font-size: 0.95rem; color: var(--dark); background: var(--warm-white); transition: all 0.25s ease; outline: none; }
        .form-input:focus, .form-textarea:focus { border-color: var(--blue); background: white; box-shadow: 0 0 0 3px rgba(26,79,160,0.08); }
        .form-textarea { resize: vertical; min-height: 130px; }

        .form-submit { width: 100%; padding: 1rem; background: linear-gradient(135deg, var(--navy) 0%, var(--blue) 100%); color: white; border: none; border-radius: 100px; font-family: var(--font-body); font-size: 0.92rem; font-weight: 600; letter-spacing: 0.06em; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 6px 20px rgba(10,22,40,0.25); }
        .form-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 28px rgba(10,22,40,0.35); }

        /* ============================================================
           NEWS
        ============================================================ */
        .news-section { background: var(--cream); }

        .news-container { position: relative; overflow: hidden; margin-bottom: 2.5rem; }
        .news-track { display: flex; gap: 1.5rem; transition: transform 0.5s var(--ease-out); }

        .news-card { min-width: 320px; background: white; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-100); transition: all 0.35s var(--ease-out); }
        .news-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-lg); }

        .news-image { position: relative; overflow: hidden; }
        .news-image img { width: 100%; height: 200px; object-fit: cover; transition: transform 0.5s ease; display: block; }
        .news-card:hover .news-image img { transform: scale(1.05); }

        .news-date { position: absolute; bottom: -1px; left: 1.25rem; background: var(--navy); color: white; padding: 0.4rem 0.75rem; border-radius: var(--radius-sm) var(--radius-sm) 0 0; text-align: center; }
        .date-day { display: block; font-family: var(--font-display); font-size: 1.3rem; font-weight: 700; line-height: 1; }
        .date-month { display: block; font-size: 0.65rem; letter-spacing: 0.1em; text-transform: uppercase; opacity: 0.8; }

        .news-content { padding: 1.5rem; }
        .news-category { display: inline-block; background: rgba(26,79,160,0.08); color: var(--blue); font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; padding: 0.25rem 0.6rem; border-radius: 4px; margin-bottom: 0.75rem; }
        .news-title { font-weight: 600; font-size: 0.95rem; color: var(--navy); line-height: 1.5; margin-bottom: 0.75rem; }
        .news-excerpt { font-size: 0.84rem; color: var(--gray-500); line-height: 1.6; margin-bottom: 1rem; }
        .news-link { display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.82rem; font-weight: 600; color: var(--blue); text-decoration: none; letter-spacing: 0.03em; transition: gap 0.25s ease; }
        .news-link:hover { gap: 0.65rem; }

        .news-controls { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
        .news-nav { width: 44px; height: 44px; border-radius: 50%; background: var(--navy); color: white; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; transition: all 0.25s ease; box-shadow: var(--shadow-sm); }
        .news-nav:hover { background: var(--blue); transform: scale(1.05); }

        .news-view-all { text-align: center; margin-top: 3rem; }

        /* ============================================================
           FEATURED JOBS
        ============================================================ */
        .featured-jobs-section { background: var(--warm-white); }

        .jobs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }

        .job-card { background: white; border: 1px solid var(--gray-100); border-radius: var(--radius-lg); padding: 2rem; transition: all 0.35s var(--ease-out); position: relative; overflow: hidden; }
        .job-card::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--blue), var(--gold)); transform: scaleX(0); transform-origin: left; transition: transform 0.4s var(--ease-out); }
        .job-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-lg); border-color: transparent; }
        .job-card:hover::after { transform: scaleX(1); }

        .job-header { margin-bottom: 1.25rem; }
        .job-title { font-family: var(--font-display); font-size: 1.2rem; font-weight: 600; color: var(--navy); margin-bottom: 0.4rem; }
        .job-company { font-size: 0.85rem; color: var(--gray-500); display: flex; align-items: center; gap: 0.4rem; }
        .job-company i { color: var(--blue); }

        .job-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.25rem; }
        .job-tag { background: var(--cream); color: var(--gray-700); padding: 0.35rem 0.8rem; border-radius: 100px; font-size: 0.78rem; font-weight: 500; display: flex; align-items: center; gap: 0.35rem; }
        .job-tag i { color: var(--blue); font-size: 0.7rem; }

        .job-salary { font-weight: 700; color: var(--navy); font-size: 0.95rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.4rem; }
        .job-salary i { color: var(--gold); }

        .job-actions { display: flex; align-items: center; justify-content: space-between; padding-top: 1.25rem; border-top: 1px solid var(--gray-100); }
        .job-date { font-size: 0.78rem; color: var(--gray-500); display: flex; align-items: center; gap: 0.35rem; }
        .job-buttons { display: flex; gap: 0.5rem; }

        .btn-outline-sm { padding: 0.5rem 1rem; font-size: 0.78rem; font-weight: 600; border-radius: 100px; text-decoration: none; border: 1.5px solid var(--navy); color: var(--navy); transition: all 0.25s ease; }
        .btn-outline-sm:hover { background: var(--navy); color: white; }

        .btn-success { padding: 0.5rem 1rem; font-size: 0.78rem; font-weight: 600; border-radius: 100px; text-decoration: none; background: linear-gradient(135deg, #1a7a4a, #22a060); color: white; transition: all 0.25s ease; }
        .btn-success:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(26,122,74,0.35); }

        /* ============================================================
           FOOTER
        ============================================================ */
        footer { background: var(--dark); color: white; }
        .compact-footer { padding: 0; }

        .footer-top { padding: clamp(3rem, 8vw, 6rem) clamp(1.5rem, 5%, 4rem); }

        .footer-grid { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; gap: 3rem; max-width: 1200px; margin: 0 auto; }

        .footer-col h3 { font-size: 0.78rem; font-weight: 700; letter-spacing: 0.15em; text-transform: uppercase; color: var(--gold-light); margin-bottom: 1.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.08); }
        .footer-col p { font-size: 0.88rem; color: rgba(255,255,255,0.5); line-height: 1.8; margin-bottom: 1.5rem; }

        .footer-social { display: flex; gap: 0.6rem; }
        .social-icon { width: 38px; height: 38px; border-radius: 50%; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.9rem; transition: all 0.25s ease; }
        .social-icon:hover { background: var(--gold); border-color: var(--gold); color: var(--navy); transform: translateY(-2px); }

        .footer-links { list-style: none; }
        .footer-links li { margin-bottom: 0.75rem; }
        .footer-links a { color: rgba(255,255,255,0.5); text-decoration: none; font-size: 0.88rem; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 0.4rem; }
        .footer-links a:hover { color: white; padding-left: 4px; }

        .footer-bottom { padding: 1.5rem clamp(1.5rem, 5%, 4rem); border-top: 1px solid rgba(255,255,255,0.06); text-align: center; }
        .footer-bottom p { font-size: 0.8rem; color: rgba(255,255,255,0.3); }

        /* ============================================================
           ANIMATIONS
        ============================================================ */
        @keyframes fadeUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeIn  { from { opacity: 0; } to { opacity: 1; } }

        .animate-on-scroll { opacity: 0; transform: translateY(20px); transition: all 0.7s var(--ease-out); }
        .animate-on-scroll.visible { opacity: 1; transform: translateY(0); }
        /* ============================================================
   REDESIGNED PARTNER BRANDS SECTION - CLEAN & AESTHETIC
   ============================================================ */
.brands-section {
    background: linear-gradient(135deg, #ffffff 0%, #faf9f7 100%);
    position: relative;
    overflow: hidden;
    padding: clamp(4rem, 8vw, 6rem) clamp(1.5rem, 5%, 4rem);
}

.brands-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold-light), transparent);
}

/* Section Header */
.brands-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 2rem;
    flex-wrap: wrap;
    margin-bottom: 3rem;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

.brands-header-left {
    flex: 1;
}

.brands-header-left .section-label {
    margin-bottom: 0.75rem;
}

.brands-header-left .section-title {
    margin-bottom: 0.5rem;
    font-size: clamp(1.8rem, 4vw, 2.8rem);
}

.brands-subtitle {
    font-size: 0.9rem;
    color: var(--gray-500);
    margin-top: 0.5rem;
    max-width: 450px;
    line-height: 1.6;
}

/* Filter Buttons - Pill Design */
.brands-filter {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    background: white;
    padding: 0.5rem;
    border-radius: 60px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02);
}

.filter-btn {
    padding: 0.6rem 1.4rem;
    border: none;
    background: transparent;
    border-radius: 40px;
    font-family: var(--font-body);
    font-size: 0.85rem;
    font-weight: 500;
    color: #4a4752;
    cursor: pointer;
    transition: all 0.25s ease;
    letter-spacing: 0.01em;
}

.filter-btn:hover {
    color: var(--navy);
    background: rgba(10, 22, 40, 0.05);
}

.filter-btn.active {
    background: var(--navy);
    color: white;
    box-shadow: 0 4px 12px rgba(10, 22, 40, 0.15);
}

/* Brands Grid */
.brands-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 0.5rem;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
}

/* Brand Card - Modern Clean Design */
.brand-card {
    background: white;
    border-radius: 24px;
    padding: 1.75rem 1.25rem;
    cursor: pointer;
    position: relative;
    transition: all 0.35s cubic-bezier(0.2, 0, 0, 1);
    border: 1px solid rgba(0, 0, 0, 0.04);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
    text-align: center;
    overflow: hidden;
}

/* Top accent bar on hover */
.brand-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, var(--gold), var(--gold-light));
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.35s ease;
}

.brand-card:hover {
    transform: translateY(-6px);
    border-color: rgba(184, 151, 58, 0.15);
    box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.05);
}

.brand-card:hover::before {
    transform: scaleX(1);
}

/* Logo Wrapper */
.brand-logo-wrapper {
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.25rem;
    position: relative;
}

.brand-logo-wrapper img {
    max-height: 60px;
    max-width: 120px;
    object-fit: contain;
    transition: all 0.3s ease;
    filter: grayscale(0%) brightness(1);
}

.brand-card:hover .brand-logo-wrapper img {
    transform: scale(1.03);
}

/* Placeholder when no logo */
.brand-logo-placeholder {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #f5f3f0, #efede8);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--gray-500);
    font-size: 0.75rem;
    font-weight: 500;
}

/* Brand Text Content */
.brand-name {
    font-size: 1rem;
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 0.5rem;
    letter-spacing: -0.2px;
    transition: color 0.25s ease;
    line-height: 1.3;
}

.brand-card:hover .brand-name {
    color: var(--gold);
}

.brand-description {
    font-size: 0.75rem;
    color: var(--gray-500);
    line-height: 1.5;
    margin-bottom: 0;
    transition: color 0.25s ease;
}

/* Hint text that appears on hover */
.brand-hint {
    opacity: 0;
    transform: translateY(5px);
    transition: all 0.25s ease;
    font-size: 0.7rem;
    color: var(--gold);
    margin-top: 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-weight: 500;
}

.brand-card:hover .brand-hint {
    opacity: 1;
    transform: translateY(0);
}

/* ============================================================
   MODAL - CLEAN REDESIGN
   ============================================================ */
.brand-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 22, 40, 0.8);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    z-index: 2000;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.brand-modal.active {
    display: flex;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        backdrop-filter: blur(0);
    }
    to {
        opacity: 1;
        backdrop-filter: blur(12px);
    }
}

.modal-content {
    background: white;
    border-radius: 32px;
    max-width: 520px;
    width: 100%;
    overflow: hidden;
    box-shadow: 0 40px 70px rgba(0, 0, 0, 0.3);
    animation: modalSlideUp 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
}

@keyframes modalSlideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    background: linear-gradient(135deg, var(--navy) 0%, var(--navy-mid) 100%);
    padding: 1.75rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modal-header h2 {
    color: white;
    font-family: var(--font-display);
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.3px;
    margin: 0;
}

.modal-close {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: rgba(255, 255, 255, 0.7);
    font-size: 1.4rem;
    cursor: pointer;
    line-height: 1;
    transition: all 0.2s ease;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: rotate(90deg);
}

.modal-body {
    padding: 2rem;
}

.modal-body p {
    color: #2d2a36;
    font-size: 1rem;
    line-height: 1.7;
    margin-bottom: 1.25rem;
}

.modal-desc {
    border-top: 1px solid #eeedf0;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    font-size: 0.9rem;
    color: #5a5663;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}

.modal-desc i {
    color: var(--gold);
    font-size: 1rem;
    margin-top: 0.1rem;
}

.modal-desc span {
    flex: 1;
}

/* Empty state */
.brands-empty {
    grid-column: 1 / -1;
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 24px;
    color: var(--gray-500);
}

.brands-empty i {
    font-size: 3rem;
    color: var(--gray-300);
    margin-bottom: 1rem;
    display: block;
}

/* Responsive Adjustments */
@media (max-width: 1024px) {
    .brands-grid {
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1.25rem;
    }
}

@media (max-width: 768px) {
    .brands-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1.5rem;
    }
    
    .brands-filter {
        background: transparent;
        padding: 0;
        box-shadow: none;
        gap: 0.5rem;
    }
    
    .filter-btn {
        background: white;
        border: 1px solid #e2e0e6;
        padding: 0.5rem 1.2rem;
        font-size: 0.8rem;
    }
    
    .filter-btn.active {
        background: var(--navy);
        border-color: var(--navy);
    }
    
    .brands-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 1rem;
    }
    
    .brand-card {
        padding: 1.25rem 0.75rem;
    }
    
    .brand-logo-wrapper {
        height: 65px;
    }
    
    .brand-logo-wrapper img {
        max-height: 48px;
    }
    
    .modal-header {
        padding: 1.25rem 1.5rem;
    }
    
    .modal-header h2 {
        font-size: 1.25rem;
    }
    
    .modal-body {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .brands-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.875rem;
    }
    
    .brand-card {
        padding: 1rem 0.5rem;
    }
    
    .brand-name {
        font-size: 0.85rem;
    }
    
    .brand-description {
        font-size: 0.7rem;
    }
    
    .brand-logo-wrapper {
        height: 55px;
    }
    
    .brand-logo-wrapper img {
        max-height: 40px;
    }
}

/* Animation for cards appearing */
@keyframes cardAppear {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.brand-card {
    animation: cardAppear 0.5s ease backwards;
}

.brand-card:nth-child(1) { animation-delay: 0.02s; }
.brand-card:nth-child(2) { animation-delay: 0.04s; }
.brand-card:nth-child(3) { animation-delay: 0.06s; }
.brand-card:nth-child(4) { animation-delay: 0.08s; }
.brand-card:nth-child(5) { animation-delay: 0.10s; }
.brand-card:nth-child(6) { animation-delay: 0.12s; }
.brand-card:nth-child(7) { animation-delay: 0.14s; }
.brand-card:nth-child(8) { animation-delay: 0.16s; }
.brand-card:nth-child(9) { animation-delay: 0.18s; }
.brand-card:nth-child(10) { animation-delay: 0.20s; }
.brand-card:nth-child(11) { animation-delay: 0.22s; }
.brand-card:nth-child(12) { animation-delay: 0.24s; }

        /* ============================================================
           RESPONSIVE
        ============================================================ */
        @media (max-width: 1024px) {
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .contact-grid { grid-template-columns: 1fr; gap: 3rem; }
        }
        @media (max-width: 768px) {
            .nav-toggle { display: block; }
            .nav-menu { position: fixed; top: 76px; left: -100%; width: 100%; height: calc(100vh - 76px); background: var(--navy); flex-direction: column; align-items: stretch; padding: 2rem 1.5rem; transition: left 0.4s var(--ease-out); overflow-y: auto; }
            .nav-menu.active { left: 0; }
            .nav-list { flex-direction: column; gap: 0; }
            .nav-item { width: 100%; }
            .nav-link { padding: 0.75rem 1rem; font-size: 1rem; border-radius: var(--radius-sm); }
            .dropdown { position: static; opacity: 1; visibility: visible; transform: none; box-shadow: none; border: none; background: rgba(255,255,255,0.05); border-radius: var(--radius-sm); margin-top: 0.25rem; display: none; }
            .nav-item.active .dropdown { display: block; }
            .stats-bar { grid-template-columns: repeat(2, 1fr); }
            .stat-item:nth-child(2)::after { display: none; }
            .brands-header { flex-direction: column; align-items: flex-start; }
            .brands-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .footer-grid { grid-template-columns: 1fr; gap: 2rem; }
            .hero-nav { display: none; }
            .contact-form { padding: 1.75rem; }
        }
        @media (max-width: 480px) {
            .hero-title { font-size: 2.2rem; }
            .brands-grid { grid-template-columns: repeat(2, 1fr); }
            .jobs-grid { grid-template-columns: 1fr; }
            .news-card { min-width: 280px; }
        }

        .gold-divider { width: 64px; height: 3px; background: linear-gradient(90deg, var(--gold), var(--gold-light)); border-radius: 3px; margin: 0 auto 2rem; }
        .section-center { text-align: center; }
        .section-center .section-label { justify-content: center; }
        .section-center .section-subtitle { margin-left: auto; margin-right: auto; }
    </style>
</head>

<body>
    <!-- ===================== HEADER ===================== -->
    <header>
        <div class="header-container">
            <div class="logo">
                <a href="index.php">
                    <img src="images/mbLogo.png" alt="MULTIBIZ INTERNATIONAL CORPORATION">
                </a>
            </div>
            <button class="nav-toggle" id="navToggle"><i class="fas fa-bars"></i></button>
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
                            <div class="dropdown-item"><a href="services.php" class="dropdown-link">Managed Print</a></div>
                            <div class="dropdown-item"><a href="services.php#managedit" class="dropdown-link">Managed I.T.</a></div>
                            <div class="dropdown-item"><a href="services.php#managedpc" class="dropdown-link">Managed PC</a></div>
                            <div class="dropdown-item"><a href="services.php#managedhris" class="dropdown-link">Managed HRIS</a></div>
                            <div class="dropdown-item"><a href="services.php#managedsap" class="dropdown-link">Managed SAP</a></div>
                        </div>
                    </li>
                    <li class="nav-item"><a href="careers.php" class="nav-link">Careers</a></li>
                    <li class="nav-item"><a href="loginregister.php" class="nav-link login-register-btn">Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- ===================== HERO ===================== -->
    <section class="hero" style="min-height: 400px;">
        <div class="hero-background" id="heroBackground">
            <?php foreach ($hero_slides as $index => $slide): ?>
                <div class="hero-image <?php echo $index === 0 ? 'active' : ''; ?>"
                     style="background-image: url('<?php echo isset($slide['image_path']) && $slide['image_path'] ? $slide['image_path'] : 'images/picture' . ($index + 1) . '.png'; ?>')"></div>
            <?php endforeach; ?>
        </div>
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <span class="hero-eyebrow">Trusted Since 2002</span>
            <h1 class="hero-title">
                <?php echo isset($hero_slides[0]['title']) ? $hero_slides[0]['title'] : 'Integrated Business<br><em>Solutions</em> That Scale'; ?>
            </h1>
            <p class="hero-subtitle"><?php echo isset($hero_slides[0]['subtitle']) ? $hero_slides[0]['subtitle'] : 'Connect with opportunities that match your skills. Partner with the Philippines\' leading managed services corporation.'; ?></p>
            <div class="hero-buttons">
                <a href="<?php echo isset($hero_slides[0]['button_link']) ? $hero_slides[0]['button_link'] : 'careers.php'; ?>" class="btn btn-primary">
                    <i class="fas fa-briefcase"></i> <?php echo isset($hero_slides[0]['button_text']) ? $hero_slides[0]['button_text'] : 'Browse Jobs'; ?>
                </a>
                <a href="services.php" class="btn btn-outline">Our Services <i class="fas fa-arrow-right"></i></a>
            </div>
        </div>
        <div class="hero-indicators">
            <?php foreach ($hero_slides as $index => $slide): ?>
                <button class="hero-indicator <?php echo $index === 0 ? 'active' : ''; ?>" data-index="<?php echo $index; ?>"></button>
            <?php endforeach; ?>
        </div>
        <button class="hero-nav prev" id="heroPrev"><i class="fas fa-chevron-left"></i></button>
        <button class="hero-nav next" id="heroNext"><i class="fas fa-chevron-right"></i></button>
    </section>

    <!-- ===================== STATS BAR ===================== -->
    <div class="stats-bar">
        <div class="stat-item"><span class="stat-number"><?php echo $stats_years; ?></span><div class="stat-label">Years of Excellence</div></div>
        <div class="stat-item"><span class="stat-number"><?php echo $stats_clients; ?></span><div class="stat-label">Corporate Clients</div></div>
        <div class="stat-item"><span class="stat-number"><?php echo $stats_efficiency; ?></span><div class="stat-label">Efficiency Rating</div></div>
        <div class="stat-item"><span class="stat-number"><?php echo $stats_units; ?></span><div class="stat-label">Units Maintained</div></div>
    </div>

    <!-- ===================== PARTNER BRANDS (now DB-driven) ===================== -->
    <section class="section brands-section">
        <div class="brands-header">
            <div>
                <p class="section-label">Trusted Partners</p>
                <h2 class="section-title" style="margin-bottom:0"><?php echo htmlspecialchars($partners_title); ?></h2>
            </div>
            <div class="brands-filter">
                <button class="filter-btn active" data-filter="all">All Brands</button>
                <button class="filter-btn" data-filter="gov">Government</button>
                <button class="filter-btn" data-filter="cor">Corporate</button>
                <button class="filter-btn" data-filter="foo">Food &amp; Hospitality</button>
                <button class="filter-btn" data-filter="edu">Education</button>
            </div>
        </div>

        <div class="brands-grid compact-grid">
            <?php foreach ($partner_brands as $brand): ?>
            <div class="brand-card animate-on-scroll compact-brand-card"
                 data-category="<?php echo htmlspecialchars($brand['brand_category']); ?>">
                <div class="brand-logo">
                    <?php if (!empty($brand['brand_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($brand['brand_logo']); ?>"
                             alt="<?php echo htmlspecialchars($brand['brand_name']); ?>"
                             style="height:56px;">
                    <?php else: ?>
                        <div style="height:56px;width:80px;background:var(--gray-100);border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--gray-500);font-size:0.7rem;">No Logo</div>
                    <?php endif; ?>
                </div>
                <div class="brand-content">
                    <h3><?php echo htmlspecialchars($brand['brand_name']); ?></h3>
                    <p><?php echo htmlspecialchars($brand['brand_description']); ?></p>
                </div>
                <?php if (!empty($brand['brand_overlay_title'])): ?>
                <div class="brand-overlay">
                    <h4><?php echo htmlspecialchars($brand['brand_overlay_title']); ?></h4>
                    <p><?php echo htmlspecialchars($brand['brand_overlay_description']); ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($partner_brands)): ?>
                <p style="color:var(--gray-500);grid-column:1/-1;text-align:center;">No partner brands to display.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Brand Modal -->
    <div class="brand-modal" id="brandModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalBrandName">Brand Name</h2>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modalBrandContent"></div>
        </div>
    </div>

    <!-- ===================== TESTIMONIALS ===================== -->
    <section class="section testimonials">
        <div style="max-width: 820px; margin: 0 auto;">
            <p class="section-label">Client Success</p>
            <h2 class="section-title"><?php echo htmlspecialchars($testimonials_title); ?></h2>
            <p class="section-subtitle" style="color: rgba(255,255,255,0.55);">Discover why leading organizations across the Philippines trust MULTIBIZ for their managed services needs.</p>

            <div class="testimonial-slider">
                <?php foreach ($testimonials as $index => $testimonial): ?>
                    <div class="testimonial-item <?php echo $index === 0 ? 'active' : ''; ?>">
                        <p class="testimonial-text">"<?php echo htmlspecialchars($testimonial['content']); ?>"</p>
                        <div class="testimonial-author"><?php echo htmlspecialchars($testimonial['author_name']); ?></div>
                        <div class="testimonial-role"><?php echo htmlspecialchars($testimonial['author_role'] . ($testimonial['company'] ? ' @ ' . $testimonial['company'] : '')); ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="testimonial-controls">
                    <?php foreach ($testimonials as $index => $testimonial): ?>
                        <button class="testimonial-dot <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== CONTACT ===================== -->
    <section class="section contact-section">
        <div class="contact-grid">
            <div class="contact-info-panel">
                <p class="section-label">Get In Touch</p>
                <h2 class="section-title">Let's Start a Conversation</h2>
                <p class="section-subtitle" style="margin-bottom: 0;">Have questions or ready to get started? Our team is here to help you find the right solution.</p>
                <div class="contact-info-items">
                    <div class="contact-info-item"><div class="contact-info-icon"><i class="fas fa-globe"></i></div><span class="contact-info-text">https://multibiz.global</span></div>
                    <div class="contact-info-item"><div class="contact-info-icon"><i class="fas fa-phone"></i></div><span class="contact-info-text">+63 917 544 1674</span></div>
                    <div class="contact-info-item"><div class="contact-info-icon"><i class="fas fa-envelope"></i></div><span class="contact-info-text">inquiry@multibiz.global</span></div>
                </div>
            </div>
           <div class="contact-form animate-on-scroll compact-form">
                <div id="contactFormMessage" style="display: none; margin-bottom: 1rem; padding: 0.8rem; border-radius: 6px; font-size: 0.9rem;"></div>
                <form id="contactForm" novalidate>
                    <div class="form-group">
                        <label for="name" class="form-label">Your Name</label>
                        <input type="text" id="name" name="name" class="form-input" required placeholder="John Smith" maxlength="150">
                    </div>
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" required placeholder="john@company.com">
                    </div>
                    <div class="form-group">
                        <label for="subject" class="form-label">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-input" placeholder="How can we help?" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="message" class="form-label">Message</label>
                        <textarea id="message" name="message" class="form-textarea" required placeholder="Tell us about your business needs..." minlength="10"></textarea>
                    </div>
                    <button type="submit" class="form-submit" id="contactSubmitBtn">
                        Send Message <i class="fas fa-arrow-right" style="margin-left:0.4rem; font-size:0.8rem;"></i>
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- ===================== NEWS ===================== -->
    <section class="section news-section">
        <div class="section-center" style="margin-bottom: 3rem;">
            <p class="section-label" style="justify-content:center;">Latest Updates</p>
            <h2 class="section-title">Events</h2>
        </div>
        <div class="news-container">
            <div class="news-track" id="newsTrack">
                <?php if (!empty($news_articles)): ?>
                    <?php foreach ($news_articles as $news): ?>
                        <div class="news-card animate-on-scroll compact-news-card">
                            <div class="news-image">
                                <img src="<?php echo $news['image_path'] ?: 'https://placehold.co/400x250/0a1628/d4af55?text=News'; ?>" alt="<?php echo htmlspecialchars($news['title']); ?>">
                                <div class="news-date">
                                    <span class="date-day"><?php echo date('d', strtotime($news['news_date'])); ?></span>
                                    <span class="date-month"><?php echo strtoupper(date('M', strtotime($news['news_date']))); ?></span>
                                </div>
                            </div>
                            <div class="news-content">
                                <span class="news-category"><?php echo htmlspecialchars($news['category']); ?></span>
                                <h3 class="news-title"><?php echo htmlspecialchars($news['title']); ?></h3>
                                <p class="news-excerpt"><?php echo htmlspecialchars(substr($news['excerpt'], 0, 100)) . '...'; ?></p>
                                <a href="news_details.php?id=<?php echo $news['id']; ?>" class="news-link">Read More <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="news-card animate-on-scroll compact-news-card">
                        <div class="news-image"><img src="https://placehold.co/400x250/0a1628/d4af55?text=Partnership" alt="New Partnership"><div class="news-date"><span class="date-day">15</span><span class="date-month">OCT</span></div></div>
                        <div class="news-content"><span class="news-category">Partnership</span><h3 class="news-title">MULTIBIZ Announces Strategic Partnership with Tech Giant</h3><p class="news-excerpt">We're excited to announce our new partnership that will revolutionize business solutions across the region.</p><a href="#" class="news-link">Read More <i class="fas fa-arrow-right"></i></a></div>
                    </div>
                    <div class="news-card animate-on-scroll compact-news-card">
                        <div class="news-image"><img src="https://placehold.co/400x250/1a4fa0/ffffff?text=Product+Launch" alt="Product Launch"><div class="news-date"><span class="date-day">08</span><span class="date-month">OCT</span></div></div>
                        <div class="news-content"><span class="news-category">Product Launch</span><h3 class="news-title">Introducing Our New Managed Print Solutions</h3><p class="news-excerpt">Discover our latest managed print services designed to optimize your business operations and reduce costs.</p><a href="#" class="news-link">Read More <i class="fas fa-arrow-right"></i></a></div>
                    </div>
                    <div class="news-card animate-on-scroll compact-news-card">
                        <div class="news-image"><img src="https://placehold.co/400x250/18151f/b8973a?text=Industry+Award" alt="Award"><div class="news-date"><span class="date-day">25</span><span class="date-month">SEP</span></div></div>
                        <div class="news-content"><span class="news-category">Award</span><h3 class="news-title">MULTIBIZ Wins Prestigious Industry Innovation Award</h3><p class="news-excerpt">Recognized for excellence in business solutions and customer service for the third consecutive year.</p><a href="#" class="news-link">Read More <i class="fas fa-arrow-right"></i></a></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="news-controls">
            <button class="news-nav prev" id="newsPrev"><i class="fas fa-chevron-left"></i></button>
            <button class="news-nav next" id="newsNext"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="news-view-all"><a href="news.php" class="btn btn-primary">View All News</a></div>
    </section>

    <!-- ===================== FEATURED JOBS ===================== -->
    <section class="section featured-jobs-section">
        <div style="text-align:center; margin-bottom: 3.5rem;">
            <p class="section-label" style="justify-content:center;">Opportunities</p>
            <h2 class="section-title"><?php echo htmlspecialchars($featured_jobs_title); ?></h2>
            <p class="section-subtitle" style="margin: 0.75rem auto 0;">Discover exciting career opportunities posted by our trusted employer network.</p>
        </div>
        <div class="jobs-grid">
            <div class="job-card animate-on-scroll">
                <div class="job-header"><h3 class="job-title">Senior Software Engineer</h3><p class="job-company"><i class="fas fa-building"></i> Tech Solutions Inc.</p></div>
                <div class="job-tags"><span class="job-tag"><i class="fas fa-map-marker-alt"></i> Metro Manila</span><span class="job-tag"><i class="fas fa-briefcase"></i> Full-time</span></div>
                <p class="job-salary"><i class="fas fa-coins"></i> ₱80,000 – ₱120,000 / month</p>
                <div class="job-actions"><span class="job-date"><i class="fas fa-clock"></i> Posted Oct 15, 2023</span><div class="job-buttons"><a href="job_details_public.php?id=1" class="btn-outline-sm">Details</a><a href="loginregister.php?redirect=apply&job_id=1" class="btn-success">Apply Now</a></div></div>
            </div>
            <div class="job-card animate-on-scroll">
                <div class="job-header"><h3 class="job-title">Marketing Manager</h3><p class="job-company"><i class="fas fa-building"></i> Global Enterprises</p></div>
                <div class="job-tags"><span class="job-tag"><i class="fas fa-map-marker-alt"></i> Laguna</span><span class="job-tag"><i class="fas fa-briefcase"></i> Full-time</span></div>
                <p class="job-salary"><i class="fas fa-coins"></i> ₱60,000 – ₱90,000 / month</p>
                <div class="job-actions"><span class="job-date"><i class="fas fa-clock"></i> Posted Oct 12, 2023</span><div class="job-buttons"><a href="job_details_public.php?id=2" class="btn-outline-sm">Details</a><a href="loginregister.php?redirect=apply&job_id=2" class="btn-success">Apply Now</a></div></div>
            </div>
            <div class="job-card animate-on-scroll">
                <div class="job-header"><h3 class="job-title">IT Support Specialist</h3><p class="job-company"><i class="fas fa-building"></i> Retail Corporation</p></div>
                <div class="job-tags"><span class="job-tag"><i class="fas fa-map-marker-alt"></i> Cavite</span><span class="job-tag"><i class="fas fa-briefcase"></i> Full-time</span></div>
                <p class="job-salary"><i class="fas fa-coins"></i> ₱35,000 – ₱50,000 / month</p>
                <div class="job-actions"><span class="job-date"><i class="fas fa-clock"></i> Posted Oct 10, 2023</span><div class="job-buttons"><a href="job_details_public.php?id=3" class="btn-outline-sm">Details</a><a href="loginregister.php?redirect=apply&job_id=3" class="btn-success">Apply Now</a></div></div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 3rem;"><a href="jobs.php" class="btn btn-primary"><i class="fas fa-briefcase"></i> View All Jobs</a></div>
    </section>

    <!-- ===================== FOOTER ===================== -->
    <footer class="compact-footer" style="background: #18151f;">
        <div class="footer-top">
            <div class="footer-grid">
                <div class="footer-col">
                    <h3>MULTIBIZ INTERNATIONAL</h3>
                    <p>Your trusted career matching platform and managed services partner. Connecting talented professionals with leading employers across the Philippines.</p>
                    <div class="footer-social">
                        <a href="https://www.facebook.com/photo/?fbid=767175968760529&set=a.472946251516837" class="social-icon"><i class="fab fa-facebook-f"></i></a>
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
                        <li><a href="jobs.php">Browse Jobs</a></li>
                        <li><a href="careers.php">Careers</a></li>
                        <li><a href="contactus.php">Contact Us</a></li>
                        <li><a href="loginregister.php">Login</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Services</h3>
                    <ul class="footer-links">
                        <li><a href="services.php">Managed Print</a></li>
                        <li><a href="services.php#managedit">Managed I.T.</a></li>
                        <li><a href="services.php#managedpc">Managed PC</a></li>
                        <li><a href="services.php#managedhris">Managed HRIS</a></li>
                        <li><a href="services.php#managedsap">Managed SAP</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Contact Info</h3>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-globe"></i> multibiz.global</a></li>
                        <li><a href="tel:+639175441674"><i class="fas fa-phone"></i> +63 917 544 1674</a></li>
                        <li><a href="mailto:inquiry@multibiz.global"><i class="fas fa-envelope"></i> inquiry@multibiz.global</a></li>
                        <li><a href="#"><i class="fab fa-facebook-f"></i> Multibiz International</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2023 MULTIBIZ INTERNATIONAL CORPORATION. All Rights Reserved.</p>
        </div>
    </footer>

    <script>
        // ── Mobile nav toggle ──────────────────────────────────
        const navToggle = document.getElementById('navToggle');
        const navMenu = document.getElementById('navMenu');
        if (navToggle && navMenu) {
            navToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                navToggle.innerHTML = navMenu.classList.contains('active')
                    ? '<i class="fas fa-times"></i>'
                    : '<i class="fas fa-bars"></i>';
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            // Mobile dropdown toggle
            const navLinksWithDropdown = document.querySelectorAll('.nav-link:has(.fa-chevron-down)');
            navLinksWithDropdown.forEach(link => {
                link.addEventListener('click', function (e) {
                    if (window.innerWidth <= 768) {
                        e.preventDefault();
                        const dropdown = this.nextElementSibling;
                        document.querySelectorAll('.dropdown').forEach(d => { if (d !== dropdown) d.style.display = ''; });
                        dropdown.style.display = dropdown.style.display === 'block' ? '' : 'block';
                    }
                });
            });
            document.addEventListener('click', function (e) {
                if (window.innerWidth <= 768 && !e.target.closest('.nav-item')) {
                    document.querySelectorAll('.dropdown').forEach(d => d.style.display = '');
                }
            });

            // Brand filter
            const filterBtns = document.querySelectorAll('.filter-btn');
            const brandCards = document.querySelectorAll('.brand-card');
            filterBtns.forEach(btn => {
                btn.addEventListener('click', function () {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    const filter = this.dataset.filter;
                    brandCards.forEach(card => {
                        const show = filter === 'all' || card.dataset.category === filter;
                        card.style.display = show ? '' : 'none';
                    });
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
        });

        // Testimonial slider
        const testimonialDots  = document.querySelectorAll('.testimonial-dot');
        const testimonialItems = document.querySelectorAll('.testimonial-item');
        testimonialDots.forEach(dot => {
            dot.addEventListener('click', function () {
                const slide = parseInt(this.dataset.slide);
                testimonialItems.forEach(item => item.classList.remove('active'));
                testimonialDots.forEach(d => d.classList.remove('active'));
                testimonialItems[slide].classList.add('active');
                this.classList.add('active');
            });
        });

        // News slider
        let newsPosition = 0;
        const newsPrev   = document.getElementById('newsPrev');
        const newsNext   = document.getElementById('newsNext');
        const newsTrack  = document.getElementById('newsTrack');
        function getNewsStep() { return window.innerWidth < 480 ? 296 : 336; }
        if (newsPrev && newsNext && newsTrack) {
            newsPrev.addEventListener('click', () => {
                newsPosition = Math.min(newsPosition + getNewsStep(), 0);
                newsTrack.style.transform = `translateX(${newsPosition}px)`;
            });
            newsNext.addEventListener('click', () => {
                const maxScroll = -(newsTrack.scrollWidth - newsTrack.parentElement.offsetWidth);
                newsPosition = Math.max(newsPosition - getNewsStep(), maxScroll);
                newsTrack.style.transform = `translateX(${newsPosition}px)`;
            });
        }

        // Hero slider
        let heroIndex = 0;
        const heroImages     = document.querySelectorAll('.hero-image');
        const heroIndicators = document.querySelectorAll('.hero-indicator');
        function setHeroSlide(n) {
            heroImages.forEach(img => img.classList.remove('active'));
            heroIndicators.forEach(ind => ind.classList.remove('active'));
            heroIndex = (n + heroImages.length) % heroImages.length;
            heroImages[heroIndex].classList.add('active');
            heroIndicators[heroIndex].classList.add('active');
        }
        document.getElementById('heroPrev')?.addEventListener('click', () => setHeroSlide(heroIndex - 1));
        document.getElementById('heroNext')?.addEventListener('click', () => setHeroSlide(heroIndex + 1));
        heroIndicators.forEach(ind => ind.addEventListener('click', () => setHeroSlide(parseInt(ind.dataset.index))));
        setInterval(() => setHeroSlide(heroIndex + 1), 5000);

        // Contact form — with validation, fetch submission & DB storage
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', async function (e) {
                e.preventDefault();
 
                const msg    = document.getElementById('contactFormMessage');
                const btn    = document.getElementById('contactSubmitBtn');
                const name   = document.getElementById('name').value.trim();
                const email  = document.getElementById('email').value.trim();
                const subject= (document.getElementById('subject')?.value || '').trim();
                const message= document.getElementById('message').value.trim();
 
                // ── Client-side validation ──────────────────────────────────
                const emailRx = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const errors  = [];
 
                if (!name)               errors.push('Please enter your name.');
                if (!email)              errors.push('Please enter your email.');
                else if (!emailRx.test(email)) errors.push('Please enter a valid email address.');
                if (!message)            errors.push('Please enter your message.');
                else if (message.length < 10)  errors.push('Message must be at least 10 characters.');
 
                if (errors.length) {
                    msg.style.display    = 'block';
                    msg.style.background = '#fef2f2';
                    msg.style.color      = '#991b1b';
                    msg.style.border     = '1px solid #fca5a5';
                    msg.innerHTML        = errors.map(e => `<div>• ${e}</div>`).join('');
                    return;
                }
 
                // ── Loading state ───────────────────────────────────────────
                btn.disabled     = true;
                btn.innerHTML    = '<i class="fas fa-spinner fa-spin" style="margin-right:.4rem;"></i> Sending…';
                msg.style.display = 'none';
 
                // ── Submit to backend ───────────────────────────────────────
                try {
                    const formData = new FormData();
                    formData.append('name',    name);
                    formData.append('email',   email);
                    formData.append('subject', subject || 'General Inquiry');
                    formData.append('message', message);
 
                    const response = await fetch('includes/handlers/contact_handler.php', {
                        method: 'POST',
                        body:   formData
                    });
 
                    const data = await response.json();
 
                    if (data.success) {
                        msg.style.display    = 'block';
                        msg.style.background = '#ecfdf5';
                        msg.style.color      = '#065f46';
                        msg.style.border     = '1px solid #6ee7b7';
                        msg.textContent      = data.message || 'Thank you! Your message has been sent successfully.';
                        contactForm.reset();
                        setTimeout(() => { msg.style.display = 'none'; }, 6000);
                    } else {
                        const errList = Array.isArray(data.errors) ? data.errors : ['Something went wrong. Please try again.'];
                        msg.style.display    = 'block';
                        msg.style.background = '#fef2f2';
                        msg.style.color      = '#991b1b';
                        msg.style.border     = '1px solid #fca5a5';
                        msg.innerHTML        = errList.map(e => `<div>• ${e}</div>`).join('');
                    }
                } catch (err) {
                    msg.style.display    = 'block';
                    msg.style.background = '#fef2f2';
                    msg.style.color      = '#991b1b';
                    msg.style.border     = '1px solid #fca5a5';
                    msg.textContent      = 'Network error. Please check your connection and try again.';
                } finally {
                    btn.disabled  = false;
                    btn.innerHTML = 'Send Message <i class="fas fa-arrow-right" style="margin-left:0.4rem; font-size:0.8rem;"></i>';
                }
            });
        }
    </script>
</body>
</html>