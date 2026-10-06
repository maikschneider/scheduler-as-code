<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\Configuration;

use MaikSchneider\SchedulerAsCode\Configuration\InvalidTaskDefinitionException;
use MaikSchneider\SchedulerAsCode\Configuration\TaskDefinitionLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class TaskDefinitionLoaderTest extends UnitTestCase
{
    private const FIXTURES = __DIR__ . '/Fixtures/';

    #[Test]
    public function loadsYamlAndYmlFilesKeyedByFileName(): void
    {
        $definitions = (new TaskDefinitionLoader())->loadFromDirectory(self::FIXTURES . 'valid');

        self::assertSame(['cleanup-deleted', 'optimize_tables'], array_keys($definitions));
        self::assertSame('cleanup:deletedrecords', $definitions['cleanup-deleted']->configuration['type']);
        self::assertSame(['options' => ['min-age' => 30]], $definitions['cleanup-deleted']->configuration['parameters']);
        self::assertSame(self::FIXTURES . 'valid/optimize_tables.yml', $definitions['optimize_tables']->sourceFile);
    }

    #[Test]
    public function missingDirectoryYieldsNoDefinitions(): void
    {
        self::assertSame([], (new TaskDefinitionLoader())->loadFromDirectory(self::FIXTURES . 'does-not-exist'));
    }

    #[Test]
    public function sameIdentifierInYamlAndYmlIsRejected(): void
    {
        $this->expectException(InvalidTaskDefinitionException::class);
        $this->expectExceptionCode(1791360001);

        (new TaskDefinitionLoader())->loadFromDirectory(self::FIXTURES . 'duplicate');
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function invalidDirectoryDataProvider(): array
    {
        return [
            'file name is not an identifier' => ['invalid-name', 1791360002],
            'YAML syntax error' => ['invalid-yaml', 1791360003],
            'list instead of mapping' => ['list', 1791360004],
            'no type' => ['missing-type', 1791360005],
            'neither frequency nor start' => ['missing-execution', 1791360006],
            'parameters is not a mapping' => ['invalid-parameters', 1791360007],
        ];
    }

    #[Test]
    #[DataProvider('invalidDirectoryDataProvider')]
    public function invalidFilesAreRejected(string $directory, int $expectedCode): void
    {
        $this->expectException(InvalidTaskDefinitionException::class);
        $this->expectExceptionCode($expectedCode);

        (new TaskDefinitionLoader())->loadFromDirectory(self::FIXTURES . $directory);
    }
}
