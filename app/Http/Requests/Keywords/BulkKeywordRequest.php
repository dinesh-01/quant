<?php

namespace App\Http\Requests\Keywords;

use App\Enums\KeywordBulkMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A bulk keyword run over the cases under a suite.
 *
 * The mode is required and has no default. A default would decide for the user
 * what a malformed request meant, and one of the three modes clears every
 * keyword from every case in a subtree.
 */
class BulkKeywordRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(KeywordBulkMode::class)],
            'keywords' => ['array', Rule::requiredIf(
                fn (): bool => $this->input('mode') !== KeywordBulkMode::ClearAll->value,
            )],
            'keywords.*' => ['integer'],
            'direct_children_only' => ['boolean'],
        ];
    }

    public function mode(): KeywordBulkMode
    {
        return KeywordBulkMode::from((string) $this->input('mode'));
    }

    /**
     * @return list<int>
     */
    public function keywordIds(): array
    {
        /** @var array<int, mixed> $ids */
        $ids = $this->input('keywords', []);

        return array_values(array_map(intval(...), $ids));
    }

    public function directChildrenOnly(): bool
    {
        return $this->boolean('direct_children_only');
    }
}
