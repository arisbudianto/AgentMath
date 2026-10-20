<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController, UserController, AssessmentController};
use App\Services\AiConfiguration;
Route::pattern('question', '[0-9]+');
Route::pattern('participant', '[0-9]+');
Route::pattern('rubric', '[0-9]+');
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
        Route::get('/assessments/{assessment}/questions/{question}/rubrics', [\App\Http\Controllers\RubricController::class,'index'])->name('rubrics.index');
        Route::post('/assessments/{assessment}/questions/{question}/rubrics', [\App\Http\Controllers\RubricController::class,'store'])->middleware('throttle:3,1')->name('rubrics.store');
        Route::get('/assessments/{assessment}/questions/{question}/rubrics/{rubric}', [\App\Http\Controllers\RubricController::class,'show'])->name('rubrics.show');
        Route::patch('/assessments/{assessment}/questions/{question}/rubrics/{rubric}', [\App\Http\Controllers\RubricController::class,'update'])->name('rubrics.update');
        Route::post('/assessments/{assessment}/questions/{question}/rubrics/{rubric}/approve', [\App\Http\Controllers\RubricController::class,'approve'])->name('rubrics.approve');
        Route::get('/classrooms', [\App\Http\Controllers\ClassroomController::class, 'index'])->name('classrooms.index');
        Route::post('/classrooms', [\App\Http\Controllers\ClassroomController::class, 'store'])->name('classrooms.store');
        Route::get('/classrooms/{classroom}', [\App\Http\Controllers\ClassroomController::class, 'show'])->name('classrooms.show');
        Route::patch('/classrooms/{classroom}', [\App\Http\Controllers\ClassroomController::class, 'update'])->name('classrooms.update');
        Route::post('/classrooms/{classroom}/participants', [\App\Http\Controllers\ParticipantController::class, 'store'])->name('participants.store');
        Route::patch('/classrooms/{classroom}/participants/{participant}', [\App\Http\Controllers\ParticipantController::class, 'update'])->name('participants.update');
        Route::post('/assessments/{assessment}/questions', [\App\Http\Controllers\QuestionController::class, 'store'])->name('questions.store');
        Route::get('/assessments/{assessment}/questions/{question}/edit', [\App\Http\Controllers\QuestionController::class, 'edit'])->name('questions.edit');
        Route::patch('/assessments/{assessment}/questions/{question}', [\App\Http\Controllers\QuestionController::class, 'update'])->name('questions.update');
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
