<?php

namespace App\Models;

use App\Enums\CodeTrackerType;
use Database\Factories\CodeTrackerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Where a project's automation scripts live.
 *
 * One row per project. `view_url_template` builds a browse URL for a script
 * link; placeholders are `{base_url}`, `{project_key}`, `{repository}`,
 * `{path}`, `{branch}` and `{commit}`.
 *
 * @property int $id
 * @property int $test_project_id
 * @property string $name
 * @property CodeTrackerType $type
 * @property string $base_url
 * @property string|null $project_key
 * @property string|null $view_url_template
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestProject $testProject
 */
#[Fillable(['name', 'type', 'base_url', 'project_key', 'view_url_template', 'is_enabled'])]
class CodeTracker extends Model
{
    /** @use HasFactory<CodeTrackerFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'generic',
        'is_enabled' => true,
    ];

    /**
     * @return BelongsTo<TestProject, $this>
     */
    public function testProject(): BelongsTo
    {
        return $this->belongsTo(TestProject::class);
    }

    /**
     * A browse URL for one script, or null when the template is empty.
     */
    public function urlFor(TestCaseScriptLink $link): ?string
    {
        if ($this->view_url_template === null || $this->view_url_template === '') {
            return null;
        }

        $replacements = [
            '{base_url}' => rtrim($this->base_url, '/'),
            '{project_key}' => $link->project_key !== '' ? $link->project_key : (string) $this->project_key,
            '{repository}' => $link->repository,
            '{path}' => $link->path,
            '{branch}' => (string) $link->branch,
            '{commit}' => (string) $link->commit,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $this->view_url_template);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CodeTrackerType::class,
            'is_enabled' => 'boolean',
        ];
    }
}
