<?php

namespace App\Actions\Audit;

use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Writes the audit trail.
 *
 * Records facts — who, what act, on what, and which values changed — and
 * leaves the sentence to be composed when the log is read. Legacy stored a
 * rendered localised string instead, which cannot be re-rendered, translated
 * or filtered on afterwards.
 *
 * The actor is passed in rather than read from the guard, matching the domain
 * actions that call this: a console command has no session, and an action's
 * behaviour should not change depending on how it was reached.
 */
final class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        AuditAction $action,
        ?User $actor = null,
        ?Model $subject = null,
        array $properties = [],
    ): AuditEvent {
        $event = new AuditEvent([
            'action' => $action->value,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $this->request->ip(),
        ]);

        $event->user()->associate($actor);

        if ($subject instanceof Model) {
            $event->subject()->associate($subject);
        }

        $event->save();

        return $event;
    }

    /**
     * What a pending change does to a model, as `field => [from, to]`.
     *
     * Call before saving, while the model is still dirty.
     *
     * Attributes the model marks hidden are recorded as having changed without
     * their values. Reusing the model's own `$hidden` declaration means a new
     * secret is protected here the moment it is hidden from serialisation,
     * rather than relying on this class keeping its own list in step — which
     * is how an audit trail ends up holding password hashes and two-factor
     * secrets.
     *
     * @return array<string, array<string, mixed>>
     */
    public function changes(Model $model): array
    {
        $hidden = $model->getHidden();
        $changes = [];

        foreach ($model->getDirty() as $attribute => $value) {
            if (in_array($attribute, $hidden, true)) {
                $changes[$attribute] = ['changed' => true];

                continue;
            }

            $changes[$attribute] = [
                'from' => $this->readable($model->getOriginal($attribute)),
                'to' => $this->readable($model->getAttribute($attribute)),
            ];
        }

        return $changes;
    }

    /**
     * Reduce a value to something JSON can hold and a person can read.
     *
     * Enum collections and dates would otherwise serialise as objects whose
     * shape depends on the cast, which makes the stored history harder to read
     * than the change it describes.
     */
    private function readable(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
            $value instanceof Collection => $value
                ->map(fn (mixed $item): mixed => $this->readable($item))
                ->values()
                ->all(),
            is_scalar($value), is_null($value), is_array($value) => $value,
            default => (string) json_encode($value),
        };
    }
}
