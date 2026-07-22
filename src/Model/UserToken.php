<?php

namespace Webservice\Model;

use Core\Model\Model;
use PDO;

class UserToken extends Model
{

    /**
     * only the SHA-256 hash of a token ever touches the database - a token
     * is already a 256-bit random value (bin2hex(random_bytes(32))), so
     * there's no dictionary/guessing attack a slow, peppered hash (like
     * OneWay's password hashing) would defend against here; this exists
     * purely so read access to the DB alone (a leaked backup, etc.) doesn't
     * hand out directly-usable session tokens
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * issues a new opaque session token for a user and stores it, so it can
     * later be looked up via User::loadWithToken() - returns the plaintext
     * token (only the caller/client ever sees it), stores only its hash
     */
    public function issue(int $userID, ?string $deviceLabel = null): string
    {
        $token  = bin2hex(random_bytes(32));
        $sql    = '
            INSERT INTO user_token (id_user, token, device_label)
            VALUES (:id_user, :token, :device_label)
        ';
        $params = array(
            'id_user'      => array('value' => $userID, 'type' => PDO::PARAM_INT),
            'token'        => array('value' => self::hash($token), 'type' => PDO::PARAM_STR),
            'device_label' => array('value' => $deviceLabel, 'type' => PDO::PARAM_STR),
        );
        $this->mysql->query($sql, $params);

        return $token;
    }

    public function revoke(string $token): void
    {
        $sql    = '
            DELETE FROM user_token
            WHERE token = :token
        ';
        $params = array(
            'token' => array('value' => self::hash($token), 'type' => PDO::PARAM_STR),
        );
        $this->mysql->query($sql, $params);
    }

    /**
     * revokes every device token for a user in one go - used by
     * Controller\DeleteAccount before deleting the user row itself
     */
    public function revokeAllForUser(int $userID): void
    {
        $sql    = '
            DELETE FROM user_token
            WHERE id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value' => $userID, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);
    }

    public function touch(string $token): void
    {
        $sql    = '
            UPDATE user_token
            SET last_used = NOW()
            WHERE token = :token
        ';
        $params = array(
            'token' => array('value' => self::hash($token), 'type' => PDO::PARAM_STR),
        );
        $this->mysql->query($sql, $params);
    }

}
