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

    public function testLoadWithUsernameReturnsFalseWhenNotFound(): void
    {
        $user = new User(new FixturePdo(array(array())));

        $this->assertFalse($user->loadWithUsername('nobody'));
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
        $row  = array('id_user' => 3, 'email' => 'a@example.com', 'password' => 'hash', 'username' => 'a');
        $user = new User(new FixturePdo(array(array($row))));

        $id = $user->loadWithEmail('a@example.com');

        $this->assertSame(3, $id);
        $this->assertSame($row, $user->getInfo());
    }

    public function testLoadWithUsernamePopulatesInfoWhenFound(): void
    {
        $row  = array('id_user' => 3, 'email' => 'a@example.com', 'password' => 'hash', 'username' => 'alice');
        $user = new User(new FixturePdo(array(array($row))));

        $id = $user->loadWithUsername('alice');

        $this->assertSame(3, $id);
        $this->assertSame($row, $user->getInfo());
    }

    public function testRegisterReturnsFalseWhenEmailAlreadyRegistered(): void
    {
        $existing = array('id_user' => 1, 'email' => 'taken@example.com', 'password' => 'hash', 'username' => 'x');
        $mysql    = new FixturePdo(array(array($existing)));
        $user     = new User($mysql);

        $result = $user->register('taken@example.com', 'secret123', 'newuser');

        $this->assertFalse($result);
        // loadWithEmail() alone already found a match, so the || short-
        // circuits - loadWithUsername() never runs, no INSERT attempted
        $this->assertCount(1, $mysql->queries);
    }

    public function testRegisterReturnsFalseWhenUsernameAlreadyTaken(): void
    {
        $existing = array('id_user' => 1, 'email' => 'other@example.com', 'password' => 'hash', 'username' => 'taken');
        // 1st query: loadWithEmail() (not found), 2nd: loadWithUsername() (found)
        $mysql = new FixturePdo(array(array(), array($existing)));
        $user  = new User($mysql);

        $result = $user->register('new@example.com', 'secret123', 'taken');

        $this->assertFalse($result);
        $this->assertCount(2, $mysql->queries);
    }

    public function testRegisterReturnsFalseWhenTheInsertFails(): void
    {
        // email not found, username not found, but the INSERT itself fails
        $mysql = new FixturePdo(array(array(), array()), state: false);
        $user  = new User($mysql);

        $this->assertFalse($user->register('new@example.com', 'secret123', 'newuser'));
    }

    public function testRegisterHashesThePasswordInsteadOfStoringItInPlainText(): void
    {
        $newRow = array('id_user' => 7, 'email' => 'new@example.com', 'password' => 'irrelevant', 'username' => 'newuser');
        // 1st: loadWithEmail() (not found), 2nd: loadWithUsername() (not
        // found), 3rd: the INSERT, 4th: loadWithID() re-read
        $mysql = new FixturePdo(array(array(), array(), array(), array($newRow)), lastInsertId: '7');
        $user  = new User($mysql);

        $id = $user->register('new@example.com', 'secret123', 'newuser');

        $this->assertSame(7, $id);
        $storedPassword = $mysql->queries[2]['params']['password']['value'];
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
        $registerMysql = new FixturePdo(array(array(), array(), array(), array()));
        (new User($registerMysql))->register('real@example.com', 'correct horse', 'realuser');
        $hashedPassword = $registerMysql->queries[2]['params']['password']['value'];

        $row = array('id_user' => 9, 'email' => 'real@example.com', 'password' => $hashedPassword, 'username' => 'realuser');

        $this->assertTrue((new User(new FixturePdo(array(array($row)))))->authenticate('real@example.com', 'correct horse'));
        $this->assertFalse((new User(new FixturePdo(array(array($row)))))->authenticate('real@example.com', 'wrong password'));
    }

    public function testDeleteDeletesByID(): void
    {
        $mysql = new FixturePdo();
        $user  = new User($mysql);

        $user->delete(42);

        $this->assertCount(1, $mysql->queries);
        $this->assertStringContainsString('DELETE FROM user', $mysql->queries[0]['sql']);
        $this->assertSame(42, $mysql->queries[0]['params']['id_user']['value']);
    }

}
