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
use Symfony\Component\Console\Input\ArgvInput;

class ExtractCommandTest extends BaseCommandTestCase
{
    public function testExtract()
    {
        $input = new ArgvInput([
            'app/console',
            'translation:extract',
            'en',
            '--dir=' . $inputDir = __DIR__ . '/../../Translation/Extractor/Fixture/SimpleTest',
            '--output-dir=' . ($outputDir = sys_get_temp_dir() . '/' . uniqid('extract')),
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

    public function testExtractDryRun()
    {
        $input = new ArgvInput([
            'app/console',
            'translation:extract',
            'en',
            '--dir=' . $inputDir = __DIR__ . '/../../Translation/Extractor/Fixture/SimpleTest',
            '--output-dir=' . ($outputDir = sys_get_temp_dir() . '/' . uniqid('extract')),
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

    public function testExtractRefreshesDescButKeepsTargetWithoutForce(): void
    {
        $scanDir = sys_get_temp_dir() . '/' . uniqid('extract_force_src');
        mkdir($scanDir, 0777, true);
        $outputDir = sys_get_temp_dir() . '/' . uniqid('extract_force_out');

        $phpFile = $scanDir . '/Controller.php';
        file_put_contents($phpFile, $this->getControllerFixture('Original'));

        $this->runExtract($scanDir, $outputDir);

        $contents = file_get_contents($outputDir . '/messages.en.xlf');
        $this->assertStringContainsString('<source>Original</source>', $contents);
        $this->assertMatchesRegularExpression('/<target[^>]*>Original<\/target>/', $contents);

        // Simulate a developer changing the default text (@Desc) in code.
        file_put_contents($phpFile, $this->getControllerFixture('Updated'));
        $this->runExtract($scanDir, $outputDir);

        $contents = file_get_contents($outputDir . '/messages.en.xlf');
        $this->assertStringContainsString(
            '<source>Updated</source>',
            $contents,
            'desc/source must always be resynced from the scan, even without --force'
        );
        $this->assertMatchesRegularExpression(
            '/<target[^>]*>Original<\/target>/',
            $contents,
            'target must be preserved without --force'
        );
    }

    public function testExtractForceRefreshesTarget(): void
    {
        $scanDir = sys_get_temp_dir() . '/' . uniqid('extract_force_src');
        mkdir($scanDir, 0777, true);
        $outputDir = sys_get_temp_dir() . '/' . uniqid('extract_force_out');

        $phpFile = $scanDir . '/Controller.php';
        file_put_contents($phpFile, $this->getControllerFixture('Original'));

        $this->runExtract($scanDir, $outputDir);

        file_put_contents($phpFile, $this->getControllerFixture('Updated'));
        $this->runExtract($scanDir, $outputDir, ['--force']);

        $contents = file_get_contents($outputDir . '/messages.en.xlf');
        $this->assertStringContainsString('<source>Updated</source>', $contents);
        $this->assertMatchesRegularExpression(
            '/<target[^>]*>Updated<\/target>/',
            $contents,
            '--force must resync the target too'
        );
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

    private function runExtract(string $scanDir, string $outputDir, array $extraArgs = []): void
    {
        $input = new ArgvInput(array_merge([
            'app/console',
            'translation:extract',
            'en',
            '--dir=' . $scanDir,
            '--output-dir=' . $outputDir,
        ], $extraArgs));

        $this->getApp()->run($input, new Output());
    }
}
