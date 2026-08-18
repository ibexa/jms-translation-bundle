<?php

declare(strict_types=1);

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\TranslationBundle\Tests\Functional\Command;

use JMS\TranslationBundle\Util\FileUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArgvInput;

class ExtractCommandTest extends BaseCommandTestCase
{
    /** @var list<string> */
    private array $temporaryPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryPaths as $path) {
            $this->removeRecursively($path);
        }

        $this->temporaryPaths = [];

        parent::tearDown();
    }

    public function testExtract(): void
    {
        $input = new ArgvInput([
            'app/console',
            'jms:translation:extract',
            'en',
            '--dir=' . $inputDir = __DIR__ . '/../../Translation/Extractor/Fixture/SimpleTest',
            '--output-dir=' . ($outputDir = $this->createTemporaryPath('extract')),
        ]);

        $expectedOutput =
            'Extracting Translations for locale en' . "\n"
           . 'Keep old translations: No' . "\n"
           . 'Force refresh: No' . "\n"
           . 'Output-Path: ' . $outputDir . "\n"
           . 'Directories: ' . $inputDir . "\n"
           . 'Excluded Directories: Tests' . "\n"
           . 'Excluded Names: *Test.php, *TestCase.php' . "\n"
           . 'Output-Format: # whatever is present, if nothing then xlf #' . "\n"
           . 'Custom Extractors: # none #' . "\n"
           . '============================================================' . "\n"
           . 'Loading catalogues from "' . $outputDir . '"' . "\n"
           . 'Extracting translation keys' . "\n"
           . 'Extracting messages from directory : ' . $inputDir . "\n"
           . 'Writing translation file "' . $outputDir . '/messages.en.xlf".' . "\n"
           . 'done!' . "\n";

        $this->getApp()->run($input, $output = new Output());
        $this->assertEquals($expectedOutput, $output->getContent());

        $files = FileUtils::findTranslationFiles($outputDir);
        $this->assertTrue(isset($files['messages']['en']));
    }

    public function testExtractDryRun(): void
    {
        $input = new ArgvInput([
            'app/console',
            'jms:translation:extract',
            'en',
            '--dir=' . $inputDir = __DIR__ . '/../../Translation/Extractor/Fixture/SimpleTest',
            '--output-dir=' . ($outputDir = $this->createTemporaryPath('extract')),
            '--dry-run',
            '--verbose',
        ]);

        $expectedOutput = [
            'php.foo->',
            'php.bar-> Bar',
            'php.baz->',
            'php.foo_bar-> Foo',
            'twig.foo->',
            'twig.bar-> Bar',
            'twig.baz->',
            'twig.foo_bar-> Foo',
            'form.foo->',
            'form.bar->',
            'controller.foo-> Foo',
        ];

        $this->getApp()->run($input, $output = new Output());

        foreach ($expectedOutput as $transID) {
            $this->assertStringContainsString($transID, $output->getContent());
        }
    }

    /**
     * @param list<string> $extraArgs
     */
    #[DataProvider('provideForceCases')]
    public function testExtractAlwaysResyncsDescAndOnlyForceResyncsTarget(
        array $extraArgs,
        string $expectedTargetAfterChange
    ): void {
        [$scanDir, $phpFile] = $this->createScanDirWithController('Original');
        $outputDir = $this->createTemporaryPath('extract_force_out');

        $this->runExtract($scanDir, $outputDir);

        $contents = $this->getExtractedFileContents($outputDir);
        $this->assertStringContainsString('<source>Original</source>', $contents);
        $this->assertMatchesRegularExpression('/<target[^>]*>Original<\/target>/', $contents);

        // Simulate a developer changing the default text (@Desc) in code.
        file_put_contents($phpFile, $this->getControllerFixture('Updated'));
        $this->runExtract($scanDir, $outputDir, $extraArgs);

        $contents = $this->getExtractedFileContents($outputDir);
        $this->assertStringContainsString(
            '<source>Updated</source>',
            $contents,
            'desc must always be resynced from the scan, regardless of --force'
        );
        $this->assertMatchesRegularExpression(
            sprintf('/<target[^>]*>%s<\/target>/', $expectedTargetAfterChange),
            $contents,
            sprintf('target must be "%s" after re-extracting', $expectedTargetAfterChange)
        );
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function provideForceCases(): iterable
    {
        yield 'without force, target is preserved' => [[], 'Original'];

        yield 'with force, target is resynced' => [['--force'], 'Updated'];
    }

    /**
     * @return array{string, string}
     */
    private function createScanDirWithController(string $desc): array
    {
        $scanDir = $this->createTemporaryPath('extract_force_src');
        mkdir($scanDir, 0777, true);

        $phpFile = $scanDir . '/Controller.php';
        file_put_contents($phpFile, $this->getControllerFixture($desc));

        return [$scanDir, $phpFile];
    }

    private function getExtractedFileContents(string $outputDir): string
    {
        $file = $outputDir . '/messages.en.xlf';
        $this->assertFileExists($file);

        $contents = file_get_contents($file);
        $this->assertNotFalse($contents);

        return $contents;
    }

    private function getControllerFixture(string $desc): string
    {
        return <<<PHP
        <?php

        class ForceTestController
        {
            private \$translator;

            public function indexAction()
            {
                return /** @Desc("{$desc}") */ \$this->translator->trans('force.foo');
            }
        }

        PHP;
    }

    /**
     * @param list<string> $extraArgs
     */
    private function runExtract(string $scanDir, string $outputDir, array $extraArgs = []): void
    {
        $input = new ArgvInput(array_merge([
            'app/console',
            'jms:translation:extract',
            'en',
            '--dir=' . $scanDir,
            '--output-dir=' . $outputDir,
        ], $extraArgs));

        $this->getApp()->run($input, new Output());
    }

    private function createTemporaryPath(string $prefix): string
    {
        $path = sys_get_temp_dir() . '/' . uniqid($prefix);
        $this->temporaryPaths[] = $path;

        return $path;
    }

    private function removeRecursively(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (!is_dir($path)) {
            unlink($path);

            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removeRecursively($path . '/' . $entry);
        }

        rmdir($path);
    }
}
