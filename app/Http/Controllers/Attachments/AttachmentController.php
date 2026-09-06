<?php

namespace App\Http\Controllers\Attachments;

use App\Actions\Attachments\DeleteAttachment;
use App\Actions\Attachments\StoreAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attachments\AttachmentStoreRequest;
use App\Models\Attachable;
use App\Models\Attachment;
use App\Models\Execution;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Uploading, downloading and removing attachments.
 *
 * **One upload route per parent type, on purpose.** The alternative — a single
 * route taking a type name and an id — is how legacy did it, and it let a
 * caller point an upload at any table it could name. Here the parent arrives
 * through route model binding, so the only targets that exist are the ones with
 * a route, and each has already been resolved and scoped by the router.
 */
class AttachmentController extends Controller
{
    public function storeForProject(
        AttachmentStoreRequest $request,
        TestProject $testProject,
        StoreAttachment $storeAttachment,
    ): RedirectResponse {
        return $this->store($request, $testProject, $storeAttachment);
    }

    public function storeForSuite(
        AttachmentStoreRequest $request,
        TestSuite $testSuite,
        StoreAttachment $storeAttachment,
    ): RedirectResponse {
        return $this->store($request, $testSuite, $storeAttachment);
    }

    public function storeForVersion(
        AttachmentStoreRequest $request,
        TestCaseVersion $testCaseVersion,
        StoreAttachment $storeAttachment,
    ): RedirectResponse {
        return $this->store($request, $testCaseVersion, $storeAttachment);
    }

    public function storeForPlan(
        AttachmentStoreRequest $request,
        TestPlan $testPlan,
        StoreAttachment $storeAttachment,
    ): RedirectResponse {
        return $this->store($request, $testPlan, $storeAttachment);
    }

    public function storeForExecution(
        AttachmentStoreRequest $request,
        Execution $execution,
        StoreAttachment $storeAttachment,
    ): RedirectResponse {
        return $this->store($request, $execution, $storeAttachment);
    }

    /**
     * Serve the file.
     *
     * This is the endpoint legacy got most wrong. Its version required a login
     * and nothing else — no check that the requester could see the test case,
     * suite or project the file belonged to — while ids were sequential, so any
     * user could walk the range and collect every attachment in the
     * installation. CVE-2022-35195 was the same endpoint being reachable with
     * no login at all, and requiring one was treated as the fix.
     *
     * Three things are different here:
     *
     * 1. The parent's own view ability is checked, in the parent's project. An
     *    id from another project is a 403, not a file.
     * 2. `Content-Type` is the type this application guessed by reading the
     *    file when it was uploaded, never the type the uploader claimed.
     *    Legacy echoed the claim, so an uploaded `.html` came back as
     *    `text/html` and ran as a page on this origin.
     * 3. Only raster images are shown in place. Everything else downloads, and
     *    `nosniff` stops a browser second-guessing either decision.
     */
    public function show(Attachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('attachable');

        /** @var Attachable&Model $target */
        $target = $attachment->attachable;

        $scope = $target->attachmentScope();

        if (
            ! Gate::allows($target->attachmentViewAbility()->value, $scope)
            && ! Gate::allows($target->attachmentManageAbility()->value, $scope)
        ) {
            throw new AuthorizationException;
        }

        abort_unless($attachment->existsOnDisk(), 404);

        return Storage::disk($attachment->diskName())->response(
            $attachment->disk_path,
            $attachment->file_name,
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ],
            $attachment->isSafeToShowInline() ? 'inline' : 'attachment',
        );
    }

    public function destroy(
        Request $request,
        Attachment $attachment,
        DeleteAttachment $deleteAttachment,
    ): RedirectResponse {
        $name = $attachment->label();

        $deleteAttachment($this->actingUser($request), $attachment);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('":name" removed.', ['name' => $name]),
        ]);

        return back();
    }

    private function store(
        AttachmentStoreRequest $request,
        Attachable&Model $target,
        StoreAttachment $storeAttachment,
    ): RedirectResponse {
        $attachment = $storeAttachment(
            $this->actingUser($request),
            $target,
            $request->uploadedFile(),
            $request->title(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('":name" attached.', ['name' => Str::limit($attachment->label(), 40)]),
        ]);

        return back();
    }
}
