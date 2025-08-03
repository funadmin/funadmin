<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\Command\XliffLintCommand;

/**
 * Tests the XliffLintCommand.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
class XliffLintCommandTest extends TestCase
{
    private array $files;

    public function testLintCorrectFile()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile();

        $tester->execute(
            ['filename' => $filename],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]
        );

        $tester->assertCommandIsSuccessful('Returns 0 in case of success');
        $this->assertStringContainsString('OK', trim($tester->getDisplay()));
    }

    public function testLintCorrectFiles()
    {
        $tester = $this->createCommandTester();
        $filename1 = $this->createFile();
        $filename2 = $this->createFile();

        $tester->execute(
            ['filename' => [$filename1, $filename2]],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]
        );

        $tester->assertCommandIsSuccessful('Returns 0 in case of success');
        $this->assertStringContainsString('OK', trim($tester->getDisplay()));
    }

    /**
     * @dataProvider provideStrictFilenames
     */
    public function testStrictFilenames($requireStrictFileNames, $fileNamePattern, $targetLanguage, $mustFail)
    {
        $tester = $this->createCommandTester($requireStrictFileNames);
        $filename = $this->createFile('note', $targetLanguage, $fileNamePattern);

        $tester->execute(
            ['filename' => $filename],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE, 'decorated' => false]
        );

        $this->assertEquals($mustFail ? 1 : 0, $tester->getStatusCode());
        $this->assertStringContainsString($mustFail ? '[WARNING] 0 XLIFF files have valid syntax and 1 contain errors.' : '[OK] All 1 XLIFF files contain valid syntax.', $tester->getDisplay());
    }

    public function testLintIncorrectXmlSyntax()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('note <target>');

        $tester->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertEquals(1, $tester->getStatusCode(), 'Returns 1 in case of error');
        $this->assertStringContainsString('Opening and ending tag mismatch: target line 6 and source', trim($tester->getDisplay()));
    }

    public function testLintIncorrectTargetLanguage()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('note', 'es');

        $tester->execute(['filename' => $filename], ['decorated' => false]);

        $this->assertEquals(1, $tester->getStatusCode(), 'Returns 1 in case of error');
        $this->assertStringContainsString('There is a mismatch between the language included in the file name ("messages.en.xlf") and the "es" value used in the "target-language" attribute of the file.', trim($tester->getDisplay()));
    }

    public function testLintTargetLanguageIsCaseInsensitive()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('note', 'zh-cn', 'messages.zh_CN.xlf');

        $tester->execute(['filename' => $filename], ['decorated' => false]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] All 1 XLIFF files contain valid syntax.', trim($tester->getDisplay()));
    }

    public function testLintSucceedsWhenLocaleInFileAndInTargetLanguageNameUsesDashesInsteadOfUnderscores()
    {
        $tester = $this->createCommandTester();
        $filename = $this->createFile('note', 'en-GB', 'messages.en-GB.xlf');

        $tester->execute(['filename' => $filename], ['decorated' => false]);

        $tester->assertCommandIsSuccessful();
        $this->assertStringContainsString('[OK] All 1 XLIFF files contain valid syntax.', trim($tester->getDisplay()));
    }

    public function testLintFileNotReadable()
    {
        $this->expectException(\RuntimeException::class);
        $tester = $this->createCommandTester();
        $filename = $this->createFile();
        unlink($filename);

        $tester->execute(['filename' => $filename], ['decorated' => false]);
    }

    public function testGetHelp()
    {
        $command = new XliffLintCommand();
        $expected = <<<EOF
Or of a whole directory:

  <info>php %command.full_name% dirname</info>
  <info>php %command.full_name% dirname --format=json</info>

EOF;

        $this->assertStringContainsString($expected, $command->getHelp());
    }

    public function testLintIncorrectFileWithGithubFormat()
    {
        $filename = $this->createFile('note <target>');
        $tester = $this->createCommandTester();
        $tester->execute(['filename' => [$filename], '--format' => 'github'], ['decorated' => false]);
        self::assertEquals(1, $tester->getStatusCode(), 'Returns 1 in case of error');
        self::assertStringMatchesFormat('%A::error file=%s,line=6,col=47::Opening and ending tag mismatch: target line 6 and source%A', trim($tester->getDisplay()));
    }

    public function testLintAutodetectsGithubActionEnvironment()
    {
        $prev = getenv('GITHUB_ACTIONS');
        putenv('GITHUB_ACTIONS');

        try {
            putenv('GITHUB_ACTIONS=1');

            $filename = $this->createFile('note <target>');
            $tester = $this->createCommandTester();

            $tester->execute(['filename' => [$filename]], ['decorated' => false]);
            self::assertStringMatchesFormat('%A::error file=%s,line=6,col=47::Opening and ending tag mismatch: target line 6 and source%A', trim($tester->getDisplay()));
        } finally {
            putenv('GITHUB_ACTIONS'.($prev ? "=$prev" : ''));
        }
    }

    public function testPassingClosureAndCallableToConstructor()
    {
        $command = new XliffLintCommand('translation:xliff:lint',
            $this->testPassingClosureAndCallableToConstructor(...),
            [$this, 'testPassingClosureAndCallableToConstructor']
        );

        self::assertInstanceOf(XliffLintCommand::class, $command);
    }

    private function createFile($sourceContent = 'note', $targetLanguage = 'en', $fileNamePattern = 'messages.%locale%.xlf'): string
    {
        $xliffContent = <<<XLIFF
<?xml version="1.0"?>
<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">
    <file source-language="en" target-language="$targetLanguage" datatype="plaintext" original="file.ext">
        <body>
            <trans-unit id="note">
                <source>$sourceContent</source>
                <target>NOTE</target>
            </trans-unit>
        </body>
    </file>
</xliff>
XLIFF;

        $filename = \sprintf('%s/translation-xliff-lint-test/%s', sys_get_temp_dir(), str_replace('%locale%', 'en', $fileNamePattern));
        file_put_contents($filename, $xliffContent);

        $this->files[] = $filename;

        return $filename;
    }

    private function createCommand($requireStrictFileNames = true, $application = null): Command
    {
        if (!$application) {
            $application = new Application();
            $application->add(new XliffLintCommand(null, null, null, $requireStrictFileNames));
        }

        $command = $application->find('lint:xliff');

        if ($application) {
            $command->setApplication($application);
        }

        return $command;
    }

    private function createCommandTester($requireStrictFileNames = true, $application = null): CommandTester
    {
        return new CommandTester($this->createCommand($requireStrictFileNames, $application));
    }

    protected function setUp(): void
    {
        $this->files = [];
        @mkdir(sys_get_temp_dir().'/translation-xliff-lint-test');
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        @rmdir(sys_get_temp_dir().'/translation-xliff-lint-test');
    }

    public static function provideStrictFilenames()
    {
        yield [false, 'messages.%locale%.xlf', 'en', false];
        yield [false, 'messages.%locale%.xlf', 'es', true];
        yield [false, '%locale%.messages.xlf', 'en', false];
        yield [false, '%locale%.messages.xlf', 'es', true];
        yield [true, 'messages.%locale%.xlf', 'en', false];
        yield [true, 'messages.%locale%.xlf', 'es', true];
        yield [true, '%locale%.messages.xlf', 'en', true];
        yield [true, '%locale%.messages.xlf', 'es', true];
    }

    /**
     * @dataProvider provideCompletionSuggestions
     */
    public function testComplete(array $input, array $expectedSuggestions)
    {
        $tester = new CommandCompletionTester($this->createCommand());

        $this->assertSame($expectedSuggestions, $tester->complete($input));
    }

    public static function provideCompletionSuggestions()
    {
        yield 'option' => [['--format', ''], ['txt', 'json', 'github']];
    }
}
