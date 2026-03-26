<div class="wf" data-theme="dark">
    <style>
        .wf {
            --bg: #0a0c10;
            --text: #e8eaf0;
            --surface: #1c2333;
            --border: #2a3347;
            --accent: #00d4aa;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 0rem;
            background: var(--bg);
            color: var(--text);
            transition: background 0.25s ease, color 0.25s ease;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }

        .wf[data-theme="light"] {
            --bg: #f3f5f9;
            --text: #0f1520;
            --surface: #ffffff;
            --border: #c8cfe0;
        }

        .landing {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.5rem, 3vw, 2.2rem);
            line-height: 1.1;
            color: var(--text);
        }

    </style>

    <main class="landing">
        <h1>{{ $title }}</h1>
    </main>

    <script>
        const root = document.querySelector('.wf');

        // Keeps this stub synced with an external theme switch that updates html/body data-theme.
        function applyExternalTheme() {
            if (!root) {
                return;
            }

            const sourceTheme =
                document.documentElement.getAttribute('data-theme') ||
                document.body.getAttribute('data-theme');

            if (sourceTheme === 'light' || sourceTheme === 'dark') {
                root.setAttribute('data-theme', sourceTheme);
            }
        }

        applyExternalTheme();

        const observer = new MutationObserver(applyExternalTheme);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
        observer.observe(document.body, { attributes: true, attributeFilter: ['data-theme'] });
    </script>
</div>
