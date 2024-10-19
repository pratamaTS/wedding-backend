<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

class BillController extends Controller
{
    public function getBills(Request $request)
    {
        try {
            $secretKey = Config::get('app.flip_secret_key') . ':';
            $flipUrl = Config::get('app.flip_url') . '/v2/pwf/payment';

            // Prepare query parameters
            $queryParams = [
                'start_date' => $request->query('start_date', '2024-06-01'),
                'end_date' => $request->query('end_date', '2024-06-30'),
                'pagination' => $request->query('pagination', 50),
                'page' => $request->query('page', 1),
                'sort_by' => $request->query('sort_by', 'created_at'),
                'sort_type' => $request->query('sort_type', 'sort_desc'),
            ];

            // Make a GET request to the Flip API with query parameters
            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->withBasicAuth($secretKey, '')
                ->get($flipUrl, $queryParams);

            // Check for errors
            if ($response->failed()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Failed to fetch bills from Flip API',
                    'data' => [],
                ], 500);
            }

            $bills = $response->json();

            // Return the sorted and filtered bills
            return response()->json([
                'error' => false,
                'message' => 'Bills fetched successfully',
                'data' => $bills,
            ], 200);
        } catch (\Exception $e) {
            // Handle any exceptions
            return response()->json([
                'error' => true,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
