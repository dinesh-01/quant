<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Attachments\StoreAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attachments\AttachmentStoreRequest;
use App\Http\Resources\Api\V1\AttachmentResource;
use App\Models\Attachable;
use App\Models\Attachment;
use App\Models\Execution;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function storeForProject(
        AttachmentStoreRequest $request,
        TestProject $testProject,
        StoreAttachment $storeAttachment,
    ): JsonResponse {
        return $this->store($request, $testProject, $storeAttachment);
    }

    public function storeForSuite(
        AttachmentStoreRequest $request,
        TestSuite $testSuite,
        StoreAttachment $storeAttachment,
    ): JsonResponse {
        return $this->store($request, $testSuite, $storeAttachment);
    }

    public function storeForVersion(
        AttachmentStoreRequest $request,
        TestCaseVersion $testCaseVersion,
        StoreAttachment $storeAttachment,
    ): JsonResponse {
        return $this->store($request, $testCaseVersion, $storeAttachment);
    }

    public function storeForPlan(
        AttachmentStoreRequest $request,
        TestPlan $testPlan,
        StoreAttachment $storeAttachment,
    ): JsonResponse {
        return $this->store($request, $testPlan, $storeAttachment);
    }

    public function storeForExecution(
        AttachmentStoreRequest $request,
        Execution $execution,
        StoreAttachment $storeAttachment,
    ): JsonResponse {
        return $this->store($request, $execution, $storeAttachment);
    }

    /**
     * Same ACL and headers as the web download: the parent's view or manage
     * ability, the sniffed type, and no inline HTML.
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

    private function store(
        AttachmentStoreRequest $request,
        Attachable&Model $target,
        StoreAttachment $storeAttachment,
    ): JsonResponse {
        $attachment = $storeAttachment(
            $this->actingUser($request),
            $target,
            $request->uploadedFile(),
            $request->title(),
        );

        return (new AttachmentResource($attachment))
            ->response()
            ->setStatusCode(201);
    }
}
