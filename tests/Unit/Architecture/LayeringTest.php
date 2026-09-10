<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class LayeringTest extends TestCase
{
    public function test_no_model_is_queried_outside_the_eloquent_repositories(): void
    {
        $appPath = dirname(__DIR__, 3).'/app';

        $models = array_map(
            static fn (string $file): string => basename($file, '.php'),
            glob($appPath.'/Models/*.php') ?: [],
        );

        $this->assertNotEmpty($models, 'No models found to check against.');

        $allowedPrefixes = [
            $appPath.'/Repositories/Eloquent',
            $appPath.'/Models',
        ];

        $offenders = [];

        /** @var \SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appPath)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            foreach ($allowedPrefixes as $prefix) {
                if (str_starts_with($file->getPathname(), $prefix)) {
                    continue 2;
                }
            }

            $contents = (string) file_get_contents($file->getPathname());

            foreach ($models as $model) {
                // ::class is a reference, not a query, so it is allowed.
                if (preg_match('/\b'.preg_quote($model, '/').'::(?!class\b)/', $contents)) {
                    $offenders[] = str_replace($appPath, 'app', $file->getPathname()).' uses '.$model.'::';
                }
            }
        }

        $this->assertSame([], $offenders, "Model queries found outside the repositories:\n".implode("\n", $offenders));
    }
}
