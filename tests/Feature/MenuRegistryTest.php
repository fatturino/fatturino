<?php

use App\Enums\MenuItem;
use App\Services\MenuRegistry;
use Tests\TestCase;

/** @var TestCase $this */
beforeEach(function () {
    $this->menu = new MenuRegistry;
});

it('builds a flat menu in registration order', function () {
    $this->menu->add('a', 'A', 'o-a', '/a');
    $this->menu->add('b', 'B', 'o-b', '/b');
    $this->menu->add('c', 'C', 'o-c', '/c');

    $ids = collect($this->menu->tree())->pluck('id')->all();

    expect($ids)->toBe(['a', 'b', 'c']);
});

it('places item after a specific item', function () {
    $this->menu->add('a', 'A', 'o-a', '/a');
    $this->menu->add('b', 'B', 'o-b', '/b');
    $this->menu->add('c', 'C', 'o-c', '/c', after: 'a');

    $ids = collect($this->menu->tree())->pluck('id')->all();

    expect($ids)->toBe(['a', 'c', 'b']);
});

it('places item before a specific item', function () {
    $this->menu->add('a', 'A', 'o-a', '/a');
    $this->menu->add('b', 'B', 'o-b', '/b');
    $this->menu->add('c', 'C', 'o-c', '/c', before: 'b');

    $ids = collect($this->menu->tree())->pluck('id')->all();

    expect($ids)->toBe(['a', 'c', 'b']);
});

it('nests children under a parent sub-menu', function () {
    $this->menu->sub('group', 'Group', 'o-folder');
    $this->menu->add('child-1', 'Child 1', 'o-1', '/child-1', parent: 'group');
    $this->menu->add('child-2', 'Child 2', 'o-2', '/child-2', parent: 'group');

    $tree = $this->menu->tree();

    expect($tree)->toHaveCount(1);
    expect($tree[0]['id'])->toBe('group');
    expect($tree[0]['children'])->toHaveCount(2);
    expect($tree[0]['children'][0]['id'])->toBe('child-1');
    expect($tree[0]['children'][1]['id'])->toBe('child-2');
});

it('accepts MenuItem enum for positioning', function () {
    $this->menu->add(MenuItem::Dashboard, 'Dashboard', 'o-home', '/dashboard');
    $this->menu->add(MenuItem::Contacts, 'Contacts', 'o-users', '/contacts');
    $this->menu->add('hello', 'Hello', 'o-hand', '/hello', after: MenuItem::Dashboard);

    $ids = collect($this->menu->tree())->pluck('id')->all();

    expect($ids)->toBe(['dashboard', 'hello', 'contacts']);
});

it('handles after reference to item registered later', function () {
    // Plugin registers BEFORE core (the real bug scenario)
    $this->menu->add('hello', 'Hello', 'o-hand', '/hello', after: MenuItem::Contacts);

    // Core registers after
    $this->menu->add(MenuItem::Dashboard, 'Dashboard', 'o-home', '/dashboard');
    $this->menu->add(MenuItem::Contacts, 'Contacts', 'o-users', '/contacts');

    $ids = collect($this->menu->tree())->pluck('id')->all();

    expect($ids)->toBe(['dashboard', 'contacts', 'hello']);
});

it('handles before reference to item registered later', function () {
    $this->menu->add('hello', 'Hello', 'o-hand', '/hello', before: MenuItem::Configuration);

    $this->menu->add(MenuItem::Dashboard, 'Dashboard', 'o-home', '/dashboard');
    $this->menu->add(MenuItem::Contacts, 'Contacts', 'o-users', '/contacts');
    $this->menu->sub(MenuItem::Configuration, 'Config', 'o-cog');

    $ids = collect($this->menu->tree())->pluck('id')->all();

    expect($ids)->toBe(['dashboard', 'contacts', 'hello', 'configuration']);
});

it('places plugin item inside a sub-menu registered later', function () {
    // Plugin registers child before the parent sub-menu exists
    $this->menu->add('webhooks', 'Webhooks', 'o-link', '/webhooks',
        parent: MenuItem::Configuration, after: MenuItem::Imports);

    // Core registers
    $this->menu->sub(MenuItem::Configuration, 'Config', 'o-cog');
    $this->menu->add(MenuItem::Imports, 'Imports', 'o-arrow', '/imports', parent: MenuItem::Configuration);

    $tree = $this->menu->tree();
    $config = collect($tree)->firstWhere('id', 'configuration');
    $childIds = collect($config['children'])->pluck('id')->all();

    expect($childIds)->toBe(['imports', 'webhooks']);
});
