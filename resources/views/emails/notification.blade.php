@extends('emails.layout')

@section('content')
    @php
        $isRtl = (bool) ($rtl ?? false);
        $dirValue = $dir ?? ($isRtl ? 'rtl' : 'ltr');
        $alignStart = $align ?? ($isRtl ? 'right' : 'left');
    @endphp

    @if(!empty($headline))
        <h1 dir="{{ $dirValue }}" style="margin:0 0 16px;direction:{{ $dirValue }};text-align:{{ $alignStart }};font-size:22px;font-weight:700;line-height:1.45;color:{{ $colors['textPrimary'] }};unicode-bidi:plaintext;">
            {{ $headline }}
        </h1>
    @endif

    <div dir="{{ $dirValue }}" style="direction:{{ $dirValue }};text-align:{{ $alignStart }};">
        {!! $bodyHtml !!}
    </div>
@endsection
