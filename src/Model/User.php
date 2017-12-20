<?php

namespace Webservice\Model;

use Core\Model\Model;

class User extends Model {

    /**
     * @var int     user identifier
     */
    protected $id = 0;

    /**
     * @var string  user token
     */
    protected $token = '';

    /**
     * @return array    user info
     */
    protected $info = array();

    public function getID(){
        return $this->id;
    }

    public function getToken(){
        return $this->token;
    }

    public function getInfo(){
        return $this->info;
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
            $this->info = $user[0];
            $this->id = $this->info['id_user'];
            return $this->id;
        }
        return false;
    }

}