<!DOCTYPE html>
<html lang="{{ $locale ?? 'ar' }}" dir="{{ $dir ?? 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? $brandName ?? 'Booke' }}</title>
</head>
@php
    $bg = '#eef3f9';
    $white = '#FFFFFF';
    $text = '#0f172a';
    $muted = '#64748b';
    $line = '#e2e8f0';
    $brand = '#2f6db8';
    $accent = '#f0b429';
    $isRtl = (bool) ($rtl ?? false);
    $dirValue = $dir ?? ($isRtl ? 'rtl' : 'ltr');
    $start = $isRtl ? 'right' : 'left';
    $padStart = $isRtl ? 'right' : 'left';
    $logoSrc = $logoUrl ?? '';
    if (isset($message) && !empty($logoPath) && is_file($logoPath)) {
        $logoSrc = $message->embed($logoPath);
    }
@endphp
<body dir="{{ $dirValue }}" style="margin:0;padding:0;direction:{{ $dirValue }};background-color:{{ $bg }};font-family:Tahoma,'Segoe UI',Arial,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" dir="{{ $dirValue }}" style="direction:{{ $dirValue }};background-color:{{ $bg }};">
    <tr>
        <td align="center" style="padding:40px 16px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" dir="{{ $dirValue }}" style="max-width:600px;width:100%;direction:{{ $dirValue }};">

                <tr>
                    <td style="padding:0 8px 18px;text-align:center;">
                        @if(!empty($logoSrc))
                            <img src="{{ $logoSrc }}" alt="{{ $brandName }}" width="48" height="48" style="display:inline-block;border:0;border-radius:12px;vertical-align:middle;background-color:#FFFFFF;">
                        @endif
                        <span dir="ltr" style="display:inline-block;margin-{{ $padStart }}:12px;vertical-align:middle;font-size:22px;font-weight:700;color:{{ $brand }};unicode-bidi:isolate;">
                            {{ $brandName }}
                        </span>
                    </td>
                </tr>

                <tr>
                    <td style="background-color:{{ $white }};border:1px solid {{ $line }};border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.06);">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="height:4px;line-height:4px;font-size:0;">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                        <tr>
                                            <td width="72%" style="height:4px;background-color:{{ $brand }};font-size:0;line-height:0;">&nbsp;</td>
                                            <td width="28%" style="height:4px;background-color:{{ $accent }};font-size:0;line-height:0;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            <tr>
                                <td dir="{{ $dirValue }}" style="padding:28px 32px 8px;direction:{{ $dirValue }};text-align:{{ $start }};unicode-bidi:plaintext;">
                                    <p style="margin:0 0 10px;font-size:12px;font-weight:700;color:{{ $brand }};">
                                        {{ $eyebrow }}
                                    </p>
                                    <h1 style="margin:0 0 12px;font-size:22px;line-height:1.45;font-weight:700;color:{{ $text }};">
                                        {{ $headline }}
                                    </h1>
                                    <p style="margin:0;font-size:15px;line-height:1.85;color:{{ $muted }};">
                                        {{ $intro }}
                                    </p>
                                </td>
                            </tr>

                            @if(!empty($details))
                                <tr>
                                    <td style="padding:22px 32px 8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f8fafc;border:1px solid {{ $line }};border-radius:14px;">
                                            <tr>
                                                <td style="padding:6px 18px;">
                                                    @foreach($details as $index => $detail)
                                                        @php
                                                            $rowPad = 'padding:14px 0;';
                                                            if ($index < count($details) - 1) {
                                                                $rowPad .= 'border-bottom:1px solid '.$line.';';
                                                            }
                                                            $isMono = !empty($detail['mono']);
                                                            $valueFont = $isMono
                                                                ? "'Courier New',Courier,monospace"
                                                                : "Tahoma,'Segoe UI',Arial,sans-serif";
                                                        @endphp
                                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                                            <tr>
                                                                <td style="{{ $rowPad }}text-align:{{ $start }};">
                                                                    <span style="display:block;font-size:12px;color:{{ $muted }};margin-bottom:4px;">{{ $detail['label'] }}</span>
                                                                    <span style="display:block;font-size:15px;font-weight:600;line-height:1.45;color:{{ $text }};font-family:{{ $valueFont }};">{{ $detail['value'] }}</span>
                                                                    @if(!empty($detail['hint']))
                                                                        <span style="display:block;margin-top:4px;font-size:12px;line-height:1.4;color:#94a3b8;">{{ $detail['hint'] }}</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @endforeach
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endif

                            <tr>
                                <td style="padding:20px 32px 8px;text-align:{{ $start }};">
                                    <p style="margin:0 0 6px;font-size:14px;font-weight:700;line-height:1.5;color:{{ $text }};">
                                        {{ $warningTitle }}
                                    </p>
                                    <p style="margin:0;font-size:14px;line-height:1.65;color:{{ $muted }};">
                                        {{ $warningBody }}
                                    </p>
                                </td>
                            </tr>

                            @if(!empty($ctaUrl) && !empty($ctaLabel))
                                <tr>
                                    <td style="padding:24px 32px 32px;text-align:{{ $start }};">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                            <tr>
                                                <td bgcolor="{{ $brand }}" style="background-color:{{ $brand }};border-radius:12px;">
                                                    <a href="{{ $ctaUrl }}" style="display:inline-block;padding:13px 22px;font-size:14px;font-weight:700;color:#FFFFFF;text-decoration:none;">
                                                        {{ $ctaLabel }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 8px 0;text-align:center;">
                        @if(!empty($supportEmail))
                            <p style="margin:0 0 6px;font-size:13px;line-height:1.5;color:{{ $muted }};">
                                {{ $footerHelpTitle }}
                                <a href="mailto:{{ $supportEmail }}" style="color:{{ $brand }};text-decoration:none;font-weight:700;">{{ $supportEmail }}</a>
                            </p>
                        @endif
                        <p style="margin:0;font-size:12px;line-height:1.5;color:#94a3b8;">
                            {{ $footerAutomated }} {{ $brandName }}
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
