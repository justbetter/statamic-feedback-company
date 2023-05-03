<?php

namespace JustBetter\StatamicFeedbackCompany\Actions;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Statamic\Entries\Entry as StatamicEntry;
use Statamic\Facades\Entry;
use Statamic\Facades\GlobalSet;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class HarvestReviews
{
    protected $reviewCollection;

    protected $totalScore = 0;
    protected $totalCount = 0;
    protected $totalRecommends = 0;

    public function getToken(bool $new = false): string
    {
        if ($new) {
            Cache::forget('fbc_authcode');
        }

        return Cache::remember('fbc_authcode', now()->addDays(60), function () {
            if(!config('feedback-company.fb_client_id') || !config('feedback-company.fb_client_secret')) {
                throw new Exception('You need to configure the client ID and client secret tokens to use this API.');
            }
            
            info('Generating new token...');

            $response = Http::get('https://www.feedbackcompany.com/api/v2/oauth2/token', [
                'client_id' => config('feedback-company.fb_client_id'),
                'client_secret' => config('feedback-company.fb_client_secret'),
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
        $this->fetchPage(0, 100, $this->getToken(), 0);
        $this->updateTotals();
        info('Finished retrieving reviews.');
    }

    protected function fetchPage(int $start, int $pageSize, string $token, int $errorCount)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('https://www.feedbackcompany.com/api/v2/review', [
            'limit' => $pageSize,
            'start' => $start,
        ]);

        $responseJson = $response->json();

        // Handle non-success responses
        if (! $responseJson || ! $responseJson['success']) {
            info('API returned unexpected error when retrieving reviews');

            // Stop when there's too many errors
            $errorCount++;
            if ($errorCount > 2) {
                info('API returned too many errors, breaking out of the loop...');

                return;
            }

            // Regenerate token if unauthorized
            if ($responseJson && $responseJson['error'] == '401 Unauthorized') {
                $token = $this->getToken(true);
            }
        }

        if (! array_key_exists('reviews', $responseJson)) {
            return;
        }

        collect($responseJson['reviews'])->each(function ($review) {
            $score = round($review['total_score'] * 2);
            $recommends = $review['recommends'] == config('feedback-company.recommended_value');

            // Update running totals
            $this->totalScore += $score;
            $this->totalCount++;
            if ($recommends) {
                $this->totalRecommends++;
            }

            $questions = collect($review['questions'])->mapWithKeys(fn ($question) => [
                $question['question_id'] => $question['value']
            ]);

            // Create statamic entry if it doesn't already exist (assume reviews don't get updated)
            $this->createIfNecessary([
                'title' => $review['id'],
                'review_date' => $review['date_created'],
                'total_score' => $score,
                'recommends' => $recommends,
                'name' => $review['client']['name'],
                'questions' => $questions,
                'product' => $review['product'],
            ], $review['id'], 'default');
        });

        info($start + $pageSize.'/'.$responseJson['count']['total'].' reviews harvested and saved in Statamic');

        // Two ways to break out of the loop, just in case
        if ($start > $responseJson['count']['total'] || count($responseJson['reviews']) < $pageSize) {
            return;
        }

        $this->fetchPage($start + $pageSize, $pageSize, $token, $errorCount);
    }

    protected function updateTotals()
    {
        if ($this->totalCount == 0) {
            return;
        }

        $average_score = round($this->totalScore / $this->totalCount, 1);
        $recommendation_percentage = round($this->totalRecommends / $this->totalCount * 100);

        $set = GlobalSet::findByHandle('reviews');
        if (! $set || ! $set->localizations()['default']) {
            return;
        }

        $set->localizations()['default']->average_score = $average_score;
        $set->localizations()['default']->recommendation_percentage = $recommendation_percentage;
        $set->save();

        Cache::forget('feedback-company-data');
    }
}
