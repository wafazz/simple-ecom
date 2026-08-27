@props(['text'])

{{-- Admin-written plain text, rendered as paragraphs.

     Escaped FIRST, then line breaks are added — so a policy is typed as words
     and can never carry markup or a script into the page. The admin is trusted,
     but a trusted account is also the one worth stealing, and there is no
     reason a returns policy needs to run anything.

     A blank line starts a new paragraph; a single newline is a line break. That
     is the whole format, and it is what someone typing into a textarea already
     expects. --}}
@php
    $paragraphs = preg_split('/\R\s*\R/', trim($text)) ?: [];
@endphp

<div {{ $attributes->merge(['class' => 'prose']) }}>
    @foreach ($paragraphs as $paragraph)
        @if (trim($paragraph) !== '')
            <p>{!! nl2br(e(trim($paragraph))) !!}</p>
        @endif
    @endforeach
</div>
