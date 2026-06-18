<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} - Keepz</title>
    <style>
        :root {
            --brand: #08a651;
            --brand-dark: #057a3c;
            --brand-soft: #e8f7ee;
            --ink: #10231a;
            --muted: #5d6b63;
            --line: #dce9e1;
            --surface: #ffffff;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--ink);
            background: linear-gradient(180deg, #f5fbf7 0%, #ffffff 100%);
        }

        .page {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .panel {
            width: min(100%, 430px);
            border: 1px solid var(--line);
            border-radius: 8px;
            background: var(--surface);
            box-shadow: 0 18px 45px rgba(16, 35, 26, 0.08);
            padding: 28px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 26px;
        }

        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            color: #fff;
            background: var(--brand);
            font-weight: 800;
            font-size: 20px;
            line-height: 1;
        }

        .brand-title {
            margin: 0;
            font-size: 20px;
            line-height: 1.2;
            font-weight: 800;
        }

        .brand-subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .amount {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            border: 1px solid var(--line);
            border-radius: 8px;
            padding: 14px 16px;
            margin-bottom: 24px;
            background: #fbfefc;
        }

        .amount span {
            color: var(--muted);
            font-size: 14px;
        }

        .amount strong {
            font-size: 20px;
            line-height: 1.2;
        }

        .loader {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 20px;
        }

        .spinner {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 3px solid var(--brand-soft);
            border-top-color: var(--brand);
            animation: spin 0.8s linear infinite;
            flex: 0 0 auto;
        }

        .action {
            width: 100%;
            min-height: 46px;
            border: 0;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            color: #fff;
            background: var(--brand);
            font-size: 15px;
            font-weight: 700;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .action:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
        }

        .mode {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 0 10px;
            border-radius: 999px;
            color: var(--brand-dark);
            background: var(--brand-soft);
            font-size: 12px;
            font-weight: 700;
            margin-top: 16px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 480px) {
            .page {
                padding: 16px;
            }

            .panel {
                padding: 22px;
            }

            .amount {
                align-items: flex-start;
                flex-direction: column;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
<main class="page">
    <section class="panel" aria-live="polite">
        <div class="brand">
            <div class="brand-mark">M</div>
            <div>
                <h1 class="brand-title">{{ $title }}</h1>
                <p class="brand-subtitle">Keepz</p>
            </div>
        </div>

        <div class="amount">
            <span>გადასახდელი თანხა</span>
            <strong>{{ $amount }} {{ $currency }}</strong>
        </div>

        <div class="loader">
            <span class="spinner" aria-hidden="true"></span>
            <span>გადაგამისამართებთ უსაფრთხო გადახდის გვერდზე</span>
        </div>

        <a class="action" href="{{ $redirectUrl }}" rel="noopener">გაგრძელება</a>

        @if(!$isLiveMode)
            <div class="mode">TEST MODE</div>
        @endif
    </section>
</main>

<script>
    window.setTimeout(function () {
        window.location.replace(@json($redirectUrl));
    }, 700);
</script>
</body>
</html>
