<!DOCTYPE html>
<html lang="{{ $locale ?? 'ar' }}" dir="{{ $dir ?? 'rtl' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? $brandName ?? 'Booke' }}</title>
</head>
@php
    $bg = '#EEF2F7';
    $white = '#FFFFFF';
    $text = '#1E293B';
    $muted = '#64748B';
    $line = '#E2E8F0';
    $brand = '#3469B2';
    $isRtl = (bool) ($rtl ?? false);
    $start = $isRtl ? 'right' : 'left';
@endphp
<body style="margin:0;padding:0;background-color:{{ $bg }};font-family:Tahoma,'Segoe UI',Arial,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:{{ $bg }};">
    <tr>
        <td align="center" style="padding:40px 16px;">
            <table role="presentation" width="560" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;width:100%;">

                {{-- Brand --}}
                <tr>
                    <td style="padding:0 4px 20px;text-align:{{ $start }};">
                        <img src="{{ $logoUrl }}" alt="{{ $brandName }}" width="32" height="32" style="display:inline-block;border:0;border-radius:8px;vertical-align:middle;">
                        <span style="display:inline-block;margin-{{ $isRtl ? 'right' : 'left' }}:10px;vertical-align:middle;font-size:17px;font-weight:700;color:{{ $brand }};letter-spacing:0.01em;">
                            {{ $brandName }}
                        </span>
                    </td>
                </tr>

                {{-- Main panel --}}
                <tr>
                    <td style="background-color:{{ $white }};border:1px solid {{ $line }};border-radius:16px;overflow:hidden;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">

                            <tr>
                                <td style="height:3px;background-color:{{ $brand }};font-size:0;line-height:0;">&nbsp;</td>
                            </tr>

                            <tr>
                                <td style="padding:32px 28px 8px;text-align:{{ $start }};">
                                    <p style="margin:0 0 10px;font-size:12px;font-weight:500;line-height:1.4;color:#94A3B8;">
                                        {{ $eyebrow }}
                                    </p>
                                    <h1 style="margin:0 0 12px;font-size:26px;line-height:1.35;font-weight:700;color:{{ $text }};">
                                        {{ $headline }}
                                    </h1>
                                    <p style="margin:0;font-size:15px;line-height:1.7;color:{{ $muted }};">
                                        {{ $intro }}
                                    </p>
                                </td>
                            </tr>

                            @if(!empty($details))
                                <tr>
                                    <td style="padding:24px 28px 8px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#F8FAFC;border-radius:12px;">
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
                                                                        <span style="display:block;margin-top:4px;font-size:12px;line-height:1.4;color:#94A3B8;">{{ $detail['hint'] }}</span>
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
                                <td style="padding:20px 28px 8px;text-align:{{ $start }};">
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
                                    <td style="padding:24px 28px 32px;text-align:{{ $start }};">
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

                {{-- Footer --}}
                <tr>
                    <td style="padding:22px 8px 0;text-align:{{ $start }};">
                        @if(!empty($supportEmail))
                            <p style="margin:0 0 4px;font-size:13px;line-height:1.5;color:{{ $muted }};">
                                {{ $footerHelpTitle }}
                            </p>
                            <p style="margin:0 0 10px;font-size:13px;line-height:1.5;color:{{ $muted }};">
                                {{ $footerHelpBody }}
                                <a href="mailto:{{ $supportEmail }}" style="color:{{ $brand }};text-decoration:none;font-weight:700;">{{ $supportEmail }}</a>
                            </p>
                        @endif
                        <p style="margin:0;font-size:12px;line-height:1.5;color:#94A3B8;">
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
