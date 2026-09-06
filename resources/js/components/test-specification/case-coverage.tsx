import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { show as requirementShow } from '@/routes/requirements/items';
import { destroy as destroyCoverage } from '@/routes/requirement-coverages';
import { store as linkFromCase } from '@/routes/test-case-coverages';
import type {
    SpecificationAbilities,
    SpecificationProject,
    VersionDetail,
} from '@/types/test-specification';

const selectClasses =
    'border-input bg-background h-9 w-full rounded-md border px-3 text-sm';

export default function CaseCoverage({
    project,
    version,
    can,
}: {
    project: SpecificationProject;
    version: VersionDetail;
    can: SpecificationAbilities;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Requirements coverage</CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                {version.coverages.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        This version does not cover a requirement.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {version.coverages.map((coverage) => (
                            <li
                                key={coverage.id}
                                className="flex items-center justify-between gap-2 text-sm"
                            >
                                <Link
                                    href={requirementShow([
                                        project.id,
                                        coverage.requirement_id,
                                    ], {
                                        query: {
                                            version: coverage.requirement_version,
                                        },
                                    })}
                                    className="font-medium underline-offset-4 hover:underline"
                                >
                                    {coverage.doc_id} {coverage.name} (v
                                    {coverage.requirement_version})
                                </Link>

                                {can.coverage && (
                                    <Form
                                        {...destroyCoverage.form(coverage.id)}
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

                {can.coverage && version.coverable.length > 0 && (
                    <Form
                        {...linkFromCase.form(version.id)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="requirement_version_id">
                                        Requirement version
                                    </Label>
                                    <select
                                        id="requirement_version_id"
                                        name="requirement_version_id"
                                        className={selectClasses}
                                        required
                                    >
                                        {version.coverable.map((other) => (
                                            <option
                                                key={other.id}
                                                value={other.id}
                                            >
                                                {other.doc_id} {other.name} (v
                                                {other.version})
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={
                                            errors.requirement_version_id
                                        }
                                    />
                                </div>

                                <div>
                                    <Button
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Link coverage
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
