---
paths:
    - 'app/Actions/Attachments/**, app/Models/{Attachment,Attachable}.php, app/Concerns/HasAttachments.php, app/Http/Controllers/Attachments/**, app/Http/Requests/Attachments/**, config/attachments.php'
---

# Attachments

## Attachments

Files hang off a suite, a test case version or a project — never a test case, so a screenshot stays with the revision it describes.

Settled decisions, all of them reactions to how legacy did it:

- `StoreAttachment` is the only writer of the table, and the model has an empty `$fillable`. Everything a request could lie about is recomputed: the MIME type comes from `UploadedFile::getMimeType()` (content sniffed), the name on disk is a ULID, and the parent arrives through route model binding. There is one upload route per parent type on purpose; legacy took the parent's table name from the query string.
- Attachments carry no abilities of their own. `Attachable::attachmentViewAbility()` and `attachmentManageAbility()` name the parent's abilities and `attachmentScope()` names the project they resolve in. Every entry point reads them from the model rather than deciding for itself — that is what makes the check impossible to forget. A project's own files answer to `manage_test_projects`, which is a system ability.
- Download authorizes the parent's view ability in the parent's project, sends the sniffed type with `nosniff` and a `default-src 'none'; sandbox` CSP, and only shows raster images (`attachments.inline_types`) in place. Legacy's version required a login and nothing else, over sequential ids.
- Uploads are checked twice — `mimes` against the content and `extensions` against the name — so neither a renamed payload nor a false header gets in. Note `content_extensions` allows `zip` because every OOXML file is one.
- **A polymorphic link carries no foreign key, so nothing cascades.** Every action deleting a possible parent must call `PurgeAttachments`, and every action copying one must call `CopyAttachments`. That is `Delete{TestProject,TestSuite,TestCase,TestCaseVersion}` and `CreateTestCaseVersion`, `CopyTestCase`, `CopyTestSuite`. Both collaborators are internal: they do not authorize and take no user. Deletes put the purged count in their audit record.
- `PurgeAttachments::remove()` returns early on an empty id list. That is the safety check, not an optimisation: an empty nested `where` constrains nothing and would delete every attachment in the installation.
- Copies duplicate the bytes rather than sharing them, since `DeleteAttachment` cannot know whether it holds the last reference.

On the screens: `PresentsAttachments` builds the list and the upload hints, both from the same config the validation rules read. `is_image` is the server's answer from the sniffed type — never work it out in React from a file name, since that trusts the uploader. `AttachmentList` carries its own upload and delete forms, so it needs its own card and must never be rendered inside another form. Downloads are plain anchors; an Inertia `<Link>` would try to read a page out of a file.

Testing note: `Illuminate\Http\Testing\File::getMimeType()` returns the _reported_ type, so a fake upload cannot tell sniffing from trusting. Assert that through the action with a real `UploadedFile` over real bytes (see `AttachmentUploadTest::realUpload()`).
