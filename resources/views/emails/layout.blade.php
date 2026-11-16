<!DOCTYPE html>
<html lang="{{ $locale ?? 'ar' }}" dir="{{ $dir ?? 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? $brandName ?? 'Booke' }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
@php
    $isRtl = (bool) ($rtl ?? false);
    $alignStart = $align ?? ($isRtl ? 'right' : 'left');
    $dirValue = $dir ?? ($isRtl ? 'rtl' : 'ltr');
    $padStart = $isRtl ? 'right' : 'left';
    $logoSrc = $logoUrl ?? '';
    if (isset($message) && !empty($logoPath) && is_file($logoPath)) {
        $logoSrc = $message->embed($logoPath);
    }
@endphp
<body dir="{{ $dirValue }}" style="margin:0;padding:0;direction:{{ $dirValue }};background-color:{{ $colors['background'] }};font-family:Tahoma,'Segoe UI',Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" dir="{{ $dirValue }}" style="direction:{{ $dirValue }};background-color:{{ $colors['background'] }};min-width:100%;">
    <tr>
        <td align="center" style="padding:40px 16px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" dir="{{ $dirValue }}" style="max-width:600px;width:100%;direction:{{ $dirValue }};">

                {{-- Brand mark above card --}}
                <tr>
                    <td style="padding:0 8px 18px;text-align:center;">
                        @if(!empty($logoSrc))
                            <img src="{{ $logoSrc }}" alt="{{ $brandName ?? 'Booke' }}" width="48" height="48" style="display:inline-block;border:0;border-radius:12px;vertical-align:middle;background-color:#FFFFFF;">
                        @endif
                        <span dir="ltr" style="display:inline-block;margin-{{ $padStart }}:12px;vertical-align:middle;font-size:22px;font-weight:700;color:{{ $colors['primary'] }};unicode-bidi:isolate;">
                            {{ $brandName ?? 'Booke' }}
                        </span>
                    </td>
                </tr>

                {{-- Main card --}}
                <tr>
                    <td style="background-color:{{ $colors['surface'] }};border:1px solid {{ $colors['border'] }};border-radius:18px;overflow:hidden;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" dir="{{ $dirValue }}" style="direction:{{ $dirValue }};">

                            {{-- Brand accent strip --}}
                            <tr>
                                <td style="height:4px;line-height:4px;font-size:0;background-color:{{ $colors['primary'] }};">
                                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                        <tr>
                                            <td width="72%" style="height:4px;background-color:{{ $colors['primary'] }};font-size:0;line-height:0;">&nbsp;</td>
                                            <td width="28%" style="height:4px;background-color:{{ $colors['accent'] }};font-size:0;line-height:0;">&nbsp;</td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                            @isset($headerEyebrow)
                                <tr>
                                    <td dir="{{ $dirValue }}" style="padding:22px 32px 0;direction:{{ $dirValue }};text-align:{{ $alignStart }};">
                                        <p style="margin:0;font-size:12px;font-weight:700;color:{{ $colors['primary'] }};">
                                            {{ $headerEyebrow }}
                                        </p>
                                    </td>
                                </tr>
                            @endisset

                            <tr>
                                <td dir="{{ $dirValue }}" style="padding:28px 32px 32px;direction:{{ $dirValue }};background-color:{{ $colors['cardBackground'] }};color:{{ $colors['textPrimary'] }};font-size:15px;line-height:1.85;text-align:{{ $alignStart }};unicode-bidi:plaintext;">
                                    @yield('content')
                                </td>
                            </tr>

                            <tr>
                                <td style="padding:0 32px;">
                                    <div style="height:1px;background-color:{{ $colors['border'] }};font-size:0;line-height:0;">&nbsp;</div>
                                </td>
                            </tr>

                            <tr>
                                <td dir="{{ $dirValue }}" style="padding:22px 32px 26px;direction:{{ $dirValue }};background-color:{{ $colors['surface'] }};text-align:{{ $alignStart }};">
                                    @if(!empty($supportEmail) || !empty($supportPhone))
                                        <p style="margin:0 0 8px;font-size:13px;line-height:1.7;color:{{ $colors['textSecondary'] }};">
                                            {{ $footerHelp ?? 'Need help? Contact us at' }}
                                            @if(!empty($supportEmail))
                                                <a dir="ltr" href="mailto:{{ $supportEmail }}" style="color:{{ $colors['primary'] }};text-decoration:none;font-weight:700;unicode-bidi:isolate;">{{ $supportEmail }}</a>
                                            @endif
                                            @if(!empty($supportEmail) && !empty($supportPhone))
                                                <span> · </span>
                                            @endif
                                            @if(!empty($supportPhone))
                                                <a dir="ltr" href="tel:{{ preg_replace('/\s+/', '', $supportPhone) }}" style="color:{{ $colors['primary'] }};text-decoration:none;font-weight:700;unicode-bidi:isolate;">{{ $supportPhone }}</a>
                                            @endif
                                        </p>
                                    @endif
                                    <p style="margin:0;font-size:12px;line-height:1.6;color:#94a3b8;">
                                        {{ $footerAutomated ?? 'This is an automated message from' }}
                                        <span dir="ltr" style="unicode-bidi:isolate;">{{ $brandName ?? 'Booke' }}</span>.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 8px 0;text-align:center;">
                        <p style="margin:0;font-size:11px;line-height:1.5;color:#94a3b8;">
                            © {{ date('Y') }}
                            <span dir="ltr" style="unicode-bidi:isolate;">{{ $brandName ?? 'Booke' }}</span>
                            · {{ $footerRights ?? 'All rights reserved' }}
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
