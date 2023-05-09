<?php

namespace JustBetter\StatamicFeedbackCompany\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use JustBetter\StatamicFeedbackCompany\Actions\HarvestReviews;

class HarvestReviewsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(HarvestReviews $harvestReviews): void
    {
        $harvestReviews->harvest();
    }
}
