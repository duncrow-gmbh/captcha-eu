<?php

declare(strict_types=1);

/*
 * Regenerates public/manifest.json with a content hash for every public asset.
 *
 * Contao uses the manifest as version strategy for the bundle's asset package, so
 * asset('email-disguise.js', 'duncrow_gmbh_captcha_eu') becomes "/bundles/duncrowgmbhcaptchaeu/email-disguise.js?v=<hash>"
 * and browsers load the new file after an update. Run after changing a file in public/:
 *
 *     composer manifest
 */

$publicDir = dirname(__DIR__).'/public';
$manifest = [];

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDir, FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    if (!$file->isFile() || 'manifest.json' === $file->getFilename()) {
        continue;
    }

    $path = str_replace('\\', '/', substr($file->getPathname(), strlen($publicDir) + 1));
    $manifest[$path] = $path.'?v='.substr(hash_file('sha256', $file->getPathname()), 0, 8);
}

ksort($manifest);

file_put_contents($publicDir.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

echo 'Updated public/manifest.json ('.count($manifest)." files)\n";
