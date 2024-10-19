<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoginActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatisticsController extends Controller
{
    public function weeklyActiveUsers(Request $request)
    {
        $today = Carbon::now();
        $yesterday = Carbon::yesterday();

        $weeklyActiveUsersToday = LoginActivity::where('login_at', '>=', $today->subDays(7))->distinct('user_id')->count('user_id');
        $weeklyActiveUsersYesterday = LoginActivity::where('login_at', '>=', $yesterday->subDays(7))->where('login_at', '<', $today)->distinct('user_id')->count('user_id');

        $totalActivePremiumUsers = $totalActivePremiumUsers = User::whereHas('userMembership', function ($query) {
                                        $query->where('membership_id', '!=', 1);
                                    })->where('is_active', true)->count();

        $weeklyActiveUsersPercentageToday = ($totalActivePremiumUsers > 0) ? ($weeklyActiveUsersToday / $totalActivePremiumUsers) * 100 : 0;
        $weeklyActiveUsersPercentageYesterday = ($totalActivePremiumUsers > 0) ? ($weeklyActiveUsersYesterday / $totalActivePremiumUsers) * 100 : 0;

        return response()->json([
            'data' => [
                'today' => round($weeklyActiveUsersPercentageToday, 2),
                'yesterday' => round($weeklyActiveUsersPercentageYesterday, 2),
                'total_active_premium_users' => $totalActivePremiumUsers,
            ],
        ], 200);
    }

    public function dailyActiveUsers()
    {
        $dailyActiveUsers = LoginActivity::selectRaw('DATE(login_at) as date, COUNT(DISTINCT user_id) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        return response()->json([
            'data' => $dailyActiveUsers,
        ], 200);
    }

    public function weeklyPaidUsers()
    {
        $today = Carbon::now();
        $weekAgo = $today->subDays(7);

        $weeklyPaidUsers = User::where('is_premium', true)->where('created_at', '>=', $weekAgo)->count();
        $totalPaidUsers = User::where('is_premium', true)->count();

        return response()->json([
            'data' => [
                'weekly_paid_users' => $weeklyPaidUsers,
                'total_paid_users' => $totalPaidUsers,
            ],
        ], 200);
    }

    public function weeklySignups()
    {
        $today = Carbon::now();
        $weekAgo = $today->subDays(7);

        $weeklySignups = User::where('created_at', '>=', $weekAgo)->count();
        $totalSignups = User::count();

        return response()->json([
            'data' => [
                'weekly_signups' => $weeklySignups,
                'total_signups' => $totalSignups,
            ],
        ], 200);
    }

    public function averageDaysAfterAdvis()
    {
        $users = User::whereNotNull('advis_opened_at')
            ->whereNotNull('last_login_at')
            ->get();

        $daysAfterAdvis = $users->map(function ($user) {
            return Carbon::parse($user->last_login_at)->diffInDays(Carbon::parse($user->advis_opened_at));
        });

        $averageDaysAfterAdvis = $daysAfterAdvis->avg();

        return response()->json([
            'data' => [
                'average_days_after_advis' => round($averageDaysAfterAdvis, 2),
            ],
        ], 200);
    }
}
