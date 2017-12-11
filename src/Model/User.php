<?php

namespace Webservice\Model;

use Core\Model\Model;

class User extends Model {

    private $id = 0;
    private $token = '';

    public function getID(){
        return $this->id;
    }

    public function getToken(){
        return $this->token;
    }

    /**
     * load user with token
     * @param $token
     * @return bool|int
     */
    public function loadWithToken($token){
        $sql = '
            SELECT *
            FROM user
            WHERE token = :token
        ';
        $params = array(
            'token' => array('value'=>$token, 'type'=>\PDO::PARAM_STR),
        );
        $user = $this->mysql->query($sql, $params);
        return $this->load($user);
    }

    /**
     * load user basic information
     * @param $user
     * @return bool|int
     */
    protected function load($user){
        if( count($user) ){
            $info = $user[0];
            $this->id = $info['id_user'];
            return $this->id;
        }
        return false;
    }

}