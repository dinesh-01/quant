<?php

namespace App\Http\Requests\Keywords;

use App\Concerns\KeywordValidationRules;
use App\Models\Keyword;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class KeywordUpdateRequest extends FormRequest
{
    use KeywordValidationRules;

    /**
     * The keyword excludes itself from the uniqueness check, so saving it
     * without renaming is not a collision with itself.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $keyword = $this->routeKeyword();

        return $this->keywordRules($keyword->test_project_id, $keyword->getKey());
    }

    /**
     * @return array{name: string, notes: string|null}
     */
    public function keyword(): array
    {
        return $this->keywordAttributes();
    }

    /**
     * The bound keyword, narrowed for static analysis.
     */
    public function routeKeyword(): Keyword
    {
        $keyword = $this->route('keyword');

        abort_unless($keyword instanceof Keyword, 404);

        return $keyword;
    }
}
