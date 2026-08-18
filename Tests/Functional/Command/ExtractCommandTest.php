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
        $inputDir = __DIR__ . '/../../Translation/Extractor/Fixture/SimpleTest';
        $outputDir = $this->createTemporaryPath('extract');

        $output = $this->runExtract($inputDir, $outputDir);

        // What the command produced, which is the actual contract of the command.
        $files = FileUtils::findTranslationFiles($outputDir);
        $this->assertTrue(isset($files['messages']['en']));

        $contents = $this->getExtractedFileContents($outputDir);
        foreach (['php.foo', 'twig.bar', 'form.foo', 'controller.foo'] as $id) {
            $this->assertStringContainsString(sprintf('resname="%s"', $id), $contents);
        }

        // Only the parts of the report a caller relies on: the configuration it echoes
        // back, and the confirmation of what was written. Asserting the full output would
        // break on every rewording or newly reported option.
        $this->assertStringContainsString('Extracting Translations for locale en', $output->getContent());
        $this->assertStringContainsString('Output-Path: ' . $outputDir, $output->getContent());
        $this->assertStringContainsString('Directories: ' . $inputDir, $output->getContent());
        $this->assertStringContainsString(
            sprintf('Writing translation file "%s/messages.en.xlf".', $outputDir),
            $output->getContent()
        );
        $this->assertStringContainsString('done!', $output->getContent());
    }

    public function testExtractDryRun(): void
    {
        $inputDir = __DIR__ . '/../../Translation/Extractor/Fixture/SimpleTest';
        $outputDir = $this->createTemporaryPath('extract');

        $output = $this->runExtract($inputDir, $outputDir, ['--dry-run', '--verbose']);

        $expectedTranslations = [
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

        foreach ($expectedTranslations as $transID) {
            $this->assertStringContainsString($transID, $output->getContent());
        }

        // The whole point of --dry-run: report, but leave the filesystem alone.
        $this->assertFileDoesNotExist($outputDir . '/messages.en.xlf');
        $this->assertSame([], FileUtils::findTranslationFiles($outputDir));
        $this->assertStringNotContainsString('Writing translation file', $output->getContent());
    }

    /**
     * @param list<string> $extraArgs
     */
    #[DataProvider('provideForceCases')]
    public function testExtractAlwaysResyncsDescAndOnlyForceResyncsTarget(
        array $extraArgs,
        string $expectedTargetAfterChange,
        string $expectedReportedForce
    ): void {
        [$scanDir, $phpFile] = $this->createScanDirWithController('Original');
        $outputDir = $this->createTemporaryPath('extract_force_out');

        $this->runExtract($scanDir, $outputDir);

        $contents = $this->getExtractedFileContents($outputDir);
        $this->assertStringContainsString('<source>Original</source>', $contents);
        $this->assertMatchesRegularExpression('/<target[^>]*>Original<\/target>/', $contents);

        // Simulate a developer changing the default text (@Desc) in code.
        file_put_contents($phpFile, $this->getControllerFixture('Updated'));
        $output = $this->runExtract($scanDir, $outputDir, $extraArgs);

        $this->assertStringContainsString(
            'Force refresh: ' . $expectedReportedForce,
            $output->getContent(),
            'the command must report the force mode it runs in'
        );

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
     * @return iterable<string, array{list<string>, string, string}>
     */
    public static function provideForceCases(): iterable
    {
        yield 'without force, target is preserved' => [[], 'Original', 'No'];

        yield 'with force, target is resynced' => [['--force'], 'Updated', 'Yes'];
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
    private function runExtract(string $scanDir, string $outputDir, array $extraArgs = []): Output
    {
        $input = new ArgvInput(array_merge([
            'app/console',
            'jms:translation:extract',
            'en',
            '--dir=' . $scanDir,
            '--output-dir=' . $outputDir,
        ], $extraArgs));

        $output = new Output();
        $this->getApp()->run($input, $output);

        return $output;
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
