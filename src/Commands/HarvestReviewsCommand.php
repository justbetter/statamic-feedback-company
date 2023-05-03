<?php

namespace JustBetter\StatamicFeedbackCompany\Commands;

use Illuminate\Console\Command;
use JustBetter\StatamicFeedbackCompany\Jobs\HarvestReviewsJob;

class HarvestReviewsCommand extends Command
{
    protected $signature = 'reviews:harvest';

    protected $description = 'Harvest the reviews from FeedbackCompany into Statamic.';

    public function handle(): int
    {
        HarvestReviewsJob::dispatch();

        return static::SUCCESS;
    }
}
