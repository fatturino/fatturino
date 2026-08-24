<?php

use App\Models\User;

it('renders an accessible mobile navigation trigger and skip link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('href="#main-content"', false)
        ->assertSee('Salta al contenuto principale')
        ->assertSee('id="main-content"', false)
        ->assertSee('tabindex="-1"', false)
        ->assertSee('x-ref="menuTrigger"', false)
        ->assertSee('aria-controls="app-sidebar"', false)
        ->assertSee(':aria-expanded="sidebarOpen.toString()"', false)
        ->assertSee('@keydown.escape.window="if (sidebarOpen && !isDesktop) closeSidebar(true)"', false)
        ->assertSee('openSidebar()', false)
        ->assertSee('closeSidebar(returnFocus = false)', false)
        ->assertSee('aria-current="page"', false);
});
