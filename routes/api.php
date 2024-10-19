<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthenticationController;
use App\Http\Controllers\Master\UniversityController;
use App\Http\Controllers\Master\EducationalStatusController;
use App\Http\Controllers\Master\MembershipController;
use App\Http\Controllers\Master\CategoryLabController;
use App\Http\Controllers\Master\LabValuesController;
use App\Http\Controllers\Master\ExamDateController;
use App\Http\Controllers\Master\QuestionPacketController;
use App\Http\Controllers\Master\QuestionController;
use App\Http\Controllers\Transaction\BillController;
use App\Http\Controllers\Transaction\TransactionController;
use App\Http\Controllers\User\UserController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\StatisticsController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\Config\ConfigController;
use App\Http\Controllers\Master\StudentController;
use App\Http\Controllers\Master\SubTopicListController;
use App\Http\Controllers\Master\SystemListController;
use App\Http\Controllers\Master\TopicController;
use App\Http\Controllers\Media\ImageController;
use App\Http\Controllers\Report\AnalyzeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
Route::get('/', function () {
    return response()->json(['error' => false, 'status' => 'Healthy'], 200);
});

Route::middleware('throttle:200,1')->group(function () {
    Route::prefix('/guest')->middleware('cors')->group(function () {
        Route::get('/comments', [CommentController::class, 'index']);
        Route::post('/comment', [CommentController::class, 'upsert']);
    });
});
