<div class="wf">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Space+Mono:ital,wght@0,400;0,700;1,400&family=DM+Sans:wght@300;400;500&display=swap');

        body {
            padding: 0;
        }

        /* ─── CSS Variables ─── */
        .wf {
            --bg: #0a0c10;
            --bg2: #10141c;
            --bg3: #161b26;
            --surface: #1c2333;
            --border: #2a3347;
            --text: #e8eaf0;
            --muted: #7a8499;
            --accent: #00d4aa;
            --accent2: #0099ff;
            --accent3: #ff6b35;
            --code-bg: #0d1117;
            --code-text: #79c0ff;
            --green: #3dd68c;
            --red: #ff5555;
            --shadow: 0 8px 40px rgba(0, 0, 0, 0.5);
            --radius: 12px;
            --font-display: 'Space Mono', monospace;
            --font-mono: 'Space Mono', monospace;
            --font-body: 'DM Sans', sans-serif;
            --transition: 0.25s cubic-bezier(.4, 0, .2, 1);
            --accent-label: var(--accent);
            --green-label: var(--green);
        }

        .wf[data-theme="light"],
        html[data-theme="light"] .wf,
        body[data-theme="light"] .wf {
            --bg: #f0f2f7;
            --bg2: #e4e8f0;
            --bg3: #d8dde8;
            --surface: #ffffff;
            --border: #c8cfe0;
            --text: #0f1520;
            --muted: #5a6580;
            --code-bg: #1c2333;
            --shadow: 0 8px 40px rgba(0, 0, 0, 0.12);
            /* Badges/pills: color más oscuro para contraste sobre fondo claro */
            --accent-label: #005a48;
            --green-label: #145e35;
        }

        /* ─── Reset & Base ─── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
            font-size: 16px;
        }

        .wf {
            background: var(--bg);
            color: var(--text);
            font-family: var(--font-body);
            line-height: 1.6;
            min-height: 100vh;
            transition: background var(--transition), color var(--transition);
            overflow-x: hidden;
            position: relative;
            isolation: isolate;
            width: 100%;
        }

        .wf>* {
            position: relative;
            z-index: 1;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        code,
        pre {
            font-family: var(--font-mono);
        }

        /* ─── Grid noise texture overlay ─── */
        .wf::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.5;
        }

        /* ─── NAV ─── */
        nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            transition: background var(--transition);
        }

        .wf[data-theme="light"] nav,
        html[data-theme="light"] .wf nav,
        body[data-theme="light"] .wf nav {
            background: var(--bg);
        }

        .nav-inner {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 2rem;
            height: 64px;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .logo {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.25rem;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .logo-mark {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            color: #000;
            font-weight: 700;
        }

        .logo-version {
            font-family: var(--font-mono);
            font-size: 0.65rem;
            background: var(--surface);
            border: 1px solid var(--border);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--accent);
            margin-left: 0.25rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .nav-link {
            padding: 0.4rem 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--muted);
            transition: color var(--transition), background var(--transition);
            white-space: nowrap;
            cursor: pointer;
        }

        .nav-link:hover {
            color: var(--text);
            background: var(--surface);
        }

        /* Dropdown */
        .dropdown {
            position: relative;
        }

        .dropdown-toggle {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .dropdown-toggle svg {
            transition: transform var(--transition);
        }

        .dropdown:hover .dropdown-toggle svg,
        .dropdown.open .dropdown-toggle svg {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 50%;
            transform: translateX(-50%) translateY(-6px);
            opacity: 0;
            pointer-events: none;
            background: var(--bg2);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.5rem;
            min-width: 200px;
            box-shadow: var(--shadow);
            transition: opacity 0.2s, transform 0.2s;
            z-index: 200;
        }

        .dropdown:hover .dropdown-menu,
        .dropdown.open .dropdown-menu {
            opacity: 1;
            pointer-events: all;
            transform: translateX(-50%) translateY(0);
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--muted);
            transition: all var(--transition);
            cursor: pointer;
        }

        .dropdown-item:hover {
            background: var(--surface);
            color: var(--text);
        }

        .dropdown-item .dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent);
            flex-shrink: 0;
        }

        /* User menu */
        .user-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem 0.35rem 0.35rem;
            border-radius: 50px;
            background: var(--surface);
            border: 1px solid var(--border);
            cursor: pointer;
            font-size: 0.85rem;
            transition: all var(--transition);
        }

        .user-btn:hover {
            border-color: var(--accent);
        }

        .avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: #000;
            flex-shrink: 0;
        }

        .user-dropdown-menu {
            min-width: 180px;
            left: auto;
            right: 0;
            transform: translateY(-6px);
        }

        .dropdown:hover .user-dropdown-menu {
            transform: translateY(0);
        }

        .divider {
            height: 1px;
            background: var(--border);
            margin: 0.35rem 0.5rem;
        }

        /* Theme & Lang toggles */
        .nav-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .icon-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all var(--transition);
            color: var(--muted);
        }

        .icon-btn:hover {
            background: var(--surface);
            color: var(--text);
            border-color: var(--accent);
        }

        .lang-btn {
            padding: 0 0.75rem;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: 0.75rem;
            cursor: pointer;
            transition: all var(--transition);
        }

        .lang-btn:hover {
            background: var(--surface);
            color: var(--text);
            border-color: var(--accent);
        }

        /* Hamburger */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 4px;
            background: none;
            border: none;
            color: var(--text);
        }

        .hamburger span {
            display: block;
            width: 22px;
            height: 2px;
            background: currentColor;
            border-radius: 2px;
            transition: all var(--transition);
        }

        /* ─── HERO ─── */
        .hero {
            position: relative;
            min-height: auto;
            display: flex;
            align-items: flex-start;
            padding: 5.5rem 2rem 5rem;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 60% 40%, rgba(0, 212, 170, 0.08) 0%, transparent 60%),
                radial-gradient(ellipse 50% 40% at 20% 80%, rgba(0, 153, 255, 0.06) 0%, transparent 50%);
            pointer-events: none;
        }

        .hero-grid {
            position: absolute;
            inset: 0;
            background-image: linear-gradient(var(--border) 1px, transparent 1px), linear-gradient(90deg, var(--border) 1px, transparent 1px);
            background-size: 60px 60px;
            opacity: 0.15;
            pointer-events: none;
        }

        .hero-inner {
            position: relative;
            max-width: 1280px;
            margin: 0 auto;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
        }

        .hero-mobile-tabs {
            display: none;
            gap: 0.5rem;
            padding: 0.35rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 999px;
            width: max-content;
        }

        .hero-mobile-tab {
            border: 0;
            background: transparent;
            color: var(--muted);
            font-family: var(--font-mono);
            font-size: 0.72rem;
            text-transform: lowercase;
            letter-spacing: 0.08em;
            border-radius: 999px;
            padding: 0.45rem 0.9rem;
            cursor: pointer;
            transition: all var(--transition);
        }

        .hero-mobile-tab.active {
            background: var(--accent);
            color: #000;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            border: 1px solid rgba(0, 212, 170, 0.3);
            background: rgba(0, 212, 170, 0.06);
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: var(--accent-label);
            margin-bottom: 1.5rem;
            animation: fadeUp 0.6s both;
        }

        .wf[data-theme="light"] .hero-badge,
        html[data-theme="light"] .wf .hero-badge,
        body[data-theme="light"] .wf .hero-badge {
            border-color: rgba(0, 90, 72, 0.3);
            background: rgba(0, 90, 72, 0.07);
            color: #005a48;
        }

        .wf[data-theme="light"] .hero-meta-value,
        html[data-theme="light"] .wf .hero-meta-value,
        body[data-theme="light"] .wf .hero-meta-value {
            color: #005a48;
        }

        .wf[data-theme="light"] h1 .accent-word,
        html[data-theme="light"] .wf h1 .accent-word,
        body[data-theme="light"] .wf h1 .accent-word {
            background: linear-gradient(90deg, #0077b6, #00a8e8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .wf[data-theme="light"] .mit-badge,
        html[data-theme="light"] .wf .mit-badge,
        body[data-theme="light"] .wf .mit-badge {
            background: rgba(0, 90, 72, 0.08);
            border-color: rgba(0, 90, 72, 0.25);
        }

        .wf[data-theme="light"] .section-tag,
        html[data-theme="light"] .wf .section-tag,
        body[data-theme="light"] .wf .section-tag {
            color: #005a48;
        }

        .wf[data-theme="light"] .feature-card,
        html[data-theme="light"] .wf .feature-card,
        body[data-theme="light"] .wf .feature-card {
            background: #ffffff;
            border-color: #d8dde8;
        }

        .wf[data-theme="light"] .feature-card h3,
        html[data-theme="light"] .wf .feature-card h3,
        body[data-theme="light"] .wf .feature-card h3 {
            color: #0a0c10;
        }

        .wf[data-theme="light"] .feature-card p,
        html[data-theme="light"] .wf .feature-card p,
        body[data-theme="light"] .wf .feature-card p {
            color: #5a6580;
        }

        .hero-badge .pulse {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1)
            }

            50% {
                opacity: 0.5;
                transform: scale(1.5)
            }
        }

        h1 {
            font-family: var(--font-display);
            font-size: clamp(1.85rem, 3.8vw, 3.5rem);
            font-weight: 700;
            line-height: 1.12;
            letter-spacing: -0.01em;
            margin-bottom: 1.5rem;
            animation: fadeUp 0.6s 0.1s both;
        }

        h1 .accent-word {
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-desc {
            font-size: 1.125rem;
            color: var(--muted);
            max-width: 520px;
            margin-bottom: 2.5rem;
            line-height: 1.7;
            animation: fadeUp 0.6s 0.2s both;
        }

        .hero-ctas {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            animation: fadeUp 0.6s 0.3s both;
        }

        .btn-primary {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            background: var(--accent);
            color: #000;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: transform var(--transition), opacity var(--transition);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .btn-secondary {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            background: transparent;
            color: var(--text);
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all var(--transition);
        }

        .btn-secondary:hover {
            border-color: var(--accent);
            background: var(--surface);
        }

        .hero-meta {
            display: flex;
            gap: 2rem;
            margin-top: 2.5rem;
            animation: fadeUp 0.6s 0.4s both;
        }

        .hero-meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .hero-meta-value {
            font-family: var(--font-display);
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--accent-label);
            letter-spacing: 0.02em;
        }

        .hero-meta-label {
            font-size: 0.8rem;
            color: var(--muted);
        }

        /* Code card */
        .hero-code {
            position: relative;
            animation: fadeLeft 0.8s 0.2s both;
        }

        @keyframes fadeLeft {
            from {
                opacity: 0;
                transform: translateX(30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .code-card {
            background: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow), 0 0 0 1px rgba(0, 212, 170, 0.1);
        }

        .code-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: #0d1117;
            border-bottom: 1px solid #2a3347;
        }

        .code-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .code-dot.r {
            background: #ff5f57;
        }

        .code-dot.y {
            background: #ffbd2e;
        }

        .code-dot.g {
            background: #28c840;
        }

        .code-filename {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            color: #7a8499;
            margin-left: auto;
        }

        /* El code-body siempre tiene fondo oscuro — los colores son fijos independientemente del tema */
        .code-body {
            padding: 1.25rem 1.5rem;
            font-size: 0.8rem;
            line-height: 1.8;
            overflow-x: auto;
            color: #c9d1d9;
        }

        .kw {
            color: #ff7b72;
        }

        .cls {
            color: #79c0ff;
        }

        .fn {
            color: #d2a8ff;
        }

        .str {
            color: #a5d6ff;
        }

        .cm {
            color: #8b949e;
            font-style: italic;
        }

        .ch {
            color: #00d4aa;
        }

        /* ─── SECTIONS COMMON ─── */
        section {
            padding: 6rem 2rem;
            position: relative;
            z-index: 1;
        }

        .section-inner {
            max-width: 1280px;
            margin: 0 auto;
        }

        .section-tag {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--accent-label);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-tag::before {
            content: '//';
            opacity: 0.5;
        }

        h2 {
            font-family: var(--font-display);
            font-size: clamp(1.4rem, 2.8vw, 2.1rem);
            font-weight: 700;
            letter-spacing: -0.01em;
            margin-bottom: 1rem;
        }

        .section-lead {
            font-size: 1.1rem;
            color: var(--muted);
            max-width: 600px;
            line-height: 1.7;
            margin-bottom: 3rem;
        }

        /* ─── INSTALL STRIP ─── */
        .install-strip {
            background: var(--bg2);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 1.5rem 2rem;
        }

        .install-inner {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .install-label {
            font-size: 0.85rem;
            color: var(--muted);
        }

        .install-cmd {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            background: var(--code-bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.6rem 1rem;
            font-family: var(--font-mono);
            font-size: 0.9rem;
            color: var(--accent);
        }

        .copy-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--muted);
            display: flex;
            align-items: center;
            padding: 0;
            transition: color var(--transition);
        }

        .copy-btn:hover {
            color: var(--accent);
        }

        .mit-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            background: rgba(61, 214, 140, 0.1);
            border: 1px solid rgba(61, 214, 140, 0.3);
            font-size: 0.8rem;
            color: var(--green-label);
            font-weight: 600;
        }

        /* ─── FEATURES ─── */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .feature-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.75rem;
            transition: all var(--transition);
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--accent), var(--accent2));
            transform: scaleX(0);
            transition: transform var(--transition);
        }

        .feature-card:hover {
            border-color: rgba(0, 212, 170, 0.3);
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.25rem;
            font-size: 1.25rem;
        }

        .feature-card h3 {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
        }

        .feature-card p {
            font-size: 0.875rem;
            color: var(--muted);
            line-height: 1.6;
        }

        /* ─── CONCEPTS ─── */
        .concepts-section {
            background: var(--bg2);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .concepts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
        }

        .concepts-note {
            margin-top: 1.5rem;
            padding: 1rem 1.15rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: color-mix(in srgb, var(--surface) 84%, transparent);
            color: var(--muted);
            font-size: 0.88rem;
            line-height: 1.65;
        }

        .concepts-note strong {
            color: var(--text);
        }

        .concept-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: all var(--transition);
        }

        .concept-card:hover {
            border-color: rgba(0, 212, 170, 0.35);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .concept-label {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-family: var(--font-mono);
            font-size: 0.68rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--accent-label);
            margin-bottom: 0.8rem;
        }

        .concept-label::before {
            content: '#';
            opacity: 0.7;
        }

        .concept-card h3 {
            font-family: var(--font-display);
            font-size: 1rem;
            margin-bottom: 0.45rem;
            letter-spacing: -0.01em;
        }

        .concept-card p {
            font-size: 0.86rem;
            color: var(--muted);
            line-height: 1.6;
        }

        /* ─── ARCHITECTURE ─── */
        .arch-section {
            background: var(--bg2);
        }

        .arch-diagram-wrap {
            background: #161d29;
            border: 1px solid #2f3a4f;
            border-radius: var(--radius);
            padding: 2.5rem;
            overflow-x: auto;
            --arch-surface: #1d2636;
            --arch-border: #32405a;
            --arch-core: #121925;
            --arch-node: #182131;
            --arch-text: #e8eef8;
            --arch-muted: #94a3bb;
            --arch-accent: #00d4aa;
            --arch-accent2: #0099ff;
            --border-svg: #5b6d8b;
            --accent-svg: #00d4aa;
            --bg-node: var(--arch-node);
            --bg-core: var(--arch-core);
        }

        .arch-svg {
            width: 100%;
            max-width: 1080px;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        /* ─── COMPONENTS TABLE ─── */
        .components-section {
            background: var(--bg2);
        }

        .comp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1rem;
        }

        .comp-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.875rem;
            transition: all var(--transition);
        }

        .comp-item:hover {
            border-color: var(--accent);
            background: var(--bg3);
        }

        .comp-name {
            font-family: var(--font-mono);
            color: var(--code-text);
            font-size: 0.8rem;
        }

        .comp-label {
            font-size: 0.8rem;
            color: var(--muted);
            margin-top: 0.1rem;
        }

        /* ─── TUTORIALS ─── */
        .tut-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .tut-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            cursor: pointer;
            transition: all var(--transition);
        }

        .tut-card:hover {
            border-color: var(--accent2);
            transform: translateY(-4px);
            box-shadow: var(--shadow);
        }

        .tut-thumb {
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            position: relative;
        }

        .tut-body {
            padding: 1.25rem;
        }

        .tut-tag {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 0.5rem;
        }

        .tut-card h3 {
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 0.4rem;
            letter-spacing: -0.01em;
        }

        .tut-card p {
            font-size: 0.825rem;
            color: var(--muted);
            line-height: 1.5;
        }

        .tut-footer {
            padding: 0 1.25rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .tut-meta {
            font-size: 0.75rem;
            color: var(--muted);
        }

        /* ─── COMMUNITY ─── */
        .community-section {
            background: var(--bg2);
        }

        .community-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: start;
        }

        .messages-feed {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-width: 640px;
            width: 100%;
        }

        .message {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.25rem;
            display: flex;
            gap: 1rem;
            transition: border-color var(--transition);
        }

        .message:hover {
            border-color: var(--border);
        }

        .message>div {
            min-width: 0;
        }

        .msg-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
            font-weight: 700;
            color: #000;
        }

        .msg-name {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .msg-time {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            color: var(--muted);
            margin-left: 0.5rem;
        }

        .msg-text {
            font-size: 0.875rem;
            color: var(--muted);
            margin-top: 0.3rem;
            line-height: 1.5;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .msg-code {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            background: var(--code-bg);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--code-text);
        }

        .community-cta {
            margin-top: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* ─── INSTITUTES ─── */
        .institutes-section {
            border-top: 1px solid var(--border);
        }

        .institutes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .institute-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 2rem;
            text-align: center;
            transition: all var(--transition);
        }

        .institute-card:hover {
            border-color: var(--accent2);
        }

        .institute-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            margin: 0 auto 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
        }

        .institute-icon img {
            width: 38px;
            height: 38px;
            object-fit: contain;
        }

        .institute-card h3 {
            font-family: var(--font-display);
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
        }

        .institute-card p {
            font-size: 0.85rem;
            color: var(--muted);
            line-height: 1.5;
        }

        /* ─── FOOTER ─── */
        footer {
            background: var(--bg2);
            border-top: 1px solid var(--border);
            padding: 3rem 2rem;
        }

        .footer-inner {
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
        }

        .footer-copy {
            font-size: 0.85rem;
            color: var(--muted);
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .footer-link {
            font-size: 0.85rem;
            color: var(--muted);
            transition: color var(--transition);
        }

        .footer-link:hover {
            color: var(--accent);
        }

        /* ─── TOAST ─── */
        .toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .toast {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: toastIn 0.3s, toastOut 0.3s 2.5s forwards;
            max-width: 320px;
        }

        @keyframes toastIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes toastOut {
            to {
                opacity: 0;
                transform: translateX(20px);
            }
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 900px) {
            .hero-inner {
                grid-template-columns: 1fr;
                gap: 3rem;
            }

            .community-layout {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .messages-feed {
                max-width: none;
            }

            .hero-mobile-tabs {
                display: inline-flex;
            }

            .hero-content,
            .hero-code {
                display: none;
            }

            .hero-inner.show-content .hero-content,
            .hero-inner.show-code .hero-code {
                display: block;
            }

            .hero-code {
                width: 100%;
            }

            .code-card {
                width: 100%;
            }

            .code-body {
                padding: 1rem 1.1rem;
                font-size: 0.75rem;
                line-height: 1.7;
                overflow-x: hidden;
            }

            .code-body pre {
                white-space: pre-wrap;
                word-break: break-word;
                overflow-wrap: anywhere;
            }

            h1 {
                font-size: 2.5rem;
            }

            .nav-links {
                display: none;
            }

            .nav-links.open {
                display: flex;
                flex-direction: column;
                align-items: flex-start;
                position: fixed;
                top: 64px;
                left: 0;
                right: 0;
                background: var(--bg2);
                border-bottom: 1px solid var(--border);
                padding: 1rem;
                gap: 0.25rem;
            }

            .hamburger {
                display: flex;
            }

            .dropdown-menu {
                left: 0;
                transform: none;
                position: static;
                box-shadow: none;
                opacity: 1;
                pointer-events: all;
                display: none;
                margin-top: 0.25rem;
                border-radius: 8px;
            }

            .dropdown.open .dropdown-menu {
                display: block;
            }

            .nav-controls {
                margin-left: auto;
            }
        }

        @media (max-width: 580px) {
            section {
                padding: 4rem 1.25rem;
            }

            .hero {
                padding: 5rem 1.25rem 3.5rem;
            }

            .code-header {
                padding: 0.65rem 0.85rem;
            }

            .code-filename {
                font-size: 0.7rem;
            }

            .code-body {
                padding: 0.9rem 0.95rem;
                font-size: 0.72rem;
                line-height: 1.62;
            }

            .hero-meta {
                gap: 1rem;
                flex-wrap: wrap;
            }

            .message {
                padding: 1rem;
                gap: 0.75rem;
            }

            .msg-name {
                font-size: 0.84rem;
            }

            .msg-text {
                font-size: 0.83rem;
                line-height: 1.45;
            }

            .footer-inner {
                flex-direction: column;
                align-items: flex-start;
            }

            .install-inner {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>

    <!-- ─── HERO ─── -->
    <section class="hero">
        <div class="hero-bg"></div>
        <div class="hero-grid"></div>
        <div class="hero-inner show-content" id="hero-inner">
            <div class="hero-mobile-tabs" role="tablist" aria-label="{{ t('welcome.ui.hero_views') }}">
                <button class="hero-mobile-tab active" id="hero-tab-content" type="button" data-hero-view="content"
                    role="tab" aria-selected="true">{{ t('welcome.ui.tab_content') }}</button>
                <button class="hero-mobile-tab" id="hero-tab-code" type="button" data-hero-view="code" role="tab"
                    aria-selected="false">{{ t('welcome.ui.tab_example') }}</button>
            </div>
            <div class="hero-content">
                <div class="hero-badge">
                    <span class="pulse"></span>
                    <span>{{ t('welcome.hero.badge') }}</span>
                </div>
                <h1>
                    <span>{{ t('welcome.hero.title1') }}</span><br>
                    <span class="accent-word">{{ t('welcome.hero.title2') }}</span><br>
                    <span>{{ t('welcome.hero.title3') }}</span>
                </h1>
                <p class="hero-desc">{{ t('welcome.hero.desc') }}</p>
                <div class="hero-ctas">
                    <button class="btn-primary"
                        onclick="document.getElementById('install-strip').scrollIntoView({behavior:'smooth'})">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <polyline points="4 17 10 11 4 5" />
                            <line x1="12" y1="19" x2="20" y2="19" />
                        </svg>
                        <span>{{ t('welcome.hero.cta1') }}</span>
                    </button>
                </div>
                <div class="hero-meta">
                    <div class="hero-meta-item">
                        <span class="hero-meta-value">v0.7.0</span>
                        <span class="hero-meta-label">{{ t('welcome.hero.meta.version') }}</span>
                    </div>
                    <div class="hero-meta-item">
                        <span class="hero-meta-value">MIT</span>
                        <span class="hero-meta-label">{{ t('welcome.hero.meta.license') }}</span>
                    </div>
                    <div class="hero-meta-item">
                        <span class="hero-meta-value">PHP 8.2+</span>
                        <span class="hero-meta-label">{{ t('welcome.hero.meta.req') }}</span>
                    </div>
                </div>
            </div>
            <div class="hero-code">
                <div class="code-card">
                    <div class="code-header">
                        <div class="code-dot r"></div>
                        <div class="code-dot y"></div>
                        <div class="code-dot g"></div>
                        <span class="code-filename">HelloScreen.php</span>
                    </div>
                    <div class="code-body">
                        <pre><span class="kw">class</span> <span class="cls">HelloScreen</span> <span class="kw">extends</span> <span class="cls">Screen</span>
{
  <span class="kw">protected function</span> <span class="fn">buildBaseUI</span>(
    <span class="cls">Container</span> <span class="ch">$container</span>
  ): <span class="kw">void</span> {
    <span class="ch">$container</span>-><span class="fn">layout</span>(<span class="cls">LayoutType</span>::VERTICAL)
             -><span class="fn">padding</span>(<span class="str">20</span>);

    <span class="ch">$container</span>-><span class="fn">add</span>(
      <span class="cls">UI</span>::<span class="fn">label</span>(<span class="str">'title'</span>)
        -><span class="fn">text</span>(<span class="str">'Welcome to USIM!'</span>)
        -><span class="fn">style</span>(<span class="str">'h1'</span>)
    );

    <span class="ch">$container</span>-><span class="fn">add</span>(
      <span class="cls">UI</span>::<span class="fn">button</span>(<span class="str">'hello_btn'</span>)
        -><span class="fn">label</span>(<span class="str">'Say Hello'</span>)
        -><span class="fn">primary</span>()
        -><span class="fn">action</span>(<span class="str">'greet'</span>)
    );
  }

  <span class="kw">public function</span> <span class="fn">onGreet</span>(<span class="kw">array</span> <span class="ch">$params</span>): <span class="kw">void</span>
  {
    <span class="ch">$this</span>-><span class="fn">toast</span>(<span class="str">'Hello, USIM!'</span>, <span class="str">'success'</span>);
  }
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── INSTALL STRIP ─── -->
    <div class="install-strip" id="install-strip">
        <div class="install-inner">
            <span class="install-label">{{ t('welcome.install.label') }}</span>
            <div class="install-cmd">
                <span>composer require idei/usim</span>
                <button class="copy-btn" onclick="copyCmd(this, 'composer require idei/usim')" title="{{ t('welcome.ui.copy') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" />
                        <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
                    </svg>
                </button>
            </div>
            <div class="install-cmd" style="margin-left:1rem;">
                <span>php artisan usim:install</span>
                <button class="copy-btn" onclick="copyCmd(this, 'php artisan usim:install')" title="{{ t('welcome.ui.copy') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                        stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" />
                        <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
                    </svg>
                </button>
            </div>
            <div class="mit-badge">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
                <span>{{ t('welcome.install.mit') }}</span>
            </div>
        </div>
        <div class="install-inner" style="margin-top:0.5rem; gap:0.75rem; flex-wrap:wrap;">
            <span class="install-label" style="opacity:.95;">{{ t('welcome.headless.title') }}</span>
            <div class="install-cmd">
                <span>USIM_HEADLESS_MODE=true</span>
                <button class="copy-btn" onclick="copyCmd(this, 'USIM_HEADLESS_MODE=true')" title="{{ t('welcome.ui.copy') }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="9" y="9" width="13" height="13" rx="2" />
                        <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" />
                    </svg>
                </button>
            </div>
            <span class="install-label" style="font-weight:500; opacity:.8;">
                {{ t('welcome.headless.flow') }}
            </span>
        </div>
    </div>

    <!-- ─── FEATURES ─── -->
    <section>
        <div class="section-inner">
            <div class="section-tag">{{ t('welcome.features.tag') }}</div>
            <h2>{{ t('welcome.features.title') }}</h2>
            <p class="section-lead">{{ t('welcome.features.lead') }}</p>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon" style="background:rgba(0,212,170,0.12)">🖥️</div>
                    <h3>{{ t('welcome.feat.serverui.title') }}</h3>
                    <p>{{ t('welcome.feat.serverui.desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" style="background:rgba(0,153,255,0.12)">⚡</div>
                    <h3>{{ t('welcome.feat.delta.title') }}</h3>
                    <p>{{ t('welcome.feat.delta.desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" style="background:rgba(210,168,255,0.12)">🎯</div>
                    <h3>{{ t('welcome.feat.events.title') }}</h3>
                    <p>{{ t('welcome.feat.events.desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" style="background:rgba(255,107,53,0.12)">🔐</div>
                    <h3>{{ t('welcome.feat.auth.title') }}</h3>
                    <p>{{ t('welcome.feat.auth.desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" style="background:rgba(61,214,140,0.12)">🧪</div>
                    <h3>{{ t('welcome.feat.testing.title') }}</h3>
                    <p>{{ t('welcome.feat.testing.desc') }}</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon" style="background:rgba(255,189,46,0.12)">🚀</div>
                    <h3>{{ t('welcome.feat.octane.title') }}</h3>
                    <p>{{ t('welcome.feat.octane.desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── CONCEPTS ─── -->
    <section class="concepts-section" id="concepts">
        <div class="section-inner">
            <div class="section-tag">{{ t('welcome.concepts.tag') }}</div>
            <h2>{{ t('welcome.concepts.title') }}</h2>
            <p class="section-lead">{{ t('welcome.concepts.lead') }}</p>
            <div class="concepts-grid">
                <article class="concept-card">
                    <span class="concept-label">USIM</span>
                    <h3>{{ t('welcome.concept.screen.title') }}</h3>
                    <p>{{ t('welcome.concept.screen.desc') }}</p>
                </article>
                <article class="concept-card">
                    <span class="concept-label">USIM</span>
                    <h3>{{ t('welcome.concept.lifecycle.title') }}</h3>
                    <p>{{ t('welcome.concept.lifecycle.desc') }}</p>
                </article>
                <article class="concept-card">
                    <span class="concept-label">USIM</span>
                    <h3>{{ t('welcome.concept.state.title') }}</h3>
                    <p>{{ t('welcome.concept.state.desc') }}</p>
                </article>
                <article class="concept-card">
                    <span class="concept-label">USIM</span>
                    <h3>{{ t('welcome.concept.handlers.title') }}</h3>
                    <p>{{ t('welcome.concept.handlers.desc') }}</p>
                </article>
                <article class="concept-card">
                    <span class="concept-label">USIM</span>
                    <h3>{{ t('welcome.concept.routing.title') }}</h3>
                    <p>{{ t('welcome.concept.routing.desc') }}</p>
                </article>
            </div>
            <p class="concepts-note"><strong>{{ t('welcome.concepts.note_label') }}</strong> {{ t('welcome.concepts.note') }}</p>
        </div>
    </section>

    <!-- ─── ARCHITECTURE ─── -->
    <section class="arch-section" id="architecture">
        <div class="section-inner">
            <div class="section-tag">{{ t('welcome.arch.tag') }}</div>
            <h2>{{ t('welcome.arch.title') }}</h2>
            <p class="section-lead">{{ t('welcome.arch.lead') }}</p>
            <div class="arch-diagram-wrap">
                <svg class="arch-svg" viewBox="0 0 860 620" xmlns="http://www.w3.org/2000/svg"
                    font-family="'Space Mono', monospace">
                    <defs>
                        <marker id="arr" markerWidth="8" markerHeight="8" refX="6" refY="3"
                            orient="auto">
                            <path d="M0,0 L0,6 L8,3 z" fill="var(--border-svg)" />
                        </marker>
                        <marker id="arr-accent" markerWidth="8" markerHeight="8" refX="6" refY="3"
                            orient="auto">
                            <path d="M0,0 L0,6 L8,3 z" fill="var(--accent-svg)" />
                        </marker>
                        <marker id="arr-up" markerWidth="8" markerHeight="8" refX="6" refY="3"
                            orient="auto-start-reverse">
                            <path d="M0,0 L0,6 L8,3 z" fill="var(--border-svg)" />
                        </marker>
                        <filter id="glow">
                            <feGaussianBlur stdDeviation="3" result="blur" />
                            <feComposite in="SourceGraphic" in2="blur" operator="over" />
                        </filter>
                    </defs>
                    <style>
                        .arch-svg .node-rect {
                            fill: var(--arch-surface);
                            stroke: var(--arch-border);
                            stroke-width: 1.5;
                        }

                        .arch-svg .node-rect-accent {
                            fill: var(--arch-surface);
                            stroke: var(--arch-accent);
                            stroke-width: 1.5;
                        }

                        .arch-svg .core-rect {
                            fill: var(--arch-core);
                            stroke: var(--arch-accent2);
                            stroke-width: 1.5;
                        }

                        .arch-svg .node-text {
                            fill: var(--arch-text);
                            font-size: 13px;
                        }

                        .arch-svg .node-sub {
                            fill: var(--arch-muted);
                            font-size: 10.5px;
                        }

                        .arch-svg .label-text {
                            fill: var(--arch-muted);
                            font-size: 10px;
                            letter-spacing: 0.08em;
                            text-transform: uppercase;
                        }

                        .arch-svg .conn-line {
                            stroke: var(--arch-border);
                            stroke-width: 1.5;
                            fill: none;
                            marker-end: url(#arr);
                        }

                        .arch-svg .conn-line-bi {
                            stroke: var(--arch-border);
                            stroke-width: 1.5;
                            fill: none;
                            marker-end: url(#arr);
                            marker-start: url(#arr-up);
                        }

                        .arch-svg .conn-accent {
                            stroke: var(--arch-accent);
                            stroke-width: 1.5;
                            fill: none;
                            stroke-dasharray: 5 3;
                            marker-end: url(#arr-accent);
                        }

                        .arch-svg .screen-rect {
                            fill: var(--arch-surface);
                            stroke: var(--arch-border);
                            stroke-width: 1.2;
                        }

                        .arch-svg .tag-pill {
                            fill: rgba(0, 212, 170, 0.12);
                            stroke: rgba(0, 212, 170, 0.35);
                            stroke-width: 1;
                        }

                        .arch-svg .tag-text {
                            fill: var(--arch-accent);
                            font-size: 9px;
                            letter-spacing: 0.05em;
                        }
                    </style>

                    <!-- ── BROWSER (user) ── -->
                    <g transform="translate(48, 52)">
                        <!-- stick figure -->
                        <circle cx="36" cy="16" r="11" fill="none" stroke="var(--border-svg)"
                            stroke-width="1.5" />
                        <line x1="36" y1="27" x2="36" y2="58"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <line x1="36" y1="36" x2="18" y2="50"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <line x1="36" y1="36" x2="54" y2="50"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <line x1="36" y1="58" x2="20" y2="76"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <line x1="36" y1="58" x2="52" y2="76"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <text x="36" y="93" text-anchor="middle" class="node-sub">Browser</text>
                    </g>

                    <!-- HTTP arrow + label -->
                    <rect x="132" y="76" width="148" height="26" rx="5" fill="var(--bg-node)"
                        stroke="var(--border-svg)" stroke-width="1" />
                    <text x="206" y="93" text-anchor="middle" class="node-sub"
                        style="font-size:10.5px">http://screen-a</text>
                    <line x1="116" y1="89" x2="132" y2="89" class="conn-line"
                        style="marker-start:url(#arr-up)" />
                    <line x1="280" y1="89" x2="312" y2="89" class="conn-line" />

                    <!-- ── WEB CLIENT ── -->
                    <g transform="translate(312, 46)">
                        <rect width="200" height="80" rx="10" class="node-rect-accent" />
                        <rect x="0" y="54" width="200" height="26" rx="0" fill="rgba(0,212,170,0.07)"
                            stroke="none" />
                        <text x="100" y="28" text-anchor="middle" class="node-text" font-weight="700">Web</text>
                        <text x="100" y="46" text-anchor="middle" class="node-sub">(html, css, js)</text>
                        <rect x="0" y="54" width="200" height="1" fill="var(--border-svg)" />
                        <text x="100" y="71" text-anchor="middle" class="node-sub"
                            style="fill:var(--arch-accent)">Render</text>
                    </g>

                    <!-- any other client note -->
                    <g transform="translate(48, 190)">
                        <rect width="162" height="66" rx="2" fill="var(--bg-node)"
                            stroke="var(--border-svg)" stroke-width="1" stroke-dasharray="4 2" />
                        <!-- dog-ear -->
                        <polygon points="144,0 162,0 162,18 144,18" fill="var(--bg-core)" stroke="var(--border-svg)"
                            stroke-width="1" />
                        <polygon points="144,0 162,18 144,18" fill="var(--border-svg)" opacity="0.4" />
                        <text x="12" y="22" class="node-sub" style="font-size:9.5px">Any other client</text>
                        <text x="12" y="36" class="node-sub" style="font-size:9.5px">(React, Android…)</text>
                        <text x="12" y="50" class="node-sub" style="font-size:9.5px">implementing a render</text>
                    </g>
                    <!-- dashed line from other client to JSON Output -->
                    <path d="M210,223 Q260,223 312,230" class="conn-accent" marker-start="none" />

                    <!-- bi-arrow web ↔ JSON -->
                    <line x1="412" y1="126" x2="412" y2="176" class="conn-line-bi" />

                    <!-- ── JSON OUTPUT ── -->
                    <g transform="translate(312, 176)">
                        <rect width="200" height="66" rx="10" class="node-rect-accent" />
                        <rect x="6" y="8" width="76" height="18" rx="9" class="tag-pill" />
                        <text x="44" y="21" text-anchor="middle" class="tag-text">AGNOSTIC</text>
                        <text x="100" y="44" text-anchor="middle" class="node-text" font-weight="700">JSON
                            Output</text>
                        <text x="100" y="59" text-anchor="middle" class="node-sub">(Agnostic contract)</text>
                    </g>

                    <!-- bi-arrow JSON ↔ USIM Core -->
                    <line x1="412" y1="242" x2="412" y2="290" class="conn-line-bi" />

                    <!-- ── USIM CORE ── -->
                    <g transform="translate(230, 290)">
                        <rect width="364" height="148" rx="12" class="core-rect" />
                        <!-- label bottom right -->
                        <text x="184" y="136" text-anchor="middle" class="node-text" font-weight="700"
                            style="font-size:14px; fill:var(--arch-accent2)">USIM Core</text>

                        <!-- ScreenA -->
                        <g transform="translate(18, 18)">
                            <rect width="96" height="80" rx="10" class="screen-rect" />
                            <text x="48" y="46" text-anchor="middle" class="node-text" font-weight="700"
                                style="font-size:12px">ScreenA</text>
                        </g>
                        <!-- ScreenB -->
                        <g transform="translate(134, 18)">
                            <rect width="96" height="80" rx="10" class="screen-rect" />
                            <text x="48" y="46" text-anchor="middle" class="node-text" font-weight="700"
                                style="font-size:12px">ScreenB</text>
                        </g>
                        <!-- ScreenX -->
                        <g transform="translate(250, 18)">
                            <rect width="96" height="80" rx="10" class="screen-rect" />
                            <text x="48" y="46" text-anchor="middle" class="node-text" font-weight="700"
                                style="font-size:12px">ScreenX</text>
                        </g>
                    </g>

                    <!-- arrow Core → Service Layer -->
                    <line x1="412" y1="438" x2="412" y2="468" class="conn-line-bi" />

                    <!-- ── SERVICE LAYER ── -->
                    <g transform="translate(230, 468)">
                        <rect width="364" height="44" rx="10" class="node-rect" />
                        <text x="182" y="27" text-anchor="middle" class="node-text" font-weight="700">Service
                            Layer</text>
                    </g>

                    <!-- arrow Service → DB -->
                    <line x1="412" y1="512" x2="412" y2="540" class="conn-line-bi" />

                    <!-- ── DB ── -->
                    <g transform="translate(372, 538)">
                        <!-- cylinder -->
                        <ellipse cx="40" cy="8" rx="40" ry="10" fill="var(--bg-node)"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <rect x="0" y="8" width="80" height="30" fill="var(--bg-node)" stroke="none" />
                        <line x1="0" y1="8" x2="0" y2="38"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <line x1="80" y1="8" x2="80" y2="38"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <ellipse cx="40" cy="38" rx="40" ry="10" fill="var(--bg-node)"
                            stroke="var(--border-svg)" stroke-width="1.5" />
                        <!-- inner lines -->
                        <ellipse cx="40" cy="18" rx="40" ry="10" fill="none"
                            stroke="var(--border-svg)" stroke-width="0.8" opacity="0.5" />
                        <ellipse cx="40" cy="28" rx="40" ry="10" fill="none"
                            stroke="var(--border-svg)" stroke-width="0.8" opacity="0.5" />
                        <text x="40" y="57" text-anchor="middle" class="node-text" font-weight="700"
                            style="font-size:12px">DB</text>
                    </g>

                </svg>
            </div>
        </div>
    </section>

    <!-- ─── COMPONENTS ─── -->
    <section class="components-section" id="components">
        <div class="section-inner">
            <div class="section-tag">{{ t('welcome.comp.tag') }}</div>
            <h2>{{ t('welcome.comp.title') }}</h2>
            <p class="section-lead">{{ t('welcome.comp.lead') }}</p>
            <div class="comp-grid">
                <div class="comp-item">
                    <span style="font-size:1.25rem">🏷️</span>
                    <div>
                        <div class="comp-name">UI::label()</div>
                        <div class="comp-label">{{ t('welcome.comp.label') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">🔘</span>
                    <div>
                        <div class="comp-name">UI::button()</div>
                        <div class="comp-label">{{ t('welcome.comp.button') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">✏️</span>
                    <div>
                        <div class="comp-name">UI::input()</div>
                        <div class="comp-label">{{ t('welcome.comp.input') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">🔽</span>
                    <div>
                        <div class="comp-name">UI::select()</div>
                        <div class="comp-label">{{ t('welcome.comp.select') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">☑️</span>
                    <div>
                        <div class="comp-name">UI::checkbox()</div>
                        <div class="comp-label">{{ t('welcome.comp.checkbox') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">📋</span>
                    <div>
                        <div class="comp-name">UI::form()</div>
                        <div class="comp-label">{{ t('welcome.comp.form') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">📊</span>
                    <div>
                        <div class="comp-name">UI::table()</div>
                        <div class="comp-label">{{ t('welcome.comp.table') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">🃏</span>
                    <div>
                        <div class="comp-name">UI::card()</div>
                        <div class="comp-label">{{ t('welcome.comp.card') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">📦</span>
                    <div>
                        <div class="comp-name">UI::container()</div>
                        <div class="comp-label">{{ t('welcome.comp.container') }}
                        </div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">📁</span>
                    <div>
                        <div class="comp-name">UI::uploader()</div>
                        <div class="comp-label">{{ t('welcome.comp.uploader') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">📅</span>
                    <div>
                        <div class="comp-name">UI::calendar()</div>
                        <div class="comp-label">{{ t('welcome.comp.calendar') }}</div>
                    </div>
                </div>
                <div class="comp-item">
                    <span style="font-size:1.25rem">🎠</span>
                    <div>
                        <div class="comp-name">UI::carousel()</div>
                        <div class="comp-label">{{ t('welcome.comp.carousel') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── TUTORIALS ─── -->
    <section id="tutorials">
        <div class="section-inner">
            <div class="section-tag">{{ t('welcome.tut.tag') }}</div>
            <h2>{{ t('welcome.tut.title') }}</h2>
            <p class="section-lead">{{ t('welcome.tut.lead') }}</p>
            <div class="tut-grid">
                <div class="tut-card" onclick="showToast('{{ t('welcome.ui.toast_open_tutorial') }}')">
                    <div class="tut-thumb"
                        style="background: linear-gradient(135deg, rgba(0,212,170,0.15), rgba(0,153,255,0.1))">🚀</div>
                    <div class="tut-body">
                        <div class="tut-tag" style="color:var(--accent)">
                            {{ t('welcome.tut.begin') }}</div>
                        <h3>{{ t('welcome.tut1.title') }}</h3>
                        <p>{{ t('welcome.tut1.desc') }}</p>
                    </div>
                    <div class="tut-footer">
                        <span class="tut-meta">15 min · PHP</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="var(--accent)" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
                <div class="tut-card" onclick="showToast('{{ t('welcome.ui.toast_open_tutorial') }}')">
                    <div class="tut-thumb"
                        style="background: linear-gradient(135deg, rgba(0,153,255,0.15), rgba(210,168,255,0.1))">📊
                    </div>
                    <div class="tut-body">
                        <div class="tut-tag" style="color:var(--accent2)">
                            {{ t('welcome.tut.inter') }}</div>
                        <h3>{{ t('welcome.tut2.title') }}</h3>
                        <p>{{ t('welcome.tut2.desc') }}</p>
                    </div>
                    <div class="tut-footer">
                        <span class="tut-meta">30 min · Laravel + USIM</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="var(--accent2)" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
                <div class="tut-card" onclick="showToast('{{ t('welcome.ui.toast_open_tutorial') }}')">
                    <div class="tut-thumb"
                        style="background: linear-gradient(135deg, rgba(255,107,53,0.12), rgba(255,189,46,0.1))">🔐
                    </div>
                    <div class="tut-body">
                        <div class="tut-tag" style="color:var(--accent3)">
                            {{ t('welcome.tut.inter') }}</div>
                        <h3>{{ t('welcome.tut3.title') }}</h3>
                        <p>{{ t('welcome.tut3.desc') }}</p>
                    </div>
                    <div class="tut-footer">
                        <span class="tut-meta">45 min · Sanctum + Spatie</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                            stroke="var(--accent3)" stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
                <div class="tut-card" onclick="showToast('{{ t('welcome.ui.toast_open_tutorial') }}')">
                    <div class="tut-thumb"
                        style="background: linear-gradient(135deg, rgba(61,214,140,0.12), rgba(0,212,170,0.1))">🧪
                    </div>
                    <div class="tut-body">
                        <div class="tut-tag" style="color:var(--green)">
                            {{ t('welcome.tut.adv') }}</div>
                        <h3>{{ t('welcome.tut4.title') }}</h3>
                        <p>{{ t('welcome.tut4.desc') }}</p>
                    </div>
                    <div class="tut-footer">
                        <span class="tut-meta">60 min · PHPUnit</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--green)"
                            stroke-width="2">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── COMMUNITY ─── -->
    <section class="community-section" id="community">
        <div class="section-inner community-layout">
            <div>
                <div class="section-tag">{{ t('welcome.comm.tag') }}</div>
                <h2>{{ t('welcome.comm.title') }}</h2>
                <p class="section-lead">{{ t('welcome.comm.lead') }}</p>
                <div class="community-cta">
                    <button class="btn-primary" onclick="showToast('{{ t('welcome.ui.toast_join_community') }}')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" />
                        </svg>
                        <span>{{ t('welcome.comm.cta1') }}</span>
                    </button>
                    <button class="btn-secondary" onclick="showToast('{{ t('welcome.ui.toast_write_message') }}')">
                        <span>{{ t('welcome.comm.cta2') }}</span>
                    </button>
                </div>
            </div>
            <div class="messages-feed">
                <div class="message">
                    <div class="msg-avatar" style="background: linear-gradient(135deg,#00d4aa,#0099ff)">MR</div>
                    <div>
                        <div><span class="msg-name">{{ t('welcome.comm.msg1.name') }}</span><span
                            class="msg-time">{{ t('welcome.comm.msg1.time') }}</span></div>
                        <div class="msg-text">{{ t('welcome.comm.msg1.text_before') }} <span
                            class="msg-code">usim:install</span> {{ t('welcome.comm.msg1.text_after') }}</div>
                    </div>
                </div>
                <div class="message">
                    <div class="msg-avatar" style="background: linear-gradient(135deg,#ff6b35,#ffbd2e)">CL</div>
                    <div>
                        <div><span class="msg-name">{{ t('welcome.comm.msg2.name') }}</span><span
                            class="msg-time">{{ t('welcome.comm.msg2.time') }}</span></div>
                        <div class="msg-text">{{ t('welcome.comm.msg2.text') }}</div>
                    </div>
                </div>
                <div class="message">
                    <div class="msg-avatar" style="background: linear-gradient(135deg,#d2a8ff,#79c0ff)">AG</div>
                    <div>
                        <div><span class="msg-name">{{ t('welcome.comm.msg3.name') }}</span><span
                            class="msg-time">{{ t('welcome.comm.msg3.time') }}</span></div>
                        <div class="msg-text">{{ t('welcome.comm.msg3.text_before') }} <span class="msg-code">on +
                            PascalCase</span> {{ t('welcome.comm.msg3.text_after') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── INSTITUTES ─── -->
    <section class="institutes-section" id="institutes">
        <div class="section-inner">
            <div class="section-tag">{{ t('welcome.inst.tag') }}</div>
            <h2>{{ t('welcome.inst.title') }}</h2>
            <p class="section-lead">{{ t('welcome.inst.lead') }}</p>
            <div class="institutes-grid">
                <div class="institute-card">
                    <div class="institute-icon"
                        style="background: linear-gradient(135deg,rgba(0,153,255,0.15),rgba(0,212,170,0.1))">
                        <img src="{{ asset('images/Idei-circular.png') }}" alt="IDEI" loading="lazy">
                    </div>
                    <h3>{{ t('welcome.inst1.name') }}</h3>
                    <p>{{ t('welcome.inst1.desc') }}</p>
                </div>
                <div class="institute-card">
                    <div class="institute-icon"
                        style="background: linear-gradient(135deg,rgba(255,107,53,0.12),rgba(255,189,46,0.1))">🎮</div>
                    <h3>{{ t('welcome.inst2.name') }}</h3>
                    <p>{{ t('welcome.inst2.desc') }}</p>
                </div>
                <div class="institute-card">
                    <div class="institute-icon"
                        style="background: linear-gradient(135deg,rgba(61,214,140,0.12),rgba(0,212,170,0.1))">⚖️</div>
                    <h3>{{ t('welcome.inst3.name') }}</h3>
                    <p>{{ t('welcome.inst3.desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── FOOTER ─── -->
    <footer>
        <div class="footer-inner">
            <div>
                <div class="logo" style="margin-bottom:0.5rem">
                    <div class="logo-mark">U</div>
                    USIM
                    <span class="logo-version">v0.7.0</span>
                </div>
                <p class="footer-copy">{{ t('welcome.footer.copy') }}</p>
            </div>
            <div class="footer-links">
                <a class="footer-link" href="#">{{ t('welcome.footer.docs') }}</a>
                <a class="footer-link" href="#">{{ t('welcome.footer.github') }}</a>
                <a class="footer-link" href="#">{{ t('welcome.footer.tutorials') }}</a>
                <a class="footer-link" href="#">{{ t('welcome.footer.community') }}</a>
                <a class="footer-link" href="#">{{ t('welcome.footer.license') }}</a>
            </div>
        </div>
    </footer>
</div>
