<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>{{ $appName }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=tajawal:400,500,700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #334155;
            --heading: #0f172a;
            --line: #e2e8f0;
            --brand: #3469B2;
            --brand-dark: #28548f;
            --muted: #64748b;
            --success: #059669;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Tajawal, "Segoe UI", Tahoma, sans-serif;
            font-size: 14px;
            font-weight: 400;
            background: var(--bg);
            color: var(--text);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
        }
        .wrap {
            max-width: 680px;
            margin: 0 auto;
            padding: 18px 14px 40px;
        }
        header {
            margin-bottom: 18px;
        }
        .brand {
            display: inline-block;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: var(--brand);
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 24px 20px;
            text-align: center;
        }
        .app-icon {
            width: 84px;
            height: 84px;
            margin: 0 auto 16px;
            border-radius: 20px;
            background: linear-gradient(145deg, #4f86c6, var(--brand));
            color: #fff;
            display: grid;
            place-items: center;
            font-size: 34px;
            font-weight: 700;
            box-shadow: 0 10px 24px rgba(52, 105, 178, 0.25);
        }
        h1 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 700;
            color: var(--heading);
        }
        .version {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 13px;
        }
        .lead {
            margin: 0 0 22px;
            font-size: 14px;
        }
        .actions {
            display: grid;
            gap: 10px;
            margin-bottom: 18px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            min-height: 48px;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid transparent;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary {
            background: var(--brand);
            color: #fff;
        }
        .btn-primary:hover { background: var(--brand-dark); }
        .btn-secondary {
            background: #fff;
            color: var(--heading);
            border-color: var(--line);
        }
        .btn-disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .note {
            margin: 0;
            padding: 14px 16px;
            border-radius: 12px;
            background: #f1f5f9;
            color: var(--muted);
            font-size: 12.5px;
            text-align: {{ $dir === 'rtl' ? 'right' : 'left' }};
        }
        .note strong {
            display: block;
            margin-bottom: 6px;
            color: var(--heading);
            font-size: 13px;
        }
        .note ol {
            margin: 0;
            padding-inline-start: 1.2em;
        }
        .note li + li { margin-top: 4px; }
        .links {
            margin-top: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
            font-size: 13px;
        }
        .links a {
            color: var(--brand);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="wrap">
        <header>
            <span class="brand">{{ $appName }}</span>
        </header>

        <section class="card">
            <div class="app-icon" aria-hidden="true">B</div>

            <h1>{{ $locale === 'ar' ? 'حمّل تطبيق '.$appName : 'Download the '.$appName.' app' }}</h1>
            <p class="version">{{ $locale === 'ar' ? 'الإصدار' : 'Version' }} {{ $version }}</p>

            @if ($releaseNotes)
                <p class="lead">{{ $releaseNotes }}</p>
            @else
                <p class="lead">
                    @if ($locale === 'ar')
                        احجز الرحلات، الفنادق، التأمين، و eSIM من تطبيق واحد — في أي وقت ومن أي مكان.
                    @else
                        Book flights, hotels, insurance, and eSIM from one app — anytime, anywhere.
                    @endif
                </p>
            @endif

            <div class="actions">
                @if ($downloadAvailable)
                    <a class="btn btn-primary" href="{{ $downloadUrl }}">
                        {{ $locale === 'ar' ? 'تحميل APK لأندرويد' : 'Download Android APK' }}
                    </a>
                @else
                    <span class="btn btn-disabled">
                        {{ $locale === 'ar' ? 'التحميل غير متاح حالياً' : 'Download not available yet' }}
                    </span>
                @endif

                @if ($playStoreUrl)
                    <a class="btn btn-secondary" href="{{ $playStoreUrl }}" target="_blank" rel="noopener noreferrer">
                        {{ $locale === 'ar' ? 'Google Play' : 'Get it on Google Play' }}
                    </a>
                @endif

                @if ($appStoreUrl)
                    <a class="btn btn-secondary" href="{{ $appStoreUrl }}" target="_blank" rel="noopener noreferrer">
                        {{ $locale === 'ar' ? 'App Store' : 'Download on the App Store' }}
                    </a>
                @endif
            </div>

            @if ($downloadAvailable)
                <div class="note">
                    <strong>{{ $locale === 'ar' ? 'طريقة التثبيت على أندرويد' : 'How to install on Android' }}</strong>
                    @if ($locale === 'ar')
                        <ol>
                            <li>حمّل ملف APK من الزر أعلاه.</li>
                            <li>افتح الملف من مجلد التنزيلات.</li>
                            <li>إذا طُلب منك، فعّل «السماح من هذا المصدر».</li>
                            <li>اضغط «تثبيت» ثم افتح التطبيق.</li>
                        </ol>
                    @else
                        <ol>
                            <li>Download the APK using the button above.</li>
                            <li>Open the file from your Downloads folder.</li>
                            <li>Allow installation from this source if Android asks.</li>
                            <li>Tap Install, then open the app.</li>
                        </ol>
                    @endif
                </div>
            @endif

            <div class="links">
                <a href="{{ route('content.pages.index', ['locale' => $locale]) }}">
                    {{ $locale === 'ar' ? 'سياسة الخصوصية' : 'Privacy policy' }}
                </a>
                <a href="{{ route('content.pages.show', ['slug' => 'terms-of-service', 'locale' => $locale]) }}">
                    {{ $locale === 'ar' ? 'الشروط والأحكام' : 'Terms of service' }}
                </a>
            </div>
        </section>
    </main>
</body>
</html>
