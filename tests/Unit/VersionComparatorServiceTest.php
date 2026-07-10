<?php

use PelicanMarketplace\PluginMarketplace\Services\VersionComparatorService;

beforeEach(function () {
    $this->comparator = new VersionComparatorService();
});

it('detects a newer semantic version', function () {
    expect($this->comparator->isNewer('1.2.0', '1.1.9'))->toBeTrue();
    expect($this->comparator->isNewer('2.0.0', '1.9.9'))->toBeTrue();
    expect($this->comparator->isNewer('1.1.9', '1.2.0'))->toBeFalse();
});

it('treats equal versions as not newer', function () {
    expect($this->comparator->isNewer('1.2.3', '1.2.3'))->toBeFalse();
});

it('strips common version prefixes before comparing', function () {
    expect($this->comparator->isNewer('v2.0.1', '2.0.0'))->toBeTrue();
    expect($this->comparator->isNewer('Version 2.0.1', '2.0.0'))->toBeTrue();
    expect($this->comparator->compare('v2.0.0', '2.0.0'))->toBe(0);
});

it('falls back to numeric-segment comparison for non-semver strings', function () {
    // "version_compare" can't meaningfully parse these, but the plugin
    // is clearly on build 118 vs build 42.
    expect($this->comparator->isNewer('build-118', 'build-42'))->toBeTrue();
    expect($this->comparator->isNewer('Release 4', 'Release 3'))->toBeTrue();
});

it('treats a null version as never newer and never older', function () {
    expect($this->comparator->isNewer(null, '1.0.0'))->toBeFalse();
    expect($this->comparator->isNewer('1.0.0', null))->toBeFalse();
});

it('handles pre-release suffixes sensibly', function () {
    expect($this->comparator->isNewer('1.2.0', '1.2.0-SNAPSHOT'))->toBeTrue();
});
