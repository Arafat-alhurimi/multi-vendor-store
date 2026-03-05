@php
    /** @var \App\Models\Category $category */
    $category = $getRecord();
    $subcategories = $category->subcategories()->orderBy('name_ar')->get(['id', 'name_ar', 'name_en', 'is_active']);
@endphp

<div class="w-full overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
    @if ($subcategories->isEmpty())
        <div class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
            لا توجد فئات فرعية.
        </div>
    @else
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800/60">
                <tr>
                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">الاسم (عربي)</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">الاسم (EN)</th>
                    <th class="px-3 py-2 text-right font-medium text-gray-700 dark:text-gray-200">الحالة</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subcategories as $subcategory)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="px-3 py-2 text-gray-800 dark:text-gray-100">{{ $subcategory->name_ar }}</td>
                        <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ $subcategory->name_en }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $subcategory->is_active ? 'bg-success-50 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-danger-50 text-danger-700 dark:bg-danger-500/15 dark:text-danger-300' }}">
                                {{ $subcategory->is_active ? 'نشطة' : 'غير نشطة' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
