import { Form, Link } from '@inertiajs/react';
import { Tag } from 'lucide-react';
import KeywordAssignmentController from '@/actions/App/Http/Controllers/Keywords/KeywordAssignmentController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as keywordIndex } from '@/routes/keywords';
import type { KeywordOption } from '@/types/keyword';
import type {
    CaseDetail,
    SpecificationProject,
} from '@/types/test-specification';

/**
 * The keywords on a test case.
 *
 * Keywords belong to the case rather than to one of its versions, so this sits
 * above the version pane and does not change when the reader switches version.
 * Legacy tied them to a version and then read them from the newest one anyway,
 * which meant tagging an old version silently did nothing.
 *
 * The whole set is submitted every time and replaces what was there, so
 * unticking is how a keyword comes off. Anyone who may only read sees the tags
 * without the form.
 */
export default function CaseKeywords({
    project,
    testCase,
    keywords,
    canAssign,
}: {
    project: SpecificationProject;
    testCase: CaseDetail;
    keywords: KeywordOption[];
    canAssign: boolean;
}) {
    const assigned = new Set(testCase.keywords.map((keyword) => keyword.id));

    if (!canAssign) {
        return testCase.keywords.length === 0 ? null : (
            <div className="flex flex-wrap items-center gap-1.5">
                <Tag className="text-muted-foreground size-3.5" />

                {testCase.keywords.map((keyword) => (
                    <Badge key={keyword.id} variant="secondary">
                        {keyword.name}
                    </Badge>
                ))}
            </div>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Keywords</CardTitle>
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
                        {...KeywordAssignmentController.updateForTestCase.form(
                            testCase.id,
                        )}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {keywords.map((keyword) => (
                                        <div
                                            key={keyword.id}
                                            className="flex items-center gap-3"
                                        >
                                            <Checkbox
                                                id={`keyword-${keyword.id}`}
                                                name="keywords[]"
                                                value={keyword.id}
                                                defaultChecked={assigned.has(
                                                    keyword.id,
                                                )}
                                            />

                                            <Label
                                                htmlFor={`keyword-${keyword.id}`}
                                                className="font-normal"
                                            >
                                                {keyword.name}
                                            </Label>
                                        </div>
                                    ))}
                                </div>

                                <InputError message={errors.keywords} />

                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Save keywords
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}
