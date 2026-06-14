<?php

/**
 * Fail if PHP files are UTF-16 (common Windows editor mistake).
 * Usage: php scripts/check-php-encoding.php
 */

$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$bad = [];

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR)
        || str_contains($path, DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR)) {
        continue;
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        continue;
    }
    $bom = fread($handle, 2);
    fclose($handle);

    if ($bom === "\xFF\xFE" || $bom === "\xFE\xFF") {
        $bad[] = str_replace($root.DIRECTORY_SEPARATOR, '', $path);
    }
}

if ($bad !== []) {
    fwrite(STDERR, "UTF-16 encoded PHP files detected (fix with scripts/fix-utf8-php-files.php):\n");
    foreach ($bad as $path) {
        fwrite(STDERR, "  - {$path}\n");
    }
    exit(1);
}

echo "All PHP files pass UTF-8 encoding check.\n";
