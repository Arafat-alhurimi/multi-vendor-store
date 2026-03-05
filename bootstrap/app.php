<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $exception, Request $request) {
            $rawMessage = $exception->getMessage();

            $friendlyMessage = str_contains($rawMessage, 'Integrity constraint violation')
                ? 'تعذر حفظ البيانات بسبب تعارض مع بيانات موجودة مسبقًا. تأكد من الحقول الفريدة مثل البريد أو رقم الهاتف.'
                : 'حدث خطأ في قاعدة البيانات. يرجى التحقق من المدخلات ثم المحاولة مرة أخرى.';

            if ($request->expectsJson() || $request->is('livewire*')) {
                return response()->json([
                    'message' => $friendlyMessage,
                ], 422);
            }

            if ($request->isMethod('get')) {
                return response()->view('errors.friendly', [
                    'message' => $friendlyMessage,
                ], 500);
            }

            return back()->withInput()->with('error', $friendlyMessage);
        });
    })->create();
