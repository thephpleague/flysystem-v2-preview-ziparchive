<?php

declare(strict_types=1);

namespace League\FlysystemV2Preview\ZipArchive;

use ZipArchive;

interface ZipArchiveProvider
{
    public function createZipArchive(): ZipArchive;
}
