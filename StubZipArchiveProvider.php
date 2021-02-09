<?php

declare(strict_types=1);

namespace League\FlysystemV2Preview\ZipArchive;

use ZipArchive;

class StubZipArchiveProvider implements ZipArchiveProvider
{
    /**
     * @var FilesystemZipArchiveProvider
     */
    private $provider;

    /**
     * @var string
     */
    private $filename;

    /**
     * @var StubZipArchive
     */
    private $archive;

    public function __construct(string $filename, int $localDirectoryPermissions = 0700)
    {
        $this->provider = new FilesystemZipArchiveProvider($filename, $localDirectoryPermissions);
        $this->filename = $filename;
    }

    public function createZipArchive(): ZipArchive
    {
        if ( ! $this->archive instanceof StubZipArchive) {
            $zipArchive = $this->provider->createZipArchive();
            $zipArchive->close();
            unset($zipArchive);
            $this->archive = new StubZipArchive();
        }

        $this->archive->open($this->filename, ZipArchive::CREATE);

        return $this->archive;
    }

    public function stubbedZipArchive(): StubZipArchive
    {
        $this->createZipArchive();

        return $this->archive;
    }
}
