<?php

declare(strict_types=1);

namespace League\FlysystemV2Preview\ZipArchive;

use Generator;
use League\FlysystemV2Preview\AdapterTestUtilities\FilesystemAdapterTestCase;
use League\FlysystemV2Preview\Config;
use League\FlysystemV2Preview\FilesystemAdapter;
use League\FlysystemV2Preview\UnableToCopyFile;
use League\FlysystemV2Preview\UnableToCreateDirectory;
use League\FlysystemV2Preview\UnableToDeleteDirectory;
use League\FlysystemV2Preview\UnableToDeleteFile;
use League\FlysystemV2Preview\UnableToMoveFile;
use League\FlysystemV2Preview\UnableToSetVisibility;
use League\FlysystemV2Preview\UnableToWriteFile;
use League\FlysystemV2Preview\Visibility;

/**
 * @group zip
 */
final class ZipArchiveAdapterTest extends FilesystemAdapterTestCase
{
    private const ARCHIVE = __DIR__ . '/test.zip';

    /**
     * @var StubZipArchiveProvider
     */
    private static $archiveProvider;

    protected function setUp(): void
    {
        static::$adapter = static::createFilesystemAdapter();
        static::removeZipArchive();
        parent::setUp();
    }

    public static function tearDownAfterClass(): void
    {
        static::removeZipArchive();
    }

    protected function tearDown(): void
    {
        static::removeZipArchive();
    }

    protected static function createFilesystemAdapter(): FilesystemAdapter
    {
        static::$archiveProvider = new StubZipArchiveProvider(self::ARCHIVE);

        return new ZipArchiveAdapter(self::$archiveProvider, '/path-prefix');
    }

    /**
     * @test
     */
    public function not_being_able_to_create_the_parent_directory(): void
    {
        $this->expectException(UnableToCreateParentDirectory::class);

        (new ZipArchiveAdapter(new StubZipArchiveProvider('/no-way/this/will/work')))
            ->write('haha', 'lol', new Config());
    }

    /**
     * @test
     */
    public function not_being_able_to_write_a_file_because_the_parent_directory_could_not_be_created(): void
    {
        self::$archiveProvider->stubbedZipArchive()->failNextDirectoryCreation();

        $this->expectException(UnableToWriteFile::class);

        $this->adapter()->write('directoryName/is-here/filename.txt', 'contents', new Config());
    }

    /**
     * @test
     * @dataProvider scenariosThatCauseWritesToFail
     */
    public function scenarios_that_cause_writing_a_file_to_fail(callable $scenario): void
    {
        $this->runScenario($scenario);

        $this->expectException(UnableToWriteFile::class);

        $this->runScenario(function () {
            $handle = stream_with_contents('contents');
            $this->adapter()->writeStream('some/path.txt', $handle, new Config([Config::OPTION_VISIBILITY => Visibility::PUBLIC]));
            is_resource($handle) && @fclose($handle);
        });
    }

    public function scenariosThatCauseWritesToFail(): Generator
    {
        yield "writing a file fails when writing" => [function () {
            static::$archiveProvider->stubbedZipArchive()->failNextWrite();
        }];

        yield "writing a file fails when setting visibility" => [function () {
            static::$archiveProvider->stubbedZipArchive()->failWhenSettingVisibility();
        }];

        yield "writing a file fails to get the stream contents" => [function () {
            mock_function('stream_get_contents', false);
        }];
    }

    /**
     * @test
     */
    public function failing_to_delete_a_file(): void
    {
        $this->givenWeHaveAnExistingFile('path.txt');
        static::$archiveProvider->stubbedZipArchive()->failNextDeleteName();
        $this->expectException(UnableToDeleteFile::class);

        $this->adapter()->delete('path.txt');
    }

    /**
     * @test
     */
    public function deleting_a_directory(): void
    {
        $this->givenWeHaveAnExistingFile('a.txt');
        $this->givenWeHaveAnExistingFile('one/a.txt');
        $this->givenWeHaveAnExistingFile('one/b.txt');
        $this->givenWeHaveAnExistingFile('two/a.txt');

        $items = iterator_to_array($this->adapter()->listContents('', true));
        $this->assertCount(6, $items);

        $this->adapter()->deleteDirectory('one');

        $items = iterator_to_array($this->adapter()->listContents('', true));
        $this->assertCount(4, $items);
    }

    /**
     * @test
     */
    public function failing_to_create_a_directory(): void
    {
        static::$archiveProvider->stubbedZipArchive()->failNextDirectoryCreation();

        $this->expectException(UnableToCreateDirectory::class);

        $this->adapter()->createDirectory('somewhere', new Config);
    }

    /**
     * @test
     */
    public function failing_to_create_a_directory_because_setting_visibility_fails(): void
    {
        static::$archiveProvider->stubbedZipArchive()->failWhenSettingVisibility();

        $this->expectException(UnableToCreateDirectory::class);

        $this->adapter()->createDirectory('somewhere', new Config([Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PRIVATE]));
    }

    /**
     * @test
     */
    public function failing_to_delete_a_directory(): void
    {
        static::$archiveProvider->stubbedZipArchive()->failWhenDeletingAnIndex();

        $this->givenWeHaveAnExistingFile('here/path.txt');

        $this->expectException(UnableToDeleteDirectory::class);

        $this->adapter()->deleteDirectory('here');
    }

    /**
     * @test
     */
    public function setting_visibility_on_a_directory(): void
    {
        $adapter = $this->adapter();
        $adapter->createDirectory('pri-dir', new Config([Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PRIVATE]));
        $adapter->createDirectory('pub-dir', new Config([Config::OPTION_DIRECTORY_VISIBILITY => Visibility::PUBLIC]));

        $this->expectNotToPerformAssertions();
    }

    /**
     * @test
     */
    public function failing_to_move_a_file(): void
    {
        $this->givenWeHaveAnExistingFile('somewhere/here.txt');

        static::$archiveProvider->stubbedZipArchive()->failNextDirectoryCreation();

        $this->expectException(UnableToMoveFile::class);

        $this->adapter()->move('somewhere/here.txt', 'to-here/path.txt', new Config);
    }

    /**
     * @test
     */
    public function failing_to_copy_a_file(): void
    {
        $this->givenWeHaveAnExistingFile('here.txt');

        static::$archiveProvider->stubbedZipArchive()->failNextWrite();

        $this->expectException(UnableToCopyFile::class);

        $this->adapter()->copy('here.txt', 'here.txt', new Config);
    }

    /**
     * @test
     */
    public function failing_to_set_visibility_because_the_file_does_not_exist(): void
    {
        $this->expectException(UnableToSetVisibility::class);

        $this->adapter()->setVisibility('path.txt', Visibility::PUBLIC);
    }

    /**
     * @test
     */
    public function failing_to_set_visibility_because_setting_it_fails(): void
    {
        $this->givenWeHaveAnExistingFile('path.txt');
        static::$archiveProvider->stubbedZipArchive()->failWhenSettingVisibility();

        $this->expectException(UnableToSetVisibility::class);

        $this->adapter()->setVisibility('path.txt', Visibility::PUBLIC);
    }

    protected static function removeZipArchive(): void
    {
        if ( ! file_exists(self::ARCHIVE)) {
            return;
        }

        unlink(self::ARCHIVE);
    }
}
