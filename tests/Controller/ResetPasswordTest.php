<?php

namespace Webservice\Tests\Controller;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Webservice\Controller\ResetPassword;

/**
 * Same reasoning as RegisterTest: only the two validation checks that
 * return before run() ever touches `new User()` are safely testable
 * without a real Config/DB.
 */
class ResetPasswordTest extends TestCase
{

    protected function tearDown(): void
    {
        unset($_POST['email'], $_POST['code'], $_POST['password']);
    }

    private function runAndGetError(): bool|string
    {
        $reflection = new ReflectionClass(ResetPassword::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $reflection->getMethod('run')->invoke($controller);
        return $reflection->getProperty('error')->getValue($controller);
    }

    public function testFailsWhenAnyFieldIsMissing(): void
    {
        $_POST['email'] = 'user@example.com';
        // code and password left unset

        $this->assertNotFalse($this->runAndGetError());
    }

    public function testFailsWhenTheNewPasswordIsShorterThanTheMinimum(): void
    {
        $_POST['email']    = 'user@example.com';
        $_POST['code']     = '123456';
        $_POST['password'] = 'short';

        $this->assertNotFalse($this->runAndGetError());
    }

}
