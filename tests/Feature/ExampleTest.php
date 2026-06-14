<?php

test('the application redirects guest to login', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
