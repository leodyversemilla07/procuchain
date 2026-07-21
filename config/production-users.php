<?php

use App\Enums\UserRole;

return [
    /*
    |--------------------------------------------------------------------------
    | Production User Accounts
    |--------------------------------------------------------------------------
    |
    | Known production users to sync into the local database.
    | Each entry requires: email, name, blockchain_address, and roles.
    |
    */

    [
        'email' => 'brylemaamo@gmail.com',
        'name' => 'Bryle Maamo',
        'blockchain_address' => '1R5Be5615e3b7MjDiWxA9HAuzAAE3xTuVS3N54',
        'roles' => [UserRole::BAC_SECRETARIAT->value],
    ],
    [
        'email' => 'leifsagesemilla@gmail.com',
        'name' => 'Leif Sage Semilla',
        'blockchain_address' => '13JUtJaimUnhbXcUeE97Uzj4c4vwLyJvqHhMw9',
        'roles' => [UserRole::HOPE->value],
    ],
    [
        'email' => 'leobrielzilvrak@gmail.com',
        'name' => 'LeoBriel Zilvrak',
        'blockchain_address' => '1YwGYaqaeMJxMHXhKRaKqgaha1ncVQ7peXbvuF',
        'roles' => [UserRole::ADMIN->value],
    ],
    [
        'email' => 'brylemaamo@gmail.com',
        'name' => 'Bryle Maamo',
        'blockchain_address' => '1Y5R5CT8A1Be6RwnxQecEeWobYrmKH8p9HcQmb',
        'roles' => [UserRole::BAC_SECRETARIAT->value],
    ],
    [
        'email' => 'semillacelsojr@gmail.com',
        'name' => 'Celso Semilla',
        'blockchain_address' => '1TcErv2payomuanpZy5eJKVuNmrNjD2hMikjen',
        'roles' => [UserRole::BAC_CHAIRMAN->value],
    ],
    [
        'email' => 'nidasemilla15@gmail.com',
        'name' => 'Leonida Monsanto',
        'blockchain_address' => '1RDVkCmEaeG9XBqL8NN6XsdGaoc4CnqURU5K4C',
        'roles' => [UserRole::BAC_SECRETARIAT->value],
    ],
];
