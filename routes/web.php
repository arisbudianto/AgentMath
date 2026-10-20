<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController, UserController, AssessmentController};
use App\Services\AiConfiguration;
Route::redirect('/', '/dashboard');
Route::get('/health', fn () => response()->json(['app' => 'AgentMath', 'status' => 'ok']));
Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::middleware('can:teach')->group(function () {
        Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
        Route::post('/assessments', [AssessmentController::class, 'store'])->name('assessments.store');
        Route::get('/assessments/{assessment}', [AssessmentController::class, 'show'])->name('assessments.show');
        Route::patch('/assessments/{assessment}', [AssessmentController::class, 'update'])->name('assessments.update');
    });
    Route::middleware('can:manage-users')->prefix('admin')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
        Route::get('/ai', fn (AiConfiguration $ai) => view('ai', ['ai' => $ai->status()]))->name('ai.status');
    });
});
