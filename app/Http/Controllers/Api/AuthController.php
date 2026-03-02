<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\Users\UserResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Http\Request;
use Kreait\Firebase\Contract\Auth as FirebaseAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Filament\Notifications\Notification;


class AuthController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseAuth $firebase)
    {
        $this->firebase = $firebase;
    }

    // 1. تسجيل البيانات الأولية
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'phone' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
            'role' => 'required|in:customer,vendor',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'is_active' => false, // لا يفعل إلا بعد OTP
        ]);

        $admins = User::where('role', 'admin')->get();

        if ($admins->isEmpty()) {
            Log::warning('No admins found to send notification to');
        } else {
            foreach ($admins as $admin) {
                try {
                    $userUrl = UserResource::getUrl('view', ['record' => $user]);

                    Notification::make()
                        ->title('مستخدم جديد')
                        ->body("تم تسجيل مستخدم جديد: {$user->name} ({$user->email})")
                        ->actions([
                            Action::make('openUser')
                                ->label('عرض المستخدم')
                                ->url($userUrl),
                        ])
                        ->success()
                        ->sendToDatabase($admin);

                    // quick check whether a row was created for this notifiable
                    $exists = DB::table('filament_notifications')
                        ->where('notifiable_type', get_class($admin))
                        ->where('notifiable_id', $admin->getKey())
                        ->where('data', 'like', "%{$user->email}%")
                        ->exists();

                    if ($exists) {
                        // Ensure existing rows include the 'format' key Filament expects
                        $rows = DB::table('filament_notifications')
                            ->where('notifiable_type', get_class($admin))
                            ->where('notifiable_id', $admin->getKey())
                            ->where('data', 'like', "%{$user->email}%")
                            ->get();

                        foreach ($rows as $row) {
                            $data = json_decode($row->data, true);

                            if (! isset($data['format']) || $data['format'] !== 'filament') {
                                $data['format'] = 'filament';
                                $data['duration'] = $data['duration'] ?? 'persistent';
                            }

                            $data['notification_category'] = $data['notification_category'] ?? 'account';
                            $data['user_id'] = $data['user_id'] ?? $user->id;
                            $data['target_url'] = $data['target_url'] ?? $userUrl;

                            try {
                                DB::table('filament_notifications')
                                    ->where('id', $row->id)
                                    ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);

                                Log::info('Updated existing notifications row metadata for: ' . $admin->email);
                            } catch (\Exception $e) {
                                Log::error('Failed to update notifications row: ' . $e->getMessage());
                            }
                        }

                        Log::info('Filament notification stored for: ' . $admin->email);
                    } else {
                        // Fallback: insert directly into filament_notifications with Filament format
                        try {
                            $payload = [
                                'title' => 'مستخدم جديد',
                                'body' => "تم تسجيل مستخدم جديد: {$user->name} ({$user->email})",
                                'user_id' => $user->id,
                                'target_url' => $userUrl,
                                'duration' => 'persistent',
                                'format' => 'filament',
                                'notification_category' => 'account',
                            ];

                            DB::table('filament_notifications')->insert([
                                'id' => (string) Str::uuid(),
                                'type' => 'App\\Notifications\\UserRegistered',
                                'notifiable_type' => get_class($admin),
                                'notifiable_id' => $admin->getKey(),
                                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            Log::warning('Notifications fallback inserted for: ' . $admin->email);
                        } catch (\Exception $e) {
                            Log::error('Failed to insert filament_notifications fallback: ' . $e->getMessage());
                        }
                    }

                } catch (\Exception $e) {
                    Log::error('Failed to send Filament notification: ' . $e->getMessage());
                }
            }
        }


        return response()->json(['message' => 'تم تسجيل البيانات، يرجى التحقق من كود OTP']);
    }

    // 2. التحقق من الـ OTP وتفعيل الحساب
    public function verifyOtp(Request $request)
    {
        try {
            // التحقق من توكن فايربيز المرسل من فلاتر
            $verifiedIdToken = $this->firebase->verifyIdToken($request->firebase_token);
            $phone = $verifiedIdToken->claims()->get('phone_number');

            // البحث عن المستخدم (يجب تنظيف الرقم ليطابق صيغة فايربيز +966...)
            $user = User::where('phone', 'like', "%$phone%")->first();

            if (!$user) return response()->json(['message' => 'المستخدم غير موجود'], 404);

            if ($user->role === 'customer') {
                // المستخدم العادي: تفعيل فوري
                $user->update([
                    'is_active' => true,
                    'otp_verified_at' => now(),
                ]);
                $message = "تم تفعيل حسابك بنجاح";
            } else {
                // البائع: إنشاء متجر وانتظار الأدمن
                $user->update([
                    'is_active' => false,
                    'otp_verified_at' => now(),
                ]); // يبقى false حتى يوافق الأدمن
                $message = "تم التحقق من هاتفك، حسابك بانتظار موافقة الإدارة";
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'token' => $token,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'فشل التحقق من الكود'], 401);
        }
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'الحساب غير مفعل'], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'token' => $token,
            'user' => $user,
        ]);
    }

public function forgotPassword(Request $request)
{
    $newPassword = $request->input('new_password');

    try {
        // التحقق من توكن فايربيز المرسل من فلاتر
        $verifiedIdToken = $this->firebase->verifyIdToken($request->firebase_token);
        $phone = $verifiedIdToken->claims()->get('phone_number');

        // البحث عن المستخدم عبر رقم الهاتف
        $user = User::where('phone', 'like', "%$phone%")->first();

        if ($user) {
            $user->password = bcrypt($newPassword);
            $user->save();

            return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح ✅']);
        }

        return response()->json(['error' => 'المستخدم غير موجود'], 404);
    } catch (\Exception $e) {
        return response()->json(['error' => 'رمز غير صالح ❌'], 401);
    }
}
}
