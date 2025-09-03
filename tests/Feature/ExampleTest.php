<?php

describe('Example Features', function () {
    it('returns a successful response for home page', function () {
        $response = $this->get('/');

        expect($response)->toBeSuccessfulResponse();
    });
});
