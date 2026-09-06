import { Form, Link } from '@inertiajs/react';
import PlanPlatformController from '@/actions/App/Http/Controllers/Platforms/PlanPlatformController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as platformIndex } from '@/routes/platforms';
import type {
    PlanContents,
    PlanContentsAbilities,
    PlanPlatformOption,
} from '@/types/test-plan';

export default function PlanPlatforms({
    projectId,
    plan,
    platforms,
    can,
}: {
    projectId: number;
    plan: PlanContents;
    platforms: PlanPlatformOption[];
    can: PlanContentsAbilities;
}) {
    const assignedCount = platforms.filter((platform) => platform.assigned)
        .length;
    const addingFirstWhileNullItems =
        assignedCount === 0 && plan.has_null_platform_items;

    if (!can.managePlanPlatforms) {
        const assigned = platforms.filter((platform) => platform.assigned);

        return (
            <Card>
                <CardHeader>
                    <CardTitle>Platforms</CardTitle>
                </CardHeader>

                <CardContent>
                    {assigned.length === 0 ? (
                        <p className="text-muted-foreground text-sm">
                            This plan has no platforms. Linked cases run once,
                            not per environment.
                        </p>
                    ) : (
                        <ul className="text-sm">
                            {assigned.map((platform) => (
                                <li key={platform.id}>{platform.name}</li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        );
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Platforms</CardTitle>
            </CardHeader>

            <CardContent>
                {platforms.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        This project has no platforms yet.{' '}
                        {can.viewPlatforms ? (
                            <>
                                <Link
                                    href={platformIndex(projectId)}
                                    className="underline"
                                >
                                    Its vocabulary
                                </Link>{' '}
                                is where they are created.
                            </>
                        ) : (
                            'Ask someone who can manage platforms to add them.'
                        )}
                    </p>
                ) : (
                    <Form
                        {...PlanPlatformController.update.form(plan.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                {addingFirstWhileNullItems && (
                                    <p className="bg-muted rounded-md px-3 py-2 text-sm">
                                        This plan already has linked cases with
                                        no platform. Unlink them before adding
                                        the first platform — a plan with
                                        platforms cannot keep those rows.
                                    </p>
                                )}

                                {assignedCount > 0 && (
                                    <p className="text-muted-foreground text-sm">
                                        Removing a platform unlinks the cases
                                        pinned to it.
                                    </p>
                                )}

                                <div className="grid gap-3 sm:grid-cols-2">
                                    {platforms.map((platform) => {
                                        const canAssign =
                                            platform.assigned ||
                                            (platform.is_open &&
                                                platform.enable_on_execution);

                                        return (
                                            <div
                                                key={platform.id}
                                                className="flex items-center gap-3"
                                            >
                                                <Checkbox
                                                    id={`plan-platform-${platform.id}`}
                                                    name="platforms[]"
                                                    value={platform.id}
                                                    defaultChecked={
                                                        platform.assigned
                                                    }
                                                    disabled={!canAssign}
                                                />

                                                <Label
                                                    htmlFor={`plan-platform-${platform.id}`}
                                                    className="font-normal"
                                                >
                                                    {platform.name}
                                                    {!platform.is_open &&
                                                        ' (closed)'}
                                                    {!platform.enable_on_execution &&
                                                        ' (design only)'}
                                                </Label>
                                            </div>
                                        );
                                    })}
                                </div>

                                <InputError message={errors.platforms} />

                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={processing}
                                >
                                    Save platforms
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </CardContent>
        </Card>
    );
}
