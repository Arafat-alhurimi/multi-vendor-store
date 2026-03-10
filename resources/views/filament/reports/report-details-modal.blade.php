@php
    /** @var \App\Models\Report $report */
    $reporter = $report->user?->name ?? '-';
    $reason = $report->reason ?? '-';
    $createdAt = optional($report->created_at)->diffForHumans();
@endphp

<div style="display:grid;gap:12px;">
    <div style="border:1px solid #dbe2ea;border-radius:14px;padding:14px;background:linear-gradient(180deg,#ffffff 0%,#f8fafc 100%);">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
            <div style="font-size:14px;font-weight:800;color:#0f172a;">تفاصيل البلاغ</div>
            <span style="font-size:11px;font-weight:800;border-radius:999px;padding:4px 10px;background:#fee2e2;color:#b91c1c;">{{ $targetTypeLabel }}</span>
        </div>
        <div style="margin-top:10px;display:grid;gap:8px;">
            <div style="font-size:12px;color:#475569;"><strong style="color:#0f172a;">المبلّغ:</strong> {{ $reporter }}</div>
            <div style="font-size:12px;color:#475569;"><strong style="color:#0f172a;">العنصر:</strong> {{ $targetLabel }}</div>
            <div style="font-size:12px;color:#475569;"><strong style="color:#0f172a;">التاريخ:</strong> {{ $createdAt ?: '-' }}</div>
        </div>
    </div>

    <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px;background:#ffffff;">
        <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;">سبب البلاغ</div>
        <p style="font-size:13px;line-height:1.7;color:#475569;margin:0;">{{ $reason }}</p>
    </div>

    @if(filled($targetUrl))
        <div>
            <a href="{{ $targetUrl }}" target="_blank" style="display:inline-flex;align-items:center;border-radius:10px;padding:8px 12px;font-size:12px;font-weight:800;color:#fff;text-decoration:none;background:linear-gradient(135deg,#2563eb,#1d4ed8);">
                فتح العنصر المُبلّغ عنه
            </a>
        </div>
    @endif
</div>
