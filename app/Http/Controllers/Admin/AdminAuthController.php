<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\Admin;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Mail\ResetPasswordMail;


class AdminAuthController extends Controller
{

    private function generateToken($user, $token)
    {
        $tokenExpiration = Config::get('app.token_expiration_time');
        if (empty($tokenExpiration)){
            return response()->json(['error' => true, 'message' => 'Internal Server Error', 'code' => '001'], 500);
        }

        $token->token->expires_at = now()->addMinutes($tokenExpiration);
        $token->token->save();
        $token = $token->accessToken;
        $user->remember_token = $token;
        $user->save();

        return $token;
    }

    public function registerAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $admin = Admin::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'is_active' => true,
            ]);

            if (!$admin) {
                return response()->json(['error' => true, 'message' => "Register failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error register: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error register', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Admin registered successfully'], 201);
    }

    public function loginAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::exists('admins')->where(function ($query) use ($request) {
                    $query->where('email', $request->email);
                }),
            ],
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        $admin = Admin::where('email', $request->email)->first();
        if (!$admin->is_active){
            return response()->json(['error' => true, 'message' => 'Your account has been deactivated. Please contact your administrator'], 422);
        }

        if (Hash::check($request->password, $admin->password) && $admin->is_active) {
            // Verify reCAPTCHA
            $response = Http::withoutVerifying()->asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => Config::get('app.google_recaptcha_secret'),
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip()
            ]);

            if (!$response->json('success')) {
                return response()->json(['error' => true, 'message' => 'reCAPTCHA verification failed.', 'secret' => Config::get('app.google_recaptcha_secret'), 'response' => $request->input('g-recaptcha-response'), 'remoteip' => $request->ip()], 401);
            }

            $token = $admin->createToken('admin');
            if (!$token) {
                return response()->json(['error' => true, 'message' => 'Internal Server Error', 'code' => '002'], 500);
            }

            $token = $this->generateToken($admin, $token);

            return response()->json(['error' => false, 'message' => 'Login successful', 'token' => $token], 200);
        }

        return response()->json(['error' => true, 'message' => 'Email or password is invalid.'], 422);
    }

    public function logout()
    {
        Auth::guard('admin')->user()->token()->revoke();
        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    public function profile()
    {
        $admin = Auth::guard('admin')->user();
        $adminData = $admin->toArray();
        unset($adminData['id']);

        return response()->json(['error' => false, 'data' => $adminData], 200);
    }

    public function updateProfile(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:admins,email,' . $admin->id,
        ]);

        $admin->update($request->only('name', 'email'));

        return response()->json(['error' => false, 'message' => 'Profile updated successfully', 'data' => $admin], 200);
    }

    public function changePassword(Request $request)
    {
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if (!Hash::check($request->current_password, $admin->password)) {
            return response()->json(['error' => true, 'message' => 'Current password is incorrect'], 400);
        }

        $admin->password = Hash::make($request->new_password);
        $admin->save();

        return response()->json(['error' => false, 'message' => 'Password changed successfully'], 200);
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $admin = Admin::where('email', $request->email)->first();
        if (!$admin) {
            return response()->json(['error' => true, 'message' => 'Email does not exist'], 404);
        }

        // Generate reset token
        $token = Str::random(60);
        DB::table('password_reset_tokens')->insert([
            'email' => $admin->email,
            'token' => $token,
            'created_at' => now()
        ]);

        // Send reset link via email
        // You can use a notification for this
        $frontendUrl = env('FRONTEND_URL', 'https://app.berkompeten.com')."/reset-password?token=".$token;

        // Send email with OTP and reset link
        $emailData = [
            'reset_link' => $frontendUrl, // Replace with your actual reset link
        ];

        Mail::to($admin->email)->send(new ResetPasswordMail($emailData));

        return response()->json(['error' => false, 'message' => 'Reset link sent to your email'], 200);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $passwordReset = DB::table('password_reset_tokens')
                            ->where('token', $request->token)
                            ->where('email', $request->email)
                            ->first();

        if (!$passwordReset) {
            return response()->json(['error' => true, 'message' => 'Invalid token or email'], 400);
        }

        $admin = Admin::where('email', $request->email)->first();
        if (!$admin) {
            return response()->json(['error' => true, 'message' => 'Email does not exist'], 404);
        }

        $admin->password = Hash::make($request->password);
        $admin->save();

        // Delete the password reset token
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['error' => false, 'message' => 'Password reset successfully'], 200);
    }

}
