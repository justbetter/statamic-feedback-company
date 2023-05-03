<?php

namespace JustBetter\StatamicFeedbackCompany\Http\ViewComposers;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Statamic\Facades\Entry;

class ReviewsComposer
{
    public function compose(View $view): void
    {
        $reviews = Cache::remember('feedback-company-data', now()->addHour(), function () use ($view) {
            $count = Entry::query()->where('collection', 'reviews')->count();
            $score = $view->globals->reviews->average_score;
            $percentage = $view->globals->reviews->recommendation_percentage;

            $counts = [];
            for($i = 1; $i <= 10; $i++) {
                $counts[$i] = Entry::query()->where('collection', 'reviews')->where('total_score', $i)->count();
            }

            return compact(['count', 'counts', 'score', 'percentage']);
        });

        $view->with('reviews', $reviews);
    }
}
