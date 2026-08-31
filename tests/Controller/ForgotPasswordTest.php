<?php

namespace Webservice\Tests\Controller;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Webservice\Controller\ForgotPassword;

/**
 * Same reasoning as RegisterTest: only the validation check that returns
 * before run() ever touches `new User()` is safely testable without a
 * real Config/DB.
 */
class ForgotPasswordTest extends TestCase
{

    protected function tearDown(): void
    {
        unset($_POST['email']);
    }

    public function testFailsWhenEmailIsMissing(): void
    {
        $reflection = new ReflectionClass(ForgotPassword::class);
        $controller = $reflection->newInstanceWithoutConstructor();

        $reflection->getMethod('run')->invoke($controller);

        $this->assertNotFalse($reflection->getProperty('error')->getValue($controller));
    }

}
