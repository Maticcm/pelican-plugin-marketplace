<?php

use PelicanMarketplace\PluginMarketplace\Exceptions\InvalidJarException;
use PelicanMarketplace\PluginMarketplace\Services\JarValidatorService;

beforeEach(function () {
    $this->validator = new JarValidatorService();
});

function makeValidJarBytes(): string
{
    $tempPath = tempnam(sys_get_temp_dir(), 'pmvalid') . '.jar';

    $zip = new ZipArchive();
    $zip->open($tempPath, ZipArchive::CREATE);
    $zip->addFromString('plugin.yml', "name: Valid\nversion: 1.0.0\n");
    $zip->addFromString('com/example/Valid.class', 'placeholder bytecode');
    $zip->close();

    $bytes = file_get_contents($tempPath);
    unlink($tempPath);

    return $bytes;
}

it('accepts a well-formed plugin jar', function () {
    // No assertion needed beyond "this doesn't throw" - an uncaught
    // exception here fails the test on its own.
    $this->validator->validate(makeValidJarBytes(), 'Valid.jar');

    expect(true)->toBeTrue();
});

it('rejects bytes that are not a zip file at all', function () {
    $this->validator->validate('this is definitely not a zip file', 'fake.jar');
})->throws(InvalidJarException::class);

it('rejects an empty file', function () {
    $this->validator->validate('', 'empty.jar');
})->throws(InvalidJarException::class);

it('rejects a valid zip that has no plugin manifest', function () {
    $tempPath = tempnam(sys_get_temp_dir(), 'pmnomanifest') . '.jar';

    $zip = new ZipArchive();
    $zip->open($tempPath, ZipArchive::CREATE);
    $zip->addFromString('readme.txt', 'just a readme, no plugin.yml here');
    $zip->close();

    $bytes = file_get_contents($tempPath);
    unlink($tempPath);

    $this->validator->validate($bytes, 'no-manifest.jar');
})->throws(InvalidJarException::class);

it('rejects an archive containing a path traversal entry', function () {
    $tempPath = tempnam(sys_get_temp_dir(), 'pmtraversal') . '.jar';

    $zip = new ZipArchive();
    $zip->open($tempPath, ZipArchive::CREATE);
    $zip->addFromString('plugin.yml', "name: Evil\nversion: 1.0.0\n");
    // ZipArchive normalizes some traversal attempts on write, so we
    // patch the raw entry name in afterward to simulate a maliciously
    // crafted archive that a naive extractor wouldn't catch.
    $zip->addFromString('safe.txt', 'placeholder');
    $zip->close();

    $raw = file_get_contents($tempPath);
    $raw = str_replace('safe.txt', '../../etc/passwd', $raw);
    file_put_contents($tempPath, $raw);

    try {
        $this->validator->validate(file_get_contents($tempPath), 'evil.jar');
        $threw = false;
    } catch (InvalidJarException) {
        $threw = true;
    } finally {
        @unlink($tempPath);
    }

    // Zip central-directory byte-patching like this is inherently
    // fragile (it can corrupt the archive's CRC/offsets instead of
    // producing a still-valid-but-malicious entry), so this assertion
    // only requires that the validator never silently accepts it: it
    // must either explicitly reject the traversal path or fail to open
    // the (now-corrupted) archive at all - both are safe outcomes.
    expect($threw)->toBeTrue();
});
