<?php

namespace App\Models;

use App\Enums\IssueTrackerType;
use Database\Factories\IssueTrackerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Where a project's failed runs open tickets.
 *
 * One row per project. `settings` is encrypted JSON and holds the API
 * token, so it is hidden from serialisation and from the audit trail.
 *
 * @property int $id
 * @property int $test_project_id
 * @property string $name
 * @property IssueTrackerType $type
 * @property string $base_url
 * @property array<string, mixed>|null $settings
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestProject $testProject
 */
#[Fillable(['name', 'type', 'base_url', 'is_enabled'])]
#[Hidden(['settings'])]
class IssueTracker extends Model
{
    /** @use HasFactory<IssueTrackerFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'jira',
        'is_enabled' => true,
    ];

    /**
     * @return BelongsTo<TestProject, $this>
     */
    public function testProject(): BelongsTo
    {
        return $this->belongsTo(TestProject::class);
    }

    public function hasApiToken(): bool
    {
        return filled($this->settings['api_token'] ?? null);
    }

    /**
     * Enabled and credentialed, so create-from-fail can reach the host.
     */
    public function isUsable(): bool
    {
        return $this->is_enabled && $this->hasApiToken();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IssueTrackerType::class,
            'settings' => 'encrypted:array',
            'is_enabled' => 'boolean',
        ];
    }
}
