<?php

namespace Webservice\Model;

use Core\Model\Model;
use Core\Utils\Config;

class Push extends Model {

    /**
     * @var string  deep linking protocol
     */
    private $urlScheme = '';

    /**
     * @var string  uuid of device
     */
    private $id = '';

    /**
     * @var int  user id
     */
    protected $id_user = 0;

    public function __construct(){
        parent::__construct();

        // url scheme
        $config = Config::getInstance();
        $webserviceConfig = $config->get('webservice');
        $this->urlScheme = $webserviceConfig['url_scheme'];
    }

    public function setID($id){
        $this->id = $id;
    }

    public function setIDUser($id_user){
        $this->id_user = $id_user;
    }

    /**
     * register device
     * @param $token        string. Device token
     * @param $platform     string. Device platform (ios, android,....)
     * @param $model        string. Device model
     * @param $os_version   string. Device OS version
     * @param $app_version  string. Device app version
     */
    public function register($token, $platform, $model, $os_version, $app_version){
        $sql = '
            REPLACE INTO appacman_push (uuid, token, platform, model, os_version, app_version, id_user)
            VALUES (:uuid, :token, :platform, :model, :os_version, :app_version, :id_user)
        ';
        $params = array(
            'uuid'          => array('value'=>$this->id,        'type'=>\PDO::PARAM_STR),
            'token'         => array('value'=>$token,           'type'=>\PDO::PARAM_STR),
            'platform'      => array('value'=>$platform,        'type'=>\PDO::PARAM_STR),
            'model'         => array('value'=>$model,           'type'=>\PDO::PARAM_STR),
            'os_version'    => array('value'=>$os_version,      'type'=>\PDO::PARAM_STR),
            'app_version'   => array('value'=>$app_version,     'type'=>\PDO::PARAM_STR),
            'id_user'       => array('value'=>$this->id_user,   'type'=>\PDO::PARAM_INT)
        );
        $this->mysql->query($sql, $params);
    }

    /**
     * delete device
     */
    public function delete(){
        $sql = '
            DELETE FROM appacman_push
            WHERE uuid = :uuid
        ';
        $params = array(
            'uuid' => array('value'=>$this->id, 'type'=>\PDO::PARAM_STR)
        );
        $this->mysql->query($sql, $params);
    }

    /**
     * get notifications configuration
     * @return array
     */
    public function getNotifications(){
        $sql = '
            SELECT an.code, un.id_notification
            FROM appacman_notification AS an
            LEFT JOIN user_appacman_notification AS uan ON an.id_notification = uan.id_notification AND uan.id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value'=>$this->id_user, 'type'=>\PDO::PARAM_INT),
        );
        $notifications = $this->mysql->query($sql, $params);

        $config = array();
        if( count($notifications) ){
            foreach ($notifications as $noti){
                if( $noti['id_notification'] ){
                    $config[ $noti['code'] ] = true;
                }else{
                    $config[ $noti['code'] ] = false;
                }
            }
        }
        return $config;
    }

    /**
     * save notifications configuration
     * @param $notifications
     */
    public function modifyNotifications($notifications){
        foreach( $notifications as $code => $activated ){
            if( $activated == "true" ){
                $this->addNotification($code);
            }else{
                $this->removeNotification($code);
            }
        }
    }

    /**
     * activate notification
     * @param $code
     */
    private function addNotification($code){
        $sql = '
            INSERT INTO user_appacman_notification
            SET id_user = :id_user,
                id_appacman_notification = (
                    SELECT id_appacman_notification FROM appacman_notification WHERE code = :code
                )
        ';
        $params = array(
            'id_user'   => array('value'=>$this->id_user,   'type'=>\PDO::PARAM_INT),
            'code'      => array('value'=>$code,            'type'=>\PDO::PARAM_STR)
        );
        $this->mysql->query($sql, $params);
    }

    /**
     * remove notification
     * @param $code
     */
    private function removeNotification($code){
        $sql = '
            DELETE FROM user_appacman_notification
            WHERE id_user = :id_user AND
                id_appacman_notification = (
                    SELECT id_appacman_notification FROM user_appacman_notification WHERE code = :code
                )
        ';
        $params = array(
            'id_user'   => array('value'=>$this->id_user,   'type'=>\PDO::PARAM_INT),
            'code'      => array('value'=>$code,            'type'=>\PDO::PARAM_STR)
        );
        $this->mysql->query($sql, $params);
    }

    /*********************************************
     * SEND PUSH
     ********************************************
    private function send($platforms, $message, $urlScheme = ''){
        $android = $ios = array();
        foreach($platforms as $platform){
            $tokens = explode(',', $platform['tokens']);
            switch( $platform['name'] ){
                case 'android': $android = $tokens; break;
                case 'ios':     $ios = $tokens;     break;
            }
        }

        if( count($android) ){
            $pushAndroid = new PushAndroid($message, $android, $urlScheme);
            $pushAndroid->send();
            $pushAndroid->close();
        }

        if( count($ios) ){
            $pushiOS = new PushiOS($message, $ios, $urlScheme);
            $pushiOS->send();
            $pushiOS->close();
        }

    }*/

}