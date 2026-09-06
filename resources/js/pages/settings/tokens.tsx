import { Form, Head } from '@inertiajs/react';
import TokenController from '@/actions/App/Http/Controllers/Settings/TokenController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/tokens';

type Token = {
    id: number;
    name: string;
    last_used_at: string | null;
    created_at: string | null;
};

export default function Tokens({
    tokens,
    plainTextToken,
}: {
    tokens: Token[];
    plainTextToken: string | null;
}) {
    return (
        <>
            <Head title="API tokens" />

            <h1 className="sr-only">API tokens</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="API tokens"
                    description="Create a personal access token for CI and other automation. The token inherits your permissions."
                />

                {plainTextToken && (
                    <div className="space-y-2 rounded-md border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                        <p className="text-sm font-medium">
                            Copy this token now. It will not be shown again.
                        </p>
                        <Input
                            readOnly
                            value={plainTextToken}
                            className="font-mono text-sm"
                        />
                    </div>
                )}

                <Form
                    {...TokenController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Token name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="CI pipeline"
                                    autoComplete="off"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <Button disabled={processing}>Create token</Button>
                        </>
                    )}
                </Form>

                <div className="space-y-3">
                    {tokens.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No tokens yet.
                        </p>
                    ) : (
                        tokens.map((token) => (
                            <div
                                key={token.id}
                                className="flex items-center justify-between gap-4 rounded-md border p-3"
                            >
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {token.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        Created {token.created_at ?? '—'}
                                        {token.last_used_at
                                            ? ` · Last used ${token.last_used_at}`
                                            : ''}
                                    </p>
                                </div>

                                <Form
                                    {...TokenController.destroy.form(token.id)}
                                >
                                    {({ processing }) => (
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Revoke
                                        </Button>
                                    )}
                                </Form>
                            </div>
                        ))
                    )}
                </div>
            </div>
        </>
    );
}

Tokens.layout = {
    breadcrumbs: [
        {
            title: 'API tokens',
            href: index(),
        },
    ],
};
