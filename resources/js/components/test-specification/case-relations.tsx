import { Form, Link } from '@inertiajs/react';
import TestCaseRelationController from '@/actions/App/Http/Controllers/TestSpecification/TestCaseRelationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { show as caseShow } from '@/routes/specification/cases';
import type {
    CaseDetail,
    SpecificationAbilities,
    SpecificationProject,
} from '@/types/test-specification';

const selectClasses =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

export default function CaseRelations({
    project,
    testCase,
    can,
}: {
    project: SpecificationProject;
    testCase: CaseDetail;
    can: SpecificationAbilities;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Relations</CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                {testCase.relations.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        This case is not linked to another case.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {testCase.relations.map((relation) => (
                            <li
                                key={relation.id}
                                className="flex items-center justify-between gap-2 text-sm"
                            >
                                <p>
                                    {relation.label}{' '}
                                    <Link
                                        href={caseShow([
                                            project.id,
                                            relation.other_id,
                                        ])}
                                        className="font-medium underline-offset-4 hover:underline"
                                    >
                                        {relation.full_external_id}{' '}
                                        {relation.other_name}
                                    </Link>
                                </p>

                                {can.manage && (
                                    <Form
                                        {...TestCaseRelationController.destroy.form(
                                            relation.id,
                                        )}
                                        options={{ preserveScroll: true }}
                                    >
                                        <Button size="sm" variant="ghost">
                                            Remove
                                        </Button>
                                    </Form>
                                )}
                            </li>
                        ))}
                    </ul>
                )}

                {can.manage && testCase.relatable.length > 0 && (
                    <Form
                        {...TestCaseRelationController.store.form(testCase.id)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="destination_id">Case</Label>
                                    <select
                                        id="destination_id"
                                        name="destination_id"
                                        className={selectClasses}
                                        required
                                    >
                                        {testCase.relatable.map((other) => (
                                            <option
                                                key={other.id}
                                                value={other.id}
                                            >
                                                {other.full_external_id}{' '}
                                                {other.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={errors.destination}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="type">Type</Label>
                                    <select
                                        id="type"
                                        name="type"
                                        defaultValue="related"
                                        className={selectClasses}
                                    >
                                        <option value="related">
                                            is related to
                                        </option>
                                        <option value="depends_on">
                                            depends on
                                        </option>
                                        <option value="blocks">blocks</option>
                                    </select>
                                </div>

                                <div>
                                    <Button
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Add relation
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}
