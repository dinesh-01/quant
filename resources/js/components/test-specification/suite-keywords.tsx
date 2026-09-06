import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import KeywordAssignmentController from '@/actions/App/Http/Controllers/Keywords/KeywordAssignmentController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as keywordIndex } from '@/routes/keywords';
import type { KeywordOption } from '@/types/keyword';
import type {
    SpecificationProject,
    SuiteDetail,
} from '@/types/test-specification';

type Mode = 'assign' | 'remove' | 'clear_all';

const modeLabels: Record<Mode, string> = {
    assign: 'Add these keywords',
    remove: 'Take these keywords off',
    clear_all: 'Take every keyword off',
};

/**
 * Applies keywords to the test cases under a suite in one go.
 *
 * Adding and removing are separate modes rather than a replace, because a
 * replace across a subtree would quietly drop tags whoever ran it never saw.
 * Clearing everything is deliberately the third choice and needs no keywords.
 */
export default function SuiteKeywords({
    project,
    suite,
    keywords,
    canAssign,
}: {
    project: SpecificationProject;
    suite: SuiteDetail;
    keywords: KeywordOption[];
    canAssign: boolean;
}) {
    const [mode, setMode] = useState<Mode>('assign');

    if (!canAssign) {
        return null;
    }

    const needsKeywords = mode !== 'clear_all';

    return (
        <Card>
            <CardHeader>
                <CardTitle>Keywords in this subtree</CardTitle>
            </CardHeader>

            <CardContent>
                {keywords.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        This project has no keywords yet.{' '}
                        <Link
                            href={keywordIndex(project.id)}
                            className="underline"
                        >
                            Its vocabulary
                        </Link>{' '}
                        is where they are created.
                    </p>
                ) : (
                    <Form
                        {...KeywordAssignmentController.storeForTestSuite.form(
                            suite.id,
                        )}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2 sm:max-w-72">
                                    <Label htmlFor="bulk-keyword-mode">
                                        Do what
                                    </Label>

                                    <select
                                        id="bulk-keyword-mode"
                                        name="mode"
                                        value={mode}
                                        onChange={(event) =>
                                            setMode(event.target.value as Mode)
                                        }
                                        className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                                    >
                                        {Object.entries(modeLabels).map(
                                            ([value, label]) => (
                                                <option
                                                    key={value}
                                                    value={value}
                                                >
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>

                                    <InputError message={errors.mode} />
                                </div>

                                {needsKeywords && (
                                    <fieldset className="grid gap-3 sm:grid-cols-2">
                                        <legend className="sr-only">
                                            Keywords to apply
                                        </legend>

                                        {keywords.map((keyword) => (
                                            <div
                                                key={keyword.id}
                                                className="flex items-center gap-3"
                                            >
                                                <Checkbox
                                                    id={`bulk-keyword-${keyword.id}`}
                                                    name="keywords[]"
                                                    value={keyword.id}
                                                />

                                                <Label
                                                    htmlFor={`bulk-keyword-${keyword.id}`}
                                                    className="font-normal"
                                                >
                                                    {keyword.name}
                                                </Label>
                                            </div>
                                        ))}
                                    </fieldset>
                                )}

                                <InputError message={errors.keywords} />

                                <div className="flex items-center gap-3">
                                    <Checkbox
                                        id="direct_children_only"
                                        name="direct_children_only"
                                    />

                                    <Label
                                        htmlFor="direct_children_only"
                                        className="font-normal"
                                    >
                                        Only the cases directly in {suite.name},
                                        not those in suites beneath it
                                    </Label>
                                </div>

                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Apply
                                </Button>

                                <p className="text-muted-foreground text-xs">
                                    {mode === 'clear_all'
                                        ? 'Every keyword comes off every case this covers.'
                                        : 'Only the ticked keywords change; anything else a case carries is left alone.'}
                                </p>
                            </>
                        )}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}
