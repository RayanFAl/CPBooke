@extends('emails.layout')

@section('content')
    @php
        $isRtl = (bool) ($rtl ?? false);
        $dirValue = $dir ?? ($isRtl ? 'rtl' : 'ltr');
        $alignStart = $align ?? ($isRtl ? 'right' : 'left');
    @endphp

    <p dir="{{ $dirValue }}" style="margin:0 0 8px;direction:{{ $dirValue }};text-align:{{ $alignStart }};font-size:15px;line-height:1.85;color:{{ $colors['textPrimary'] }};unicode-bidi:plaintext;">
        {{ $greeting }}
    </p>

    <p dir="{{ $dirValue }}" style="margin:0 0 24px;direction:{{ $dirValue }};text-align:{{ $alignStart }};font-size:15px;line-height:1.85;color:{{ $colors['textSecondary'] }};unicode-bidi:plaintext;">
        {{ $instruction }}
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td align="center" style="background-color:#f8fafc;border:1px solid {{ $colors['border'] }};border-radius:16px;padding:22px 18px;">
                <p style="margin:0 0 10px;font-size:11px;font-weight:700;color:{{ $colors['textSecondary'] }};">
                    OTP
                </p>
                <span dir="ltr" style="display:inline-block;font-size:32px;font-weight:700;letter-spacing:0.28em;color:{{ $colors['primary'] }};font-family:'Courier New',Courier,monospace;unicode-bidi:isolate;">
                    {{ $otp }}
                </span>
            </td>
        </tr>
    </table>

    <p dir="{{ $dirValue }}" style="margin:0 0 10px;direction:{{ $dirValue }};text-align:{{ $alignStart }};font-size:14px;line-height:1.75;color:{{ $colors['textSecondary'] }};unicode-bidi:plaintext;">
        {{ $expiresLine }}
    </p>

    <p dir="{{ $dirValue }}" style="margin:0;direction:{{ $dirValue }};text-align:{{ $alignStart }};font-size:14px;line-height:1.75;color:{{ $colors['textSecondary'] }};unicode-bidi:plaintext;">
        {{ $ignoreLine }}
    </p>
@endsection
