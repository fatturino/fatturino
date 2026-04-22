<?php

use App\Services\PluginRegistry;

test('register returns true for a new plugin and defaults to active', function () {
    $registry = new PluginRegistry;
    $result = $registry->register('test-plugin', 'Test Plugin');

    expect($result)->toBeTrue();
});

test('register stores plugin metadata', function () {
    $registry = new PluginRegistry;
    $registry->register('test-plugin', 'Test Plugin', 'A description', '2.0.0', 'Author');

    $all = $registry->all();
    expect($all)->toHaveKey('test-plugin');
    expect($all['test-plugin']['name'])->toBe('Test Plugin');
    expect($all['test-plugin']['description'])->toBe('A description');
    expect($all['test-plugin']['version'])->toBe('2.0.0');
    expect($all['test-plugin']['author'])->toBe('Author');
    expect($all['test-plugin']['active'])->toBeTrue();
});

test('has returns true for an active registered plugin', function () {
    $registry = new PluginRegistry;
    $registry->register('test-plugin', 'Test Plugin');

    expect($registry->has('test-plugin'))->toBeTrue();
});

test('has returns false for an unregistered plugin', function () {
    $registry = new PluginRegistry;

    expect($registry->has('non-existent'))->toBeFalse();
});

test('deactivate sets the plugin to inactive', function () {
    $registry = new PluginRegistry;
    $registry->register('test-plugin', 'Test Plugin');
    $registry->deactivate('test-plugin');

    expect($registry->has('test-plugin'))->toBeFalse();
});

test('activate re-enables a deactivated plugin', function () {
    $registry = new PluginRegistry;
    $registry->register('test-plugin', 'Test Plugin');
    $registry->deactivate('test-plugin');
    $registry->activate('test-plugin');

    expect($registry->has('test-plugin'))->toBeTrue();
});

test('deactivate is a no-op for locked plugins', function () {
    $registry = new PluginRegistry;
    $registry->register('locked-plugin', 'Locked Plugin', '', '1.0.0', '', locked: true);
    $registry->deactivate('locked-plugin');

    // Should still be active because it's locked
    expect($registry->has('locked-plugin'))->toBeTrue();
});

test('all returns all registered plugins including inactive', function () {
    $registry = new PluginRegistry;
    $registry->register('plugin-a', 'Plugin A');
    $registry->register('plugin-b', 'Plugin B');
    $registry->deactivate('plugin-b');

    expect($registry->all())->toHaveCount(2);
});

test('count returns only active plugins', function () {
    $registry = new PluginRegistry;
    $registry->register('plugin-a', 'Plugin A');
    $registry->register('plugin-b', 'Plugin B');
    $registry->deactivate('plugin-b');

    expect($registry->count())->toBe(1);
});

test('inject and injections manage blade view slots', function () {
    $registry = new PluginRegistry;
    $registry->inject('head-scripts', 'myplugin::head');
    $registry->inject('head-scripts', 'otherplugin::head');
    $registry->inject('content-before', 'myplugin::banner');

    expect($registry->injections('head-scripts'))->toBe(['myplugin::head', 'otherplugin::head']);
    expect($registry->injections('content-before'))->toBe(['myplugin::banner']);
    expect($registry->injections('unknown-slot'))->toBe([]);
});
