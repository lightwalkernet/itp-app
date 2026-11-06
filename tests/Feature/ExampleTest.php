<?php

test('the application opens at home for guests, NativePHP\'s start URL', function () {
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertOk()->assertSee('Sign in or create a free account');
});
