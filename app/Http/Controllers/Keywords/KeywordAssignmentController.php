<?php

namespace App\Http\Controllers\Keywords;

use App\Actions\Keywords\ApplyKeywordsToTestSuite;
use App\Actions\Keywords\SyncTestCaseKeywords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Keywords\BulkKeywordRequest;
use App\Http\Requests\Keywords\TestCaseKeywordsRequest;
use App\Models\TestCase;
use App\Models\TestSuite;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Putting keywords on test cases, one case at a time or a subtree at a time.
 *
 * Separate from `KeywordController` because it is a different right —
 * `assign_keywords` tags cases, `manage_keywords` curates the vocabulary — and
 * conflating the two is how a tester ends up able to invent keywords.
 */
class KeywordAssignmentController extends Controller
{
    /**
     * Replace the keywords on one case.
     */
    public function updateForTestCase(
        TestCaseKeywordsRequest $request,
        TestCase $testCase,
        SyncTestCaseKeywords $syncTestCaseKeywords,
    ): RedirectResponse {
        $syncTestCaseKeywords(
            $this->actingUser($request),
            $testCase,
            $request->keywordIds(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Keywords updated.')]);

        return back();
    }

    /**
     * Add or remove keywords across the cases under a suite.
     *
     * The reply says how many cases changed rather than that it worked. A bulk
     * run over a subtree that matched nothing looks identical to one that
     * changed four hundred cases unless it says so.
     */
    public function storeForTestSuite(
        BulkKeywordRequest $request,
        TestSuite $testSuite,
        ApplyKeywordsToTestSuite $applyKeywords,
    ): RedirectResponse {
        $changed = $applyKeywords(
            $this->actingUser($request),
            $testSuite,
            $request->mode(),
            $request->keywordIds(),
            $request->directChildrenOnly(),
        );

        Inertia::flash('toast', [
            'type' => $changed === 0 ? 'info' : 'success',
            'message' => $changed === 0
                ? __('No test cases needed changing.')
                : trans_choice('{1} One test case updated.|[2,*] :count test cases updated.', $changed),
        ]);

        return back();
    }
}
