<?php

namespace JustBetter\StatamicFeedbackCompany\Commands;

use Illuminate\Console\Command;
use Statamic\Facades\Entry;

class RemoveReviewsCommand extends Command
{
    protected $signature = 'reviews:remove';

    protected $description = 'Remove all reviews from Statamic.';

    public function handle(): int
    {
        $reviews = Entry::query()->where('collection', 'reviews')->get();

        $reviews->each(fn($entry) => $entry->delete());

        return static::SUCCESS;
    }
}
