<div class="wf">
    <style>
        .wf {
            --bg: #0a0c10;
            --text: #e8eaf0;
            --surface: #1c2333;
            --border: #2a3347;
            --accent: #00d4aa;
            min-height: 50vh;
            display: grid;
            place-items: center;
            padding: 0rem;
            background: var(--bg);
            color: var(--text);
            transition: background 0.25s ease, color 0.25s ease;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        }

        .wf[data-theme="light"],
        html[data-theme="light"] .wf,
        body[data-theme="light"] .wf {
            --bg: #f3f5f9;
            --text: #0f1520;
            --surface: #ffffff;
            --border: #c8cfe0;
        }

        .landing {
            width: 100%;
            display: flex;
            align-items: center;
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

</div>
