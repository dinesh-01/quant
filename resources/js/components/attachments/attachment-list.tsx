import { Form } from '@inertiajs/react';
import { Download, Paperclip, Trash2, Upload } from 'lucide-react';
import AttachmentController from '@/actions/App/Http/Controllers/Attachments/AttachmentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { show } from '@/routes/attachments';
import type { AttachmentRules, AttachmentSummary } from '@/types/attachment';
import type { RouteFormDefinition } from '@/wayfinder';

/**
 * The files hanging off one suite, test case version or project.
 *
 * Contains its own upload and delete forms, so it must never be rendered inside
 * another `<form>` — a nested form is invalid HTML and the browser drops the
 * inner one. On the specification screen that means its own card, below the one
 * editing the node.
 *
 * Downloads are plain anchors, not `<Link>`s: the response is a file, and an
 * Inertia visit would try to read a page out of it.
 */
export default function AttachmentList({
    attachments,
    rules,
    upload,
    canManage,
    describedAs = 'this',
}: {
    attachments: AttachmentSummary[];
    rules: AttachmentRules;
    upload: RouteFormDefinition<'post'>;
    canManage: boolean;
    describedAs?: string;
}) {
    return (
        <div className="space-y-4">
            {attachments.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    No files attached to {describedAs}.
                </p>
            ) : (
                <ul className="divide-y rounded-md border">
                    {attachments.map((attachment) => (
                        <li
                            key={attachment.id}
                            className="flex items-start gap-3 p-3"
                        >
                            {attachment.is_image ? (
                                <a
                                    href={show(attachment.id).url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="shrink-0"
                                >
                                    <img
                                        src={show(attachment.id).url}
                                        alt={attachment.label}
                                        className="bg-muted size-12 rounded border object-cover"
                                    />
                                </a>
                            ) : (
                                <Paperclip className="text-muted-foreground mt-0.5 size-5 shrink-0" />
                            )}

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {attachment.label}
                                </p>

                                <p className="text-muted-foreground text-xs">
                                    {formatSize(attachment.size_bytes)}
                                    {attachment.uploader !== null &&
                                        ` · ${attachment.uploader}`}
                                    {attachment.uploaded_at !== null &&
                                        ` · ${new Date(attachment.uploaded_at).toLocaleDateString()}`}
                                </p>
                            </div>

                            <div className="flex shrink-0 items-center gap-1">
                                <Button asChild size="sm" variant="ghost">
                                    <a
                                        href={show(attachment.id).url}
                                        download={attachment.file_name}
                                    >
                                        <Download className="size-4" />
                                        <span className="sr-only">
                                            Download {attachment.label}
                                        </span>
                                    </a>
                                </Button>

                                {canManage && (
                                    <Form
                                        {...AttachmentController.destroy.form(
                                            attachment.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                        onBefore={() =>
                                            confirm(
                                                `Remove "${attachment.label}"? The file is deleted and cannot be recovered.`,
                                            )
                                        }
                                    >
                                        {({ processing }) => (
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                disabled={processing}
                                                className="text-destructive"
                                            >
                                                <Trash2 className="size-4" />
                                                <span className="sr-only">
                                                    Remove {attachment.label}
                                                </span>
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {canManage && (
                <Form
                    {...upload}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor={`file-${upload.action}`}>
                                        File
                                    </Label>

                                    <Input
                                        id={`file-${upload.action}`}
                                        type="file"
                                        name="file"
                                        required
                                        accept={rules.extensions
                                            .map((extension) => `.${extension}`)
                                            .join(',')}
                                    />

                                    <InputError message={errors.file} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor={`title-${upload.action}`}>
                                        Title
                                    </Label>

                                    <Input
                                        id={`title-${upload.action}`}
                                        name="title"
                                        maxLength={250}
                                        placeholder="Optional, shown instead of the file name"
                                    />

                                    <InputError message={errors.title} />
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={processing}
                                >
                                    <Upload className="size-4" />
                                    Attach file
                                </Button>

                                <p className="text-muted-foreground text-xs">
                                    Up to{' '}
                                    {formatSize(
                                        rules.max_kilobytes * 1024,
                                    )}.{' '}
                                    {rules.extensions.join(', ')}.
                                </p>
                            </div>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}

/**
 * Sizes are shown in whole units, because the exact byte count of an
 * attachment is never what the reader wants to know.
 */
function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} bytes`;
    }

    const kilobytes = bytes / 1024;

    return kilobytes < 1024
        ? `${Math.round(kilobytes)} KB`
        : `${(kilobytes / 1024).toFixed(kilobytes / 1024 < 10 ? 1 : 0)} MB`;
}
