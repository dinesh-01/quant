import { Form, Link } from '@inertiajs/react';
import { Monitor } from 'lucide-react';
import VersionPlatformController from '@/actions/App/Http/Controllers/Platforms/VersionPlatformController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { index as platformIndex } from '@/routes/platforms';
import type { PlatformOption } from '@/types/platform';
import type {
    SpecificationProject,
    VersionDetail,
} from '@/types/test-specification';

/**
 * The platforms a test case version is written for.
 *
 * These hang off the version, not the case — the opposite of keywords. A
 * keyword describes what a case is about. A platform assignment is a claim
 * about this revision of the steps, so switching version switches the tags
 * with it.
 *
 * The whole set is submitted every time and replaces what was there, so
 * unticking is how a platform comes off. Anyone who may only read sees the
 * tags without the form.
 */
export default function CasePlatforms({
    project,
    version,
    platforms,
    canAssign,
}: {
    project: SpecificationProject;
    version: VersionDetail;
    platforms: PlatformOption[];
    canAssign: boolean;
}) {
    const assigned = new Set(version.platforms.map((platform) => platform.id));

    if (!canAssign) {
        return version.platforms.length === 0 ? null : (
            <div className="flex flex-wrap items-center gap-1.5">
                <Monitor className="text-muted-foreground size-3.5" />

                {version.platforms.map((platform) => (
                    <Badge key={platform.id} variant="secondary">
                        {platform.name}
                    </Badge>
                ))}
            </div>
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
                        This project has no design-time platforms yet.{' '}
                        <Link
                            href={platformIndex(project.id)}
                            className="underline"
                        >
                            Its vocabulary
                        </Link>{' '}
                        is where they are created.
                    </p>
                ) : (
                    <Form
                        {...VersionPlatformController.update.form(version.id)}
                        options={{ preserveScroll: true }}
                        className="space-y-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {platforms.map((platform) => {
                                        const alreadyAssigned = assigned.has(
                                            platform.id,
                                        );
                                        const canCheck =
                                            alreadyAssigned || platform.is_open;

                                        return (
                                            <div
                                                key={platform.id}
                                                className="flex items-center gap-3"
                                            >
                                                <Checkbox
                                                    id={`version-platform-${platform.id}`}
                                                    name="platforms[]"
                                                    value={platform.id}
                                                    defaultChecked={
                                                        alreadyAssigned
                                                    }
                                                    disabled={!canCheck}
                                                />

                                                <Label
                                                    htmlFor={`version-platform-${platform.id}`}
                                                    className="font-normal"
                                                >
                                                    {platform.name}
                                                    {!platform.is_open &&
                                                        ' (closed)'}
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
