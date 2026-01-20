<?php

it('renders the welcome page with helpful content', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee('Manage connected systems and clients', escape: false)
        ->assertSee('Getting started')
        ->assertSee('Log in');
});

