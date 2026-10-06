<?php

declare(strict_types=1);

// Directory entries are case-insensitive prefixes of the path: "config" would also drop
// Configuration/. The project's config/ holds no tracked files, so it needs no entry.
return [
    'directories' => [
        '.build',
        '.ddev',
        '.git',
        '.github',
        'bin',
        'build',
        'Documentation-GENERATED-temp',
        'public',
        'tailor-version-upload',
        'Tests',
        'var',
        'vendor',
    ],
    'files' => [
        'DS_Store',
        'CONTRIBUTING.md',
        'editorconfig',
        'gitattributes',
        'gitignore',
        'packaging_exclude.php',
        'php-cs-fixer.php',
        'php-cs-fixer.xml',
        'phpstan.neon',
        'phpstan.xml',
        'phpstan-baseline.neon',
        'phpunit.xml',
    ],
];
