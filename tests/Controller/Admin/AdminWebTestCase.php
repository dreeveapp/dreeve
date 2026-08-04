<?php

namespace App\Tests\Controller\Admin;

use App\Tests\Controller\ControllerWebTestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;

abstract class AdminWebTestCase extends ControllerWebTestCase
{
    protected const string ADMIN_USERNAME = 'admin';
    protected const string ADMIN_PASSWORD = 'admin-password';

    #[\Override]
    protected function prepareEnvironment(): void
    {
        $_SERVER['ADMIN_USERNAME'] = $_ENV['ADMIN_USERNAME'] = self::ADMIN_USERNAME;
        $_SERVER['ADMIN_PASSWORD_HASH'] = $_ENV['ADMIN_PASSWORD_HASH'] = password_hash(
            self::ADMIN_PASSWORD,
            PASSWORD_BCRYPT,
            ['cost' => 4],
        );
    }

    protected function adminUser(): InMemoryUser
    {
        return new InMemoryUser(
            self::ADMIN_USERNAME,
            (string) $_SERVER['ADMIN_PASSWORD_HASH'],
            ['ROLE_ADMIN'],
        );
    }
}
