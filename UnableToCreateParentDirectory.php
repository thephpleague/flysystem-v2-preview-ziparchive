<?php

declare(strict_types=1);

namespace League\FlysystemV2Preview\ZipArchive;

use RuntimeException;

class UnableToCreateParentDirectory extends RuntimeException implements ZipArchiveException
{
    public static function atLocation(string $location, string $reason = ''): UnableToCreateParentDirectory
    {
        return new UnableToCreateParentDirectory(
            rtrim("Unable to create the parent directory ($location): $reason", ' :')
        );
    }
}
