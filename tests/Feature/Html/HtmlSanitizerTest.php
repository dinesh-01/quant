<?php

namespace Tests\Feature\Html;

use App\Actions\Html\HtmlSanitizer;
use App\Enums\Ability;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class HtmlSanitizerTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    /**
     * Legacy stored raw CKEditor output, so whoever could write a test case
     * could run script in the browser of everyone who later read it.
     */
    #[DataProvider('scriptVectors')]
    public function test_script_never_survives_sanitising(string $dangerous)
    {
        $clean = (string) $this->sanitizer()->sanitize($dangerous);

        $this->assertStringNotContainsStringIgnoringCase('script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onload', $clean);
        $this->assertStringNotContainsStringIgnoringCase('alert', $clean);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function scriptVectors(): array
    {
        return [
            'inline script' => ['<script>alert(1)</script>'],
            'image error handler' => ['<img src=x onerror="alert(1)">'],
            'body load handler' => ['<body onload="alert(1)">text</body>'],
            'javascript href' => ['<a href="javascript:alert(1)">click</a>'],
            'svg handler' => ['<svg onload="alert(1)"></svg>'],
            'iframe' => ['<iframe src="javascript:alert(1)"></iframe>'],
            'style block' => ['<style>body{background:url("javascript:alert(1)")}</style>'],
            'encoded javascript href' => ['<a href="java&#115;cript:alert(1)">click</a>'],
        ];
    }

    /**
     * The reason this package was chosen over `symfony/html-sanitizer`, which
     * can only allow or drop `style` wholesale. These declarations let a test
     * case float an element over the surrounding application and take clicks
     * meant for it, or hide content from a reviewer while leaving it in the
     * document.
     */
    #[DataProvider('dangerousDeclarations')]
    public function test_layout_escaping_css_is_stripped(string $property)
    {
        $clean = (string) $this->sanitizer()->sanitize(
            '<p style="'.$property.'">Innocent looking text</p>',
        );

        $this->assertStringNotContainsString($property, $clean);
        $this->assertStringContainsString('Innocent looking text', $clean);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function dangerousDeclarations(): array
    {
        return [
            'fixed positioning' => ['position:fixed'],
            'absolute positioning' => ['position:absolute'],
            'stacking order' => ['z-index:9999'],
            'hiding by display' => ['display:none'],
            'hiding by visibility' => ['visibility:hidden'],
            'hiding by opacity' => ['opacity:0'],
        ];
    }

    /**
     * The other half of the trade: keeping permissive formatting is the whole
     * reason for the heavier dependency, so gutting legacy content would defeat
     * the point.
     */
    public function test_presentational_markup_and_css_survive()
    {
        $clean = (string) $this->sanitizer()->sanitize(
            '<h2>Preconditions</h2>'
            .'<p style="color:#c00;text-align:center">A <strong>signed in</strong> user</p>'
            .'<table><tr><th>Input</th><td style="background-color:#eee">Value</td></tr></table>'
            .'<ul><li>First</li><li>Second</li></ul>'
            .'<img src="https://example.test/diagram.png" alt="Diagram" width="200">',
        );

        foreach ([
            '<h2>', '<strong>', '<table>', '<th>', '<td', '<ul>', '<li>', '<img',
            'color:#c00', 'text-align:center', 'background-color:#eee',
            'https://example.test/diagram.png', 'Diagram',
        ] as $expected) {
            $this->assertStringContainsString($expected, $clean);
        }
    }

    /**
     * A `data:text/html` or SVG payload is script execution wearing an image's
     * clothes. The cost is that a legacy inline base64 image is dropped, which
     * is the one place this profile is not permissive.
     */
    public function test_data_uris_are_refused()
    {
        $clean = (string) $this->sanitizer()->sanitize(
            '<img src="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">',
        );

        $this->assertStringNotContainsString('data:', $clean);
    }

    /**
     * A test case is written by one team and read by another, so a link out of
     * it is untrusted and must not reach back through `window.opener`.
     */
    public function test_outbound_links_cannot_reach_back_through_the_opener()
    {
        $clean = (string) $this->sanitizer()->sanitize('<a href="https://example.test">Report</a>');

        $this->assertStringContainsString('noopener', $clean);
        $this->assertStringContainsString('nofollow', $clean);
    }

    /**
     * On, HTMLPurifier rewrites plain text into paragraphs, which changes
     * content on every save and makes the audit trail show edits nobody made.
     */
    public function test_plain_text_is_not_rewritten_into_paragraphs()
    {
        $this->assertSame('Just a sentence.', $this->sanitizer()->sanitize('Just a sentence.'));
    }

    public function test_an_empty_field_stays_empty()
    {
        $this->assertNull($this->sanitizer()->sanitize(null));
        $this->assertSame('', $this->sanitizer()->sanitize(''));
    }

    /**
     * The cast is what makes "no row holds unsafe markup" a property of the
     * data rather than something each write path has to remember. These go
     * straight through the model, bypassing the actions entirely.
     */
    public function test_no_write_path_can_store_unsafe_markup()
    {
        $suite = TestSuite::factory()->create([
            'description' => '<p onclick="alert(1)">Suite</p><script>alert(1)</script>',
        ]);

        $version = TestCaseVersion::factory()->create([
            'summary' => '<img src=x onerror="alert(1)">',
            'preconditions' => '<p style="position:fixed">Overlay</p>',
        ]);

        $step = TestCaseStep::factory()->create([
            'actions' => '<a href="javascript:alert(1)">Go</a>',
            'expected_results' => '<script>alert(1)</script>Passed',
        ]);

        foreach ([
            $suite->fresh()->description,
            $version->fresh()->summary,
            $version->fresh()->preconditions,
            $step->fresh()->actions,
            $step->fresh()->expected_results,
        ] as $stored) {
            $this->assertStringNotContainsStringIgnoringCase('script', (string) $stored);
            $this->assertStringNotContainsStringIgnoringCase('onerror', (string) $stored);
            $this->assertStringNotContainsStringIgnoringCase('onclick', (string) $stored);
            $this->assertStringNotContainsString('position:fixed', (string) $stored);
        }

        $this->assertStringContainsString('Passed', (string) $step->fresh()->expected_results);
    }

    /**
     * The editing screens post markup rather than plain text, and the panes
     * render what comes back with `dangerouslySetInnerHTML`. That is only safe
     * while the round trip through a real request cleans it, so this asserts
     * the whole path rather than the cast alone: formatting a user typed
     * survives, and anything executable does not.
     */
    public function test_markup_posted_through_a_write_endpoint_is_stored_sanitised()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $version = TestCaseModel::factory()
            ->withVersion()
            ->create(['test_project_id' => $project->id])
            ->versions()
            ->sole();

        $this->actingAs($user)
            ->put(route('test-case-versions.update', $version), [
                'summary' => '<p><strong>Keep this</strong></p><script>alert(1)</script>',
                'preconditions' => '<p style="position:fixed;color:#c00">Overlay</p>',
                'status' => TestCaseStatus::Draft->value,
                'importance' => TestCaseImportance::Medium->value,
                'execution_type' => TestCaseExecutionType::Manual->value,
                'estimated_duration' => null,
            ])
            ->assertSessionHasNoErrors();

        $version->refresh();

        $this->assertStringContainsString('<strong>Keep this</strong>', (string) $version->summary);
        $this->assertStringNotContainsStringIgnoringCase('script', (string) $version->summary);

        $this->assertStringContainsString('color:#c00', (string) $version->preconditions);
        $this->assertStringNotContainsString('position', (string) $version->preconditions);
    }

    /**
     * An emptied editor submits an empty string rather than the `<p></p>`
     * ProseMirror holds internally, and these columns are nullable — so
     * clearing a field has to leave nothing behind, not a paragraph that every
     * screen would then treat as content.
     */
    public function test_clearing_a_rich_text_field_stores_nothing()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create(['description' => '<p>Written</p>']);

        $this->actingAs($user)
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'description' => '',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($suite->refresh()->description);
    }

    private function sanitizer(): HtmlSanitizer
    {
        return app(HtmlSanitizer::class);
    }
}
