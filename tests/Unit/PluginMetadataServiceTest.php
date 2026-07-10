<?php

use PelicanMarketplace\PluginMarketplace\Services\PluginMetadataService;

beforeEach(function () {
    $this->metadata = new PluginMetadataService();
});

it('parses a standard plugin.yml manifest', function () {
    $yaml = <<<'YAML'
    name: EssentialsX
    version: 2.21.2
    main: com.earth2me.essentials.Essentials
    api-version: '1.13'
    author: EssentialsX Team
    description: The essential plugin suite for Paper and Spigot.
    depend: [Vault]
    softdepend:
      - PlaceholderAPI
      - ProtocolLib
    YAML;

    $result = $this->metadata->parseManifest($yaml, 'Essentials.jar');

    expect($result)
        ->name->toBe('EssentialsX')
        ->version->toBe('2.21.2')
        ->main->toBe('com.earth2me.essentials.Essentials')
        ->api_version->toBe('1.13')
        ->description->toBe('The essential plugin suite for Paper and Spigot.')
        ->depend->toBe(['Vault'])
        ->softdepend->toBe(['PlaceholderAPI', 'ProtocolLib']);

    expect($result['authors'])->toContain('EssentialsX Team');
});

it('merges singular author and plural authors fields', function () {
    $yaml = <<<'YAML'
    name: Foo
    version: 1.0
    author: Alice
    authors: [Bob, Carol]
    YAML;

    $result = $this->metadata->parseManifest($yaml, 'Foo.jar');

    expect($result['authors'])->toEqual(['Bob', 'Carol', 'Alice']);
});

it('falls back to the jar filename when name is missing', function () {
    $yaml = "version: 1.0\nmain: com.example.Plugin\n";

    $result = $this->metadata->parseManifest($yaml, 'MyPlugin');

    expect($result['name'])->toBe('MyPlugin');
});

it('normalizes a list-form api-version to a single value', function () {
    $yaml = "name: Foo\nversion: 1.0\napi-version:\n  - '1.20'\n  - '1.21'\n";

    $result = $this->metadata->parseManifest($yaml, 'Foo.jar');

    expect($result['api_version'])->toBe('1.20');
});

it('returns null for unparsable yaml instead of throwing', function () {
    $result = $this->metadata->parseManifest("name: [unterminated\n  - broken", 'Foo.jar');

    expect($result)->toBeNull();
});

it('returns null when the manifest is not a mapping', function () {
    $result = $this->metadata->parseManifest("- just\n- a\n- list\n", 'Foo.jar');

    expect($result)->toBeNull();
});

it('extracts a manifest from inside a real jar (zip) file', function () {
    $tempJar = tempnam(sys_get_temp_dir(), 'pmtest') . '.jar';

    $zip = new ZipArchive();
    $zip->open($tempJar, ZipArchive::CREATE);
    $zip->addFromString('plugin.yml', "name: ZippedPlugin\nversion: 3.0.0\nmain: com.example.Zipped\n");
    $zip->addFromString('com/example/Zipped.class', 'not real bytecode, just a placeholder');
    $zip->close();

    try {
        $result = $this->metadata->extractFromJarPath($tempJar, 'fallback');

        expect($result)->not->toBeNull();
        expect($result['name'])->toBe('ZippedPlugin');
        expect($result['version'])->toBe('3.0.0');
    } finally {
        unlink($tempJar);
    }
});

it('prefers paper-plugin.yml when plugin.yml is absent', function () {
    $tempJar = tempnam(sys_get_temp_dir(), 'pmtest') . '.jar';

    $zip = new ZipArchive();
    $zip->open($tempJar, ZipArchive::CREATE);
    $zip->addFromString('paper-plugin.yml', "name: PaperOnly\nversion: 1.0.0\n");
    $zip->close();

    try {
        $result = $this->metadata->extractFromJarPath($tempJar, 'fallback');

        expect($result)->not->toBeNull();
        expect($result['name'])->toBe('PaperOnly');
    } finally {
        unlink($tempJar);
    }
});

it('returns null when the jar has no manifest at all', function () {
    $tempJar = tempnam(sys_get_temp_dir(), 'pmtest') . '.jar';

    $zip = new ZipArchive();
    $zip->open($tempJar, ZipArchive::CREATE);
    $zip->addFromString('com/example/NoManifest.class', 'placeholder');
    $zip->close();

    try {
        $result = $this->metadata->extractFromJarPath($tempJar, 'fallback');

        expect($result)->toBeNull();
    } finally {
        unlink($tempJar);
    }
});
