<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use App\Models\Config;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConfigController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'id'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'asc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $datas = Config::where('id', 'like', "%{$search}%")
                        ->orWhere('advice_analys', 'like', "%{$search}%")
                        ->orderBy($sortBy, $sortOrder)
                        ->paginate($perPage);

            $datas->getCollection()->transform(function ($data) {
                $data->created_date = Carbon::parse($data->created_at)->format('d F Y H:i:s');
                return $data;
            });

            return response()->json(['error' => false, 'message' => 'Success fetch data config', 'data' => $datas], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data config: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data config', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $config = Config::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data config', 'data' => $config], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data config: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data config', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $request->validate([
            'advice_analys' => 'required|integer',
        ]);

        Config::updateOrCreate(
            ['id' => 1], // Assuming there's only one entry
            ['advice_analys' => $request->advice_analys]
        );

        return response()->json(['error'=> false, 'message' => 'Config updated successfully']);
    }
}
