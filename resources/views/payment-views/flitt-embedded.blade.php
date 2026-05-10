<!doctype html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title }} - უსაფრთხო გადახდა</title>
    <link rel="stylesheet" href="https://pay.flitt.com/latest/checkout-vue/checkout.css">
    <style>
        :root {
            color-scheme: light;
            --brand: #08a651;
            --brand-dark: #04783b;
            --brand-soft: #e8f7ef;
            --ink: #111827;
            --ink-soft: #374151;
            --muted: #6b7280;
            --line: #e5e7eb;
            --surface: #ffffff;
            --page: #f5f8f6;
            --danger: #b91c1c;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--page);
            color: var(--ink);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .page {
            width: min(100%, 1120px);
            min-height: 100vh;
            margin: 0 auto;
            padding: 28px 18px;
            display: grid;
            grid-template-columns: minmax(260px, 360px) minmax(320px, 640px);
            gap: 24px;
            align-items: start;
            justify-content: center;
        }

        .summary,
        .checkout-shell {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 8px;
            box-shadow: 0 18px 40px rgba(17, 24, 39, 0.08);
        }

        .summary {
            padding: 24px;
            position: sticky;
            top: 18px;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--line);
        }

        .brand-name {
            margin: 0;
            color: var(--brand);
            font-size: 28px;
            font-weight: 800;
            line-height: 1.15;
        }

        .secure-badge {
            min-width: max-content;
            border-radius: 999px;
            background: var(--brand-soft);
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 700;
            line-height: 1;
            padding: 9px 12px;
        }

        .payment-title {
            margin: 22px 0 8px;
            font-size: 20px;
            line-height: 1.25;
            font-weight: 800;
        }

        .payment-copy {
            margin: 0;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.55;
        }

        .amount-row {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
        }

        .amount-label {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.2;
        }

        .amount {
            color: var(--ink);
            font-size: clamp(28px, 4vw, 36px);
            line-height: 1;
            font-weight: 850;
            white-space: nowrap;
        }

        .note {
            display: flex;
            gap: 10px;
            margin-top: 22px;
            color: var(--ink-soft);
            font-size: 13px;
            line-height: 1.5;
        }

        .note-dot {
            width: 10px;
            height: 10px;
            margin-top: 5px;
            border-radius: 50%;
            background: var(--brand);
            flex: 0 0 auto;
        }

        .checkout-shell {
            width: 100%;
            min-height: 650px;
            padding: 18px;
            position: relative;
        }

        #flitt-checkout {
            min-height: 614px;
        }

        .loader {
            position: absolute;
            inset: 18px;
            display: grid;
            place-items: center;
            background: var(--surface);
            border-radius: 6px;
            z-index: 2;
        }

        .loader-card {
            display: grid;
            gap: 12px;
            justify-items: center;
            color: var(--muted);
            font-size: 14px;
            line-height: 1.4;
            text-align: center;
        }

        .spinner {
            width: 38px;
            height: 38px;
            border: 4px solid var(--brand-soft);
            border-top-color: var(--brand);
            border-radius: 50%;
            animation: spin 0.9s linear infinite;
        }

        .fallback {
            display: none;
            padding: 24px;
            color: var(--danger);
            line-height: 1.5;
            border: 1px solid #fecaca;
            border-radius: 8px;
            background: #fff5f5;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 900px) {
            .page {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .summary {
                position: static;
            }
        }

        @media (max-width: 820px) {
            .page {
                min-height: auto;
                padding: 10px;
            }

            .summary {
                padding: 18px;
            }

            .brand-name {
                font-size: 24px;
            }

            .amount-row {
                align-items: flex-start;
                flex-direction: column;
                gap: 8px;
            }

            .checkout-shell {
                min-height: 560px;
                padding: 8px;
            }

            #flitt-checkout {
                min-height: 544px;
            }

            .loader {
                inset: 8px;
            }
        }
    </style>
</head>
<body>
<main class="page">
    <aside class="summary" aria-label="გადახდის შეჯამება">
        <div class="brand">
            <h1 class="brand-name">მილი</h1>
            <span class="secure-badge">უსაფრთხო გადახდა</span>
        </div>
        <h2 class="payment-title">{{ $title }}</h2>
        <p class="payment-copy">შეიყვანეთ ბარათის მონაცემები ან გამოიყენეთ ხელმისაწვდომი საფულე. ბარათის მონაცემები მუშავდება Flitt-ის უსაფრთხო გარემოში.</p>
        <div class="amount-row">
            <span class="amount-label">გადასახდელი თანხა</span>
            <strong class="amount">{{ $amount }} {{ $currency }}</strong>
        </div>
        <div class="note">
            <span class="note-dot" aria-hidden="true"></span>
            <span>გადახდის დასრულების შემდეგ შეკვეთის სტატუსი ავტომატურად განახლდება.</span>
        </div>
    </aside>

    <section class="checkout-shell" aria-label="Flitt გადახდის ფორმა">
        <div id="flitt-loader" class="loader" aria-live="polite">
            <div class="loader-card">
                <span class="spinner" aria-hidden="true"></span>
                <span>გადახდის ფორმა იტვირთება...</span>
            </div>
        </div>
        <div id="flitt-checkout"></div>
        <div id="flitt-fallback" class="fallback">გადახდის ფორმის ჩატვირთვა ვერ მოხერხდა. განაახლეთ გვერდი და სცადეთ თავიდან.</div>
    </section>
</main>

<script src="https://pay.flitt.com/latest/checkout-vue/checkout.js"></script>
<script>
    (function () {
        var options = {
            options: {
                methods: ['card', 'wallets'],
                wallet_methods_enabled: ['apple', 'google'],
                card_icons: ['visa', 'mastercard', 'maestro'],
                active_tab: 'card',
                locales: ['ka'],
                title: @json($title),
                full_screen: false,
                show_lang: false,
                show_link: false,
                show_title: false,
                show_order_desc: false,
                show_amount: false,
                show_secure_message: true,
                show_test_mode: @json(!$isLiveMode),
                theme: {
                    type: 'light',
                    preset: 'reset',
                    layout: 'plain'
                }
            },
            params: {
                token: @json($token),
                merchant_id: @json($merchantId),
                lang: 'ka'
            },
            css_variable: {
                main: '#16a34a',
                card_bg: '#ffffff',
                card_shadow: '#d1d5db'
            }
        };

        var loader = document.getElementById('flitt-loader');
        var fallback = document.getElementById('flitt-fallback');

        function hideLoader() {
            if (loader) {
                loader.style.display = 'none';
            }
        }

        function showFallback() {
            hideLoader();
            if (fallback) {
                fallback.style.display = 'block';
            }
        }

        if (typeof checkout !== 'function') {
            showFallback();
            return;
        }

        try {
            checkout('#flitt-checkout', options);
            window.setTimeout(hideLoader, 900);
        } catch (error) {
            showFallback();
        }
    })();
</script>
</body>
</html>
