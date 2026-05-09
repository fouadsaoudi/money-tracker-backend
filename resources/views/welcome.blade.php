<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Money Tracker') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|fraunces:600,700" rel="stylesheet" />
    <style>
        :root {
            --bg: #f4f7fb;
            --ink: #10203a;
            --muted: #5b6980;
            --panel: rgba(255, 255, 255, 0.8);
            --line: rgba(16, 32, 58, 0.1);
            --blue: #275df5;
            --red: #e24b63;
            --blue-soft: rgba(39, 93, 245, 0.14);
            --red-soft: rgba(226, 75, 99, 0.14);
            --shadow: 0 30px 80px rgba(28, 47, 84, 0.14);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Space Grotesk", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 15% 20%, var(--blue-soft), transparent 24%),
                radial-gradient(circle at 82% 12%, var(--red-soft), transparent 22%),
                linear-gradient(160deg, #fbfdff 0%, var(--bg) 50%, #e3ebf8 100%);
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .card {
            width: min(100%, 860px);
            display: grid;
            gap: 28px;
            padding: 32px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 30px;
            background: var(--panel);
            backdrop-filter: blur(18px);
            box-shadow: var(--shadow);
        }

        .topline {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            flex-wrap: wrap;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(16, 32, 58, 0.06);
            color: var(--muted);
            font-size: 0.85rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .badge::before {
            content: "";
            width: 9px;
            height: 9px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--blue), var(--red));
        }

        h1 {
            margin: 0;
            max-width: 10ch;
            font-family: "Fraunces", serif;
            font-size: clamp(3rem, 8vw, 5.3rem);
            line-height: 0.95;
            letter-spacing: -0.05em;
        }

        .lead {
            max-width: 40rem;
            margin: 0;
            color: var(--muted);
            font-size: 1.05rem;
            line-height: 1.75;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .button,
        .ghost {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 148px;
            padding: 14px 18px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 180ms ease, box-shadow 180ms ease, background 180ms ease;
        }

        .button {
            color: white;
            background: linear-gradient(135deg, var(--blue), var(--red));
            box-shadow: 0 18px 34px rgba(39, 93, 245, 0.2);
        }

        .ghost {
            color: var(--ink);
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid var(--line);
        }

        .button:hover,
        .ghost:hover {
            transform: translateY(-1px);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .stat {
            padding: 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.72);
            border: 1px solid var(--line);
        }

        .stat strong {
            display: block;
            margin-bottom: 6px;
            font-size: 1rem;
        }

        .stat span {
            color: var(--muted);
            line-height: 1.65;
            font-size: 0.94rem;
        }

        @media (max-width: 720px) {
            .card {
                padding: 24px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <main class="card">
            <div class="topline">
                <span class="badge">Money Tracker API</span>
            </div>

            <section>
                <h1>Track money with less friction.</h1>
                <p class="lead">
                    Money Tracker is a lightweight backend for handling account access, password recovery,
                    and the foundation for expense and finance flows. This project keeps the API surface simple,
                    mobile-friendly, and ready for the client app.
                </p>
            </section>

            <div class="actions">
                <a class="button" href="{{ url('/docs') }}">Open API Docs</a>
                <a class="ghost" href="{{ url('/docs.postman') }}">Postman Collection</a>
            </div>

            <section class="grid">
                <article class="stat">
                    <strong>Auth Ready</strong>
                    <span>Registration, login, forgot-password, and reset-password endpoints are already exposed.</span>
                </article>
                <article class="stat">
                    <strong>Mobile Friendly</strong>
                    <span>Password reset supports both app-based flows and a simple browser fallback.</span>
                </article>
                <article class="stat">
                    <strong>Documented</strong>
                    <span>Scribe-generated API docs and Postman output are available from this backend.</span>
                </article>
            </section>
        </main>
    </div>
</body>
</html>
