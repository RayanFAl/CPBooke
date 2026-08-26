<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>Booke</title>
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
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Tajawal, "Segoe UI", Tahoma, sans-serif;
            font-size: 13.5px;
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
            margin-bottom: 14px;
        }
        .brand {
            display: inline-block;
            font-size: 18px;
            font-weight: 700;
            letter-spacing: 0.02em;
            color: var(--brand);
        }
        article {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px 16px 14px;
            margin-bottom: 12px;
        }
        article h1,
        article h2,
        article h3 {
            color: var(--heading);
            font-weight: 700;
            line-height: 1.35;
            margin: 0 0 8px;
        }
        article h1 {
            font-size: 16px;
            margin-bottom: 10px;
        }
        article h2 {
            font-size: 14px;
            margin-top: 14px;
        }
        article h3 {
            font-size: 13.5px;
            margin-top: 12px;
        }
        article p {
            margin: 0 0 8px;
            font-size: 13px;
        }
        article ul,
        article ol {
            margin: 0 0 8px;
            padding-inline-start: 1.15em;
        }
        article li {
            margin-bottom: 4px;
            font-size: 13px;
        }
        article a { color: var(--brand); }
        article img,
        article table { max-width: 100%; }
    </style>
</head>
<body>
    <main class="wrap">
        <header>
            <span class="brand">
                <img src="{{ asset('images/app_logo.png') }}" alt="Booke" width="36" height="36" style="border-radius:10px;vertical-align:middle;margin-inline-end:10px;">
                Booke
            </span>
        </header>
        @foreach ($sections as $section)
            <article>
                {!! $section['body'] !!}
            </article>
        @endforeach
    </main>
</body>
</html>
