<?php

namespace Webservice\Tests\Model;

use Core\Model\Encryptor\BlindIndex;
use Core\Model\Encryptor\Secret;
use Core\Model\Encryptor\TwoWay;
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

    public function testLoadWithEmailPopulatesInfoAndDecryptsTheEmail(): void
    {
        $id      = 3;
        $created = '2026-01-01 00:00:00';
        $email   = 'a@example.com';
        // matches Model::setKey()'s "<id>_<created>_<field>" convention
        // (also documented in this project's own README, "First admin
        // user") - not a guess at a private implementation detail
        $context = $id . '_' . $created . '_email';

        $row = array(
            'id_user'    => $id,
            'email'      => TwoWay::encrypt($email, $context),
            'email_bidx' => BlindIndex::compute($email, 'email'),
            'password'   => 'hash',
            'username'   => 'a',
            'created'    => $created,
        );
        $user = new User(new FixturePdo(array(array($row))));

        $id = $user->loadWithEmail($email);

        $this->assertSame(3, $id);
        $expected          = $row;
        $expected['email'] = $email; // decrypted back to plaintext
        $this->assertSame($expected, $user->getInfo());
    }

    public function testLoadWithEmailMatchesRegardlessOfCase(): void
    {
        // BlindIndex::normalize() lowercases+trims before hashing, so the
        // stored bidx for "A@Example.com" is the same one looking up
        // "a@example.com" would compute
        $row = array('id_user' => 3, 'email_bidx' => BlindIndex::compute('A@Example.com', 'email'), 'password' => 'hash', 'username' => 'a');
        $user = new User(new FixturePdo(array(array($row))));

        $this->assertSame(3, $user->loadWithEmail('a@example.com'));
    }

    public function testLoadWithUsernamePopulatesInfoWhenFound(): void
    {
        $row  = array('id_user' => 3, 'password' => 'hash', 'username' => 'alice');
        $user = new User(new FixturePdo(array(array($row))));

        $id = $user->loadWithUsername('alice');

        $this->assertSame(3, $id);
        $this->assertSame($row, $user->getInfo());
    }

    public function testRegisterReturnsFalseWhenEmailAlreadyRegistered(): void
    {
        $existing = array('id_user' => 1, 'password' => 'hash', 'username' => 'x');
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
        $existing = array('id_user' => 1, 'password' => 'hash', 'username' => 'taken');
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
        // (register() returns before loadWithID()/encryptAndStoreEmail() run)
        $mysql = new FixturePdo(array(array(), array()), state: false);
        $user  = new User($mysql);

        $this->assertFalse($user->register('new@example.com', 'secret123', 'newuser'));
    }

    public function testRegisterHashesThePasswordAndEncryptsTheEmailInsteadOfStoringThemInPlainText(): void
    {
        $created = '2026-01-01 00:00:00';
        // the row loadWithID() re-reads right after the INSERT - email is
        // still the empty placeholder at this point, encryptAndStoreEmail()
        // (a further UPDATE) fills it in afterwards
        $newRow = array('id_user' => 7, 'email' => '', 'password' => 'irrelevant', 'username' => 'newuser', 'created' => $created);
        // 1st: loadWithEmail() (not found), 2nd: loadWithUsername() (not
        // found), 3rd: the INSERT, 4th: loadWithID() re-read, 5th: the
        // encryptAndStoreEmail() UPDATE (return value unused)
        $mysql = new FixturePdo(array(array(), array(), array(), array($newRow), array()), lastInsertId: '7');
        $user  = new User($mysql);

        $id = $user->register('new@example.com', 'secret123', 'newuser');

        $this->assertSame(7, $id);

        $storedPassword = $mysql->queries[2]['params']['password']['value'];
        $this->assertNotSame('secret123', $storedPassword);
        $this->assertStringStartsWith('$', $storedPassword);

        $storedEmail = $mysql->queries[4]['params']['email']['value'];
        $this->assertNotSame('new@example.com', $storedEmail);
        $this->assertStringStartsWith('$gcm256$', $storedEmail);
        // getInfo() sees the plaintext, kept in memory rather than requiring
        // another DB round trip to decrypt what was just encrypted
        $this->assertSame('new@example.com', $user->getInfo()['email']);
    }

    public function testRegisterStoresTheGivenLanguageID(): void
    {
        $created = '2026-01-01 00:00:00';
        $newRow  = array('id_user' => 7, 'email' => '', 'password' => 'irrelevant', 'username' => 'newuser', 'created' => $created);
        $mysql   = new FixturePdo(array(array(), array(), array(), array($newRow), array()), lastInsertId: '7');
        $user    = new User($mysql);

        $user->register('new@example.com', 'secret123', 'newuser', 2);

        $this->assertSame(2, $mysql->queries[2]['params']['id_appacman_lang']['value']);
    }

    public function testRegisterAllowsAnOmittedLanguageID(): void
    {
        $created = '2026-01-01 00:00:00';
        $newRow  = array('id_user' => 7, 'email' => '', 'password' => 'irrelevant', 'username' => 'newuser', 'created' => $created);
        $mysql   = new FixturePdo(array(array(), array(), array(), array($newRow), array()), lastInsertId: '7');
        $user    = new User($mysql);

        $id = $user->register('new@example.com', 'secret123', 'newuser');

        $this->assertSame(7, $id);
        $this->assertNull($mysql->queries[2]['params']['id_appacman_lang']['value']);
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
        $created       = '2026-01-01 00:00:00';
        $insertedRow   = array('id_user' => 9, 'email' => '', 'password' => 'irrelevant', 'username' => 'realuser', 'created' => $created);
        $registerMysql = new FixturePdo(array(array(), array(), array(), array($insertedRow), array()));
        (new User($registerMysql))->register('real@example.com', 'correct horse', 'realuser');
        $hashedPassword = $registerMysql->queries[2]['params']['password']['value'];

        $row = array('id_user' => 9, 'password' => $hashedPassword, 'username' => 'realuser');

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

    public function testUpdatePasswordHashesAndStoresTheNewPasswordForTheLoadedUser(): void
    {
        $row   = array('id_user' => 5, 'password' => 'old-hash', 'username' => 'someone');
        $mysql = new FixturePdo(array(array($row)));
        $user  = new User($mysql);
        $user->loadWithID(5);

        $user->updatePassword('new secret');

        $this->assertCount(2, $mysql->queries);
        $this->assertStringContainsString('UPDATE user', $mysql->queries[1]['sql']);
        $this->assertSame(5, $mysql->queries[1]['params']['id_user']['value']);
        $storedPassword = $mysql->queries[1]['params']['password']['value'];
        $this->assertNotSame('new secret', $storedPassword);
        $this->assertStringStartsWith('$', $storedPassword);
    }

}
