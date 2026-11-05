<?php

test('the application sends guests to sign in', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('sign-in'));
});
