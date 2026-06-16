<?php

test('the application shows the catalog to guest users', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
