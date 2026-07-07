<?php

namespace Webservice\Model;

use Core\Model\Model;
use PDO;

class User extends Model
{
    protected string $token = '';

    protected array $info = array();

    public function getToken(): string
    {
        return $this->token;
    }

    public function getInfo(): array
    {
        return $this->info;
    }

    public function loadWithToken(string $token): bool|int
    {
        $sql    = '
            SELECT *
            FROM user
            WHERE token = :token
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