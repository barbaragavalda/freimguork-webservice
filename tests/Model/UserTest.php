<?php

namespace Webservice\Tests\Model;

use Core\Model\Encryptor\Secret;
use PHPUnit\Framework\TestCase;
use Webservice\Model\User;
use Webservice\Tests\Fixtures\FixturePdo;

class UserTest extends TestCase
{

    protected function setUp(): void
    {
        Secret::setForTesting(bin2hex(random_bytes(32)));
    }

    protected function tearDown(): void
    {
        Secret::setForTesting(null);
    }

    public function testLoadWithEmailReturnsFalseWhenNotFound(): void
    {
        $user = new User(new FixturePdo(array(array())));

        $this->assertFalse($user->loadWithEmail('nobody@example.com'));
    }

    public function testLoadWithTokenReturnsFalseWhenNotFound(): void
    {
        $user = new User(new FixturePdo(array(array())));

        $this->assertFalse($user->loadWithToken('some-token'));
    }

    public function testLoadWithIDReturnsFalseWhenNotFound(): void
    {
        $user = new User(new FixturePdo(array(array())));

        $this->assertFalse($user->loadWithID(999));
    }

    public function testLoadWithEmailPopulatesInfoWhenFound(): void
    {
        $row  = array('id_user' => 3, 'email' => 'a@example.com', 'password' => 'hash', 'name' => 'A');
        $user = new User(new FixturePdo(array(array($row))));

        $id = $user->loadWithEmail('a@example.com');

        $this->assertSame(3, $id);
        $this->assertSame($row, $user->getInfo());
    }

    public function testRegisterReturnsFalseWhenEmailAlreadyRegistered(): void
    {
        $existing = array('id_user' => 1, 'email' => 'taken@example.com', 'password' => 'hash', 'name' => 'X');
        $mysql    = new FixturePdo(array(array($existing)));
        $user     = new User($mysql);

        $result = $user->register('taken@example.com', 'secret123', 'New Name');

        $this->assertFalse($result);
        // only the loadWithEmail() SELECT should have run - no INSERT attempted
        $this->assertCount(1, $mysql->queries);
    }

    public function testRegisterReturnsFalseWhenTheInsertFails(): void
    {
        // email not found (empty result), but the INSERT itself fails
        $mysql = new FixturePdo(array(array()), state: false);
        $user  = new User($mysql);

        $this->assertFalse($user->register('new@example.com', 'secret123', 'New Name'));
    }

    public function testRegisterHashesThePasswordInsteadOfStoringItInPlainText(): void
    {
        $newRow = array('id_user' => 7, 'email' => 'new@example.com', 'password' => 'irrelevant', 'name' => 'New Name');
        // 1st query: loadWithEmail() (not found), 2nd: the INSERT, 3rd: loadWithID() re-read
        $mysql = new FixturePdo(array(array(), array(), array($newRow)), lastInsertId: '7');
        $user  = new User($mysql);

        $id = $user->register('new@example.com', 'secret123', 'New Name');

        $this->assertSame(7, $id);
        $storedPassword = $mysql->queries[1]['params']['password']['value'];
        $this->assertNotSame('secret123', $storedPassword);
        $this->assertStringStartsWith('$', $storedPassword);
    }

    public function testAuthenticateReturnsFalseWhenUserNotFound(): void
    {
        $user = new User(new FixturePdo(array(array())));

        $this->assertFalse($user->authenticate('nobody@example.com', 'whatever'));
    }

    public function testAuthenticateAcceptsTheCorrectPasswordAndRejectsAWrongOne(): void
    {
        // register a user through a first User/FixturePdo pair purely to
        // capture a realistically-hashed password (same code path
        // production uses), then feed that hash back as a "found" row for
        // a separate authenticate() call - avoids hardcoding this class's
        // private hashing context in the test
        $registerMysql = new FixturePdo(array(array(), array(), array()));
        (new User($registerMysql))->register('real@example.com', 'correct horse', 'Real Name');
        $hashedPassword = $registerMysql->queries[1]['params']['password']['value'];

        $row = array('id_user' => 9, 'email' => 'real@example.com', 'password' => $hashedPassword, 'name' => 'Real Name');

        $this->assertTrue((new User(new FixturePdo(array(array($row)))))->authenticate('real@example.com', 'correct horse'));
        $this->assertFalse((new User(new FixturePdo(array(array($row)))))->authenticate('real@example.com', 'wrong password'));
    }

}
