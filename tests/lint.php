<?php

declare(strict_types=1);

$roots = [dirname(__DIR__) . '/src', __DIR__];
$failures = 0;

foreach ($roots as $root) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($iterator as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $command = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname());
        exec($command, $output, $status);
        if ($status !== 0) {
            $failures++;
            fwrite(STDERR, implode("\n", $output) . "\n");
        }
        $output = [];
    }
}

if ($failures > 0) {
    fwrite(STDERR, "{$failures} PHP files failed linting.\n");
    exit(1);
}

fwrite(STDOUT, "PHP lint passed.\n");
