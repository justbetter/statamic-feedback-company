<?php

use Illuminate\Support\Facades\Route;
use JustBetter\StatamicFeedbackCompany\Http\Controllers\ReviewsController;

Route::middleware('api')->prefix('api')->group(function () {
    Route::get('/feedbackcompany-reviews', ReviewsController::class);
});
