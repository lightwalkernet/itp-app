<?php

test('the application opens at home for guests, NativePHP\'s start URL', function () {
    $this->withoutVite();

    $response = $this->get('/');

    $response->assertOk()->assertSee('Four are open before you sign in.');
});
