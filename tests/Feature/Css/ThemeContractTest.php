<?php

namespace Tests\Feature\Css;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ThemeContractTest extends TestCase
{
    /**
     * @return list<string>
     */
    private function requiredTokens(): array
    {
        return [
            '--font-family-sans',
            '--background',
            '--foreground',
            '--card',
            '--card-foreground',
            '--popover',
            '--popover-foreground',
            '--primary',
            '--primary-foreground',
            '--secondary',
            '--secondary-foreground',
            '--muted',
            '--muted-foreground',
            '--accent',
            '--accent-foreground',
            '--destructive',
            '--destructive-foreground',
            '--success',
            '--success-foreground',
            '--warning',
            '--warning-foreground',
            '--info',
            '--info-foreground',
            '--border',
            '--input',
            '--ring',
            '--chart-1',
            '--chart-2',
            '--chart-3',
            '--chart-4',
            '--chart-5',
            '--radius',
            '--sidebar',
            '--sidebar-foreground',
            '--sidebar-primary',
            '--sidebar-primary-foreground',
            '--sidebar-accent',
            '--sidebar-accent-foreground',
            '--sidebar-border',
            '--sidebar-ring',
        ];
    }

    public function test_each_theme_defines_the_shared_token_contract(): void
    {
        $themes = File::glob(resource_path('css/themes/*.css'));

        $this->assertNotSame([], $themes);

        foreach ($themes as $path) {
            if (basename($path) === 'active.css') {
                continue;
            }

            $css = File::get($path);

            $this->assertStringContainsString(':root', $css, basename($path).' is missing :root');
            $this->assertStringContainsString('.dark', $css, basename($path).' is missing .dark');

            foreach ($this->requiredTokens() as $token) {
                $this->assertStringContainsString(
                    $token.':',
                    $css,
                    basename($path).' is missing '.$token,
                );
            }
        }
    }

    public function test_the_active_theme_imports_an_existing_theme_file(): void
    {
        $active = File::get(resource_path('css/themes/active.css'));

        $this->assertMatchesRegularExpression("/@import\\s+'\\.\\/[^']+\\.css'/", $active);

        preg_match("/@import\\s+'\\.\\/([^']+\\.css)'/", $active, $matches);

        $this->assertArrayHasKey(1, $matches);
        $this->assertFileExists(resource_path('css/themes/'.$matches[1]));
        $this->assertNotSame('active.css', $matches[1]);
    }
}
