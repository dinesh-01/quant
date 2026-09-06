<?php

namespace App\Http\Requests\Keywords;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The keywords a case should carry after this request.
 *
 * Only the shape is checked here. Whether each id is one of *this project's*
 * keywords is `SyncTestCaseKeywords`' job, because that is a rule about the
 * domain rather than about the form, and an action reached from anywhere must
 * not depend on a request having checked it.
 */
class TestCaseKeywordsRequest extends FormRequest
{
    /**
     * `keywords` may be absent entirely, which means "no keywords" — an empty
     * multi-select submits nothing at all, and treating that as a missing field
     * would make clearing the last keyword impossible.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keywords' => ['array'],
            'keywords.*' => ['integer'],
        ];
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
}
