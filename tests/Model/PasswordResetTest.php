<?php

namespace Webservice\Tests\Model;

use PHPUnit\Framework\TestCase;
use Webservice\Model\PasswordReset;
use Webservice\Tests\Fixtures\FixturePdo;

class PasswordResetTest extends TestCase
{

    public function testCreateReturnsA6DigitNumericCode(): void
    {
        $passwordReset = new PasswordReset(new FixturePdo(array(array(), array())));

        $code = $passwordReset->create(1);

        $this->assertSame(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testCreateDeletesAnyExistingCodeBeforeInsertingTheNewOne(): void
    {
        $mysql         = new FixturePdo(array(array(), array()));
        $passwordReset = new PasswordReset($mysql);

        $passwordReset->create(1);

        $this->assertCount(2, $mysql->queries);
        $this->assertStringContainsString('DELETE FROM password_reset', $mysql->queries[0]['sql']);
        $this->assertStringContainsString('INSERT INTO password_reset', $mysql->queries[1]['sql']);
        $this->assertSame(1, $mysql->queries[1]['params']['id_user']['value']);
    }

    public function testCreateStoresTheHashNotThePlaintextCode(): void
    {
        $mysql         = new FixturePdo(array(array(), array()));
        $passwordReset = new PasswordReset($mysql);

        $code = $passwordReset->create(1);

        $storedCode = $mysql->queries[1]['params']['code']['value'];
        $this->assertNotSame($code, $storedCode);
        $this->assertSame(PasswordReset::hash($code), $storedCode);
    }

    public function testRedeemReturnsFalseWhenNoCodeExists(): void
    {
        $passwordReset = new PasswordReset(new FixturePdo(array(array())));

        $this->assertFalse($passwordReset->redeem(1, '123456'));
    }

    public function testRedeemReturnsFalseWhenCodeIsExpired(): void
    {
        $row = array(
            'id_user'    => 1,
            'code'       => PasswordReset::hash('123456'),
            'attempts'   => 0,
            'expires_at' => date('Y-m-d H:i:s', time() - 60),
        );
        $passwordReset = new PasswordReset(new FixturePdo(array(array($row))));

        $this->assertFalse($passwordReset->redeem(1, '123456'));
    }

    public function testRedeemReturnsFalseWhenMaxAttemptsAlreadyReached(): void
    {
        $row = array(
            'id_user'    => 1,
            'code'       => PasswordReset::hash('123456'),
            'attempts'   => 5,
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        );
        $passwordReset = new PasswordReset(new FixturePdo(array(array($row))));

        $this->assertFalse($passwordReset->redeem(1, '123456'));
    }

    public function testRedeemIncrementsAttemptsAndReturnsFalseOnWrongCode(): void
    {
        $row = array(
            'id_user'    => 1,
            'code'       => PasswordReset::hash('123456'),
            'attempts'   => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        );
        $mysql         = new FixturePdo(array(array($row), array()));
        $passwordReset = new PasswordReset($mysql);

        $result = $passwordReset->redeem(1, '000000');

        $this->assertFalse($result);
        $this->assertCount(2, $mysql->queries);
        $this->assertStringContainsString('UPDATE password_reset', $mysql->queries[1]['sql']);
        $this->assertStringContainsString('attempts = attempts + 1', $mysql->queries[1]['sql']);
    }

    public function testRedeemReturnsTrueAndDeletesTheRowOnTheCorrectCode(): void
    {
        $row = array(
            'id_user'    => 1,
            'code'       => PasswordReset::hash('123456'),
            'attempts'   => 2,
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        );
        $mysql         = new FixturePdo(array(array($row), array()));
        $passwordReset = new PasswordReset($mysql);

        $result = $passwordReset->redeem(1, '123456');

        $this->assertTrue($result);
        $this->assertCount(2, $mysql->queries);
        $this->assertStringContainsString('DELETE FROM password_reset', $mysql->queries[1]['sql']);
    }

}
