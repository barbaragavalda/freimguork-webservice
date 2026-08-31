<?php

namespace Webservice\Tests\Controller;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Webservice\Controller\Register;

/**
 * Register::__construct() pulls in a real Config (domain/base-domain lookups) and run()'s
 * later branches touch the DB via `new User()` - only the three validation checks that
 * return *before* that are safely testable without either, via reflection (no constructor
 * call) plus $_POST.
 */
class RegisterTest extends TestCase
{

    protected function tearDown(): void
    {
        unset($_POST['email'], $_POST['password'], $_POST['username']);
    }

    private function runAndGetError(): bool|string
    {
        $reflection = new ReflectionClass(Register::class);
        $register   = $reflection->newInstanceWithoutConstructor();
        $reflection->getMethod('run')->invoke($register);
        return $reflection->getProperty('error')->getValue($register);
    }

    public function testFailsWhenAnyFieldIsMissing(): void
    {
        $_POST['email']    = 'user@example.com';
        $_POST['password'] = 'longenough';
        // username left unset

        $this->assertNotFalse($this->runAndGetError());
    }

    public function testFailsWhenUsernameDoesNotMatchThePattern(): void
    {
        $_POST['email']    = 'user@example.com';
        $_POST['password'] = 'longenough';
        $_POST['username'] = 'ab'; // shorter than the 3-char minimum

        $this->assertNotFalse($this->runAndGetError());
    }

    public function testFailsWhenPasswordIsShorterThanTheMinimum(): void
    {
        $_POST['email']    = 'user@example.com';
        $_POST['password'] = 'short';
        $_POST['username'] = 'valid_user';

        $this->assertNotFalse($this->runAndGetError());
    }

}
