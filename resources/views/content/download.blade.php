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
            --bg: #f1f5f9;
            --card: #ffffff;
            --text: #334155;
            --heading: #0f172a;
            --line: #e2e8f0;
            --brand: #2f6db8;
            --brand-dark: #255991;
            --brand-soft: color-mix(in srgb, #2f6db8 10%, white);
            --gold: #f0b429;
            --muted: #64748b;
            --success: #059669;
            --danger: #dc2626;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Tajawal, "Segoe UI", Tahoma, sans-serif;
            font-size: 14px;
            font-weight: 400;
            background:
                radial-gradient(ellipse 80% 50% at 50% -20%, color-mix(in srgb, var(--brand) 18%, transparent), transparent),
                var(--bg);
            color: var(--text);
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
        }
        .wrap {
            max-width: 680px;
            margin: 0 auto;
            padding: 18px 14px 40px;
        }
        header { margin-bottom: 18px; }
        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: var(--brand);
        }
        .brand img {
            width: 36px;
            height: 36px;
            border-radius: 10px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 28px 22px;
            text-align: center;
            box-shadow: 0 12px 40px rgba(15, 23, 42, 0.06);
        }
        .app-icon {
            width: 88px;
            height: 88px;
            margin: 0 auto 16px;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 12px 28px rgba(47, 109, 184, 0.28);
        }
        .app-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
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
        .meta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto 18px;
            padding: 6px 12px;
            border-radius: 999px;
            background: var(--brand-soft);
            color: var(--brand-dark);
            font-size: 12px;
            font-weight: 600;
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
            min-height: 50px;
            padding: 12px 16px;
            border-radius: 14px;
            border: 1px solid transparent;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.15s ease, background 0.15s ease, opacity 0.15s ease;
        }
        .btn:active { transform: scale(0.98); }
        .btn:disabled {
            opacity: 0.72;
            cursor: wait;
            transform: none;
        }
        .btn-primary {
            background: var(--brand);
            color: #fff;
        }
        .btn-primary:hover:not(:disabled) { background: var(--brand-dark); }
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
        .progress-panel {
            display: none;
            margin: 0 0 18px;
            padding: 16px;
            border-radius: 16px;
            border: 1px solid color-mix(in srgb, var(--brand) 22%, white);
            background: linear-gradient(180deg, var(--brand-soft), #fff);
            text-align: {{ $dir === 'rtl' ? 'right' : 'left' }};
        }
        .progress-panel.is-visible { display: block; }
        .progress-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 10px;
        }
        .progress-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--heading);
        }
        .progress-percent {
            font-size: 18px;
            font-weight: 800;
            color: var(--brand);
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.02em;
        }
        .progress-track {
            position: relative;
            height: 12px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            width: 0%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--brand), #4f8fd6 55%, var(--gold));
            box-shadow: 0 0 12px rgba(47, 109, 184, 0.35);
            transition: width 0.18s ease-out;
        }
        .progress-fill.is-indeterminate {
            width: 36% !important;
            animation: indeterminate 1.1s ease-in-out infinite;
        }
        @keyframes indeterminate {
            0% { transform: translateX(-120%); }
            100% { transform: translateX(280%); }
        }
        .progress-meta {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-top: 10px;
            font-size: 12px;
            color: var(--muted);
        }
        .progress-status.is-success { color: var(--success); font-weight: 700; }
        .progress-status.is-error { color: var(--danger); font-weight: 700; }
        .note {
            margin: 0;
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8fafc;
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
            <span class="brand">
                <img src="{{ asset('images/app_logo.png') }}" alt="{{ $appName }}">
                {{ $appName }}
            </span>
        </header>

        <section class="card">
            <div class="app-icon" aria-hidden="true">
                <img src="{{ asset('images/app_logo.png') }}" alt="">
            </div>

            <h1>{{ $locale === 'ar' ? 'حمّل تطبيق '.$appName : 'Download the '.$appName.' app' }}</h1>
            <p class="version">{{ $locale === 'ar' ? 'الإصدار' : 'Version' }} {{ $version }}</p>

            @if (!empty($fileSize))
                <div class="meta">
                    <span>APK</span>
                    <span aria-hidden="true">·</span>
                    <span id="file-size-label">{{ number_format($fileSize / 1048576, 1) }} MB</span>
                </div>
            @endif

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
                    <button
                        type="button"
                        id="download-btn"
                        class="btn btn-primary"
                        data-url="{{ $downloadUrl }}"
                        data-filename="{{ $apkFilename ?: ('booke-'.$version.'.apk') }}"
                        data-filesize="{{ (int) ($fileSize ?? 0) }}"
                    >
                        {{ $locale === 'ar' ? 'تحميل APK لأندرويد' : 'Download Android APK' }}
                    </button>
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
                <div id="progress-panel" class="progress-panel" aria-live="polite">
                    <div class="progress-top">
                        <span id="progress-label" class="progress-label">
                            {{ $locale === 'ar' ? 'جاري التحضير…' : 'Preparing…' }}
                        </span>
                        <span id="progress-percent" class="progress-percent">0%</span>
                    </div>
                    <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div id="progress-fill" class="progress-fill"></div>
                    </div>
                    <div class="progress-meta">
                        <span id="progress-bytes">0 MB</span>
                        <span id="progress-status" class="progress-status"></span>
                    </div>
                </div>

                <div class="note">
                    <strong>{{ $locale === 'ar' ? 'طريقة التثبيت على أندرويد' : 'How to install on Android' }}</strong>
                    @if ($locale === 'ar')
                        <ol>
                            <li>اضغط «تحميل APK» وانتظر اكتمال الشريط.</li>
                            <li>افتح الملف من مجلد التنزيلات.</li>
                            <li>إذا طُلب منك، فعّل «السماح من هذا المصدر».</li>
                            <li>اضغط «تثبيت» ثم افتح التطبيق.</li>
                        </ol>
                    @else
                        <ol>
                            <li>Tap Download APK and wait for the progress bar to finish.</li>
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

    @if ($downloadAvailable)
    <script>
        (function () {
            var isAr = @json($locale === 'ar');
            var copy = {
                preparing: isAr ? 'جاري التحضير…' : 'Preparing…',
                downloading: isAr ? 'جاري التحميل…' : 'Downloading…',
                finishing: isAr ? 'جارٍ الإنهاء…' : 'Finishing…',
                done: isAr ? 'اكتمل التحميل' : 'Download complete',
                error: isAr ? 'فشل التحميل — حاول مرة أخرى' : 'Download failed — try again',
                retry: isAr ? 'إعادة التحميل' : 'Retry download',
                idle: isAr ? 'تحميل APK لأندرويد' : 'Download Android APK',
            };

            var btn = document.getElementById('download-btn');
            if (!btn) return;

            var panel = document.getElementById('progress-panel');
            var label = document.getElementById('progress-label');
            var percentEl = document.getElementById('progress-percent');
            var fill = document.getElementById('progress-fill');
            var bytesEl = document.getElementById('progress-bytes');
            var statusEl = document.getElementById('progress-status');
            var track = panel.querySelector('[role="progressbar"]');
            var knownSize = Number(btn.getAttribute('data-filesize') || 0);
            var activeXhr = null;

            function formatBytes(bytes) {
                if (!bytes || bytes < 0) return '0 MB';
                var mb = bytes / (1024 * 1024);
                return (mb >= 10 ? mb.toFixed(0) : mb.toFixed(1)) + ' MB';
            }

            function setProgress(loaded, total) {
                var pct = 0;
                var hasTotal = total > 0;
                fill.classList.toggle('is-indeterminate', !hasTotal);

                if (hasTotal) {
                    pct = Math.max(0, Math.min(100, Math.round((loaded / total) * 100)));
                    fill.style.width = pct + '%';
                    percentEl.textContent = pct + '%';
                    track.setAttribute('aria-valuenow', String(pct));
                    bytesEl.textContent = formatBytes(loaded) + ' / ' + formatBytes(total);
                } else {
                    fill.style.width = '36%';
                    percentEl.textContent = '…';
                    track.setAttribute('aria-valuenow', '0');
                    bytesEl.textContent = formatBytes(loaded);
                }
            }

            function setState(kind, message) {
                statusEl.className = 'progress-status';
                if (kind === 'success') statusEl.classList.add('is-success');
                if (kind === 'error') statusEl.classList.add('is-error');
                statusEl.textContent = message || '';
            }

            function resetUi() {
                panel.classList.remove('is-visible');
                fill.classList.remove('is-indeterminate');
                fill.style.width = '0%';
                percentEl.textContent = '0%';
                bytesEl.textContent = knownSize > 0 ? '0 MB / ' + formatBytes(knownSize) : '0 MB';
                label.textContent = copy.preparing;
                setState('', '');
                btn.disabled = false;
                btn.textContent = copy.idle;
            }

            function fallbackNavigate() {
                window.location.href = btn.getAttribute('data-url');
            }

            function saveBlob(blob, filename) {
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = filename;
                a.rel = 'noopener';
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(url); }, 1500);
            }

            btn.addEventListener('click', function () {
                if (btn.disabled) return;

                if (activeXhr) {
                    try { activeXhr.abort(); } catch (e) {}
                }

                panel.classList.add('is-visible');
                btn.disabled = true;
                btn.textContent = copy.downloading;
                label.textContent = copy.preparing;
                setState('', '');
                setProgress(0, knownSize);

                var xhr = new XMLHttpRequest();
                activeXhr = xhr;
                xhr.open('GET', btn.getAttribute('data-url'), true);
                xhr.responseType = 'blob';

                xhr.onprogress = function (event) {
                    label.textContent = copy.downloading;
                    var total = event.lengthComputable
                        ? event.total
                        : (knownSize > 0 ? knownSize : 0);
                    setProgress(event.loaded || 0, total);
                };

                xhr.onloadstart = function () {
                    label.textContent = copy.downloading;
                };

                xhr.onload = function () {
                    if (xhr.status < 200 || xhr.status >= 300) {
                        setState('error', copy.error);
                        label.textContent = copy.error;
                        btn.disabled = false;
                        btn.textContent = copy.retry;
                        return;
                    }

                    label.textContent = copy.finishing;
                    setProgress(knownSize || 1, knownSize || 1);
                    percentEl.textContent = '100%';
                    fill.style.width = '100%';
                    fill.classList.remove('is-indeterminate');

                    try {
                        var disposition = xhr.getResponseHeader('Content-Disposition') || '';
                        var match = /filename\*?=(?:UTF-8''|")?([^\";]+)/i.exec(disposition);
                        var filename = match
                            ? decodeURIComponent(match[1].replace(/"/g, '').trim())
                            : btn.getAttribute('data-filename');
                        saveBlob(xhr.response, filename);
                        label.textContent = copy.done;
                        setState('success', copy.done);
                        btn.disabled = false;
                        btn.textContent = copy.idle;
                    } catch (err) {
                        fallbackNavigate();
                        resetUi();
                    }
                };

                xhr.onerror = function () {
                    setState('error', copy.error);
                    label.textContent = copy.error;
                    btn.disabled = false;
                    btn.textContent = copy.retry;
                };

                xhr.onabort = function () {
                    resetUi();
                };

                try {
                    xhr.send();
                } catch (err) {
                    fallbackNavigate();
                    resetUi();
                }
            });
        })();
    </script>
    @endif
</body>
</html>
