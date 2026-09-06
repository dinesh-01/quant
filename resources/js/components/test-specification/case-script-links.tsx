import { Form } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy as destroyScript } from '@/routes/script-links';
import { store as storeScript } from '@/routes/script-links';
import type {
    SpecificationAbilities,
    VersionDetail,
} from '@/types/test-specification';

export default function CaseScriptLinks({
    version,
    can,
}: {
    version: VersionDetail;
    can: SpecificationAbilities;
}) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Automation scripts</CardTitle>
            </CardHeader>

            <CardContent className="space-y-4">
                {version.script_links.length === 0 ? (
                    <p className="text-muted-foreground text-sm">
                        This version is not linked to a script.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {version.script_links.map((link) => (
                            <li
                                key={link.id}
                                className="flex items-center justify-between gap-2 text-sm"
                            >
                                {link.url ? (
                                    <a
                                        href={link.url}
                                        className="font-medium underline-offset-4 hover:underline"
                                    >
                                        {link.repository}/{link.path}
                                    </a>
                                ) : (
                                    <span className="font-medium">
                                        {link.repository}/{link.path}
                                    </span>
                                )}

                                {can.manage && (
                                    <Form
                                        {...destroyScript.form(link.id)}
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

                {can.manage && (
                    <Form
                        {...storeScript.form(version.id)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="project_key">
                                        Project key
                                    </Label>
                                    <Input
                                        id="project_key"
                                        name="project_key"
                                        required
                                        maxLength={100}
                                    />
                                    <InputError message={errors.project_key} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="repository">
                                        Repository
                                    </Label>
                                    <Input
                                        id="repository"
                                        name="repository"
                                        required
                                        maxLength={191}
                                    />
                                    <InputError message={errors.repository} />
                                </div>

                                <div className="grid gap-2 sm:col-span-2">
                                    <Label htmlFor="path">Path</Label>
                                    <Input
                                        id="path"
                                        name="path"
                                        required
                                        maxLength={255}
                                    />
                                    <InputError message={errors.path} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="branch">Branch</Label>
                                    <Input
                                        id="branch"
                                        name="branch"
                                        maxLength={191}
                                    />
                                    <InputError message={errors.branch} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="commit">Commit</Label>
                                    <Input
                                        id="commit"
                                        name="commit"
                                        maxLength={64}
                                    />
                                    <InputError message={errors.commit} />
                                </div>

                                <div>
                                    <Button
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Link script
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
