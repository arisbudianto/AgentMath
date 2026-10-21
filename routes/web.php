<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{AuthController, UserController, AssessmentController};
use App\Services\AiConfiguration;
Route::pattern('question', '[0-9]+');
Route::pattern('participant', '[0-9]+');
Route::pattern('rubric', '[0-9]+');
Route::pattern('answer', '[0-9]+');
Route::pattern('revision', '[0-9]+');
Route::pattern('batch', '[0-9]+');
Route::pattern('run', '[0-9]+');
Route::redirect('/', '/dashboard');
Route::get('/health', fn () => response()->json(['app' => 'AgentMath', 'status' => 'ok']));
Route::middleware('guest')->group(function () {
    Route::view('/login', 'login')->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware('can:learn')->group(function() {
        Route::get('/student',[\App\Http\Controllers\StudentController::class,'index'])->name('student.index');
        Route::get('/student/answers/{answer}/revisions/{revision}/photo',[\App\Http\Controllers\StudentController::class,'photo'])->name('student.photo');
    });
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::middleware('can:teach')->group(function () {
        Route::get('/assessments/{assessment}/grading-runs',[\App\Http\Controllers\GradingRunController::class,'index'])->name('grading.index');
        Route::post('/assessments/{assessment}/grading-runs',[\App\Http\Controllers\GradingRunController::class,'store'])->middleware('throttle:6,1')->name('grading.store');
        Route::get('/assessments/{assessment}/grading-runs/{run}',[\App\Http\Controllers\GradingRunController::class,'show'])->name('grading.show');
        Route::post('/assessments/{assessment}/grading-runs/{run}/control',[\App\Http\Controllers\GradingRunController::class,'control'])->middleware('throttle:6,1')->name('grading.control');
        Route::get('/assessments/{assessment}/answers/{answer}/revisions/{revision}/transcription', [\App\Http\Controllers\TranscriptionController::class,'show'])->name('transcriptions.show');
        Route::post('/assessments/{assessment}/answers/{answer}/revisions/{revision}/transcription', [\App\Http\Controllers\TranscriptionController::class,'store'])->middleware('throttle:6,1')->name('transcriptions.store');
        Route::get('/assessments/{assessment}/answers', [\App\Http\Controllers\AnswerController::class,'index'])->name('answers.index');
        Route::post('/assessments/{assessment}/answers', [\App\Http\Controllers\AnswerController::class,'store'])->name('answers.store');
        Route::get('/assessments/{assessment}/answers/{answer}', [\App\Http\Controllers\AnswerController::class,'show'])->name('answers.show');
        Route::post('/assessments/{assessment}/answers/{answer}/revisions', [\App\Http\Controllers\AnswerController::class,'revise'])->name('answers.revise');
        Route::get('/assessments/{assessment}/answers/{answer}/revisions/{revision}/photo', [\App\Http\Controllers\AnswerController::class,'photo'])->name('answers.photo');
        Route::get('/assessments/{assessment}/answer-imports/template', [\App\Http\Controllers\AnswerImportController::class,'template'])->name('imports.template');
        Route::post('/assessments/{assessment}/answer-imports', [\App\Http\Controllers\AnswerImportController::class,'preview'])->middleware('throttle:5,1')->name('imports.preview');
        Route::get('/assessments/{assessment}/answer-imports/{batch}', [\App\Http\Controllers\AnswerImportController::class,'show'])->name('imports.show');
        Route::post('/assessments/{assessment}/answer-imports/{batch}/commit', [\App\Http\Controllers\AnswerImportController::class,'commit'])->name('imports.commit');
        Route::post('/assessments/{assessment}/answer-imports/{batch}/cancel', [\App\Http\Controllers\AnswerImportController::class,'cancel'])->name('imports.cancel');
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
