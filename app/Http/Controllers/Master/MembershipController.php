<?php

namespace App\Http\Controllers\Master;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Membership;
use App\Models\UpgradeMembership;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MembershipController extends Controller
{
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10); // Set a default per page value
            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'desc'); // Sort in ascending order by default
            $search = $request->input('search', '');

            $memberships = Membership::where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('class', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('image_url', 'like', "%{$search}%")
                            ->orWhere('price', 'like', "%{$search}%")
                            ->orWhere('activation_period', 'like', "%{$search}%")
                            ->orderBy($sortBy, $sortOrder)
                            ->paginate($perPage);

            return response()->json(['error' => false, 'message' => 'Success fetch data memberhsip', 'data' => $memberships], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Membership: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data Membership', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function studentIndex(Request $request)
    {
        try {
            $user = Auth::guard('api')->user()->load('userMembership')->load('userMembership.membership');
            if (!$user) {
                return response()->json(['error' => true, 'message' => 'Unauthorized', 'admin' => $user], 401);
            }

            $sortBy = $request->input('sort_by', 'name'); // Sort by name by default
            $sortOrder = $request->input('sort_order', 'desc'); // Sort in ascending order by default

            $memberships = Membership::orderBy($sortBy, $sortOrder)->get();

            foreach ($memberships as $membership) {
                $membership->is_current = $user->userMembership && $user->userMembership->membership_id == $membership->id;

                // Determine if the membership can be upgraded
                $membership->is_can_upgrade = $this->canUpgrade($user->userMembership->membership_id, $membership->id);
            }

            return response()->json(['error' => false, 'message' => 'Success fetch data memberhsip', 'membership' => $memberships], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data Membership: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error fetch data Membership', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $membership = Membership::find($id);
            if (!$membership) {
                return response()->json(['error' => true, 'message' => 'Membership not found'], 404);
            }

            // Get the authenticated user with their membership information
            $user = Auth::guard('api')->user()->load('userMembership.membership');
            if ($user) {
                // Get the current membership ID
                $currentMembershipId = $user->userMembership ? $user->userMembership->membership_id : null;

                // Determine if the membership is the current one
                $membership->is_current = $currentMembershipId == $membership->id;

                // Determine if the membership can be upgraded
                $membership->is_can_upgrade = $this->canUpgrade($currentMembershipId, $membership->id);
            } else {
                $membership->is_current = false;
                $membership->is_can_upgrade = false;
            }

            return response()->json(['error' => false, 'message' => 'Success get data membership', 'membership' => $membership], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data membership: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data membership', 'error_message' => $e->getMessage()], 500);
        }
    }

    private function canUpgrade($currentMembershipId, $targetMembershipId)
    {
        if (is_null($currentMembershipId)) {
            return true; // No current membership, can upgrade to any membership
        }

        return $targetMembershipId > $currentMembershipId;
    }

    public function detailMaster(Request $request)
    {
        try {
            $id = $request->input('id');
            if (empty($id)){
                return response()->json(['error' => true, 'message' => 'id is required', 'error_message' => 'id is required'], 422);
            }

            $membership = Membership::find($id);

            return response()->json(['error' => false, 'message' => 'Success get data lab values', 'data' => $membership], 200);
        } catch (\Exception $e) {
            Log::error('Error fetch data membership: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error get data lab values', 'error_message' => $e->getMessage()], 500);
        }
    }

    public function upsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'class' => 'required|string|max:50',
            'description' => 'required|string|max:255',
            'image_url' => 'nullable|string',
            'price' => 'required|numeric',
            'activation_period' => 'required|integer',
            'is_active' => 'required|integer|max:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            $membershipData = Membership::where('name', 'LIKE', '%'.$request->name.'%');
            if ($membershipData){
                $is_used = UpgradeMembership::where('membership_id', $membershipData->id);
                if ($is_used){
                    return response()->json(['error' => true, 'message' => 'The membership has been used'], 400);
                }
            }

            DB::beginTransaction();
            $membership = Membership::updateOrCreate(
                ['name' => $request->name],
                [
                    'name' => $request->name,
                    'class' => $request->class,
                    'description'=> $request->description,
                    'image_url' => $request->image_url,
                    'price' => $request->price,
                    'activation_period' => $request->activation_period,
                    'is_active' => $request->is_active,
                ]
            );

            if (!$membership) {
                return response()->json(['error' => true, 'message' => "Upsert membership data failed"], 500);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error membership: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error Upsert membership data', 'error_message' => $e->getMessage()], 500);
        }
        return response()->json(['error' => false, 'message' => 'Upsert membership data successfully'], 201);
    }

    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()], 422);
        }

        try {
            $is_used = UpgradeMembership::where('membership_id', $request->id);
            if ($is_used){
                return response()->json(['error' => true, 'message' => 'The membership has been used'], 400);
            }

            DB::beginTransaction();
            $data = Membership::where('id', $request->id);
            if (!$data) {
                return response()->json(['error' => true, 'message' => 'data not found'], 404);
            }

            // Perform the delete operation
            $data->delete();
            DB::commit();

            return response()->json(['error' => false, 'message' => 'The data deleted successfully'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            // Log or handle the exception
            Log::error('Error deleting lab values: ' . $e->getMessage());
            return response()->json(['error' => true, 'message' => 'Error deleting the data', 'error_message' => $e->getMessage()], 500);
        }
    }
}
