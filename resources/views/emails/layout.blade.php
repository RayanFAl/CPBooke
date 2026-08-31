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
<body style="margin:0;padding:0;background-color:{{ $colors['background'] }};font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ $colors['background'] }};min-width:100%;">
    <tr>
        <td align="center" style="padding:32px 16px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;width:100%;background-color:{{ $colors['surface'] }};border:1px solid {{ $colors['border'] }};border-radius:16px;overflow:hidden;">
                {{-- Header --}}
                <tr>
                    <td bgcolor="{{ $colors['primary'] }}" style="background-color:{{ $colors['primary'] }};background:linear-gradient(135deg, {{ $colors['secondary'] }} 0%, {{ $colors['primary'] }} 100%);padding:0;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td style="height:4px;background-color:{{ $colors['accent'] }};font-size:0;line-height:0;">&nbsp;</td>
                            </tr>
                            <tr>
                                <td style="padding:28px 32px 24px;text-align:{{ ($rtl ?? false) ? 'right' : 'left' }};">
                                    <p style="margin:0;font-size:22px;font-weight:700;line-height:1.3;color:#FFFFFF;letter-spacing:0.02em;">
                                        {{ $brandName ?? 'Booke' }}
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Body --}}
                <tr>
                    <td style="padding:32px;background-color:{{ $colors['cardBackground'] }};color:{{ $colors['textPrimary'] }};font-size:15px;line-height:1.7;text-align:{{ ($rtl ?? false) ? 'right' : 'left' }};">
                        @yield('content')
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:24px 32px;background-color:{{ $colors['background'] }};border-top:1px solid {{ $colors['border'] }};text-align:{{ ($rtl ?? false) ? 'right' : 'left' }};">
                        @if(!empty($supportEmail))
                            <p style="margin:0 0 8px;font-size:13px;line-height:1.6;color:{{ $colors['textSecondary'] }};">
                                {{ $footerHelp ?? 'Need help? Contact us at' }}
                                <a href="mailto:{{ $supportEmail }}" style="color:{{ $colors['primary'] }};text-decoration:none;font-weight:600;">{{ $supportEmail }}</a>
                            </p>
                        @endif
                        <p style="margin:0;font-size:12px;line-height:1.5;color:{{ $colors['textSecondary'] }};">
                            {{ $footerAutomated ?? 'This is an automated message from' }} {{ $brandName ?? 'Booke' }}.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
