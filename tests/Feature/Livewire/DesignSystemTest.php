<?php

use App\Livewire\DesignSystem;
use Livewire\Livewire;

/*
 * The design page exists only in the local environment (routes/web.php), so it never ships.
 */
it('is not routed outside the local environment', function () {
    $this->get('/design')->assertNotFound();
});

it('renders the tokens and components', function () {
    Livewire::test(DesignSystem::class)
        ->assertSee('In the light')
        ->assertSee('On white')
        ->assertSee('Your ITPs');
});
