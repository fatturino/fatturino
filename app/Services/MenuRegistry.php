<?php

namespace App\Services;

use App\Enums\MenuItem;
use Illuminate\Support\Collection;

/**
 * Centralized menu registry. Core and plugins register all menu items here.
 *
 * Each item has a unique ID used for positioning (after/before).
 * Sub-menus are items with children (use sub() to create them).
 * Use MenuItem enum for core IDs, or plain strings for plugin-defined IDs.
 *
 * Positioning (after/before) is resolved lazily when tree() is called,
 * so plugins can reference core items even if they boot first.
 */
class MenuRegistry
{
    /**
     * @var array<string, array{
     *     id: string, title: string, icon: string, link: ?string,
     *     parent: ?string, after: ?string, before: ?string,
     *     gate: ?string, registrationOrder: int
     * }>
     */
    private array $items = [];

    private int $registrationCounter = 0;

    /**
     * Register a menu item. Pass $gate to restrict visibility to users
     * authorized for the given Gate ability.
     */
    public function add(
        string|MenuItem $id,
        string $title,
        string $icon,
        string $link,
        string|MenuItem|null $parent = null,
        string|MenuItem|null $after = null,
        string|MenuItem|null $before = null,
        ?string $gate = null,
    ): void {
        $this->items[$this->resolve($id)] = [
            'id' => $this->resolve($id),
            'title' => $title,
            'icon' => $icon,
            'link' => $link,
            'parent' => $this->resolve($parent),
            'after' => $this->resolve($after),
            'before' => $this->resolve($before),
            'gate' => $gate,
            'registrationOrder' => $this->registrationCounter++,
        ];
    }

    /**
     * Register a sub-menu (group with children, no link).
     */
    public function sub(
        string|MenuItem $id,
        string $title,
        string $icon,
        string|MenuItem|null $after = null,
        string|MenuItem|null $before = null,
    ): void {
        $this->items[$this->resolve($id)] = [
            'id' => $this->resolve($id),
            'title' => $title,
            'icon' => $icon,
            'link' => null,
            'parent' => null,
            'after' => $this->resolve($after),
            'before' => $this->resolve($before),
            'gate' => null,
            'registrationOrder' => $this->registrationCounter++,
        ];
    }

    /**
     * Build the menu tree: top-level items with nested children.
     *
     * Order resolution happens here so that after/before references
     * work regardless of registration order.
     *
     * @return list<array{id: string, title: string, icon: string, link: ?string, children: list}>
     */
    public function tree(): array
    {
        $ordered = $this->resolveOrder();

        // Collect children grouped by parent ID
        $children = $ordered->filter(fn ($item) => $item['parent'] !== null)->groupBy('parent');

        // Build top-level items with their children attached
        return $ordered
            ->filter(fn ($item) => $item['parent'] === null)
            ->map(function ($item) use ($children) {
                $item['children'] = $children->get($item['id'], collect())->values()->all();

                return $item;
            })
            ->values()
            ->all();
    }

    /**
     * Resolve final order for all items.
     *
     * Items without after/before keep their registration order.
     * Items with after/before are placed relative to their target.
     */
    private function resolveOrder(): Collection
    {
        // Start with registration order, spaced by 10 for insertion gaps
        $orders = [];
        foreach ($this->items as $id => $item) {
            $orders[$id] = $item['registrationOrder'] * 10;
        }

        // Resolve after/before constraints
        foreach ($this->items as $id => $item) {
            if ($item['after'] !== null && isset($orders[$item['after']])) {
                $orders[$id] = $orders[$item['after']] + 1;
            } elseif ($item['before'] !== null && isset($orders[$item['before']])) {
                $orders[$id] = $orders[$item['before']] - 1;
            }
        }

        return collect($this->items)
            ->map(fn ($item) => array_merge($item, ['order' => $orders[$item['id']]]))
            ->sortBy('order');
    }

    /**
     * Extract string value from MenuItem enum or pass through string.
     */
    private function resolve(string|MenuItem|null $value): ?string
    {
        if ($value instanceof MenuItem) {
            return $value->value;
        }

        return $value;
    }
}
