<?php

namespace Webservice\Tests\Model;

use PHPUnit\Framework\TestCase;
use Webservice\Model\UserToken;
use Webservice\Tests\Fixtures\FixturePdo;

class UserTokenTest extends TestCase
{

    public function testIssueReturnsA64CharacterHexToken(): void
    {
        $mysql     = new FixturePdo();
        $userToken = new UserToken($mysql);

        $token = $userToken->issue(1);

        $this->assertSame(64, strlen($token));
        $this->assertTrue(ctype_xdigit($token));
    }

    public function testIssueInsertsTheGeneratedTokenForTheGivenUser(): void
    {
        $mysql     = new FixturePdo();
        $userToken = new UserToken($mysql);

        $token = $userToken->issue(42, 'Barbara\'s iPhone');

        $this->assertCount(1, $mysql->queries);
        $this->assertStringContainsString('INSERT INTO user_token', $mysql->queries[0]['sql']);
        $this->assertSame(42, $mysql->queries[0]['params']['id_user']['value']);
        $this->assertSame($token, $mysql->queries[0]['params']['token']['value']);
        $this->assertSame("Barbara's iPhone", $mysql->queries[0]['params']['device_label']['value']);
    }

    public function testIssueReturnsADifferentTokenEveryTime(): void
    {
        $userToken = new UserToken(new FixturePdo());

        $this->assertNotSame($userToken->issue(1), $userToken->issue(1));
    }

    public function testRevokeDeletesByToken(): void
    {
        $mysql     = new FixturePdo();
        $userToken = new UserToken($mysql);

        $userToken->revoke('some-token');

        $this->assertCount(1, $mysql->queries);
        $this->assertStringContainsString('DELETE FROM user_token', $mysql->queries[0]['sql']);
        $this->assertSame('some-token', $mysql->queries[0]['params']['token']['value']);
    }

    public function testRevokeAllForUserDeletesByUserID(): void
    {
        $mysql     = new FixturePdo();
        $userToken = new UserToken($mysql);

        $userToken->revokeAllForUser(42);

        $this->assertCount(1, $mysql->queries);
        $this->assertStringContainsString('DELETE FROM user_token', $mysql->queries[0]['sql']);
        $this->assertSame(42, $mysql->queries[0]['params']['id_user']['value']);
    }

    public function testTouchUpdatesLastUsedByToken(): void
    {
        $mysql     = new FixturePdo();
        $userToken = new UserToken($mysql);

        $userToken->touch('some-token');

        $this->assertCount(1, $mysql->queries);
        $this->assertStringContainsString('UPDATE user_token', $mysql->queries[0]['sql']);
        $this->assertSame('some-token', $mysql->queries[0]['params']['token']['value']);
    }

}
