@props(['attachment'])

@php
    $isImage  = str_starts_with((string) $attachment->mime, 'image/');
    $isVideo  = str_starts_with((string) $attachment->mime, 'video/');
    $viewUrl  = route('ticket-attachments.download', $attachment) . '?view=1';
    $dlUrl    = route('ticket-attachments.download', $attachment);
@endphp

@if ($isImage)
    <a href="{{ $viewUrl }}" target="_blank" rel="noopener" title="{{ $attachment->original_name }}">
        <img class="chat-img" src="{{ $viewUrl }}" alt="{{ $attachment->original_name }}" loading="lazy">
    </a>
@elseif ($isVideo)
    <video class="chat-img" controls preload="metadata" src="{{ $viewUrl }}"></video>
@else
    <a class="attach-chip" href="{{ $dlUrl }}" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
        {{ $attachment->original_name }} <span style="opacity:.6">({{ $attachment->humanSize() }})</span>
    </a>
@endif
