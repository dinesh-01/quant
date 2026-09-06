<?php

namespace App\Http\Controllers\TestSpecification;

use App\Actions\CustomFields\SaveCustomFieldValues;
use App\Actions\TestSpecification\CreateTestCaseVersion;
use App\Actions\TestSpecification\DeleteTestCaseVersion;
use App\Actions\TestSpecification\FreezeTestCaseVersion;
use App\Actions\TestSpecification\UnfreezeTestCaseVersion;
use App\Actions\TestSpecification\UpdateTestCaseVersion;
use App\Http\Controllers\Controller;
use App\Http\Requests\TestSpecification\TestCaseVersionUpdateRequest;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Version endpoints: opening a new one, editing content, freezing and deleting.
 */
class TestCaseVersionController extends Controller
{
    /**
     * Open a new version, branched from the latest.
     */
    public function store(
        Request $request,
        TestCase $testCase,
        CreateTestCaseVersion $createTestCaseVersion,
    ): RedirectResponse {
        $version = $createTestCaseVersion($this->actingUser($request), $testCase);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Version :number created.', ['number' => $version->version]),
        ]);

        return back();
    }

    public function update(
        TestCaseVersionUpdateRequest $request,
        TestCaseVersion $testCaseVersion,
        UpdateTestCaseVersion $updateTestCaseVersion,
        SaveCustomFieldValues $saveCustomFieldValues,
    ): RedirectResponse {
        $updateTestCaseVersion(
            $this->actingUser($request),
            $testCaseVersion,
            $request->versionAttributes(),
        );

        /*
         * After the version, because the update action is what refuses a frozen
         * version: saving the answers first would let a frozen version's fields
         * be edited by the same request that was about to be rejected.
         */
        $saveCustomFieldValues(
            $this->actingUser($request),
            $testCaseVersion,
            $request->customFieldAnswers(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test case saved.')]);

        return back();
    }

    public function destroy(
        Request $request,
        TestCaseVersion $testCaseVersion,
        DeleteTestCaseVersion $deleteTestCaseVersion,
    ): RedirectResponse {
        $deleteTestCaseVersion($this->actingUser($request), $testCaseVersion);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version deleted.')]);

        return back();
    }

    public function freeze(
        Request $request,
        TestCaseVersion $testCaseVersion,
        FreezeTestCaseVersion $freezeTestCaseVersion,
    ): RedirectResponse {
        $freezeTestCaseVersion($this->actingUser($request), $testCaseVersion);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version frozen.')]);

        return back();
    }

    public function unfreeze(
        Request $request,
        TestCaseVersion $testCaseVersion,
        UnfreezeTestCaseVersion $unfreezeTestCaseVersion,
    ): RedirectResponse {
        $unfreezeTestCaseVersion($this->actingUser($request), $testCaseVersion);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Version reopened.')]);

        return back();
    }
}
