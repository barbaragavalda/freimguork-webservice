<?php

// a bare dev-branch sibling constraint (no tagged-version fallback) blocks
// pinning this package's own tag alongside it

$composerJsonPath = __DIR__ . '/../composer.json';
$composer          = json_decode(file_get_contents($composerJsonPath), true);

$errors = array();
foreach (($composer['require'] ?? array()) as $package => $constraint) {
    if (!str_starts_with($package, 'optisistem/freimguork-')) {
        continue;
    }

    if (preg_match('/^\s*dev-[\w.\/-]+\s*$/', $constraint)) {
        $errors[] = "$package: \"$constraint\" is a bare dev-branch constraint with no "
            . "tagged-version fallback (e.g. \"$constraint || ^2.0\")";
    }
}

if ($errors) {
    fwrite(STDERR, "Sibling constraint check failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - $error\n");
    }
    exit(1);
}

echo "Sibling constraint check passed.\n";
