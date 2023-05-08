<?php

namespace JustBetter\StatamicFeedbackCompany\Http\ViewComposers;

use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;

class ReviewsComposer
{
    public function compose(View $view): void
    {
        $reviews = Cache::remember('feedback-company-data', now()->addHour(), function () use ($view) {
            $count = Entry::query()->where('collection', 'reviews')->count();
            $reviews = $this->reviewsGlobal($view);
            $score = $reviews->average_score ?? null;
            $percentage = $reviews->recommendation_percentage ?? null;
            $counts = [];
            for($i = 1; $i <= 10; $i++) {
                $counts[$i] = Entry::query()->where('collection', 'reviews')->where('total_score', $i)->count();
            }

            return compact(['count', 'counts', 'score', 'percentage']);
        });

        $view->with('reviews', $reviews);
    }

    protected function reviewsGlobal(View $view)
    {
        if($view->globals->reviews ?? null) {
            return $view->globals->reviews;
        } else {
            $set = GlobalSet::findByHandle('reviews');
            if (! $set || ! $set->localizations()['default']) {
                return [];
            }
            return $set->localizations()['default'];
        }
    }
}
