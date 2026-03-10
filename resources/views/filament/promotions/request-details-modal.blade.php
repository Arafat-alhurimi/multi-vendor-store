@php
    $mode = $mode ?? 'request';
    $isParticipant = $mode === 'participant';
    $promotion = $record->promotion;
    $promotionImage = $promotion?->image ? \Illuminate\Support\Facades\Storage::disk('s3')->url($promotion->image) : null;
    $promotionIsActiveNow = $promotion
        ? ($promotion->is_active
            && (! $promotion->starts_at || now()->greaterThanOrEqualTo($promotion->starts_at))
            && (! $promotion->ends_at || now()->lessThanOrEqualTo($promotion->ends_at)))
        : false;
@endphp

<div class="space-y-4">
    <div style="border:1px solid #e5e7eb;border-radius:14px;padding:14px;background:linear-gradient(90deg,#f9fafb 0%,#ffffff 100%);">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
            <div>
                <div style="font-size:12px;color:#6b7280;">{{ $isParticipant ? 'تفاصيل المشاركة الفعالة' : 'تفاصيل طلب الانضمام' }}</div>
                <div style="font-size:16px;font-weight:700;color:#111827;">{{ $isParticipant ? 'مشاركة ضمن العرض' : 'طلب بانتظار المعالجة / المراجعة' }}</div>
            </div>
            <div style="border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;{{ $record->status === 'approved' ? 'background:#dcfce7;color:#166534;' : ($record->status === 'pending' ? 'background:#fef3c7;color:#92400e;' : 'background:#fee2e2;color:#991b1b;') }}">
                @if ($record->status === 'approved')
                    مشارك
                @elseif ($record->status === 'pending')
                    قيد الانتظار
                @elseif ($record->status === 'rejected')
                    مرفوض
                @else
                    -
                @endif
            </div>
        </div>
    </div>

    @if ($promotion)
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#ffffff;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div style="display:flex;align-items:center;gap:10px;min-width:0;">
                    @if ($promotionImage)
                        <img src="{{ $promotionImage }}" alt="{{ $promotion->title }}" style="width:54px;height:54px;border-radius:10px;object-fit:cover;border:1px solid #e5e7eb;">
                    @else
                        <div style="width:54px;height:54px;border-radius:10px;border:1px dashed #d1d5db;display:flex;align-items:center;justify-content:center;font-size:11px;color:#9ca3af;">بدون صورة</div>
                    @endif
                    <div style="min-width:0;">
                        <div style="font-size:12px;color:#6b7280;">تفاصيل العرض</div>
                        <div style="font-size:15px;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:520px;">{{ $promotion->title ?? '-' }}</div>
                    </div>
                </div>

                <div style="border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;{{ $promotionIsActiveNow ? 'background:#dcfce7;color:#166534;' : 'background:#fee2e2;color:#991b1b;' }}">
                    {{ $promotionIsActiveNow ? 'نشط الآن' : 'غير نشط' }}
                </div>
            </div>

            <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:10px;">
                <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
                    <div style="font-size:12px;color:#6b7280;">تاريخ البداية</div>
                    <div style="font-weight:600;color:#111827;">{{ $promotion->starts_at?->format('Y-m-d H:i') ?? '-' }}</div>
                </div>
                <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
                    <div style="font-size:12px;color:#6b7280;">تاريخ النهاية</div>
                    <div style="font-weight:600;color:#111827;">{{ $promotion->ends_at?->format('Y-m-d H:i') ?? '-' }}</div>
                </div>
                <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
                    <div style="font-size:12px;color:#6b7280;">نوع الخصم</div>
                    <div style="font-weight:600;color:#111827;">{{ $promotion->discount_type === 'percentage' ? 'نسبة مئوية' : 'قيمة ثابتة' }}</div>
                </div>
                <div style="border:1px solid #e5e7eb;border-radius:10px;padding:10px;">
                    <div style="font-size:12px;color:#6b7280;">قيمة الخصم</div>
                    <div style="font-weight:600;color:#111827;">{{ $promotion->discount_type === 'percentage' ? (rtrim(rtrim(number_format((float) $promotion->discount_value, 2, '.', ''), '0'), '.') . '%') : number_format((float) $promotion->discount_value, 2, '.', '') }}</div>
                </div>
            </div>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:repeat({{ $isParticipant ? 3 : 1 }},minmax(0,1fr));gap:10px;">
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#f9fafb;">
            <div style="font-size:12px;color:#6b7280;">إجمالي المنتجات المشاركة</div>
            <div style="font-size:22px;font-weight:700;color:#111827;line-height:1.2;">{{ (int) ($summary['products_count'] ?? 0) }}</div>
        </div>
        @if ($isParticipant)
            <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#f9fafb;">
                <div style="font-size:12px;color:#6b7280;">إضافات السلة المستفيدة</div>
                <div style="font-size:22px;font-weight:700;color:#111827;line-height:1.2;">{{ (int) ($summary['cart_additions_count'] ?? 0) }}</div>
            </div>
            <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#f9fafb;">
                <div style="font-size:12px;color:#6b7280;">الطلبات المستفيدة</div>
                <div style="font-size:22px;font-weight:700;color:#111827;line-height:1.2;">{{ (int) ($summary['orders_count'] ?? 0) }}</div>
            </div>
        @endif
    </div>

    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;">
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="font-size:12px;color:#6b7280;">المتجر</div>
            <div style="font-weight:600;color:#111827;">{{ $record->store?->name ?? '-' }}</div>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="font-size:12px;color:#6b7280;">النوع</div>
            <div style="font-weight:600;color:#111827;">
                @if ($record->promotable_type === \App\Models\Store::class)
                    متجر كامل
                @elseif ($record->promotable_type === \App\Models\Category::class)
                    قسم رئيسي
                @elseif ($record->promotable_type === \App\Models\Subcategory::class)
                    قسم فرعي
                @elseif ($record->promotable_type === \App\Models\Product::class)
                    منتج
                @else
                    {{ class_basename($record->promotable_type) }}
                @endif
            </div>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="font-size:12px;color:#6b7280;">الاسم</div>
            <div style="font-weight:600;color:#111827;">
                @php($target = $record->promotable)
                @if ($target instanceof \App\Models\Store)
                    {{ $target->name }}
                @elseif ($target instanceof \App\Models\Category || $target instanceof \App\Models\Subcategory || $target instanceof \App\Models\Product)
                    {{ $target->name_ar ?? ('#' . $record->promotable_id) }}
                @else
                    {{ '#' . $record->promotable_id }}
                @endif
            </div>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
            <div style="font-size:12px;color:#6b7280;">الحالة</div>
            <div style="font-weight:600;color:#111827;">
                @if ($record->status === 'approved')
                    مشارك
                @elseif ($record->status === 'pending')
                    قيد الانتظار
                @elseif ($record->status === 'rejected')
                    مرفوض
                @else
                    -
                @endif
            </div>
        </div>
    </div>

    <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;">
        <div style="margin-bottom:10px;font-size:13px;font-weight:700;color:#111827;">جدول المنتجات المشاركة ({{ collect($products)->count() }})</div>

        @if (collect($products)->isEmpty())
            <div style="font-size:13px;color:#6b7280;">لا توجد منتجات ضمن هذا الطلب.</div>
        @else
            <div style="overflow-x:auto;border:1px solid #e5e7eb;border-radius:10px;">
                <table style="min-width:100%;font-size:13px;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#f9fafb;color:#374151;">
                            <th style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">المنتج</th>
                            <th style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">القسم</th>
                            <th style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">السعر قبل</th>
                            <th style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">السعر بعد</th>
                            <th style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">المخزون</th>
                            @if ($isParticipant)
                                <th style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">إضافات السلة</th>
                                <th style="padding:10px 8px;border-bottom:1px solid #e5e7eb;text-align:right;white-space:nowrap;">الطلبات</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (collect($products) as $product)
                            <tr>
                                <td style="padding:9px 8px;border-bottom:1px solid #f1f5f9;">{{ $product['name_ar'] ?? '-' }}</td>
                                <td style="padding:9px 8px;border-bottom:1px solid #f1f5f9;">
                                    <span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;background:#eef2ff;color:#3730a3;font-weight:700;font-size:12px;">{{ collect([$product['category_name'] ?? null, $product['subcategory_name'] ?? null])->filter()->implode('>') ?: '-' }}</span>
                                </td>
                                <td style="padding:9px 8px;border-bottom:1px solid #f1f5f9;">{{ $product['base_price'] ?? '-' }}</td>
                                <td style="padding:9px 8px;border-bottom:1px solid #f1f5f9;font-weight:700;color:#047857;">{{ $product['after_price'] ?? '-' }}</td>
                                <td style="padding:9px 8px;border-bottom:1px solid #f1f5f9;">{{ (int) ($product['stock'] ?? 0) }}</td>
                                @if ($isParticipant)
                                    <td style="padding:9px 8px;border-bottom:1px solid #f1f5f9;">{{ (int) ($product['cart_additions_count'] ?? 0) }}</td>
                                    <td style="padding:9px 8px;border-bottom:1px solid #f1f5f9;">{{ (int) ($product['orders_count'] ?? 0) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
