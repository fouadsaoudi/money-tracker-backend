<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
    <style>
        :root {
            --bg: #f4f6fb;
            --bg-deep: #dce5f3;
            --panel: rgba(255, 255, 255, 0.88);
            --panel-solid: #ffffff;
            --text: #1a2230;
            --muted: #5f6c7c;
            --accent: #cb2f4a;
            --accent-strong: #a61f37;
            --accent-soft: rgba(203, 47, 74, 0.12);
            --danger: #b3261e;
            --danger-soft: rgba(179, 38, 30, 0.09);
            --border: rgba(26, 34, 48, 0.12);
            --shadow: 0 30px 80px rgba(34, 41, 54, 0.16);
            --ring: rgba(203, 47, 74, 0.2);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Avenir Next", "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at 15% 20%, rgba(203, 47, 74, 0.18), transparent 24%),
                radial-gradient(circle at 82% 14%, rgba(31, 78, 163, 0.18), transparent 20%),
                linear-gradient(145deg, #f9fbff 0%, var(--bg) 52%, var(--bg-deep) 100%);
            color: var(--text);
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            inset: auto;
            border-radius: 999px;
            pointer-events: none;
            filter: blur(8px);
        }

        body::before {
            width: 280px;
            height: 280px;
            left: -90px;
            bottom: 8vh;
            background: rgba(203, 47, 74, 0.11);
        }

        body::after {
            width: 220px;
            height: 220px;
            right: -60px;
            top: 10vh;
            background: rgba(31, 78, 163, 0.12);
        }

        .shell {
            min-height: calc(100vh - 48px);
            display: grid;
            place-items: center;
        }

        .card {
            width: min(100%, 980px);
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            background: var(--panel);
            backdrop-filter: blur(18px);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 32px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .hero {
            position: relative;
            padding: 42px 36px;
            background:
                linear-gradient(180deg, rgba(20, 49, 106, 0.95), rgba(13, 35, 78, 0.9)),
                linear-gradient(135deg, #1f4ea3, #11254f);
            color: #f7f5ef;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 100%;
        }

        .hero::before {
            content: "";
            position: absolute;
            inset: auto -40px -40px auto;
            width: 220px;
            height: 220px;
            border-radius: 36px;
            transform: rotate(18deg);
            background: linear-gradient(135deg, rgba(255,255,255,0.12), rgba(255,255,255,0.02));
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            font-size: 0.78rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .hero-copy {
            position: relative;
            z-index: 1;
        }

        .hero h1 {
            margin: 20px 0 14px;
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(2.6rem, 5vw, 4.2rem);
            line-height: 0.92;
            letter-spacing: -0.05em;
        }

        .hero p {
            margin: 0;
            color: rgba(247, 245, 239, 0.82);
            max-width: 28rem;
            font-size: 1rem;
            line-height: 1.7;
        }

        .hero-panel {
            position: relative;
            z-index: 1;
            margin-top: 28px;
            padding: 18px 18px 16px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.08);
        }

        .hero-panel strong,
        .hero-panel span {
            display: block;
        }

        .hero-panel strong {
            margin-bottom: 6px;
            font-size: 0.92rem;
        }

        .hero-panel span {
            color: rgba(247, 245, 239, 0.74);
            font-size: 0.92rem;
            line-height: 1.55;
        }

        .content {
            padding: 42px 36px;
            background: linear-gradient(180deg, rgba(255,255,255,0.52), rgba(255,255,255,0.92));
        }

        .content h2 {
            margin: 0 0 10px;
            font-size: 1.7rem;
            line-height: 1.1;
            letter-spacing: -0.04em;
        }

        .lead {
            margin: 0 0 26px;
            color: var(--muted);
            line-height: 1.7;
        }

        .status {
            margin-bottom: 18px;
            padding: 15px 16px;
            border-radius: 18px;
            background: var(--accent-soft);
            color: var(--accent-strong);
            border: 1px solid rgba(203, 47, 74, 0.18);
        }

        .errors {
            margin: 0 0 18px;
            padding: 15px 16px;
            border-radius: 18px;
            background: var(--danger-soft);
            color: var(--danger);
            border: 1px solid rgba(179, 38, 30, 0.15);
        }

        .errors ul {
            margin: 0;
            padding-left: 18px;
        }

        form {
            display: grid;
            gap: 14px;
        }

        label {
            display: block;
            margin-bottom: 9px;
            font-size: 0.82rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--text);
        }

        .field {
            position: relative;
        }

        input {
            width: 100%;
            border: 1px solid var(--border);
            background: rgba(255, 255, 255, 0.92);
            border-radius: 18px;
            padding: 16px 18px;
            font: inherit;
            color: var(--text);
            transition: border-color 180ms ease, box-shadow 180ms ease, transform 180ms ease;
        }

        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 5px var(--ring);
            transform: translateY(-1px);
        }

        button {
            width: 100%;
            border: 0;
            border-radius: 999px;
            margin-top: 6px;
            padding: 17px 20px;
            font: inherit;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.01em;
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-strong) 100%);
            cursor: pointer;
            box-shadow: 0 18px 30px rgba(12, 94, 79, 0.24);
            transition: transform 180ms ease, filter 180ms ease, box-shadow 180ms ease;
        }

        button:hover {
            filter: brightness(1.03);
            transform: translateY(-1px);
            box-shadow: 0 22px 36px rgba(12, 94, 79, 0.28);
        }

        .meta {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid rgba(26, 34, 48, 0.08);
            font-size: 0.9rem;
            color: var(--muted);
            line-height: 1.7;
        }

        .email-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            max-width: 100%;
            margin-top: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(203, 47, 74, 0.08);
            color: var(--accent-strong);
            font-size: 0.92rem;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        @media (max-width: 860px) {
            .card {
                grid-template-columns: 1fr;
            }

            .hero,
            .content {
                padding: 28px 24px;
            }
        }

        @media (max-width: 520px) {
            body {
                padding: 14px;
            }

            .shell {
                min-height: calc(100vh - 28px);
            }

            .card {
                border-radius: 24px;
            }

            .hero h1 {
                font-size: 2.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <main class="card">
            <section class="hero">
                <div class="hero-copy">
                    <span class="eyebrow">Account Security</span>
                    <h1>Choose a new password.</h1>
                    <p>Use a strong password you have not used before.</p>
                </div>
            </section>

            <section class="content">
                <h2>Reset password</h2>
                <p class="lead">Enter your new password below and submit the form to secure the account again.</p>

                @if (session('status'))
                    <div class="status">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="errors">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    <input type="hidden" name="token" value="{{ old('token', $token) }}">

                    <div class="field">
                        <label for="email">Email</label>
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email', $email) }}"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="password">New password</label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Confirm new password</label>
                        <input
                            id="password_confirmation"
                            type="password"
                            name="password_confirmation"
                            autocomplete="new-password"
                            required
                        >
                    </div>

                    <button type="submit">Update password</button>
                </form>
            </section>
        </main>
    </div>
</body>
</html>
