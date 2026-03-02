<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إشعارات الأدمن</title>
    <link href="https://unpkg.com/tailwindcss@^2/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-900 p-6" dir="rtl">
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-4">
        <div>
            <a href="/admin" class="inline-flex items-center gap-2 px-3 py-1 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">◀ رجوع</a>
        </div>
        <div class="text-right">
            <h1 class="text-2xl font-semibold">إدارة الإشعارات</h1>
            <div class="text-sm text-gray-600">صفحة تعرض إشعارات النظام — يمكن تمييزها وحذفها هنا.</div>
        </div>
    </div>

    @if($user_id)
        <div class="mb-4 p-3 bg-white border rounded text-sm text-gray-700">عرض إشعارات خاصة بالمستخدم: <strong>{{ $user_id }}</strong></div>
    @else
        <div class="mb-4 p-3 bg-white border rounded text-sm text-gray-700">عرض جميع الإشعارات من مصادر النظام المختلفة (مستخدمون، متاجر، أو مصادر أخرى).</div>
    @endif

    @foreach($rows as $row)
        @php $d = $row['data']; @endphp
        @php
            // determine source badge
            if (! empty($d['user_id'])) {
                $sourceLabel = 'من: مستخدم';
            } elseif (! empty($d['source'])) {
                $sourceLabel = 'من: ' . $d['source'];
            } else {
                $sourceLabel = 'المصدر: ' . ($row['type'] ?? 'غير معروف');
            }
        @endphp

        <div class="bg-white border rounded-lg p-4 mb-3 shadow-sm">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start">
                <div style="text-align:right;" class="md:ml-4">
                    <div class="text-sm text-gray-600">{{ $sourceLabel }}</div>
                    <div class="font-medium text-lg">{{ $d['title'] ?? '-' }}</div>
                    <div class="text-gray-700 mt-1">{!! nl2br(e($d['body'] ?? '-')) !!}</div>
                    <div class="text-xs text-gray-500 mt-2">{{ $row['created_at'] }} — {{ $row['read_at'] ? 'مقروء' : 'غير مقروء' }}</div>
                </div>

                <div class="flex-shrink-0 mt-3 md:mt-0 md:text-left">
                    <div class="text-sm text-gray-600">متعلق بـ</div>
                    <div class="font-medium">{{ $row['notifiable_type'] }}</div>
                    <div class="text-sm">ID: {{ $row['notifiable_id'] }}</div>

                    <div class="mt-3 flex gap-2">
                        <form method="POST" action="/admin/notifications/delete/{{ $row['id'] }}" onsubmit="return confirm('حذف الإشعار؟');">
                            @csrf
                            <button type="submit" class="px-3 py-1 bg-red-600 text-white rounded">حذف</button>
                        </form>

                        <a href="/admin/notifications" class="px-3 py-1 bg-gray-200 text-gray-800 rounded">صفحة الإشعارات</a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
</body>
</html>
