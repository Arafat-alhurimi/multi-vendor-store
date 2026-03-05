<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>حدث خطأ</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background:#f8fafc; margin:0; padding:24px; color:#111827; }
        .box { max-width:720px; margin:48px auto; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:24px; }
        h1 { margin:0 0 12px; font-size:22px; }
        p { margin:0 0 16px; color:#374151; line-height:1.8; }
        a { display:inline-block; background:#2563eb; color:#fff; text-decoration:none; padding:10px 14px; border-radius:8px; }
    </style>
</head>
<body>
    <div class="box">
        <h1>تعذر إكمال العملية</h1>
        <p>{{ $message ?? 'حدث خطأ غير متوقع. يرجى المحاولة مرة أخرى.' }}</p>
        <a href="{{ url()->previous() }}">العودة</a>
    </div>
</body>
</html>
