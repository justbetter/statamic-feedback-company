<?php

namespace JustBetter\StatamicFeedbackCompany\Actions;

use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class HarvestReviews
{
    protected $reviewCollection;

    protected $totalScore = 0;
    protected $totalCount = 0;
    protected $totalRecommends = 0;

    protected $errorCount = 0;

    public function getToken(bool $new = false): string
    {
        if ($new) {
            Cache::forget('fbc_authcode');
        }

        return Cache::remember('fbc_authcode', now()->addDays(60), function () {
            if(!config('feedback-company.client_id') || !config('feedback-company.client_secret')) {
                throw new Exception('You need to configure the client ID and client secret tokens to use this API.');
            }

            info('Generating new token...');

            $response = Http::get(config('feedback-company.api_url') . '/oauth2/token', [
                'client_id' => config('feedback-company.client_id'),
                'client_secret' => config('feedback-company.client_secret'),
                'grant_type' => 'authorization_code',
            ])->throw();
            if ($response->json('error')) {
                throw new BadRequestHttpException('API returned error');
            }

            return $response->json('access_token');
        });
    }

    protected function createIfNecessary(array $data, int $reviewId, string $storeCode): StatamicEntry
    {
        $entry = $this->reviewCollection->get($reviewId);
        if ($entry) {
            return $entry;
        }

        $entry = Entry::make()
                ->collection('reviews')
                ->blueprint('reviews')
                ->locale($storeCode)
                ->data($data)
                ->slug($reviewId);
        $entry->save();

        return $entry;
    }

    public function harvest(): void
    {
        info('Retrieving all reviews...');
        $this->reviewCollection = Entry::whereCollection('reviews')->keyBy('slug');

        $pageSize = 100;
        $token = $this->getToken();
        $auth = [ 'Authorization' => 'Bearer '.$token ];

        $firstResponse = Http::withHeaders($auth)->get(config('feedback-company.api_url') . '/review', [
            'limit' => $pageSize,
            'start' => 0,
        ]);

        $total = $firstResponse->json('count')['total'] ?? false;

        if (!$total) {
            info('No totals found, aborting..');
            return;
        }

        if($total > $pageSize) {
            $responses = Http::pool(function (Pool $pool) use ($total, $pageSize, $auth) {
                $pageRange = $total > $pageSize * 2 ? range($pageSize, $total, $pageSize) : [ $pageSize ];
                foreach ($pageRange as $page) {
                    $pools[] = $pool->withHeaders($auth)->get(config('feedback-company.api_url') . '/review', [
                        'limit' => $pageSize,
                        'start' => $page,
                    ]);
                }
                return $pools;
            });
        }

        foreach(array_merge([$firstResponse], $responses ?? []) as $response) {
            $this->saveReviews($response->json('reviews'));
        }

        $this->updateTotals();
        info('Finished retrieving reviews.');
    }

    protected function saveReviews(?array $reviews)
    {
        foreach($reviews ?? [] as $review) {
            $score = round($review['total_score'] * 2);
            $recommends = $review['recommends'] == config('feedback-company.recommended_value');

            // Update running totals
            $this->totalScore += $score;
            $this->totalCount++;
            if ($recommends) {
                $this->totalRecommends++;
            }

            $questions = collect($review['questions'])->mapWithKeys(fn ($question) => [
                $question['type'] => $question['value']
            ]);

            // Create statamic entry if it doesn't already exist (assume reviews don't get updated)
            $this->createIfNecessary([
                'title' => $review['id'],
                'review_date' => $review['date_created'],
                'total_score' => $score,
                'recommends' => $recommends,
                'name' => $review['client']['name'],
                'questions' => $questions->all(),
                'product' => $review['product'],
            ], $review['id'], Site::default()->handle());
        }
    }

    protected function updateTotals()
    {
        if ($this->totalCount == 0) {
            return;
        }

        $site = Site::default()->handle();

        $average_score = round($this->totalScore / $this->totalCount, 1);
        $recommendation_percentage = round($this->totalRecommends / $this->totalCount * 100);

        $set = GlobalSet::findByHandle('reviews');
        if (! $set || ! $set->localizations()[$site]) {
            return;
        }

        $set->localizations()[$site]->average_score = $average_score;
        $set->localizations()[$site]->recommendation_percentage = $recommendation_percentage;
        $set->save();

        Cache::forget('feedback-company-data');
    }
}
