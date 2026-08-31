@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;color:{{ $colors['textPrimary'] }};">
        {{ $greeting }}
    </p>

    <p style="margin:0 0 20px;font-size:15px;line-height:1.7;color:{{ $colors['textPrimary'] }};">
        {{ $instruction }}
    </p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td style="background-color:{{ $colors['background'] }};border:1px solid {{ $colors['border'] }};border-radius:12px;padding:16px 28px;text-align:center;">
                <span style="display:inline-block;font-size:28px;font-weight:700;letter-spacing:0.35em;color:{{ $colors['primary'] }};font-family:'Courier New',Courier,monospace;">
                    {{ $otp }}
                </span>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:{{ $colors['textSecondary'] }};">
        {{ $expiresLine }}
    </p>

    <p style="margin:0;font-size:14px;line-height:1.6;color:{{ $colors['textSecondary'] }};">
        {{ $ignoreLine }}
    </p>
@endsection
