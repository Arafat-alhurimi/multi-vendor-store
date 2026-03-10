<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
            <h3 style="font-size:16px;font-weight:700;color:#0f172a;">متابعة الأدمن في الداشبورد</h3>

            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <span style="display:inline-flex;align-items:center;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;background:#dbeafe;color:#1e40af;">
                    المتصلون: {{ $connectedCount }}
                </span>
                <span style="display:inline-flex;align-items:center;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;background:#e2e8f0;color:#334155;">
                    إجمالي الأدمن: {{ $totalCount }}
                </span>
            </div>
        </div>

        <div class="mt-4">
            @if (count($admins) === 0)
                <div style="border:1px dashed #cbd5e1;border-radius:12px;padding:14px;background:#f8fafc;color:#64748b;font-size:13px;">
                    لا يوجد حسابات أدمن.
                </div>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;">
                    @foreach ($admins as $index => $admin)
                        <div style="display:flex;align-items:center;justify-content:space-between;border:1px solid #e2e8f0;border-radius:12px;background:#ffffff;padding:10px 12px;">
                            <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                                <span style="display:inline-flex;height:28px;width:28px;align-items:center;justify-content:center;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-size:12px;font-weight:700;">
                                    {{ $index + 1 }}
                                </span>
                                <span style="font-size:14px;font-weight:600;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $admin['name'] }}</span>
                            </div>
                            <span style="font-size:12px;font-weight:700;color:{{ $admin['is_online'] ? '#047857' : '#b91c1c' }};background:{{ $admin['is_online'] ? '#d1fae5' : '#fee2e2' }};padding:4px 10px;border-radius:999px;">
                                {{ $admin['status_label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
