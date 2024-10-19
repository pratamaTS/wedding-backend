<?php

namespace App\Http\Controllers\Transaction;

use Illuminate\Support\Facades\Auth;
use App\Models\Membership;
use App\Models\Transaction;
use App\Models\UpgradeMembership;
use App\Models\User;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function createTransaction($params)
    {
        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
                'user_id' => $params['user_id'],
                'membership_id' => $params['membership_id'],
                'payment_id' => $params['payment_id'],
                'title' => $params['title'],
                'description' => $params['description'],
                'sub_price' => $params['sub_price'],
                'total_price' => $params['total_price'],
                'payment_type' => $params['payment_type'],
                'payment_via' => $params['payment_via'],
                'payment_at' => $params['payment_at'],
            ]);

            DB::commit();

            return $transaction;
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating transaction: ' . $e->getMessage());
            return null;
        }
    }


    public function createBillPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => [
                'required',
                Rule::exists('memberships')->where(function ($query) use ($request) {
                    $query->where('id', $request->id);
                }),
            ],
        ]);

        if ($validator->fails() ) {
            $errors = $validator->errors();

            // Add the "is_exist" attribute to the JSON response
            return response()->json(['error' => true, 'errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $secret_key = Config::get('app.flip_secret_key').':';

            $membership = Membership::where('id', $request->id)->first();

            $expired_date = Carbon::now()->addMinutes(15)->format('Y-m-d H:i');

            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized', 'admin' => $user], 401);
            }

            // Create Bill Payment
            $response = Http::withoutVerifying()->withHeaders([
                'Authorization' => 'Basic SkRKNUpERXpKRzlGY2xOaGJFazJjMFo0VjNOSGVWZG5WRlpETkdWVmNWbENOVzA0TVdOc01rbDFXVmh2U1dKdlduRnFkM2QwYWtSeVIyZEQ6Og==',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post('https://bigflip.id/big_sandbox_api/v2/pwf/bill', [
                "title" => $membership->name,
                "amount" => intval($membership->price),
                "type" => "SINGLE",
                "expired_date" => $expired_date,
                "redirect_url" => "https://app.berkompeten.com/upgrade/membership",
                "is_address_required" => 0,
                "is_phone_number_required" => 0,
                "sender_name" => $user->name,
                "sender_email" => $user->email,
                "step" => 2,
            ]);

            $response_data = json_decode($response->body(), true);

            $upgrade_membership = UpgradeMembership::updateOrCreate(
                ['user_id' => $user->id, 'status' => 'ACTIVE'],
                ['membership_id' => $membership->id]
            );

            if (!$upgrade_membership) {
                Log::error('Error create bill payment: Error create request payment upgrade membership');
                return response()->json(['error' => true, 'message' => 'Error request payment upgrade membership'], 500);
            }

            DB::commit();
            return response()->json(['error' => false, 'data' => $response_data], 200);
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error create bill payment: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error create bill payment', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function handleCallback(Request $request)
    {
        Log::info('Callback received', [
            'request_data' => $request->all(),
            'raw_body' => $request->getContent(),
            'headers' => $request->headers->all()
        ]);

        // Parse the URL-encoded payload
        $parsedRequestData = [];
        parse_str($request->getContent(), $parsedRequestData);

        // Decode the JSON string within 'data'
        $data = json_decode($parsedRequestData['data'], true);

        // Decode the data field from the payload
        $callbackData = json_decode($request->input('data'), true);

        // Retrieve data from the callback payload
        $id = $callbackData['id'] ?? null;
        $senderEmail = $callbackData['sender_email'] ?? null;
        $senderBank = $callbackData['sender_bank'] ?? null;
        $amount = $callbackData['amount'] ?? null;
        $status = $callbackData['status'] ?? null;

        try {
            DB::beginTransaction();

            $user = User::where('email', $senderEmail)->first();
                if (!$user) {
                    Log::error('Error callback payment: Account not found');
                    return response()->json(['error' => true, 'message' => 'Account not found'], 401);
                }

            $upgradeMembership = UpgradeMembership::where('user_id', $user->id)->where('status', 'ACTIVE')->first();
            if (!$upgradeMembership) {
                Log::error('Error callback payment: Request payment upgrade membership not found');
                return response()->json(['error' => true, 'message' => 'Request payment upgrade membership not found'], 500);
            }

            if ($status == 'CANCEL') {
                $upgradeMembership->delete();
                DB::commit();

                Log::error('Callback payment: Your payment process still is cancelled '.$senderEmail.' '.$amount.' '.$status);
                return response()->json(['error' => false, 'message' => "Your payment process is cancelled"], 200);
            }

            if ($status == 'ACTIVE') {
                Log::error('Callback payment: Your payment process still in progress '.$senderEmail.' '.$amount.' '.$status);
                return response()->json(['error' => false, 'message' => "Your payment process still in progress"], 200);
            }

            if ($status == 'SUCCESSFUL') {
                // Update the membership of the authenticated user
                $upgradeMembership->status = $status;
                $upgradeMembership->save();

                $date_now = Carbon::now();

                $userMembershipData = [
                    'membership_id' => $upgradeMembership->membership_id,
                    'start_date_activation' => $date_now,
                    'end_date_activation' => $date_now->addMonths($upgradeMembership->membership->activation_period),
                    'is_active' => true,
                ];

                $user->userMembership()->update($userMembershipData);

                // Create a new transaction record
                $transactionParams = [
                    'user_id' => $user->id,
                    'membership_id' => $upgradeMembership->membership_id,
                    'payment_id' => $id,
                    'title' => $callbackData['bill_title'],
                    'description' => 'Payment for ' . $callbackData['bill_title'] . ' membership',
                    'sub_price' => $amount,
                    'total_price' => $amount,
                    'payment_type' => $callbackData['sender_bank_type'],
                    'payment_via' => $senderBank,
                    'payment_at' => Carbon::parse($callbackData['created_at']),
                ];

                $transaction = $this->createTransaction($transactionParams);
                if (!$transaction) {
                    DB::rollback();
                    return response()->json(['error' => true, 'message' => 'Error creating transaction'], 500);
                }
                DB::commit();
                return response()->json(['error' => false, 'message' => 'Payment processed successfully'], 200);
            }
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error callback payment: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error callback payment', 'error_message' => $e->getMessage()], 500);
        }
    }
}
