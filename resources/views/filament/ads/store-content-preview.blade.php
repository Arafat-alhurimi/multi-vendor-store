@php
    $mediaPath = $record->media_path;
    $isAbsolute = is_string($mediaPath) && (str_starts_with($mediaPath, 'http://') || str_starts_with($mediaPath, 'https://'));
    $mediaUrl = $isAbsolute ? $mediaPath : \Illuminate\Support\Facades\Storage::disk('s3')->url((string) $mediaPath);
@endphp

<div style="display:grid;gap:14px;">
    <div style="padding:12px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;">
        @if ($record->media_type === 'video')
            <video src="{{ $mediaUrl }}" controls preload="metadata" style="width:100%;max-height:280px;border-radius:8px;"></video>
        @else
            <img src="{{ $mediaUrl }}" alt="محتوى الإعلان" style="width:100%;max-height:280px;object-fit:cover;border-radius:8px;" />
        @endif
    </div>

    <div style="display:grid;gap:8px;">
        <div><strong>المتجر:</strong> {{ $storeName }}</div>
        <div><strong>نوع الانتقال:</strong> {{ $transitionType }}</div>
        <div><strong>ينتقل إلى:</strong> {{ $transitionTarget }}</div>
        <div><strong>يبدأ:</strong> {{ optional($record->starts_at)->format('Y-m-d H:i') ?? '-' }}</div>
        <div><strong>ينتهي:</strong> {{ optional($record->ends_at)->format('Y-m-d H:i') ?? '-' }}</div>
    </div>
</div>
