<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $admin = Auth::guard('admin')->user();
            $datas = User::select('name', 'email', 'created_at', 'is_active')
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('created_at', 'like', "%{$search}%")
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            // Add 'is_used' attribute to each question
            $datas->getCollection()->transform(function ($data) use ($admin) {
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                $data->is_superadmin = false;
                if ($admin->id == 1) {
                    $data->is_superadmin = true;
                }
                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data User', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data User: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data User', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $data = User::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data User', 'data' => $data], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data User: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data User', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function activation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|max:4'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $data = User::find('id', $request->id)->first();
            if (!$data) {
                return response()->json(['error' => true, 'message' => "activation failed"], 500);
            }

            $data->is_active = 1;
            $data->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error activation: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error activation', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'User activation successfully'], 201);
    }

    public function deactivation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|max:4'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();
            $data = User::find('id', $request->id)->first();
            if (!$data) {
                return response()->json(['error' => true, 'message' => "de-activation failed"], 500);
            }

            $data->is_active = 0;
            $data->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error activation: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error de-activation', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'User de-activation successfully'], 201);
    }
}
