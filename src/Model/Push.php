<?php

namespace Webservice\Model;

use Core\Model\Model;

class Push extends Model {

    /**
     * @var int  user id
     */
    protected $id_user = 0;

    public function __construct($id = null){
        parent::__construct();

        $this->id = $id;
    }

    public function setIDUser($id_user){
        $this->id_user = $id_user;
    }

    /**
     * register device
     * @return string|bool error
     */
    public function register(){
        $token = $_POST['push_token'];
        if( empty($token) ){
            return gettext('Debes indicar el token del dispositivo para poder recibir notificaciones push.');
        }
        $platform = $_POST['platform'];
        $model = $_POST['model'];
        $os_version = $_POST['os_version'];
        $app_version = $_POST['app_version'];
        $params = array(
            'uuid'          => array('value'=>$this->id,        'type'=>\PDO::PARAM_STR),
            'token'         => array('value'=>$token,           'type'=>\PDO::PARAM_STR),
            'platform'      => array('value'=>$platform,        'type'=>\PDO::PARAM_STR),
            'model'         => array('value'=>$model,           'type'=>\PDO::PARAM_STR),
            'os_version'    => array('value'=>$os_version,      'type'=>\PDO::PARAM_STR),
            'app_version'   => array('value'=>$app_version,     'type'=>\PDO::PARAM_STR),
            'id_user'       => array('value'=>$this->id_user,   'type'=>\PDO::PARAM_INT)
        );

        $extraFields = '';
        if( isset($_POST['language']) ){
            $extraFields = ', language';
            $extraValues = ', :language';
            $params['language'] = array('value' => $_POST['language'], 'type' => \PDO::PARAM_STR);
        }
        $sql = '
            REPLACE INTO appacman_push_device (uuid, token, platform, model, os_version, app_version, id_user' . $extraFields . ')
            VALUES (:uuid, :token, :platform, :model, :os_version, :app_version, :id_user' . $extraValues . ')
        ';
        $this->mysql->query($sql, $params);

        if( $this->mysql->getState() ){
            return false;
        }
        return gettext('Error en el servidor. Por favor, inténtalo más tarde.');
    }

    /**
     * delete device
     * @return string|bool error
     */
    public function delete(){
        $sql = '
            DELETE FROM appacman_push_device
            WHERE uuid = :uuid
        ';
        $params = array(
            'uuid' => array('value'=>$this->id, 'type'=>\PDO::PARAM_STR)
        );
        $this->mysql->query($sql, $params);

        if( $this->mysql->getState() ){
            return false;
        }
        return gettext('Error en el servidor. Por favor, inténtalo más tarde.');
    }

    /**
     * get notifications configuration
     * @return array
     */
    public function getNotifications(){
        $sql = '
            SELECT an.code, uan.id_appacman_notification
            FROM appacman_notification AS an
            LEFT JOIN user_appacman_notification AS uan ON an.id_appacman_notification = uan.id_appacman_notification AND uan.id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value'=>$this->id_user, 'type'=>\PDO::PARAM_INT),
        );
        $notifications = $this->mysql->query($sql, $params);

        $config = array();
        if( count($notifications) ){
            foreach ($notifications as $notification){
                if( $notification['id_appacman_notification'] ){
                    $config[ $notification['code'] ] = true;
                }else{
                    $config[ $notification['code'] ] = false;
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
                    SELECT id_appacman_notification FROM appacman_notification WHERE code = :code
                )
        ';
        $params = array(
            'id_user'   => array('value'=>$this->id_user,   'type'=>\PDO::PARAM_INT),
            'code'      => array('value'=>$code,            'type'=>\PDO::PARAM_STR)
        );
        $this->mysql->query($sql, $params);
    }

}