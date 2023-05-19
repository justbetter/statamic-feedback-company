<?php

namespace JustBetter\StatamicFeedbackCompany\Tags;

use Illuminate\Support\Facades\Cache;
use Statamic\Eloquent\Entries\Entry;
use Statamic\Entries\EntryCollection;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Tags\Tags;

class Reviews extends Tags
{
    public string $reviewTitleId = 'main_open';

    public string $reviewTextId = 'open';

    public function getReviews(int $count = 3): EntryCollection
    {
        /* @var EntryCollection $reviews */
        $reviews = EntryFacade::query()
            ->where('collection', 'reviews')
            ->orderBy('review_date', 'DESC')
            ->limit($count)
            ->get(['name', 'product', 'review_date', 'questions', 'total_score']);

        return $reviews;
    }

    public function getReviewData(): array
    {
        /** @var Entry $review */
        $review = $this->params->get('review');
        $questionsData = $review->questions ?? [];

        return ['title' => $questionsData[$this->reviewTitleId] ?? '', 'text' => $questionsData[$this->reviewTextId] ?? ''];
    }

    public function getRatingData(): array
    {
        return Cache::remember('feedback-company-rating-data', now()->addHour(), function () {
            $starCounts = [];

            for ($i = 1; $i <= 5; $i++) {
                $minScore = (($i - 1) * 20) / 10;
                $maxScore = ($i * 20) / 10;
                $reviewsCount = EntryFacade::query()
                    ->where('collection', 'reviews')
                    ->whereBetween('total_score', [$minScore, $maxScore])
                    ->count();
                $starCounts[$i] = $reviewsCount;
            }

            return $starCounts;
        });
    }
}
