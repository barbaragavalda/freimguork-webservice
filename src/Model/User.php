<?php

namespace Webservice\Model;

use Core\Model\Encryptor\BlindIndex;
use Core\Model\Encryptor\OneWay;
use Core\Model\Encryptor\TwoWay;
use Core\Model\Model;
use PDO;

class User extends Model
{
    private const string PASSWORD_CONTEXT = 'webservice:user:password';

    /**
     * field name fed to BlindIndex::compute() - this and $this->key . self::EMAIL_FIELD
     * (see load()/encryptAndStoreEmail()) are the two places "email" as a
     * field label is threaded through the encryption/lookup machinery
     */
    private const string EMAIL_FIELD = 'email';

    /**
     * shared with Controller\Register (and any future username-change
     * endpoint) so the rule lives in one place - 3-20 chars, letters/
     * numbers/underscore/dot; uniqueness itself is enforced by the `username`
     * column's UNIQUE KEY - the consuming app's own db.sql owns that column
     * (this package doesn't define the `user` table), and case-insensitive
     * matching ("Bar"/"bar" colliding) depends on whichever *_ci collation
     * that table ends up with, not on anything this package does itself
     */
    public const string USERNAME_PATTERN = '/^[a-zA-Z0-9_.]{3,20}$/';

    /**
     * shared by Controller\{Register,ChangePassword,ResetPassword} - length
     * only, no composition rules (must-have-a-number/uppercase/symbol):
     * modern guidance (NIST SP 800-63B) treats those as adding friction
     * without meaningfully improving security, since they mostly just push
     * people toward predictable substitutions
     */
    public const int PASSWORD_MIN_LENGTH = 8;

    protected array $info = array();

    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * creates a new user, returns its id, or false if the email/username is
     * already registered or the insert failed. $idAppacmanLang (the
     * consuming app's own appacman_lang.id_appacman_lang, resolved from
     * this request's language by the caller - see Controller\Register) is
     * stored so a later notification (e.g. Controller\ForgotPassword's
     * reset email) can be sent in the language this user actually
     * registered with, not whatever language a later, unrelated request
     * happens to resolve to
     */
    public function register(string $email, string $password, string $username, ?int $idAppacmanLang = null): int|false
    {
        if ($this->loadWithEmail($email) || $this->loadWithUsername($username)) {
            return false;
        }

        // email is inserted empty for now: TwoWay's per-row context (see
        // Model::setKey(), "<id>_<created>_<field>") needs id_user, which
        // AUTO_INCREMENT only assigns once the row already exists -
        // encryptAndStoreEmail() below fills it in right after, in the
        // same request, before this method returns to its caller
        $sql    = '
            INSERT INTO user (email, email_bidx, password, username, id_appacman_lang)
            VALUES (:email, :email_bidx, :password, :username, :id_appacman_lang)
        ';
        $params = array(
            'email'            => array('value' => '', 'type' => PDO::PARAM_STR),
            'email_bidx'       => array('value' => BlindIndex::compute($email, self::EMAIL_FIELD), 'type' => PDO::PARAM_STR),
            'password'         => array(
                'value' => OneWay::encrypt($password, self::PASSWORD_CONTEXT),
                'type'  => PDO::PARAM_STR,
            ),
            'username'         => array('value' => $username, 'type' => PDO::PARAM_STR),
            'id_appacman_lang' => array('value' => $idAppacmanLang, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);
        if (!$this->mysql->getState()) {
            return false;
        }

        $this->id = (int) $this->mysql->lastInsertId();
        $this->loadWithID($this->id);
        $this->encryptAndStoreEmail($email);
        return $this->id;
    }

    /**
     * encrypts $email under this row's own per-row context (now that
     * id_user/created are known, see register()) and writes it, keeping
     * $this->info's copy as the plaintext so the caller doesn't need an
     * extra re-read to see it decrypted
     */
    private function encryptAndStoreEmail(string $email): void
    {
        $this->setCreated($this->info['created']);
        $this->setKey();

        $sql    = '
            UPDATE user
            SET email = :email
            WHERE id_user = :id_user
        ';
        $params = array(
            'email'   => array('value' => TwoWay::encrypt($email, $this->key . self::EMAIL_FIELD), 'type' => PDO::PARAM_STR),
            'id_user' => array('value' => $this->id, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);

        $this->info['email'] = $email;
    }

    /**
     * deletes this user's own row - the caller is responsible for revoking
     * its tokens first (see UserToken::revokeAllForUser()), same composition
     * pattern as register() + UserToken::issue() in Controller\Register
     */
    public function delete(int $id): void
    {
        $sql    = '
            DELETE FROM user
            WHERE id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value' => $id, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);
    }

    /**
     * used by Controller\ResetPassword after PasswordReset::redeem()
     * confirms the code - relies on $this->id already being set (e.g. by a
     * prior loadWithEmail() on this same instance), same convention as
     * encryptAndStoreEmail()
     */
    public function updatePassword(string $newPassword): void
    {
        $sql    = '
            UPDATE user
            SET password = :password
            WHERE id_user = :id_user
        ';
        $params = array(
            'password' => array(
                'value' => OneWay::encrypt($newPassword, self::PASSWORD_CONTEXT),
                'type'  => PDO::PARAM_STR,
            ),
            'id_user'  => array('value' => $this->id, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);
    }

    public function authenticate(string $email, string $password): bool
    {
        if (!$this->loadWithEmail($email)) {
            return false;
        }
        return OneWay::check($this->info['password'], $password, self::PASSWORD_CONTEXT);
    }

    /**
     * checks $password against this already-loaded user's own stored hash -
     * unlike authenticate(), doesn't load by email first (the caller already
     * has $this->user from the request's token, see WebserviceController::
     * checkToken()), used to confirm the current password before changing it
     */
    public function verifyPassword(string $password): bool
    {
        return OneWay::check($this->info['password'], $password, self::PASSWORD_CONTEXT);
    }

    /**
     * renames this already-loaded user, or returns false if $username is
     * already taken by a different account (uniqueness itself is still
     * enforced by the `username` column's UNIQUE KEY - this check just gives
     * the caller a clean false instead of a caught constraint-violation
     * exception)
     */
    public function updateUsername(string $username): bool
    {
        // shares $this->mysql rather than a bare `new self()` - not just
        // style, an injected test fixture (FixturePdo) would otherwise be
        // silently bypassed in favor of Model's own default connection
        $existing = new self($this->mysql);
        if ($existing->loadWithUsername($username) && $existing->id !== $this->id) {
            return false;
        }

        $sql    = '
            UPDATE user
            SET username = :username
            WHERE id_user = :id_user
        ';
        $params = array(
            'username' => array('value' => $username, 'type' => PDO::PARAM_STR),
            'id_user'  => array('value' => $this->id, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);

        $this->info['username'] = $username;
        return true;
    }

    /**
     * $idAppacmanLang is the consuming app's own appacman_lang.id_appacman_lang
     * (this package doesn't know or care what that maps to - see
     * Api\Model\TheTvdb\Languages in tv-tracker-local for this project's own
     * culture <-> id mapping)
     */
    public function updateLanguage(int $idAppacmanLang): void
    {
        $sql    = '
            UPDATE user
            SET id_appacman_lang = :id_appacman_lang
            WHERE id_user = :id_user
        ';
        $params = array(
            'id_appacman_lang' => array('value' => $idAppacmanLang, 'type' => PDO::PARAM_INT),
            'id_user'          => array('value' => $this->id, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);

        $this->info['id_appacman_lang'] = $idAppacmanLang;
    }

    /**
     * re-encrypts and stores a new email for this already-loaded user (same
     * per-row TwoWay context as encryptAndStoreEmail(), already set up by
     * whichever loadWith*() call loaded $this in the first place), or
     * returns false if $email is already taken by a different account
     */
    public function updateEmail(string $email): bool
    {
        // see updateUsername()'s own comment on sharing $this->mysql here
        $existing = new self($this->mysql);
        if ($existing->loadWithEmail($email) && $existing->id !== $this->id) {
            return false;
        }

        $sql    = '
            UPDATE user
            SET email = :email, email_bidx = :email_bidx
            WHERE id_user = :id_user
        ';
        $params = array(
            'email'      => array(
                'value' => TwoWay::encrypt($email, $this->key . self::EMAIL_FIELD),
                'type'  => PDO::PARAM_STR,
            ),
            'email_bidx' => array('value' => BlindIndex::compute($email, self::EMAIL_FIELD), 'type' => PDO::PARAM_STR),
            'id_user'    => array('value' => $this->id, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);

        $this->info['email'] = $email;
        return true;
    }

    public function loadWithID(int $id): bool|int
    {
        $sql    = '
            SELECT *
            FROM user
            WHERE id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value' => $id, 'type' => PDO::PARAM_INT),
        );
        $user   = $this->mysql->query($sql, $params);
        return $this->load($user);
    }

    public function loadWithEmail(string $email): bool|int
    {
        $sql    = '
            SELECT *
            FROM user
            WHERE email_bidx = :email_bidx
        ';
        $params = array(
            'email_bidx' => array('value' => BlindIndex::compute($email, self::EMAIL_FIELD), 'type' => PDO::PARAM_STR),
        );
        $user   = $this->mysql->query($sql, $params);
        return $this->load($user);
    }

    public function loadWithUsername(string $username): bool|int
    {
        $sql    = '
            SELECT *
            FROM user
            WHERE username = :username
        ';
        $params = array(
            'username' => array('value' => $username, 'type' => PDO::PARAM_STR),
        );
        $user   = $this->mysql->query($sql, $params);
        return $this->load($user);
    }

    public function loadWithToken(string $token): bool|int
    {
        $sql    = '
            SELECT u.*
            FROM user u
            INNER JOIN user_token t ON t.id_user = u.id_user
            WHERE t.token = :token
        ';
        $params = array(
            'token' => array('value' => UserToken::hash($token), 'type' => PDO::PARAM_STR),
        );
        $user   = $this->mysql->query($sql, $params);
        return $this->load($user);
    }

    protected function load(array $user): bool|int
    {
        if (count($user)) {
            $this->info = $user[0];
            $this->id   = $this->info['id_user'];

            // decrypt in place so every loadWith*() gives callers the real
            // email back, not the ciphertext - guarded because a brand new
            // row (mid-way through register(), before encryptAndStoreEmail()
            // runs) still has an empty placeholder here, which TwoWay::decrypt()
            // itself already refuses to touch (returns false on an empty
            // string); also requires 'created' (setKey()'s per-row context),
            // never actually missing on a real `user` row (NOT NULL DEFAULT
            // CURRENT_TIMESTAMP) but a cheap guard against a malformed row
            if (!empty($this->info['email']) && !empty($this->info['created'])) {
                $this->setCreated($this->info['created']);
                $this->setKey();
                $decrypted = TwoWay::decrypt($this->info['email'], $this->key . self::EMAIL_FIELD);
                if ($decrypted !== false) {
                    $this->info['email'] = $decrypted;
                }
            }

            return $this->id;
        }
        return false;
    }

}
