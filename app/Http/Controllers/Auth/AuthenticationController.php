<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\ResetPasswordMail;
use App\Mail\OtpResetPasswordMail;
use App\Models\Membership;
use App\Models\ExamDate;

class AuthenticationController extends Controller
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

    public function loginStudent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::exists('users')->where(function ($query) use ($request) {
                    $query->where('email', $request->email);
                }),
            ],
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            // Check if the user exists
            $isExist = !$errors->has('email');

            // Add the "is_exist" attribute to the JSON response
            return response()->json(['error' => true, 'errors' => $validator->errors(), 'is_exist' => $isExist], 422);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user->is_active){
            return response()->json(['error' => true, 'message' => 'Your account has been deactivated. Please contact your administrator'], 422);
        }

        if (Hash::check($request->password, $user->password) && $user->is_active) {
            // Verify reCAPTCHA
            $response = Http::withoutVerifying()->asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => Config::get('app.google_recaptcha_secret'),
                'response' => $request->input('g-recaptcha-response'),
                'remoteip' => $request->ip()
            ]);

            if (!$response->json('success')) {
                return response()->json(['error' => true, 'message' => 'reCAPTCHA verification failed.', 'secret' => Config::get('app.google_recaptcha_secret'), 'response' => $request->input('g-recaptcha-response'), 'remoteip' => $request->ip()], 401);
            }

            $token = $user->createToken('student');
            if (!$token) {
                return response()->json(['error' => true, 'message' => 'Internal Server Error', 'code' => '002'], 500);
            }

            $token = $this->generateToken($user, $token);

            $user->loginActivity()->create([
                'login_at' => Carbon::now(),
            ]);

            return response()->json(['error' => false, 'message' => 'Login successful', 'token' => $token], 200);
        }

        return response()->json(['error' => true, 'message' => 'Email or password is invalid.'], 422);
    }

    public function emailDuplicateCheck(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            // Add the "is_exist" attribute to the JSON response
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        return response()->json(['error' => false, 'message' => 'Email and password is available'], 200);
    }

    public function profile(Request $request)
    {
        $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized'], 401);
        }

        // Access the userMembership and membership details
        $userMembership = $user->userMembership;
        $membership = $userMembership->membership;

        // Customize the response to include only necessary details and modify the 'membership' key
        $userArray = $user->toArray(); // Convert user data to array

        // Replace the 'user_membership' key with 'membership' and update the data
        $userArray['membership'] = [
            'name' => $membership->name,
            'start_date' => $userMembership->start_date_activation,
            'end_date' => $userMembership->end_date_activation,
            'activation_left' => $this->calculateActivationLeft($userMembership->end_date_activation),
        ];

        unset($userArray['id']);
        unset($userArray['user_membership']);
        // Use $admin to retrieve admin data or perform actions
        return response()->json(['error' => false, 'profile' => $userArray], 200);
    }

    public function logout(Request $request)
    {
        // Revoke the current access token
        $user = Auth::guard('api')->user();
        if ($user) {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Successfully logged out'], 200);
    }

    public function registerStudent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'name' => 'required|string|max:255',
            'gender' => 'required|string|max:255',
            'phone_number' => 'required|string|max:13',
            'year_of_entry' => 'required|string|max:255',
            'exam_date_id' => 'required|string|max:12',
            'university_id' => [
                'required',
                'string',
                'max:12',
            ],
            'educational_status_id' => [
                'required',
                'string',
                'max:12',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $membership = Membership::where('name', 'Trial')->first();
            if (empty($membership)) {
                return response()->json(['error' => true, 'message' => "Membership trial not found, please contact your administrator"], 500);
            }

            $exam_date = ExamDate::where('id', $request->exam_date_id)->first();
            if (empty($exam_date)) {
                return response()->json(['error' => true, 'message' => "exam date is empty, please contact your administrator"], 500);
            }

            $target_exam_date = Carbon::parse($exam_date->date);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'student',
                'gender' => $request->gender,
                'phone_number' => $request->phone_number,
                'year_of_entry' => $request->year_of_entry,
                'target_exam_date' => $target_exam_date->toDateString(),
                'exam_date_id' => $exam_date->id,
                'university_id' => $request->university_id,
                'educational_status_id' => $request->educational_status_id,
                'change_password' => 0,
                'otp_submitted' => 0,
                'is_active' => true
            ]);

            $date_now = Carbon::now();
            $end_date_activation = $date_now->copy()->addMonths($membership->activation_period);

            if ($membership->activation_period > 12) {
                $end_date_activation = $date_now->copy()->addDays($membership->activation_period + 1);
            }

            $userMembershipData = [
                'membership_id' => $membership->id,
                'start_date_activation' => $date_now,
                'end_date_activation' => $end_date_activation,
                'is_active' => true,
            ];

            $user->userMembership()->create($userMembershipData);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error register: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error register', 'error_message' => $e->getMessage()], 500);
        }


        return response()->json(['error' => false, 'message' => 'Registration successful.', 'data' => $user], 201);
    }

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect()->getTargetUrl();
    }

    public function handleGoogleCallback()
    {
        try {
            $httpClientOptions = app()->environment('local') ? ['verify' => true] : [];

            $user = Socialite::driver('google')
                ->setHttpClient(new \GuzzleHttp\Client($httpClientOptions))
                ->user();
        } catch (\Exception $e) {
            \Log::error('Google authentication error in '.app()->environment().': ' . $e);
            return response()->json(['error' => true, 'message' => 'Google authentication failed.'], 401);
        }

        // Check if the user with this email already exists
        $existingUser = User::where('email', $user->email)->first();
        // Retrieve the frontend URL from the environment variables
        $frontendUrl = env('FRONTEND_URL', 'https://app.berkompeten.com');


        if ($existingUser) {
            $token = $existingUser->createToken('student');
            if (!$token) {
                return response()->json(['error' => true, 'message' => 'Internal Server Error', 'code' => '002'], 500);
            }

            $token = $this->generateToken($existingUser, $token);
            return redirect()->away("{$frontendUrl}/dashboard?token=".$token);
            // return response()->json(['error' => false, 'action' => "LOGIN", 'message' => 'Login successful', 'token' => $token, 200]);
        }

        return redirect()->away("{$frontendUrl}/register?email=".$user->email."&name=".$user->name);
        // return response()->json(['error' => false, 'action' => "REGISTER", 'message' => 'Authenticate successful', 'profile' => $user], 200);
    }

    public function generateRandomOtp()
    {
        // Generate a random six-digit OTP
        $otp = mt_rand(100000, 999999);

        return $otp;
    }

    public function sendOtpResetPassword(Request $request)
    {
        $user = Auth::guard('api')->user(); // Fetch the authenticated admin
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized', 'admin' => $user], 401);
        }

        $frontendUrl = env('FRONTEND_URL', 'https://app.berkompeten.com')."/otp";

        try {
            // Set OTP expiration time to 1 minute from now
            $otpExpiration = Carbon::now()->addMinutes(1);

            // Save the OTP and its expiration time to the user or database
            $user->otp = $this->generateRandomOtp(); // Replace with your OTP generation logic
            $user->otp_expiration = $otpExpiration;
            $user->otp_submitted = true;
            $user->save();

            // Send email with OTP and reset link
            $emailData = [
                'otp' => $user->otp,
                'reset_link' => $frontendUrl, // Replace with your actual reset link
            ];

            Mail::to($user->email)->send(new OtpResetPasswordMail($emailData));
        } catch (\Exception $e) {
            // Log or handle the exception
            Log::error('Error send otp reset password: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error send otp reset password', 'error_message' => $e->getMessage()], 500);
        }

        return response()->json(['error' => false, 'message' => 'Send otp password successful.'], 200);
    }

    public function getChangePasswordStatus(Request $request)
    {
        $user = Auth::guard('api')->user(); // Fetch the authenticated admin
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized', 'admin' => $user], 401);
        }

        return response()->json(['error' => false, 'message' => 'Get change password status successful.', 'data' => ['change_password' => $user->change_password, 'otp_submitted' => $user->otp_submitted]], 200);
    }

    public function OtpVerification(Request $request)
    {
        $user = Auth::guard('api')->user(); // Fetch the authenticated admin
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized', 'admin' => $user], 401);
        }

        try {
            // Set OTP expiration time to 1 minute from now
            $otpExpiration = Carbon::now()->addMinutes(1);

            // Save the OTP and its expiration time to the user or database
            $user->otp = 0;
            $user->change_password = true;
            $user->otp_submitted = false;
            $user->save();
        } catch (\Exception $e) {
            // Log or handle the exception
            Log::error('Error send otp reset password: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error send otp reset password', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Verication otp successfully'], 200);
    }

    public function ChangePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        $user = Auth::guard('api')->user(); // Fetch the authenticated admin
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Unauthorized', 'admin' => $user], 401);
        }

        try {
            // Set OTP expiration time to 1 minute from now
            $otpExpiration = Carbon::now()->addMinutes(1);

            $user->password = Hash::make($request->password);
            $user->change_password = false;
            $user->save();
        } catch (\Exception $e) {
            // Log or handle the exception
            Log::error('Error send otp reset password: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error send otp reset password', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Change password successfully'], 200);
    }

    private function calculateActivationLeft($endDate)
    {
        $now = Carbon::now();
        $endDate = Carbon::parse($endDate);

        // Calculate the difference in days
        $activationLeft = $endDate->diffInDays($now);

        return $activationLeft;
    }

    public function sendResetLinkEmail(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => true, 'message' => 'Email does not exist'], 404);
        }

        // Generate reset token
        $token = Str::random(60);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => $token,
            'created_at' => now()
        ]);

        // Send reset link via email
        // You can use a notification for this
        $frontendUrl = env('FRONTEND_URL', 'https://app.berkompeten.com')."/reset-password?token=".$token.'&email='.$user->email;

        // Send email with OTP and reset link
        $emailData = [
            'reset_link' => $frontendUrl, // Replace with your actual reset link
        ];

        Mail::to($user->email)->send(new ResetPasswordMail($emailData));

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

        $admin = User::where('email', $request->email)->first();
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
