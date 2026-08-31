<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $config = $app->make('config');

        if (! $app->environment('testing')
            || $config->get('database.default') !== 'sqlite'
            || $config->get('database.connections.sqlite.database') !== ':memory:') {
            throw new RuntimeException('Las pruebas deben ejecutarse con APP_ENV=testing y SQLite en memoria. Usa el servicio Docker "test".');
        }

        return $app;
    }
}
