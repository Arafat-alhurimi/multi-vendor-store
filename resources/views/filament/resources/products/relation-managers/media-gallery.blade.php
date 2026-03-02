<x-filament::section>
    @php
        $mediaItems = $this->getMediaItems();
    @endphp

    @if ($mediaItems->isEmpty())
        <div class="text-sm text-gray-500">لا توجد وسائط لهذا المنتج.</div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, 125px); gap: 10px; align-items: start; justify-content: start;">
            @foreach ($mediaItems as $media)
                @php
                    $mimeType = (string) ($media->mime_type ?? '');
                    $isImage = str_starts_with($mimeType, 'image/');
                    $isVideo = str_starts_with($mimeType, 'video/');
                    $url = (string) ($media->url ?? '');
                @endphp

                <div class="rounded-md border p-1" style="width: 125px; max-width: 125px; min-width: 125px;">
                    @if ($isImage)
                        <img src="{{ $url }}" alt="{{ $media->file_name }}" class="rounded object-cover" style="width: 125px; height: 125px; min-width: 125px; max-width: 125px; min-height: 125px; max-height: 125px;" />
                        <a href="{{ $url }}" target="_blank" class="mt-1.5 block rounded border px-1.5 py-1 text-center text-[11px]">
                            عرض الصورة
                        </a>
                    @elseif ($isVideo)
                        <video src="{{ $url }}" controls muted playsinline preload="metadata" class="rounded object-cover" style="width: 125px; height: 125px; min-width: 125px; max-width: 125px; min-height: 125px; max-height: 125px;"></video>
                        <a href="{{ $url }}" target="_blank" class="mt-1.5 block rounded border px-1.5 py-1 text-center text-[11px]">
                            تشغيل الفيديو
                        </a>
                    @else
                        <div class="flex items-center justify-center rounded border text-[11px] text-gray-500" style="width: 125px; height: 125px; min-width: 125px; max-width: 125px; min-height: 125px; max-height: 125px;">
                            ملف
                        </div>
                        <a href="{{ $url }}" target="_blank" class="mt-1.5 block rounded border px-1.5 py-1 text-center text-[11px]">
                            فتح الملف
                        </a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-filament::section>
