<?php

namespace Tests\Feature\Livewire\Sequences;

use App\Livewire\Sequences\Index;
use App\Models\Sequence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_page_renders_for_authenticated_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/sequences')
            ->assertSeeLivewire(Index::class)
            ->assertStatus(200);
    }

    public function test_can_create_a_new_sequence()
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Fatture 2025')
            ->set('type', 'electronic_invoice')
            ->set('pattern', '{SEQ}')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sequences', ['name' => 'Fatture 2025']);
    }

    public function test_validates_unique_name_and_type_combination()
    {
        $user = User::factory()->create();
        Sequence::factory()->create(['name' => 'Existing', 'type' => 'electronic_invoice']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Existing')
            ->set('type', 'electronic_invoice')
            ->set('pattern', '{SEQ}')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_same_name_is_allowed_for_different_type()
    {
        $user = User::factory()->create();
        Sequence::factory()->create(['name' => 'Test', 'type' => 'electronic_invoice']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Test')
            ->set('type', 'purchase') // Different type
            ->set('pattern', '{SEQ}')
            ->call('save')
            ->assertHasNoErrors();
    }

    public function test_cannot_delete_system_sequence()
    {
        $user = User::factory()->create();
        $sequence = Sequence::factory()->system()->create(['type' => 'electronic_invoice']);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('delete', $sequence);

        // Should still exist in DB
        $this->assertDatabaseHas('sequences', ['id' => $sequence->id]);
    }

    public function test_can_delete_non_system_sequence_without_invoices()
    {
        $user = User::factory()->create();
        $sequence = Sequence::factory()->create();

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('delete', $sequence);

        $this->assertDatabaseMissing('sequences', ['id' => $sequence->id]);
    }
}
