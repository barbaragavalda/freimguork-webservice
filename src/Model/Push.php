<?php

namespace Webservice\Model;

use Core\Model\Model;
use Core\Model\Utils\DateUtils;

class Push extends Model
{

    /**
     * @var int  user id
     */
    protected $id_user = 0;

    public function __construct($id = null)
    {
        parent::__construct();

        $this->id = $id;
    }

    public function setIDUser($id_user)
    {
        $this->id_user = $id_user;
    }

    /**
     * register device
     * @return string|bool error
     */
    public function register()
    {
        $token       = isset($_POST['push_token']) ? $_POST['push_token'] : '';
        $platform    = $_POST['platform'];
        $model       = $_POST['model'];
        $os_version  = $_POST['os_version'];
        $app_version = $_POST['app_version'];
        $language    = isset($_POST['language']) ? $_POST['language'] : '';

        if ($this->exists()) {
            return $this->update($token, $platform, $model, $os_version, $app_version, $language);
        }
        return $this->push($token, $platform, $model, $os_version, $app_version, $language);
    }

    private function exists()
    {
        $sql    = '
            SELECT *
            FROM appacman_push_device
            WHERE uuid = :uuid
        ';
        $params = array(
            'uuid' => array('value' => $this->id, 'type' => \PDO::PARAM_STR)
        );
        $device = $this->mysql->query($sql, $params);
        if (count($device)) {
            return true;
        }
        return false;
    }

    private function push($token, $platform, $model, $os_version, $app_version, $language)
    {
        $params      = array(
            'uuid'        => array('value' => $this->id, 'type' => \PDO::PARAM_STR),
            'token'       => array('value' => $token, 'type' => \PDO::PARAM_STR),
            'platform'    => array('value' => $platform, 'type' => \PDO::PARAM_STR),
            'model'       => array('value' => $model, 'type' => \PDO::PARAM_STR),
            'os_version'  => array('value' => $os_version, 'type' => \PDO::PARAM_STR),
            'app_version' => array('value' => $app_version, 'type' => \PDO::PARAM_STR),
            'id_user'     => array('value' => $this->id_user, 'type' => \PDO::PARAM_INT)
        );
        $extraFields = $extraValues = '';
        if (!empty($language)) {
            $extraFields        = ', language';
            $extraValues        = ', :language';
            $params['language'] = array('value' => $language, 'type' => \PDO::PARAM_STR);
        }
        $sql = "
            REPLACE INTO appacman_push_device (uuid, token, platform, model, os_version, app_version, id_user $extraFields)
            VALUES (:uuid, :token, :platform, :model, :os_version, :app_version, :id_user $extraValues)
        ";
        $this->mysql->query($sql, $params);

        if ($this->mysql->getState()) {
            return false;
        }
        return gettext('Error en el servidor. Por favor, inténtalo más tarde.');
    }

    private function update($token, $platform, $model, $os_version, $app_version, $language)
    {
        $extraFields = array('last_connection = :now');
        $params      = array(
            'uuid'    => array('value' => $this->id, 'type' => \PDO::PARAM_STR),
            'id_user' => array('value' => $this->id_user, 'type' => \PDO::PARAM_INT),
            'now'     => array('value' => date(DateUtils::FORMAT_TIMESTAMP_DB), 'type' => \PDO::PARAM_STR)
        );

        if (!empty($token)) {
            $extraFields[]   = 'token = :token';
            $params['token'] = array('value' => $token, 'type' => \PDO::PARAM_STR);
        }
        if (!empty($platform)) {
            $extraFields[]      = 'platform = :platform';
            $params['platform'] = array('value' => $platform, 'type' => \PDO::PARAM_STR);
        }
        if (!empty($model)) {
            $extraFields[]   = 'model = :model';
            $params['model'] = array('value' => $model, 'type' => \PDO::PARAM_STR);
        }
        if (!empty($os_version)) {
            $extraFields[]        = 'os_version = :os_version';
            $params['os_version'] = array('value' => $os_version, 'type' => \PDO::PARAM_STR);
        }
        if (!empty($app_version)) {
            $extraFields[]         = 'app_version = :app_version';
            $params['app_version'] = array('value' => $app_version, 'type' => \PDO::PARAM_STR);
        }
        if (!empty($language)) {
            $extraFields[]      = 'language = :language';
            $params['language'] = array('value' => $language, 'type' => \PDO::PARAM_STR);
        }

        $fields = '';
        if (count($extraFields)) {
            $fields = ', ' . implode(', ', $extraFields);
        }
        $sql = "
            UPDATE appacman_push_device
            SET id_user = :id_user $fields
            WHERE uuid = :uuid
        ";
        $this->mysql->query($sql, $params);

        if ($this->mysql->getState()) {
            return false;
        }
        return gettext('Error en el servidor. Por favor, inténtalo más tarde.');
    }

    /**
     * delete device
     * @return string|bool error
     */
    public function delete()
    {
        $sql    = '
            DELETE FROM appacman_push_device
            WHERE uuid = :uuid
        ';
        $params = array(
            'uuid' => array('value' => $this->id, 'type' => \PDO::PARAM_STR)
        );
        $this->mysql->query($sql, $params);

        if ($this->mysql->getState()) {
            return false;
        }
        return gettext('Error en el servidor. Por favor, inténtalo más tarde.');
    }

    /**
     * get notifications configuration
     * @return array
     */
    public function getNotifications()
    {
        $sql           = '
            SELECT anl.name, an.id_appacman_notification AS id, uan.id_appacman_notification AS is_on
            FROM appacman_notification AS an
            INNER JOIN appacman_notification_lang AS anl ON an.id_appacman_notification = anl.id_appacman_notification AND anl.id_appacman_lang = :lang
            LEFT JOIN user_appacman_notification AS uan ON an.id_appacman_notification = uan.id_appacman_notification AND uan.id_user = :id_user
        ';
        $params        = array(
            'lang'    => array('value' => $this->langID, 'type' => \PDO::PARAM_INT),
            'id_user' => array('value' => $this->id_user, 'type' => \PDO::PARAM_INT),
        );
        $notifications = $this->mysql->query($sql, $params);

        if (count($notifications)) {
            foreach ($notifications as &$notification) {
                if ($notification['is_on']) {
                    $notification['on'] = true;
                } else {
                    $notification['on'] = false;
                }
            }
            return $notifications;
        }
        return array();
    }

    public function has($type)
    {
        $sql          = '
            SELECT *
            FROM user_appacman_notification
            WHERE id_user = :id_user AND id_appacman_notification = :id
        ';
        $params       = array(
            'id_user' => array('value' => $this->id_user, 'type' => \PDO::PARAM_INT),
            'id'      => array('value' => $type, 'type' => \PDO::PARAM_INT)
        );
        $notification = $this->mysql->query($sql, $params);

        if (count($notification)) {
            return true;
        }
        return false;
    }

    /**
     * save notifications configuration
     *
     * @param $notifications
     */
    public function modifyNotifications($notifications)
    {
        foreach ($notifications as $id => $activated) {
            if ($activated) {
                $this->addNotification($id);
            } else {
                $this->removeNotification($id);
            }
        }
    }

    /**
     * activate notification
     *
     * @param $id
     *
     * @return boolean success
     */
    public function addNotification($id)
    {
        if (!$this->has($id)) {
            $sql    = '
                INSERT INTO user_appacman_notification
                SET id_user = :id_user,
                    id_appacman_notification = :id
            ';
            $params = array(
                'id_user' => array('value' => $this->id_user, 'type' => \PDO::PARAM_INT),
                'id'      => array('value' => $id, 'type' => \PDO::PARAM_INT)
            );
            $this->mysql->query($sql, $params);
            return $this->mysql->getState();
        }
        return true;
    }

    /**
     * remove notification
     *
     * @param $id
     *
     * @return boolean success
     */
    public function removeNotification($id)
    {
        $sql    = '
            DELETE FROM user_appacman_notification
            WHERE id_user = :id_user AND
                id_appacman_notification = :id
        ';
        $params = array(
            'id_user' => array('value' => $this->id_user, 'type' => \PDO::PARAM_INT),
            'id'      => array('value' => $id, 'type' => \PDO::PARAM_INT)
        );
        $this->mysql->query($sql, $params);
        return $this->mysql->getState();
    }

}