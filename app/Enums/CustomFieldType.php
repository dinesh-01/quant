<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * What kind of value a custom field holds, and everything that follows from it.
 *
 * Legacy kept this as a `smallint` on the definition row and then spread the
 * consequences over five places: a `$custom_field_types` array in
 * `cfield_mgr.class.php`, a duplicate of the same list in
 * `cfield_validation.js`, a `switch` for parsing the request, another for
 * rendering the input and a third for rendering the value. The lists drifted —
 * type `3` is a hole left by a Mantis enum, and the JavaScript validated four
 * of the thirteen types while the server validated none.
 *
 * Everything type-dependent therefore hangs off this enum, and the numeric
 * codes are gone: a stored `7` meaning "multiselection list" is only readable
 * with the table to hand.
 *
 * Dropped from the legacy set: `script` and `server`, which existed for a
 * remote-execution feature that keyed off field *names* matching `RE-XMLRPC_%`
 * rather than off the type at all, and which fell through to a plain text input
 * unless a `custom/cf_*.php` plugin was dropped in.
 */
enum CustomFieldType: string
{
    case String = 'string';
    case Text = 'text';
    case Numeric = 'numeric';
    case Float = 'float';
    case Email = 'email';
    case Date = 'date';
    case DateTime = 'datetime';
    case Dropdown = 'dropdown';
    case MultiSelect = 'multi_select';
    case Checkbox = 'checkbox';
    case Radio = 'radio';

    /**
     * The format a date or date-time value is stored and submitted in.
     *
     * Legacy stored both as a Unix timestamp in a `varchar`, parsed out of
     * localised date parts by `split_localized_date`. A failed parse produced
     * an empty string, which its write path then treated as "clear this field"
     * — so entering a date in the wrong locale's order deleted the value.
     */
    public const string DATE_FORMAT = 'Y-m-d';

    public const string DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * A human readable name, derived rather than listed, as `Ability` does.
     */
    public function label(): string
    {
        return Str::ucfirst(Str::lower(Str::headline($this->name)));
    }

    /**
     * Whether the definition has to declare the set of values to choose from.
     */
    public function hasOptions(): bool
    {
        return in_array($this, [self::Dropdown, self::MultiSelect, self::Checkbox, self::Radio], true);
    }

    /**
     * Whether one value of this type is a list rather than a scalar.
     *
     * Radio is deliberately not here. Legacy rendered radios with an array
     * input name and then joined multiple answers with a pipe, which no radio
     * group can actually produce — a leftover from the checkbox code it was
     * copied from.
     */
    public function isMultiValue(): bool
    {
        return in_array($this, [self::MultiSelect, self::Checkbox], true);
    }

    /**
     * Whether `length_min` and `length_max` mean anything for this type.
     *
     * Only for free text. Legacy stored both columns for every type, applied
     * `length_max` as an HTML `maxlength` attribute on text inputs and never
     * checked either on the server.
     */
    public function usesLengthLimits(): bool
    {
        return in_array($this, [self::String, self::Text, self::Email], true);
    }

    /**
     * Whether `pattern` means anything for this type.
     *
     * A regular expression over a value the user picked from a list, or over a
     * date this application formatted itself, can only ever reject what the
     * type already guarantees.
     */
    public function usesPattern(): bool
    {
        return in_array($this, [self::String, self::Text, self::Email], true);
    }

    /**
     * Whether a submitted answer counts as no answer at all.
     *
     * An unticked checkbox group submits an empty array and a cleared text box
     * submits an empty string; both mean the same thing, and both delete the
     * stored row rather than replacing it with an empty one.
     */
    public function isBlank(mixed $answer): bool
    {
        if ($this->isMultiValue()) {
            return ! is_array($answer) || $answer === [];
        }

        return $answer === null || trim((string) $answer) === '';
    }

    /**
     * Turn a submitted answer into the single string the column stores.
     *
     * The multi-value types become a JSON list. Legacy joined them with `|`
     * and did not escape it, so an option containing a pipe read back as two
     * answers and there was no way to tell which case you were looking at.
     *
     * Deliberately not an Eloquent cast: a cast would have to reach the
     * definition through `$model->customField` to learn the type, which is a
     * lazy load per value on a screen that reads dozens of them. Every caller
     * already holds the definition.
     *
     * @return string|null Null when the answer is blank, meaning the row goes.
     */
    public function encode(mixed $answer): ?string
    {
        if ($this->isBlank($answer)) {
            return null;
        }

        if ($this->isMultiValue()) {
            /** @var array<int|string, mixed> $answer */
            $chosen = array_values(array_map(static fn (mixed $option): string => trim((string) $option), $answer));

            return json_encode($chosen, JSON_THROW_ON_ERROR);
        }

        return trim((string) $answer);
    }

    /**
     * Read a stored value back into what a form field expects.
     *
     * @return string|list<string>
     */
    public function decode(?string $stored): string|array
    {
        if (! $this->isMultiValue()) {
            return $stored ?? '';
        }

        if ($stored === null || $stored === '') {
            return [];
        }

        /*
         * Defensive rather than expected: `encode()` is the only writer and it
         * always produces a list. A malformed value is treated as no answer,
         * because throwing here would take a whole screen down over one row.
         */
        $decoded = json_decode($stored, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $option): string => (string) $option, $decoded));
    }

    /**
     * The longest value worth accepting when the definition sets no limit.
     *
     * A single-line field gets legacy's column width; a text area gets the
     * width of the legacy `value` column it will be stored in. Legacy instead
     * clipped every type to 255 characters from
     * `$tlCfg->custom_fields->max_length` — silently, on write, in a column
     * declared `varchar(4000)`.
     */
    public function defaultMaximumLength(): int
    {
        return $this === self::Text ? 4000 : 255;
    }
}
