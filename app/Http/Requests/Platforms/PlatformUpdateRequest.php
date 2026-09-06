<?php

namespace App\Http\Requests\Platforms;

use App\Concerns\PlatformValidationRules;
use App\Models\Platform;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlatformUpdateRequest extends FormRequest
{
    use PlatformValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $platform = $this->routePlatform();

        return $this->platformRules($platform->test_project_id, $platform->getKey());
    }

    /**
     * @return array{name: string, notes: string|null, enable_on_design: bool, enable_on_execution: bool, is_open: bool}
     */
    public function platform(): array
    {
        return $this->platformAttributes();
    }

    public function routePlatform(): Platform
    {
        $platform = $this->route('platform');

        abort_unless($platform instanceof Platform, 404);

        return $platform;
    }
}
