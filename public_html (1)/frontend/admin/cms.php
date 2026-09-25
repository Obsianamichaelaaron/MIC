<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMS Admin — MULTIBIZ</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            --gray-200:   #e4e2e8;
            --gray-500:   #8a8691;
            --gray-700:   #4a4752;
            --dark:       #18151f;
            --success:    #22c55e;
            --danger:     #ef4444;
            --warning:    #f59e0b;
            --sidebar-w:  260px;
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body:    'DM Sans', -apple-system, sans-serif;
            --shadow-sm: 0 2px 12px rgba(10,22,40,0.07);
            --shadow-md: 0 8px 32px rgba(10,22,40,0.11);
            --shadow-lg: 0 20px 56px rgba(10,22,40,0.16);
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 20px;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-body); background: #f0f2f7; color: var(--dark); display: flex; min-height: 100vh; -webkit-font-smoothing: antialiased; }

        /* ── SIDEBAR ── */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--navy);
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: transform 0.3s ease;
            overflow-y: auto;
        }
        .sidebar-brand {
            padding: 1.5rem 1.25rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .sidebar-brand .logo-text {
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 600;
            color: white;
            line-height: 1.3;
        }
        .sidebar-brand .logo-text span { color: var(--gold-light); }
        .sidebar-brand .admin-badge {
            font-size: 0.62rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--gold);
            background: rgba(184,151,58,0.12);
            border: 1px solid rgba(184,151,58,0.25);
            border-radius: 100px;
            padding: 0.2rem 0.6rem;
            display: inline-block;
            margin-top: 0.35rem;
        }

        .sidebar-section {
            padding: 1.1rem 0.75rem 0.4rem;
        }
        .sidebar-section-label {
            font-size: 0.6rem;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.3);
            padding: 0 0.5rem;
            margin-bottom: 0.4rem;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            padding: 0.6rem 0.75rem;
            border-radius: var(--radius-sm);
            color: rgba(255,255,255,0.55);
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.87rem;
            font-weight: 500;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            text-decoration: none;
        }
        .nav-item i { width: 16px; font-size: 0.85rem; opacity: 0.8; flex-shrink: 0; }
        .nav-item:hover { color: white; background: rgba(255,255,255,0.06); }
        .nav-item.active { color: var(--gold-light); background: rgba(184,151,58,0.1); border-left: 2px solid var(--gold); }
        .nav-item.active i { opacity: 1; }
        .nav-badge {
            margin-left: auto;
            font-size: 0.6rem;
            font-weight: 700;
            background: var(--gold);
            color: var(--navy);
            border-radius: 100px;
            padding: 0.1rem 0.45rem;
        }

        .sidebar-footer {
            margin-top: auto;
            padding: 1rem 0.75rem;
            border-top: 1px solid rgba(255,255,255,0.07);
        }

        /* ── MAIN LAYOUT ── */
        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── TOPBAR ── */
        .topbar {
            background: white;
            border-bottom: 1px solid var(--gray-200);
            padding: 0 2rem;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: var(--shadow-sm);
        }
        .topbar-left { display: flex; align-items: center; gap: 1rem; }
        .topbar-title { font-size: 1.05rem; font-weight: 600; color: var(--navy); }
        .breadcrumb { font-size: 0.78rem; color: var(--gray-500); display: flex; align-items: center; gap: 0.4rem; }
        .breadcrumb i { font-size: 0.6rem; }

        .topbar-right { display: flex; align-items: center; gap: 0.75rem; }
        .btn { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.5rem 1.1rem; border-radius: var(--radius-sm); font-size: 0.82rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s ease; font-family: var(--font-body); letter-spacing: 0.02em; }
        .btn-primary { background: var(--navy); color: white; }
        .btn-primary:hover { background: var(--blue); transform: translateY(-1px); }
        .btn-gold { background: linear-gradient(135deg, var(--gold), var(--gold-light)); color: var(--navy); box-shadow: 0 4px 12px rgba(184,151,58,0.3); }
        .btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(184,151,58,0.4); }
        .btn-outline { background: transparent; color: var(--navy); border: 1.5px solid var(--gray-200); }
        .btn-outline:hover { border-color: var(--navy); background: var(--gray-100); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-sm { padding: 0.35rem 0.7rem; font-size: 0.75rem; }

        /* ── CONTENT AREA ── */
        .content { padding: 2rem; flex: 1; }
        .content-panel { display: none; }
        .content-panel.active { display: block; }

        /* ── PAGE TITLE ── */
        .page-header {
            margin-bottom: 1.75rem;
        }
        .page-header h1 {
            font-family: var(--font-display);
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--navy);
            margin-bottom: 0.25rem;
        }
        .page-header p { font-size: 0.87rem; color: var(--gray-500); }

        /* ── TABS ── */
        .tabs {
            display: flex;
            gap: 0;
            background: white;
            border-radius: var(--radius-md);
            padding: 0.35rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.75rem;
            width: fit-content;
            border: 1px solid var(--gray-200);
        }
        .tab {
            padding: 0.55rem 1.25rem;
            border-radius: var(--radius-sm);
            font-size: 0.83rem;
            font-weight: 500;
            cursor: pointer;
            color: var(--gray-500);
            transition: all 0.2s ease;
            border: none;
            background: none;
            font-family: var(--font-body);
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .tab:hover { color: var(--navy); }
        .tab.active { background: var(--navy); color: white; box-shadow: var(--shadow-sm); }

        /* ── CARD ── */
        .card {
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .card-header {
            padding: 1.1rem 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #fafafa;
        }
        .card-header-left { display: flex; align-items: center; gap: 0.75rem; }
        .card-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--navy), var(--blue));
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
        }
        .card-icon i { color: var(--gold-light); font-size: 0.9rem; }
        .card-title { font-size: 0.9rem; font-weight: 600; color: var(--navy); }
        .card-subtitle { font-size: 0.75rem; color: var(--gray-500); }
        .card-body { padding: 1.5rem; }

        /* ── FORM ELEMENTS ── */
        .form-group { margin-bottom: 1.25rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem; }
        label {
            display: block;
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.4rem;
            letter-spacing: 0.02em;
        }
        label span { color: var(--danger); margin-left: 2px; }
        input[type="text"],
        input[type="url"],
        input[type="tel"],
        input[type="email"],
        textarea,
        select {
            width: 100%;
            padding: 0.6rem 0.85rem;
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 0.87rem;
            color: var(--dark);
            background: white;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            outline: none;
        }
        input:focus, textarea:focus, select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(26,79,160,0.08);
        }
        textarea { resize: vertical; min-height: 80px; line-height: 1.6; }
        textarea.tall { min-height: 120px; }

        .hint { font-size: 0.72rem; color: var(--gray-500); margin-top: 0.3rem; }

        /* ── IMAGE UPLOAD ── */
        .img-upload-zone {
            border: 2px dashed var(--gray-200);
            border-radius: var(--radius-md);
            padding: 2rem 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--gray-100);
            position: relative;
        }
        .img-upload-zone:hover, .img-upload-zone.drag-over {
            border-color: var(--blue);
            background: rgba(26,79,160,0.04);
        }
        .img-upload-zone input[type="file"] {
            position: absolute; inset: 0;
            width: 100%; height: 100%;
            opacity: 0; cursor: pointer;
            border: none;
        }
        .img-upload-icon { font-size: 2rem; color: var(--gray-500); margin-bottom: 0.6rem; }
        .img-upload-text { font-size: 0.82rem; color: var(--gray-500); }
        .img-upload-text strong { color: var(--blue); }
        .img-preview-wrap {
            margin-top: 0.75rem;
            position: relative;
            display: inline-block;
        }
        .img-preview {
            width: 100%;
            max-width: 260px;
            height: 140px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            border: 1.5px solid var(--gray-200);
            display: block;
        }
        .img-preview-remove {
            position: absolute;
            top: -6px; right: -6px;
            width: 22px; height: 22px;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            font-size: 0.7rem;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }

        /* ── SECTION EDITOR ── */
        .section-editor {
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-md);
            overflow: hidden;
            margin-bottom: 1rem;
        }
        .section-editor-header {
            padding: 0.75rem 1.1rem;
            background: #f8f9fb;
            border-bottom: 1.5px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }
        .section-editor-header:hover { background: #f0f2f7; }
        .section-editor-title { font-size: 0.85rem; font-weight: 600; color: var(--navy); display: flex; align-items: center; gap: 0.5rem; }
        .section-editor-title .badge {
            font-size: 0.62rem;
            font-weight: 700;
            padding: 0.15rem 0.5rem;
            border-radius: 100px;
            background: rgba(26,79,160,0.1);
            color: var(--blue);
            letter-spacing: 0.05em;
        }
        .section-chevron { color: var(--gray-500); transition: transform 0.25s ease; }
        .section-editor.open .section-chevron { transform: rotate(180deg); }
        .section-editor-body { padding: 1.25rem; display: none; }
        .section-editor.open .section-editor-body { display: block; }

        /* ── LIST EDITOR (for packages, services, etc.) ── */
        .list-editor { display: flex; flex-direction: column; gap: 0.5rem; }
        .list-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .list-item input { flex: 1; }
        .list-item-actions { display: flex; gap: 0.3rem; flex-shrink: 0; }
        .icon-btn {
            width: 30px; height: 30px;
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-sm);
            background: white;
            color: var(--gray-500);
            display: flex; align-items: center; justify-content: center;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s ease;
        }
        .icon-btn:hover { border-color: var(--danger); color: var(--danger); background: #fef2f2; }
        .icon-btn.add:hover { border-color: var(--success); color: var(--success); background: #f0fdf4; }
        .add-item-btn {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            border: 1.5px dashed var(--gray-200);
            border-radius: var(--radius-sm);
            background: none;
            color: var(--blue);
            font-size: 0.78rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: var(--font-body);
            margin-top: 0.35rem;
        }
        .add-item-btn:hover { border-color: var(--blue); background: rgba(26,79,160,0.04); }

        /* ── BRAND CARD EDITOR ── */
        .brand-cards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .brand-card-editor {
            border: 1.5px solid var(--gray-200);
            border-radius: var(--radius-md);
            overflow: hidden;
        }
        .brand-card-editor-header {
            padding: 0.6rem 0.9rem;
            background: #f8f9fb;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .brand-card-editor-header .bname { font-size: 0.82rem; font-weight: 600; color: var(--navy); }
        .brand-card-editor-body { padding: 0.9rem; }

        /* ── TOAST ── */
        .toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .toast {
            background: var(--navy);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: var(--shadow-lg);
            animation: slideIn 0.3s ease;
            max-width: 320px;
        }
        .toast.success { background: #166534; border-left: 4px solid var(--success); }
        .toast.error { background: #7f1d1d; border-left: 4px solid var(--danger); }
        .toast.info { background: var(--navy); border-left: 4px solid var(--blue); }
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(120%); opacity: 0; } }

        /* ── STATS ROW ── */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 1.75rem;
        }
        .stat-card {
            background: white;
            border-radius: var(--radius-md);
            padding: 1.1rem 1.25rem;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .stat-icon {
            width: 44px; height: 44px;
            border-radius: var(--radius-sm);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .stat-icon.navy { background: rgba(10,22,40,0.08); }
        .stat-icon.navy i { color: var(--navy); }
        .stat-icon.blue { background: rgba(26,79,160,0.1); }
        .stat-icon.blue i { color: var(--blue); }
        .stat-icon.gold { background: rgba(184,151,58,0.12); }
        .stat-icon.gold i { color: var(--gold); }
        .stat-icon.green { background: rgba(34,197,94,0.1); }
        .stat-icon.green i { color: var(--success); }
        .stat-num { font-size: 1.45rem; font-weight: 700; color: var(--navy); line-height: 1; }
        .stat-label { font-size: 0.75rem; color: var(--gray-500); margin-top: 0.2rem; }

        /* ── DIVIDER ── */
        .divider { height: 1px; background: var(--gray-200); margin: 1.5rem 0; }

        /* ── SAVE INDICATOR ── */
        .unsaved-dot {
            width: 7px; height: 7px;
            background: var(--warning);
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.3rem;
        }
        .save-status {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .save-status.unsaved { color: var(--warning); }
        .save-status.saved { color: var(--success); }

        /* ── COLOR PICKER ── */
        .color-row { display: flex; align-items: center; gap: 0.5rem; }
        .color-row input[type="color"] {
            width: 36px; height: 36px;
            padding: 0; border-radius: var(--radius-sm);
            cursor: pointer; flex-shrink: 0;
            border: 1.5px solid var(--gray-200);
        }

        /* ── RESPONSIVE ── */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main { margin-left: 0; }
            .form-row, .form-row-3, .brand-cards-grid { grid-template-columns: 1fr; }
            .stats-row { grid-template-columns: repeat(2, 1fr); }
        }

        /* ── TOGGLE SWITCH ── */
        .toggle-wrap { display: flex; align-items: center; gap: 0.6rem; }
        .toggle { position: relative; width: 38px; height: 20px; flex-shrink: 0; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .toggle-slider {
            position: absolute; inset: 0;
            background: var(--gray-200);
            border-radius: 100px;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .toggle-slider::before {
            content: '';
            position: absolute;
            width: 14px; height: 14px;
            background: white;
            border-radius: 50%;
            top: 3px; left: 3px;
            transition: transform 0.2s ease;
            box-shadow: 0 1px 4px rgba(0,0,0,0.2);
        }
        .toggle input:checked + .toggle-slider { background: var(--blue); }
        .toggle input:checked + .toggle-slider::before { transform: translateX(18px); }
        .toggle-label { font-size: 0.82rem; color: var(--gray-700); font-weight: 500; }
    </style>
</head>
<body>

<!-- ── SIDEBAR ── -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="logo-text">MULTIBIZ<br><span>INTERNATIONAL</span></div>
        <div class="admin-badge">CMS Admin</div>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Navigation</div>
        <button class="nav-item active" onclick="showPanel('dashboard',this)">
            <i class="fas fa-th-large"></i> Dashboard
        </button>
        <a class="nav-item" href="../services.php" target="_blank">
            <i class="fas fa-external-link-alt"></i> View Live Site
        </a>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Content Pages</div>
        <button class="nav-item" onclick="showPanel('about',this)">
            <i class="fas fa-building"></i> About Page
            <span class="nav-badge">7</span>
        </button>
        <button class="nav-item" onclick="showPanel('services',this)">
            <i class="fas fa-cog"></i> Services Page
            <span class="nav-badge">4</span>
        </button>
    </div>

    <div class="sidebar-section">
        <div class="sidebar-section-label">Global</div>
        <button class="nav-item" onclick="showPanel('header',this)">
            <i class="fas fa-bars"></i> Header / Nav
        </button>
        <button class="nav-item" onclick="showPanel('footer',this)">
            <i class="fas fa-grip-lines"></i> Footer
        </button>
    </div>

    <div class="sidebar-footer">
        <button class="nav-item" onclick="saveAll()">
            <i class="fas fa-save"></i> Save All Changes
        </button>
        <button class="nav-item" onclick="exportJSON()">
            <i class="fas fa-download"></i> Export JSON
        </button>
    </div>
</aside>

<!-- ── MAIN ── -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <div>
                <div class="topbar-title" id="topbarTitle">Dashboard</div>
                <div class="breadcrumb"><span>Admin</span> <i class="fas fa-chevron-right"></i> <span id="topbarBread">Overview</span></div>
            </div>
        </div>
        <div class="topbar-right">
            <div class="save-status" id="saveStatus"><i class="fas fa-check-circle"></i> All changes saved</div>
            <button class="btn btn-outline btn-sm" onclick="previewChanges()"><i class="fas fa-eye"></i> Preview</button>
            <button class="btn btn-gold" onclick="saveAll()"><i class="fas fa-save"></i> Save Changes</button>
        </div>
    </div>

    <!-- CONTENT -->
    <div class="content">

        <!-- ══ DASHBOARD ══ -->
        <div class="content-panel active" id="panel-dashboard">
            <div class="page-header">
                <h1>Welcome to the CMS</h1>
                <p>Manage all content for the About and Services pages from here.</p>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon navy"><i class="fas fa-file-alt"></i></div>
                    <div><div class="stat-num">2</div><div class="stat-label">Editable Pages</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fas fa-layer-group"></i></div>
                    <div><div class="stat-num">11</div><div class="stat-label">Content Sections</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon gold"><i class="fas fa-image"></i></div>
                    <div><div class="stat-num">15+</div><div class="stat-label">Editable Images</div></div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                    <div><div class="stat-num" id="savedCount">0</div><div class="stat-label">Fields Saved</div></div>
                </div>
            </div>

            <div class="form-row">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-building"></i></div>
                            <div><div class="card-title">About Page</div><div class="card-subtitle">7 sections</div></div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="showPanel('about', document.querySelector('[onclick*=about]'))">Edit</button>
                    </div>
                    <div class="card-body">
                        <p style="font-size:0.85rem;color:var(--gray-500);">Hero, Vision/Mission, History, Industries, Certifications, Awards, and Location sections.</p>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-cog"></i></div>
                            <div><div class="card-title">Services Page</div><div class="card-subtitle">4 sections</div></div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="showPanel('services', document.querySelector('[onclick*=services]'))">Edit</button>
                    </div>
                    <div class="card-body">
                        <p style="font-size:0.85rem;color:var(--gray-500);">Managed Print, Document Management, Scanner Leasing, and System Integration.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ ABOUT PAGE ══ -->
        <div class="content-panel" id="panel-about">
            <div class="page-header">
                <h1>About Page</h1>
                <p>Edit all text, images, and content blocks for the About page.</p>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab(this,'about-hero')"><i class="fas fa-star"></i> Hero</button>
                <button class="tab" onclick="switchTab(this,'about-vision')"><i class="fas fa-eye"></i> Vision & Mission</button>
                <button class="tab" onclick="switchTab(this,'about-history')"><i class="fas fa-clock"></i> History</button>
                <button class="tab" onclick="switchTab(this,'about-industries')"><i class="fas fa-industry"></i> Industries</button>
                <button class="tab" onclick="switchTab(this,'about-awards')"><i class="fas fa-trophy"></i> Certifications & Awards</button>
                <button class="tab" onclick="switchTab(this,'about-location')"><i class="fas fa-map-marker-alt"></i> Location</button>
            </div>

            <!-- ABOUT HERO -->
            <div class="tab-content active" id="about-hero">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-star"></i></div>
                            <div><div class="card-title">Hero Section</div><div class="card-subtitle">Main banner for About page</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div>
                                <div class="form-group">
                                    <label>Eyebrow Text</label>
                                    <input type="text" id="about_hero_eyebrow" value="Our Story" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Hero Title</label>
                                    <input type="text" id="about_hero_title" value="Who We Are" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Hero Subtitle / Description</label>
                                    <textarea id="about_hero_subtitle" oninput="markUnsaved()">MultiBiz International Corporation is a trusted managed services partner delivering innovative technology solutions that drive efficiency, reduce costs, and enhance productivity for businesses across the Philippines.</textarea>
                                </div>
                            </div>
                            <div>
                                <label>Hero Background Image</label>
                                <div class="img-upload-zone" id="zone_about_hero_bg" ondragover="handleDrag(event,this)" ondragleave="handleDragLeave(this)" ondrop="handleDrop(event,'about_hero_bg')">
                                    <input type="file" accept="image/*" onchange="handleImgUpload(this,'about_hero_bg')">
                                    <div class="img-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                    <div class="img-upload-text"><strong>Click or drag</strong> to upload hero background</div>
                                    <div class="hint">Recommended: 1920×600px</div>
                                    <div id="preview_about_hero_bg"></div>
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>
                        <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.85rem;"><i class="fas fa-chart-bar" style="margin-right:0.4rem;color:var(--gold);"></i> Stats Bar</div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label>Stat 1 — Number</label>
                                <input type="text" id="about_stat1_num" value="25+" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Stat 1 — Label</label>
                                <input type="text" id="about_stat1_label" value="Years in Business" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Stat 1 — Icon (FA class)</label>
                                <input type="text" id="about_stat1_icon" value="fas fa-award" oninput="markUnsaved()">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label>Stat 2 — Number</label>
                                <input type="text" id="about_stat2_num" value="500+" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Stat 2 — Label</label>
                                <input type="text" id="about_stat2_label" value="Clients Served" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Stat 3 — Number</label>
                                <input type="text" id="about_stat3_num" value="9" oninput="markUnsaved()">
                            </div>
                        </div>
                        <div class="form-row-3">
                            <div class="form-group">
                                <label>Stat 3 — Label</label>
                                <input type="text" id="about_stat3_label" value="Brand Partners" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Stat 4 — Number</label>
                                <input type="text" id="about_stat4_num" value="4" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Stat 4 — Label</label>
                                <input type="text" id="about_stat4_label" value="Core Services" oninput="markUnsaved()">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ABOUT VISION -->
            <div class="tab-content" id="about-vision" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-eye"></i></div>
                            <div><div class="card-title">Vision, Mission & Values</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div>
                                <div class="form-group">
                                    <label>Section Eyebrow</label>
                                    <input type="text" id="vmv_eyebrow" value="Our Purpose" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Vision Statement</label>
                                    <textarea id="vmv_vision" class="tall" oninput="markUnsaved()">To be the leading provider of innovative managed services solutions in the Philippines, empowering businesses to achieve peak operational efficiency through technology.</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Mission Statement</label>
                                    <textarea id="vmv_mission" class="tall" oninput="markUnsaved()">To deliver reliable, cost-effective, and scalable managed services that simplify complex business processes, supported by exceptional customer service and cutting-edge technology partnerships.</textarea>
                                </div>
                            </div>
                            <div>
                                <label>Section Image</label>
                                <div class="img-upload-zone" ondragover="handleDrag(event,this)" ondragleave="handleDragLeave(this)" ondrop="handleDrop(event,'vmv_image')">
                                    <input type="file" accept="image/*" onchange="handleImgUpload(this,'vmv_image')">
                                    <div class="img-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                    <div class="img-upload-text"><strong>Click or drag</strong> to upload image</div>
                                    <div class="hint">Recommended: 600×400px</div>
                                    <div id="preview_vmv_image"></div>
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>
                        <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.85rem;"><i class="fas fa-list-check" style="margin-right:0.4rem;color:var(--gold);"></i> Core Values</div>
                        <div class="list-editor" id="values-list"></div>
                        <button class="add-item-btn" onclick="addListItem('values-list')"><i class="fas fa-plus"></i> Add Value</button>
                    </div>
                </div>
            </div>

            <!-- ABOUT HISTORY -->
            <div class="tab-content" id="about-history" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-clock"></i></div>
                            <div><div class="card-title">Company History / Timeline</div></div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="addTimelineItem()"><i class="fas fa-plus"></i> Add Milestone</button>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Section Title</label>
                                <input type="text" id="history_title" value="Our Journey" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Section Subtitle</label>
                                <input type="text" id="history_subtitle" value="Over two decades of innovation and service excellence." oninput="markUnsaved()">
                            </div>
                        </div>
                        <div id="timeline-items">
                            <!-- Timeline items injected by JS -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- ABOUT INDUSTRIES -->
            <div class="tab-content" id="about-industries" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-industry"></i></div>
                            <div><div class="card-title">Industries We Serve</div></div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="addIndustry()"><i class="fas fa-plus"></i> Add Industry</button>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Section Title</label>
                                <input type="text" id="industries_title" value="Industries We Serve" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Section Description</label>
                                <input type="text" id="industries_desc" value="Our solutions are deployed across diverse sectors throughout the Philippines." oninput="markUnsaved()">
                            </div>
                        </div>
                        <div id="industries-list" class="form-row-3" style="margin-top:0.5rem;"></div>
                    </div>
                </div>
            </div>

            <!-- ABOUT AWARDS -->
            <div class="tab-content" id="about-awards" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-medal"></i></div>
                            <div><div class="card-title">Certifications</div></div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="addCertification()"><i class="fas fa-plus"></i> Add Certification</button>
                    </div>
                    <div class="card-body">
                        <div id="certifications-list" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-trophy"></i></div>
                            <div><div class="card-title">Awards</div></div>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="addAward()"><i class="fas fa-plus"></i> Add Award</button>
                    </div>
                    <div class="card-body">
                        <div id="awards-list" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"></div>
                    </div>
                </div>
            </div>

            <!-- ABOUT LOCATION -->
            <div class="tab-content" id="about-location" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div><div class="card-title">Where We Are</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div>
                                <div class="form-group">
                                    <label>Section Title</label>
                                    <input type="text" id="loc_title" value="Where We Are" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Company Address</label>
                                    <textarea id="loc_address" oninput="markUnsaved()">Unit 2301, 88 Corporate Center, Valero Street, Salcedo Village, Makati City, Metro Manila, Philippines</textarea>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <input type="tel" id="loc_phone" value="+63 917 544 1674" oninput="markUnsaved()">
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" id="loc_email" value="inquiry@multibiz.global" oninput="markUnsaved()">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Website</label>
                                    <input type="url" id="loc_website" value="https://multibiz.global" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Google Maps Embed URL</label>
                                    <input type="url" id="loc_map_url" value="https://www.google.com/maps/embed?pb=..." oninput="markUnsaved()">
                                    <div class="hint">Paste the embed src URL from Google Maps > Share > Embed a map</div>
                                </div>
                            </div>
                            <div>
                                <label>Office Photo</label>
                                <div class="img-upload-zone" ondragover="handleDrag(event,this)" ondragleave="handleDragLeave(this)" ondrop="handleDrop(event,'loc_photo')">
                                    <input type="file" accept="image/*" onchange="handleImgUpload(this,'loc_photo')">
                                    <div class="img-upload-icon"><i class="fas fa-building"></i></div>
                                    <div class="img-upload-text"><strong>Click or drag</strong> to upload office photo</div>
                                    <div class="hint">Recommended: 600×400px</div>
                                    <div id="preview_loc_photo"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /panel-about -->

        <!-- ══ SERVICES PAGE ══ -->
        <div class="content-panel" id="panel-services">
            <div class="page-header">
                <h1>Services Page</h1>
                <p>Edit the 4 service sections, hero content, and brand cards.</p>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab(this,'svc-hero')"><i class="fas fa-star"></i> Hero</button>
                <button class="tab" onclick="switchTab(this,'svc-mps')"><i class="fas fa-print"></i> Managed Print</button>
                <button class="tab" onclick="switchTab(this,'svc-dms')"><i class="fas fa-folder-open"></i> Doc Management</button>
                <button class="tab" onclick="switchTab(this,'svc-scanner')"><i class="fas fa-scanner"></i> Scanner Leasing</button>
                <button class="tab" onclick="switchTab(this,'svc-si')"><i class="fas fa-network-wired"></i> System Integration</button>
                <button class="tab" onclick="switchTab(this,'svc-cta')"><i class="fas fa-bullhorn"></i> CTA Section</button>
            </div>

            <!-- SERVICES HERO -->
            <div class="tab-content active" id="svc-hero">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-star"></i></div>
                            <div><div class="card-title">Services Hero Banner</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div>
                                <div class="form-group">
                                    <label>Eyebrow Text</label>
                                    <input type="text" id="svc_hero_eyebrow" value="Our Capabilities" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Hero Title (plain part)</label>
                                    <input type="text" id="svc_hero_title" value="Integrated" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Hero Title (italic/gold part)</label>
                                    <input type="text" id="svc_hero_title_em" value="Business" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Hero Title (suffix)</label>
                                    <input type="text" id="svc_hero_title_suffix" value="Solutions" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Hero Subtitle</label>
                                    <textarea id="svc_hero_subtitle" oninput="markUnsaved()">From managed print to system integration — comprehensive technology services designed to drive efficiency and growth.</textarea>
                                </div>
                            </div>
                            <div>
                                <label>Hero Background Image</label>
                                <div class="img-upload-zone" ondragover="handleDrag(event,this)" ondragleave="handleDragLeave(this)" ondrop="handleDrop(event,'svc_hero_bg')">
                                    <input type="file" accept="image/*" onchange="handleImgUpload(this,'svc_hero_bg')">
                                    <div class="img-upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                                    <div class="img-upload-text"><strong>Click or drag</strong> to upload</div>
                                    <div class="hint">Recommended: 1920×600px</div>
                                    <div id="preview_svc_hero_bg"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MANAGED PRINT -->
            <div class="tab-content" id="svc-mps" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-print"></i></div>
                            <div><div class="card-title">Service 01 — Managed Print Services (MPS)</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Section Eyebrow</label>
                            <input type="text" id="mps_eyebrow" value="Service 01" oninput="markUnsaved()">
                        </div>
                        <div class="form-group">
                            <label>Section Title</label>
                            <input type="text" id="mps_title" value="Comprehensive Managed Print Program" oninput="markUnsaved()">
                        </div>
                        <div class="form-group">
                            <label>Paragraph 1</label>
                            <textarea id="mps_p1" class="tall" oninput="markUnsaved()">Optimize your document workflow and reduce printing costs with our comprehensive managed print solutions. We provide tailored packages for every volume — from low to high — ensuring you only pay for what you need while enjoying maximum uptime and performance.</textarea>
                        </div>
                        <div class="form-group">
                            <label>Paragraph 2</label>
                            <textarea id="mps_p2" class="tall" oninput="markUnsaved()">Our MPS program covers everything from device deployment and proactive maintenance to consumables replenishment and detailed reporting, giving you complete visibility and control over your print environment.</textarea>
                        </div>

                        <div class="divider"></div>
                        <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.85rem;"><i class="fas fa-box-open" style="margin-right:0.4rem;color:var(--gold);"></i> MPS Packages</div>
                        <div class="form-group">
                            <label>Packages Box Title</label>
                            <input type="text" id="mps_packages_title" value="Our MPS Packages" oninput="markUnsaved()">
                        </div>
                        <div class="list-editor" id="mps-packages-list"></div>
                        <button class="add-item-btn" onclick="addListItem('mps-packages-list')"><i class="fas fa-plus"></i> Add Package</button>

                        <div class="divider"></div>
                        <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.85rem;"><i class="fas fa-handshake" style="margin-right:0.4rem;color:var(--gold);"></i> Brand Partnership Cards</div>
                        <div class="brand-cards-grid" id="brand-cards-editor"></div>
                        <div style="margin-top:0.75rem;">
                            <button class="add-item-btn" onclick="addBrandCard()"><i class="fas fa-plus"></i> Add Brand Card</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DMS -->
            <div class="tab-content" id="svc-dms" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-folder-open"></i></div>
                            <div><div class="card-title">Service 02 — Document Management System</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div>
                                <div class="form-group">
                                    <label>Section Eyebrow</label>
                                    <input type="text" id="dms_eyebrow" value="Service 02" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Section Title</label>
                                    <input type="text" id="dms_title" value="Document Management System" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Tagline</label>
                                    <input type="text" id="dms_tagline" value="Efficient, Secure, and Accessible Document Handling" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Description Paragraph 1</label>
                                    <textarea id="dms_p1" oninput="markUnsaved()">At MultiBiz International Corporation, we recognize the unique document management needs of each business.</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Description Paragraph 2</label>
                                    <textarea id="dms_p2" class="tall" oninput="markUnsaved()">Our tailored Document Management Systems (DMS) cater to both small startups and large enterprises, ensuring the perfect fit for your size, industry, and workflow. Whether you need a streamlined solution or comprehensive document control, we have the ideal DMS for you.</textarea>
                                </div>
                            </div>
                            <div>
                                <div class="form-group">
                                    <label>Feature Pills (comma-separated)</label>
                                    <input type="text" id="dms_pills" value="Convenience, Advanced Security, Contingency" oninput="markUnsaved()">
                                    <div class="hint">Separate pill labels with commas</div>
                                </div>
                                <div class="form-group" style="margin-top:1rem;">
                                    <label>Footer Note Text</label>
                                    <textarea id="dms_footer_note" oninput="markUnsaved()">No matter the size or nature of your business, we have a Document Management System to streamline your workflow, enhance collaboration, and safeguard your valuable information.</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Footer Note CTA Text (italic)</label>
                                    <input type="text" id="dms_footer_cta" value="Get in touch with us today to discover the perfect DMS solution tailored to your needs!" oninput="markUnsaved()">
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>
                        <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.85rem;"><i class="fas fa-th" style="margin-right:0.4rem;color:var(--gold);"></i> DMS Offering Cards</div>
                        <div id="dms-cards-list" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"></div>
                        <button class="add-item-btn" onclick="addDmsCard()"><i class="fas fa-plus"></i> Add DMS Card</button>
                    </div>
                </div>
            </div>

            <!-- SCANNER LEASING -->
            <div class="tab-content" id="svc-scanner" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-scanner"></i></div>
                            <div><div class="card-title">Service 03 — Document Scanner Leasing</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div>
                                <div class="form-group">
                                    <label>Section Eyebrow</label>
                                    <input type="text" id="scanner_eyebrow" value="Service 03" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Section Title</label>
                                    <input type="text" id="scanner_title" value="Document Scanner Leasing" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Paragraph 1</label>
                                    <textarea id="scanner_p1" class="tall" oninput="markUnsaved()">At MIC, we understand the importance of efficiency and productivity in today's fast-paced business environment. That's why we offer top-of-the-line document scanner leasing services to streamline your document management processes.</textarea>
                                </div>
                                <div class="form-group">
                                    <label>Paragraph 2</label>
                                    <textarea id="scanner_p2" class="tall" oninput="markUnsaved()">Whether you're a small startup, a medium-sized enterprise, or a large corporation, our tailored leasing solutions are designed to meet your specific needs — without the burden of capital expenditure.</textarea>
                                </div>

                                <div class="divider"></div>
                                <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.85rem;">Scanner Options</div>
                                <div class="list-editor" id="scanner-options-list"></div>
                                <button class="add-item-btn" onclick="addListItem('scanner-options-list')"><i class="fas fa-plus"></i> Add Option</button>
                            </div>
                            <div>
                                <div class="form-group">
                                    <label>Offer Strip Text</label>
                                    <input type="text" id="scanner_offer" value="Scanner Leasing & Scanner Direct Selling — flexible options designed around your business needs." oninput="markUnsaved()">
                                </div>

                                <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin:1rem 0 0.6rem;">Benefits List</div>
                                <div class="list-editor" id="scanner-benefits-list"></div>
                                <button class="add-item-btn" onclick="addListItem('scanner-benefits-list')"><i class="fas fa-plus"></i> Add Benefit</button>

                                <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin:1rem 0 0.6rem;">Advantages Grid Items</div>
                                <div class="list-editor" id="scanner-adv-list"></div>
                                <button class="add-item-btn" onclick="addListItem('scanner-adv-list')"><i class="fas fa-plus"></i> Add Advantage</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SYSTEM INTEGRATION -->
            <div class="tab-content" id="svc-si" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-network-wired"></i></div>
                            <div><div class="card-title">Service 04 — System Integration</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div>
                                <div class="form-group">
                                    <label>Section Eyebrow</label>
                                    <input type="text" id="si_eyebrow" value="Service 04" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Section Title</label>
                                    <input type="text" id="si_title" value="System Integration Services" oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Quote / Tagline</label>
                                    <input type="text" id="si_quote" value="One Platform, Infinite Possibilities." oninput="markUnsaved()">
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                    <textarea id="si_desc" class="tall" oninput="markUnsaved()">At MIC, we understand the challenges businesses face when it comes to managing multiple systems and applications. That's why we're dedicated to providing cutting-edge system integration solutions that streamline processes, enhance collaboration, and drive business success.</textarea>
                                </div>

                                <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin:1rem 0 0.6rem;">Integration Services List</div>
                                <div class="list-editor" id="si-services-list"></div>
                                <button class="add-item-btn" onclick="addListItem('si-services-list')"><i class="fas fa-plus"></i> Add Service</button>
                            </div>
                            <div>
                                <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.75rem;">Our Approach Steps</div>
                                <div id="si-steps-list"></div>
                                <button class="add-item-btn" onclick="addApproachStep()"><i class="fas fa-plus"></i> Add Step</button>
                            </div>
                        </div>

                        <div class="divider"></div>
                        <div style="font-size:0.82rem;font-weight:600;color:var(--navy);margin-bottom:0.85rem;">Benefit Cards</div>
                        <div id="si-benefits-cards" style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;"></div>
                        <button class="add-item-btn" onclick="addSIBenefit()"><i class="fas fa-plus"></i> Add Benefit Card</button>
                    </div>
                </div>
            </div>

            <!-- CTA SECTION -->
            <div class="tab-content" id="svc-cta" style="display:none">
                <div class="card">
                    <div class="card-header">
                        <div class="card-header-left">
                            <div class="card-icon"><i class="fas fa-bullhorn"></i></div>
                            <div><div class="card-title">Bottom CTA Section</div></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Eyebrow</label>
                                <input type="text" id="cta_eyebrow" value="Let's Work Together" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Title (plain part)</label>
                                <input type="text" id="cta_title" value="Ready to" oninput="markUnsaved()">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Title (italic/gold word)</label>
                                <input type="text" id="cta_title_em" value="Elevate" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Title (suffix)</label>
                                <input type="text" id="cta_title_suffix" value="Your Operations?" oninput="markUnsaved()">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Description</label>
                            <textarea id="cta_desc" oninput="markUnsaved()">Contact our team today to discover which solution is right for your organization. We'll craft a tailored package that fits your needs and budget.</textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Primary Button Label</label>
                                <input type="text" id="cta_btn1_label" value="Get in Touch" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Primary Button URL</label>
                                <input type="text" id="cta_btn1_url" value="contactus.php" oninput="markUnsaved()">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Secondary Button Label</label>
                                <input type="text" id="cta_btn2_label" value="Learn About Us" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Secondary Button URL</label>
                                <input type="text" id="cta_btn2_url" value="about.php" oninput="markUnsaved()">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /panel-services -->

        <!-- ══ HEADER ══ -->
        <div class="content-panel" id="panel-header">
            <div class="page-header">
                <h1>Header & Navigation</h1>
                <p>Edit logo, nav links, and header styling.</p>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon"><i class="fas fa-image"></i></div>
                        <div><div class="card-title">Logo</div></div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div>
                            <div class="form-group">
                                <label>Logo Text (primary)</label>
                                <input type="text" id="header_logo_text" value="MULTIBIZ" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Logo Text (gold part)</label>
                                <input type="text" id="header_logo_gold" value="INTERNATIONAL" oninput="markUnsaved()">
                            </div>
                        </div>
                        <div>
                            <label>Logo Image</label>
                            <div class="img-upload-zone" ondragover="handleDrag(event,this)" ondragleave="handleDragLeave(this)" ondrop="handleDrop(event,'header_logo')">
                                <input type="file" accept="image/*" onchange="handleImgUpload(this,'header_logo')">
                                <div class="img-upload-icon"><i class="fas fa-image"></i></div>
                                <div class="img-upload-text"><strong>Click or drag</strong> to upload logo</div>
                                <div class="hint">PNG with transparent background recommended. Current: images/mbLogo.png</div>
                                <div id="preview_header_logo"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ FOOTER ══ -->
        <div class="content-panel" id="panel-footer">
            <div class="page-header">
                <h1>Footer</h1>
                <p>Edit footer company info, links, and contact details.</p>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-header-left">
                        <div class="card-icon"><i class="fas fa-grip-lines"></i></div>
                        <div><div class="card-title">Footer Content</div></div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div>
                            <div class="form-group">
                                <label>Company Name</label>
                                <input type="text" id="footer_company" value="MULTIBIZ INTERNATIONAL" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Company Tagline</label>
                                <textarea id="footer_tagline" oninput="markUnsaved()">Your trusted managed services partner. Delivering innovative solutions that drive efficiency, reduce costs, and enhance productivity.</textarea>
                            </div>
                            <div class="form-group">
                                <label>Website</label>
                                <input type="url" id="footer_website" value="https://multibiz.global" oninput="markUnsaved()">
                            </div>
                        </div>
                        <div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="tel" id="footer_phone" value="+63 917 544 1674" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" id="footer_email" value="inquiry@multibiz.global" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Facebook Page Name</label>
                                <input type="text" id="footer_fb" value="Multibiz International Corporation" oninput="markUnsaved()">
                            </div>
                            <div class="form-group">
                                <label>Copyright Text</label>
                                <input type="text" id="footer_copyright" value="MULTIBIZ INTERNATIONAL CORPORATION. All Rights Reserved." oninput="markUnsaved()">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main -->

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ─────────────────────────────────────────
// PANEL & TAB NAVIGATION
// ─────────────────────────────────────────
function showPanel(id, btn) {
    document.querySelectorAll('.content-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('panel-' + id).classList.add('active');
    if (btn) btn.classList.add('active');

    const titles = {
        dashboard: ['Dashboard', 'Overview'],
        about:     ['About Page', 'Content Editor'],
        services:  ['Services Page', 'Content Editor'],
        header:    ['Header / Nav', 'Global Settings'],
        footer:    ['Footer', 'Global Settings'],
    };
    const t = titles[id] || [id, ''];
    document.getElementById('topbarTitle').textContent = t[0];
    document.getElementById('topbarBread').textContent = t[1];
}

function switchTab(btn, targetId) {
    const panel = btn.closest('.content-panel');
    panel.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    panel.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    btn.classList.add('active');
    document.getElementById(targetId).style.display = 'block';
}

// ─────────────────────────────────────────
// SAVE STATUS
// ─────────────────────────────────────────
let unsaved = false;

function markUnsaved() {
    unsaved = true;
    const el = document.getElementById('saveStatus');
    el.className = 'save-status unsaved';
    el.innerHTML = '<span class="unsaved-dot"></span> Unsaved changes';
}

function markSaved() {
    unsaved = false;
    const el = document.getElementById('saveStatus');
    el.className = 'save-status saved';
    el.innerHTML = '<i class="fas fa-check-circle"></i> All changes saved';
    document.getElementById('savedCount').textContent = countFields();
}

function countFields() {
    return document.querySelectorAll('input[id], textarea[id]').length;
}

// ─────────────────────────────────────────
// SAVE & EXPORT
// ─────────────────────────────────────────
function saveAll() {
    const data = collectAllData();
    try {
        localStorage.setItem('multibiz_cms', JSON.stringify(data));
        markSaved();
        showToast('success', 'All changes saved successfully!');
    } catch(e) {
        showToast('error', 'Save failed: ' + e.message);
    }
}

function collectAllData() {
    const data = {};
    document.querySelectorAll('input[id], textarea[id], select[id]').forEach(el => {
        data[el.id] = el.value;
    });
    // Collect lists
    data['_lists'] = {};
    document.querySelectorAll('[id$="-list"], [id$="-items"], [id$="-cards"]').forEach(container => {
        if (container.id) {
            const items = [];
            container.querySelectorAll('input[type="text"]').forEach(i => items.push(i.value));
            if (items.length) data['_lists'][container.id] = items;
        }
    });
    // Collect images
    data['_images'] = window._uploadedImages || {};
    return data;
}

function exportJSON() {
    const data = collectAllData();
    const blob = new Blob([JSON.stringify(data, null, 2)], {type: 'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'multibiz-cms-content.json';
    a.click();
    URL.revokeObjectURL(url);
    showToast('info', 'Content exported as JSON!');
}

function loadSaved() {
    try {
        const raw = localStorage.getItem('multibiz_cms');
        if (!raw) return;
        const data = JSON.parse(raw);
        Object.entries(data).forEach(([k, v]) => {
            if (k.startsWith('_')) return;
            const el = document.getElementById(k);
            if (el) el.value = v;
        });
        markSaved();
    } catch(e) {}
}

// ─────────────────────────────────────────
// IMAGE UPLOAD
// ─────────────────────────────────────────
window._uploadedImages = {};

function handleImgUpload(input, key) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
        window._uploadedImages[key] = e.target.result;
        showImgPreview(key, e.target.result);
        markUnsaved();
    };
    reader.readAsDataURL(file);
}

function showImgPreview(key, src) {
    const container = document.getElementById('preview_' + key);
    if (!container) return;
    container.innerHTML = `
        <div class="img-preview-wrap">
            <img src="${src}" class="img-preview" alt="preview">
            <button class="img-preview-remove" onclick="removeImg('${key}')"><i class="fas fa-times"></i></button>
        </div>`;
}

function removeImg(key) {
    delete window._uploadedImages[key];
    const container = document.getElementById('preview_' + key);
    if (container) container.innerHTML = '';
    markUnsaved();
}

function handleDrag(e, el) { e.preventDefault(); el.classList.add('drag-over'); }
function handleDragLeave(el) { el.classList.remove('drag-over'); }
function handleDrop(e, key) {
    e.preventDefault();
    const el = e.currentTarget; el.classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = ev => {
        window._uploadedImages[key] = ev.target.result;
        showImgPreview(key, ev.target.result);
        markUnsaved();
    };
    reader.readAsDataURL(file);
}

// ─────────────────────────────────────────
// LIST EDITORS
// ─────────────────────────────────────────
function addListItem(containerId, value = '') {
    const container = document.getElementById(containerId);
    const item = document.createElement('div');
    item.className = 'list-item';
    item.innerHTML = `
        <input type="text" value="${value}" placeholder="Enter item..." oninput="markUnsaved()">
        <div class="list-item-actions">
            <button class="icon-btn" onclick="this.closest('.list-item').remove();markUnsaved()"><i class="fas fa-trash-alt"></i></button>
        </div>`;
    container.appendChild(item);
    markUnsaved();
}

// ─────────────────────────────────────────
// TIMELINE
// ─────────────────────────────────────────
let timelineCount = 0;
function addTimelineItem(year='', title='', desc='') {
    timelineCount++;
    const container = document.getElementById('timeline-items');
    const d = document.createElement('div');
    d.className = 'section-editor open';
    d.id = 'timeline-' + timelineCount;
    d.innerHTML = `
        <div class="section-editor-header" onclick="toggleSection(this)">
            <div class="section-editor-title"><span class="badge">Milestone</span> ${year || 'New Milestone'}</div>
            <div style="display:flex;align-items:center;gap:0.5rem;">
                <button class="btn btn-danger btn-sm" onclick="event.stopPropagation();this.closest('.section-editor').remove();markUnsaved()"><i class="fas fa-trash"></i></button>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
        </div>
        <div class="section-editor-body">
            <div class="form-row">
                <div class="form-group">
                    <label>Year</label>
                    <input type="text" value="${year}" placeholder="e.g. 1999" oninput="markUnsaved()">
                </div>
                <div class="form-group">
                    <label>Milestone Title</label>
                    <input type="text" value="${title}" placeholder="e.g. Company Founded" oninput="markUnsaved()">
                </div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea oninput="markUnsaved()">${desc}</textarea>
            </div>
        </div>`;
    container.appendChild(d);
    markUnsaved();
}

// ─────────────────────────────────────────
// INDUSTRIES
// ─────────────────────────────────────────
let industryCount = 0;
function addIndustry(name='', icon='fas fa-building') {
    industryCount++;
    const container = document.getElementById('industries-list');
    const d = document.createElement('div');
    d.className = 'brand-card-editor';
    d.innerHTML = `
        <div class="brand-card-editor-header">
            <span class="bname">Industry</span>
            <button class="btn btn-danger btn-sm" onclick="this.closest('.brand-card-editor').remove();markUnsaved()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="brand-card-editor-body">
            <div class="form-group">
                <label>Industry Name</label>
                <input type="text" value="${name}" placeholder="e.g. Healthcare" oninput="markUnsaved()">
            </div>
            <div class="form-group">
                <label>Icon (FA class)</label>
                <input type="text" value="${icon}" placeholder="fas fa-hospital" oninput="markUnsaved()">
            </div>
        </div>`;
    container.appendChild(d);
    markUnsaved();
}

// ─────────────────────────────────────────
// CERTIFICATIONS / AWARDS
// ─────────────────────────────────────────
function addCertification(name='', issuer='', year='') {
    const container = document.getElementById('certifications-list');
    const d = document.createElement('div');
    d.className = 'brand-card-editor';
    d.innerHTML = `
        <div class="brand-card-editor-header">
            <span class="bname">Certification</span>
            <button class="btn btn-danger btn-sm" onclick="this.closest('.brand-card-editor').remove();markUnsaved()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="brand-card-editor-body">
            <div class="form-group"><label>Certification Name</label><input type="text" value="${name}" placeholder="e.g. ISO 9001" oninput="markUnsaved()"></div>
            <div class="form-row">
                <div class="form-group"><label>Issuing Body</label><input type="text" value="${issuer}" placeholder="Issuer" oninput="markUnsaved()"></div>
                <div class="form-group"><label>Year</label><input type="text" value="${year}" placeholder="2023" oninput="markUnsaved()"></div>
            </div>
            <div class="form-group">
                <label>Badge Image</label>
                <div class="img-upload-zone" style="padding:1rem;" ondragover="handleDrag(event,this)" ondragleave="handleDragLeave(this)">
                    <input type="file" accept="image/*" onchange="handleImgUpload(this,'cert_img_${Date.now()}')">
                    <div class="img-upload-text"><strong>Upload badge</strong> (optional)</div>
                </div>
            </div>
        </div>`;
    container.appendChild(d);
    markUnsaved();
}

function addAward(name='', org='', year='') {
    const container = document.getElementById('awards-list');
    const d = document.createElement('div');
    d.className = 'brand-card-editor';
    d.innerHTML = `
        <div class="brand-card-editor-header">
            <span class="bname">Award</span>
            <button class="btn btn-danger btn-sm" onclick="this.closest('.brand-card-editor').remove();markUnsaved()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="brand-card-editor-body">
            <div class="form-group"><label>Award Name</label><input type="text" value="${name}" placeholder="e.g. Best Managed Print Provider" oninput="markUnsaved()"></div>
            <div class="form-row">
                <div class="form-group"><label>Awarding Organization</label><input type="text" value="${org}" placeholder="Organization" oninput="markUnsaved()"></div>
                <div class="form-group"><label>Year</label><input type="text" value="${year}" placeholder="2024" oninput="markUnsaved()"></div>
            </div>
        </div>`;
    container.appendChild(d);
    markUnsaved();
}

// ─────────────────────────────────────────
// BRAND CARDS
// ─────────────────────────────────────────
const defaultBrands = [
    {name:'Canon', program:'Total Guarantee', tagline:'Supplying Printers to Suit Various Needs', desc:'Canon provides a comprehensive approach to optimize printer fleets and has a deep history of success in printer technology.'},
    {name:'FUJIFILM FSMA', program:'Fujifilm Smart Managed Accounts', tagline:'Producing Detailed Printouts in Vibrant Colors', desc:'FUJIFILM business innovation can manage cost and improve efficiency while creating a secure print environment.'},
    {name:'Epson EasyCare Mono', program:'Print-All-You-Can', tagline:'Unlimited Prints at a Fixed Monthly Fee', desc:'Get unlimited monochrome prints at a fixed monthly cost, covering consumables, repairs, and maintenance.'},
    {name:'HP MPS', program:'Managed Print Services', tagline:'Advance Hybrid Work Strategy', desc:'HP manages, secures, and optimizes the entire fleet of devices across your home and office workforce.'},
    {name:'Aicon Savers MPS', program:'Global MPS Network', tagline:'Connecting Global Providers', desc:'Aicon Savers helps to improve employee productivity, reduce print-related expenses, and enhance network integration.'},
    {name:'Brother', program:'Toner Management Program', tagline:'Professional Printing Solutions', desc:'Discover hidden costs and regain control of your printing environment with Brother.'},
    {name:'RICOH', program:'Pay-Per-Click', tagline:'The Right Information in the Right Place', desc:'Optimize the efficiency of your information management with Ricoh.'},
    {name:'Epson EasyCare 360', program:'All-in-One Print Management', tagline:'Hassle-Free Print Management Solution', desc:'This all-in-one system efficiently oversees printing, unit status, and handles consumables for smooth operations.'},
];

function addBrandCard(b = {}) {
    const container = document.getElementById('brand-cards-editor');
    const id = Date.now();
    const d = document.createElement('div');
    d.className = 'brand-card-editor';
    d.innerHTML = `
        <div class="brand-card-editor-header">
            <span class="bname">${b.name || 'Brand Card'}</span>
            <button class="btn btn-danger btn-sm" onclick="this.closest('.brand-card-editor').remove();markUnsaved()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="brand-card-editor-body">
            <div class="form-row">
                <div class="form-group"><label>Brand Name</label><input type="text" value="${b.name||''}" placeholder="Canon" oninput="this.closest('.brand-card-editor').querySelector('.bname').textContent=this.value;markUnsaved()"></div>
                <div class="form-group"><label>Program Name</label><input type="text" value="${b.program||''}" placeholder="Total Guarantee" oninput="markUnsaved()"></div>
            </div>
            <div class="form-group"><label>Tagline</label><input type="text" value="${b.tagline||''}" oninput="markUnsaved()"></div>
            <div class="form-group"><label>Description</label><textarea oninput="markUnsaved()">${b.desc||''}</textarea></div>
            <div class="form-group"><label>Name Color (hex)</label><input type="text" value="#CC0000" placeholder="#CC0000" oninput="markUnsaved()"></div>
            <div class="form-group">
                <label>Brand Logo Image</label>
                <div class="img-upload-zone" style="padding:0.85rem;" ondragover="handleDrag(event,this)" ondragleave="handleDragLeave(this)" ondrop="handleDrop(event,'brand_${id}')">
                    <input type="file" accept="image/*" onchange="handleImgUpload(this,'brand_${id}')">
                    <div class="img-upload-text"><strong>Upload logo</strong></div>
                    <div id="preview_brand_${id}"></div>
                </div>
            </div>
        </div>`;
    container.appendChild(d);
    markUnsaved();
}

// ─────────────────────────────────────────
// DMS CARDS
// ─────────────────────────────────────────
const defaultDmsCards = [
    {title:'Basic DMS', icon:'fas fa-store', desc:'Perfect for small businesses and startups. Provides essential document organization and storage features at an affordable price point.'},
    {title:'Standard DMS', icon:'fas fa-building', desc:'Designed for growing businesses. Offers advanced document management capabilities, including version control, user permissions, and search functionalities.'},
    {title:'Enterprise DMS', icon:'fas fa-city', desc:'Tailored for large corporations and organizations with complex document management needs. Provides scalability, security, and customization options.'},
    {title:'Industry-Specific DMS', icon:'fas fa-users-cog', desc:'Tailored for specific industries like healthcare, legal, and finance, these DMS solutions include features and compliance standards necessary for each sector.'},
    {title:'Cloud-Based DMS', icon:'fas fa-cloud', desc:'Embrace flexibility and accessibility. Securely access, share, and manage documents from anywhere, at any time, with our Cloud-Based DMS.'},
    {title:'On-Premises DMS', icon:'fas fa-server', desc:'For organizations requiring complete control over their document management infrastructure, our On-Premises DMS offers a robust solution installed directly on your servers.'},
];

function addDmsCard(c = {}) {
    const container = document.getElementById('dms-cards-list');
    const d = document.createElement('div');
    d.className = 'brand-card-editor';
    d.innerHTML = `
        <div class="brand-card-editor-header">
            <span class="bname">${c.title||'DMS Card'}</span>
            <button class="btn btn-danger btn-sm" onclick="this.closest('.brand-card-editor').remove();markUnsaved()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="brand-card-editor-body">
            <div class="form-row">
                <div class="form-group"><label>Card Title</label><input type="text" value="${c.title||''}" oninput="this.closest('.brand-card-editor').querySelector('.bname').textContent=this.value;markUnsaved()"></div>
                <div class="form-group"><label>Icon (FA class)</label><input type="text" value="${c.icon||'fas fa-folder'}" oninput="markUnsaved()"></div>
            </div>
            <div class="form-group"><label>Description</label><textarea oninput="markUnsaved()">${c.desc||''}</textarea></div>
        </div>`;
    container.appendChild(d);
    markUnsaved();
}

// ─────────────────────────────────────────
// SYSTEM INTEGRATION
// ─────────────────────────────────────────
const defaultSISteps = [
    {title:'Discovery & Analysis', desc:'Comprehensive review of your existing systems, processes, and business goals.'},
    {title:'Strategy Design', desc:'Tailored integration strategy aligned to your objectives and technology investments.'},
    {title:'Implementation', desc:'Seamless deployment with minimal disruption to your day-to-day operations.'},
    {title:'Ongoing Support', desc:'Continuous monitoring, optimization, and support to ensure long-term success.'},
];
const defaultSIBenefits = [
    {title:'Efficiency', icon:'fas fa-bolt', desc:'Say goodbye to manual data entry and redundant processes. Our system integration solutions automate workflows and optimize efficiency.'},
    {title:'Collaboration', icon:'fas fa-users', desc:'Foster collaboration and communication among teams with seamless data sharing and real-time access to information.'},
    {title:'Insights', icon:'fas fa-chart-line', desc:'Gain actionable insights into your business performance with integrated analytics and reporting capabilities.'},
    {title:'Scalability', icon:'fas fa-expand-arrows-alt', desc:'Scale your systems and operations seamlessly as your business grows, without costly migrations or overhauls.'},
    {title:'Security', icon:'fas fa-lock', desc:'Protect your sensitive data and ensure compliance with industry regulations through robust security measures.'},
    {title:'Connectivity', icon:'fas fa-network-wired', desc:'Connect all your business applications, cloud services, and on-premise systems into a single unified platform.'},
];

let stepCount = 0;
function addApproachStep(s = {}) {
    stepCount++;
    const container = document.getElementById('si-steps-list');
    const d = document.createElement('div');
    d.className = 'section-editor';
    d.style.marginBottom = '0.5rem';
    d.innerHTML = `
        <div class="section-editor-header" onclick="toggleSection(this)" style="padding:0.55rem 0.85rem;">
            <div class="section-editor-title"><span class="badge">Step ${stepCount}</span> ${s.title||'New Step'}</div>
            <div style="display:flex;align-items:center;gap:0.4rem;">
                <button class="btn btn-danger btn-sm" onclick="event.stopPropagation();this.closest('.section-editor').remove();markUnsaved()"><i class="fas fa-trash"></i></button>
                <i class="fas fa-chevron-down section-chevron"></i>
            </div>
        </div>
        <div class="section-editor-body">
            <div class="form-group"><label>Step Title</label><input type="text" value="${s.title||''}" oninput="markUnsaved()"></div>
            <div class="form-group"><label>Step Description</label><textarea oninput="markUnsaved()">${s.desc||''}</textarea></div>
        </div>`;
    container.appendChild(d);
    markUnsaved();
}

function addSIBenefit(b = {}) {
    const container = document.getElementById('si-benefits-cards');
    const d = document.createElement('div');
    d.className = 'brand-card-editor';
    d.innerHTML = `
        <div class="brand-card-editor-header">
            <span class="bname">${b.title||'Benefit'}</span>
            <button class="btn btn-danger btn-sm" onclick="this.closest('.brand-card-editor').remove();markUnsaved()"><i class="fas fa-trash"></i></button>
        </div>
        <div class="brand-card-editor-body">
            <div class="form-row">
                <div class="form-group"><label>Title</label><input type="text" value="${b.title||''}" oninput="this.closest('.brand-card-editor').querySelector('.bname').textContent=this.value;markUnsaved()"></div>
                <div class="form-group"><label>Icon (FA class)</label><input type="text" value="${b.icon||'fas fa-star'}" oninput="markUnsaved()"></div>
            </div>
            <div class="form-group"><label>Description</label><textarea oninput="markUnsaved()">${b.desc||''}</textarea></div>
        </div>`;
    container.appendChild(d);
    markUnsaved();
}

// ─────────────────────────────────────────
// TOGGLE COLLAPSIBLE SECTIONS
// ─────────────────────────────────────────
function toggleSection(header) {
    const editor = header.closest('.section-editor');
    editor.classList.toggle('open');
}

// ─────────────────────────────────────────
// TOAST NOTIFICATIONS
// ─────────────────────────────────────────
function showToast(type, msg) {
    const icons = { success:'fa-check-circle', error:'fa-exclamation-circle', info:'fa-info-circle' };
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `<i class="fas ${icons[type]||'fa-bell'}"></i> ${msg}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'slideOut 0.3s ease forwards';
        setTimeout(() => toast.remove(), 300);
    }, 3200);
}

// ─────────────────────────────────────────
// PREVIEW
// ─────────────────────────────────────────
function previewChanges() {
    const panel = document.querySelector('.content-panel.active').id.replace('panel-','');
    const urls = { services: '../services.php', about: '../about.php', header: '../index.php', footer: '../index.php' };
    const url = urls[panel] || '../services.php';
    window.open(url, '_blank');
    showToast('info', 'Opening live page in new tab…');
}

// ─────────────────────────────────────────
// INIT — POPULATE DEFAULT DATA
// ─────────────────────────────────────────
function initDefaultData() {
    // Core values
    ['Integrity', 'Innovation', 'Customer Focus', 'Excellence', 'Partnership'].forEach(v => addListItem('values-list', v));

    // Timeline
    [{year:'1999', title:'Company Founded', desc:'MultiBiz International Corporation was established in Manila.'},
     {year:'2005', title:'Expanded to MPS', desc:'Launched our Managed Print Services division with Canon partnership.'},
     {year:'2012', title:'DMS Division Launch', desc:'Introduced Document Management System solutions to the portfolio.'},
     {year:'2018', title:'System Integration', desc:'Added System Integration Services to meet growing enterprise demand.'},
     {year:'2023', title:'9 Brand Partners', desc:'Expanded brand portfolio to nine leading global manufacturers.'}
    ].forEach(m => addTimelineItem(m.year, m.title, m.desc));

    // Industries
    [{name:'Banking & Finance', icon:'fas fa-university'},{name:'Healthcare', icon:'fas fa-hospital'},{name:'Education', icon:'fas fa-graduation-cap'},
     {name:'Government', icon:'fas fa-landmark'},{name:'Retail', icon:'fas fa-store'},{name:'Manufacturing', icon:'fas fa-industry'},
     {name:'Legal', icon:'fas fa-balance-scale'},{name:'Real Estate', icon:'fas fa-home'},{name:'Logistics', icon:'fas fa-truck'}
    ].forEach(i => addIndustry(i.name, i.icon));

    // Certifications
    [{name:'ISO 9001:2015', issuer:'Bureau Veritas', year:'2022'},{name:'Canon Authorized Dealer', issuer:'Canon Philippines', year:'2023'}]
        .forEach(c => addCertification(c.name, c.issuer, c.year));

    // Awards
    [{name:'Best Managed Print Provider', org:'Canon Philippines', year:'2023'},{name:'Top IT Solutions Partner', org:'HP Philippines', year:'2022'}]
        .forEach(a => addAward(a.name, a.org, a.year));

    // MPS Packages
    ['Subscription', 'Consumable Pack', 'Click Charge', 'Consumable Support (CSP)'].forEach(p => addListItem('mps-packages-list', p));

    // Brand cards
    defaultBrands.forEach(b => addBrandCard(b));

    // DMS cards
    defaultDmsCards.forEach(c => addDmsCard(c));

    // Scanner options
    ['Automatic Document Feeder Scanner','Book Overhead Scanner','ADF-Flatbed Dual Scanner','Large Format Scanner (for Blueprints)']
        .forEach(o => addListItem('scanner-options-list', o));
    ['No capital expenditure','Unlimited scanning','Inclusive of consumables','Phone and onsite support','Preventive maintenance support','Back-up scanner']
        .forEach(b => addListItem('scanner-benefits-list', b));
    ['Cost Savings','Latest Technology','Flexibility','Maintenance & Support','Back-up Scanner','Parts & Consumables']
        .forEach(a => addListItem('scanner-adv-list', a));

    // SI
    ['Application Integration','Cloud Integration','Custom Integration Solutions','Compliance Management','Analytics & Reporting','Reduced Administrative Burden']
        .forEach(s => addListItem('si-services-list', s));
    defaultSISteps.forEach(s => addApproachStep(s));
    defaultSIBenefits.forEach(b => addSIBenefit(b));
}

// ─────────────────────────────────────────
// BOOT
// ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initDefaultData();
    loadSaved();
    markSaved();

    // Auto-save every 30s
    setInterval(() => { if (unsaved) saveAll(); }, 30000);

    // Keyboard shortcut: Ctrl+S
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key === 's') { e.preventDefault(); saveAll(); }
    });
});
</script>
</body>
</html>