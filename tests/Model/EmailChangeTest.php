<?php

namespace Webservice\Tests\Model;

use PHPUnit\Framework\TestCase;
use Webservice\Model\EmailChange;
use Webservice\Tests\Fixtures\FixturePdo;

class EmailChangeTest extends TestCase
{

    public function testCreateReturnsA6DigitNumericCode(): void
    {
        $emailChange = new EmailChange(new FixturePdo(array(array(), array())));

        $code = $emailChange->create(1, 'new@example.com');

        $this->assertSame(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
    }

    public function testCreateDeletesAnyExistingRequestBeforeInsertingTheNewOne(): void
    {
        $mysql       = new FixturePdo(array(array(), array()));
        $emailChange = new EmailChange($mysql);

        $emailChange->create(1, 'new@example.com');

        $this->assertCount(2, $mysql->queries);
        $this->assertStringContainsString('DELETE FROM email_change', $mysql->queries[0]['sql']);
        $this->assertStringContainsString('INSERT INTO email_change', $mysql->queries[1]['sql']);
        $this->assertSame(1, $mysql->queries[1]['params']['id_user']['value']);
        $this->assertSame('new@example.com', $mysql->queries[1]['params']['new_email']['value']);
    }

    public function testCreateStoresTheHashNotThePlaintextCode(): void
    {
        $mysql       = new FixturePdo(array(array(), array()));
        $emailChange = new EmailChange($mysql);

        $code = $emailChange->create(1, 'new@example.com');

        $storedCode = $mysql->queries[1]['params']['code']['value'];
        $this->assertNotSame($code, $storedCode);
        $this->assertSame(EmailChange::hash($code), $storedCode);
    }

    public function testRedeemReturnsNullWhenNoRequestExists(): void
    {
        $emailChange = new EmailChange(new FixturePdo(array(array())));

        $this->assertNull($emailChange->redeem(1, '123456'));
    }

    public function testRedeemReturnsNullWhenCodeIsExpired(): void
    {
        $row = array(
            'id_user'    => 1,
            'new_email'  => 'new@example.com',
            'code'       => EmailChange::hash('123456'),
            'attempts'   => 0,
            'expires_at' => date('Y-m-d H:i:s', time() - 60),
        );
        $emailChange = new EmailChange(new FixturePdo(array(array($row))));

        $this->assertNull($emailChange->redeem(1, '123456'));
    }

    public function testRedeemReturnsNullWhenMaxAttemptsAlreadyReached(): void
    {
        $row = array(
            'id_user'    => 1,
            'new_email'  => 'new@example.com',
            'code'       => EmailChange::hash('123456'),
            'attempts'   => 5,
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        );
        $emailChange = new EmailChange(new FixturePdo(array(array($row))));

        $this->assertNull($emailChange->redeem(1, '123456'));
    }

    public function testRedeemIncrementsAttemptsAndReturnsNullOnWrongCode(): void
    {
        $row = array(
            'id_user'    => 1,
            'new_email'  => 'new@example.com',
            'code'       => EmailChange::hash('123456'),
            'attempts'   => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        );
        $mysql       = new FixturePdo(array(array($row), array()));
        $emailChange = new EmailChange($mysql);

        $result = $emailChange->redeem(1, '000000');

        $this->assertNull($result);
        $this->assertCount(2, $mysql->queries);
        $this->assertStringContainsString('UPDATE email_change', $mysql->queries[1]['sql']);
        $this->assertStringContainsString('attempts = attempts + 1', $mysql->queries[1]['sql']);
    }

    public function testRedeemReturnsTheNewEmailAndDeletesTheRowOnTheCorrectCode(): void
    {
        $row = array(
            'id_user'    => 1,
            'new_email'  => 'new@example.com',
            'code'       => EmailChange::hash('123456'),
            'attempts'   => 2,
            'expires_at' => date('Y-m-d H:i:s', time() + 900),
        );
        $mysql       = new FixturePdo(array(array($row), array()));
        $emailChange = new EmailChange($mysql);

        $result = $emailChange->redeem(1, '123456');

        $this->assertSame('new@example.com', $result);
        $this->assertCount(2, $mysql->queries);
        $this->assertStringContainsString('DELETE FROM email_change', $mysql->queries[1]['sql']);
    }

}
