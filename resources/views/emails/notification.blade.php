@extends('emails.layout')

@section('content')
    @if(!empty($headline))
        <p style="margin:0 0 20px;font-size:18px;font-weight:700;line-height:1.4;color:{{ $colors['textPrimary'] }};">
            {{ $headline }}
        </p>
    @endif

    <div style="color:{{ $colors['textPrimary'] }};font-size:15px;line-height:1.7;">
        {!! $bodyHtml !!}
    </div>
@endsection
