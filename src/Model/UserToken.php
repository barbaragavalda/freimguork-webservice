<?php

namespace Webservice\Model;

use Core\Model\Model;
use PDO;

class UserToken extends Model
{

    /**
     * issues a new opaque session token for a user and stores it, so it can
     * later be looked up via User::loadWithToken()
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
            'token'        => array('value' => $token, 'type' => PDO::PARAM_STR),
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
            'token' => array('value' => $token, 'type' => PDO::PARAM_STR),
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
            'token' => array('value' => $token, 'type' => PDO::PARAM_STR),
        );
        $this->mysql->query($sql, $params);
    }

}
