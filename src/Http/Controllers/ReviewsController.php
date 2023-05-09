<?php

namespace JustBetter\StatamicFeedbackCompany\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Entry;

class ReviewsController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'page' => 'integer',
            'count' => 'integer|max:32',
            'minscore' => 'integer|min:1|max:10',
            'maxscore' => 'integer|min:1|max:10',
        ]);

        $page = $request->get('page', 1);
        $count = $request->get('count',3);
        $minscore = (int)$request->get('minscore', 1);
        $maxscore = (int)$request->get('maxscore', 10);

        return Entry::query()
            ->where('collection', 'reviews')
            ->where('total_score', '>=', $minscore)
            ->where('total_score', '<=', $maxscore)
            ->offset(($page - 1) * $count)
            ->limit($count)
            ->get(['name', 'product', 'review_date', 'questions', 'total_score']);
    }
}
