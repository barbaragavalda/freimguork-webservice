<?php

namespace Webservice\Model;

use Core\Model\Encryptor\OneWay;
use Core\Model\Model;
use PDO;

class User extends Model
{
    private const string PASSWORD_CONTEXT = 'webservice:user:password';

    protected array $info = array();

    public function getInfo(): array
    {
        return $this->info;
    }

    /**
     * creates a new user, returns its id, or false if the email is already
     * registered or the insert failed
     */
    public function register(string $email, string $password, string $name): int|false
    {
        if ($this->loadWithEmail($email)) {
            return false;
        }

        $sql    = '
            INSERT INTO user (email, password, name)
            VALUES (:email, :password, :name)
        ';
        $params = array(
            'email'    => array('value' => $email, 'type' => PDO::PARAM_STR),
            'password' => array(
                'value' => OneWay::encrypt($password, self::PASSWORD_CONTEXT),
                'type'  => PDO::PARAM_STR,
            ),
            'name'     => array('value' => $name, 'type' => PDO::PARAM_STR),
        );
        $this->mysql->query($sql, $params);
        if (!$this->mysql->getState()) {
            return false;
        }

        $this->id = (int) $this->mysql->lastInsertId();
        $this->loadWithID($this->id);
        return $this->id;
    }

    public function authenticate(string $email, string $password): bool
    {
        if (!$this->loadWithEmail($email)) {
            return false;
        }
        return OneWay::check($this->info['password'], $password, self::PASSWORD_CONTEXT);
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
            WHERE email = :email
        ';
        $params = array(
            'email' => array('value' => $email, 'type' => PDO::PARAM_STR),
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
            'token' => array('value' => $token, 'type' => PDO::PARAM_STR),
        );
        $user   = $this->mysql->query($sql, $params);
        return $this->load($user);
    }

    protected function load(array $user): bool|int
    {
        if (count($user)) {
            $this->info = $user[0];
            $this->id   = $this->info['id_user'];
            return $this->id;
        }
        return false;
    }

}
