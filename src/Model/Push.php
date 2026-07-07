<?php

namespace Webservice\Model;

use Core\Model\Model;
use Core\Model\Utils\DateUtils;
use PDO;

class Push extends Model
{

    protected int $id_user = 0;

    public function __construct(?int $id = null)
    {
        parent::__construct();

        $this->id = $id;
    }

    public function setIDUser(int $id_user): void
    {
        $this->id_user = $id_user;
    }

    public function register(): string|bool
    {
        $token       = $_POST['push_token'] ?? '';
        $platform    = $_POST['platform'];
        $model       = $_POST['model'];
        $os_version  = $_POST['os_version'];
        $app_version = $_POST['app_version'];
        $language    = $_POST['language'] ?? '';

        if ($this->exists()) {
            return $this->update($token, $platform, $model, $os_version, $app_version, $language);
        }
        return $this->push($token, $platform, $model, $os_version, $app_version, $language);
    }

    private function exists(): bool
    {
        $sql    = '
            SELECT *
            FROM appacman_push_device
            WHERE uuid = :uuid
        ';
        $params = array(
            'uuid' => array('value' => $this->id, 'type' => PDO::PARAM_STR)
        );
        $device = $this->mysql->query($sql, $params);
        if (count($device)) {
            return true;
        }
        return false;
    }

    private function push(
        string $token,
        string $platform,
        string $model,
        string $os_version,
        string $app_version,
        string $language
    ): bool|string {
        $params      = array(
            'uuid'        => array('value' => $this->id, 'type' => PDO::PARAM_STR),
            'token'       => array('value' => $token, 'type' => PDO::PARAM_STR),
            'platform'    => array('value' => $platform, 'type' => PDO::PARAM_STR),
            'model'       => array('value' => $model, 'type' => PDO::PARAM_STR),
            'os_version'  => array('value' => $os_version, 'type' => PDO::PARAM_STR),
            'app_version' => array('value' => $app_version, 'type' => PDO::PARAM_STR),
            'id_user'     => array('value' => $this->id_user, 'type' => PDO::PARAM_INT)
        );
        $extraFields = $extraValues = '';
        if (!empty($language)) {
            $extraFields        = ', language';
            $extraValues        = ', :language';
            $params['language'] = array('value' => $language, 'type' => PDO::PARAM_STR);
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

    private function update(
        string $token,
        string $platform,
        string $model,
        string $os_version,
        string $app_version,
        string $language
    ): false|string {
        $extraFields = array('last_connection = :now');
        $params      = array(
            'uuid'    => array('value' => $this->id, 'type' => PDO::PARAM_STR),
            'id_user' => array('value' => $this->id_user, 'type' => PDO::PARAM_INT),
            'now'     => array('value' => date(DateUtils::FORMAT_TIMESTAMP_DB), 'type' => PDO::PARAM_STR)
        );

        if (!empty($token)) {
            $extraFields[]   = 'token = :token';
            $params['token'] = array('value' => $token, 'type' => PDO::PARAM_STR);
        }
        if (!empty($platform)) {
            $extraFields[]      = 'platform = :platform';
            $params['platform'] = array('value' => $platform, 'type' => PDO::PARAM_STR);
        }
        if (!empty($model)) {
            $extraFields[]   = 'model = :model';
            $params['model'] = array('value' => $model, 'type' => PDO::PARAM_STR);
        }
        if (!empty($os_version)) {
            $extraFields[]        = 'os_version = :os_version';
            $params['os_version'] = array('value' => $os_version, 'type' => PDO::PARAM_STR);
        }
        if (!empty($app_version)) {
            $extraFields[]         = 'app_version = :app_version';
            $params['app_version'] = array('value' => $app_version, 'type' => PDO::PARAM_STR);
        }
        if (!empty($language)) {
            $extraFields[]      = 'language = :language';
            $params['language'] = array('value' => $language, 'type' => PDO::PARAM_STR);
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

    public function delete(): string|bool
    {
        $sql    = '
            DELETE FROM appacman_push_device
            WHERE uuid = :uuid
        ';
        $params = array(
            'uuid' => array('value' => $this->id, 'type' => PDO::PARAM_STR)
        );
        $this->mysql->query($sql, $params);

        if ($this->mysql->getState()) {
            return false;
        }
        return gettext('Error en el servidor. Por favor, inténtalo más tarde.');
    }

    public function getNotifications(): array
    {
        $sql           = '
            SELECT anl.name, an.id_appacman_notification AS id, uan.id_appacman_notification AS is_on
            FROM appacman_notification AS an
            INNER JOIN appacman_notification_lang AS anl ON an.id_appacman_notification = anl.id_appacman_notification AND anl.id_appacman_lang = :lang
            LEFT JOIN user_appacman_notification AS uan ON an.id_appacman_notification = uan.id_appacman_notification AND uan.id_user = :id_user
        ';
        $params        = array(
            'lang'    => array('value' => $this->langID, 'type' => PDO::PARAM_INT),
            'id_user' => array('value' => $this->id_user, 'type' => PDO::PARAM_INT),
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

    public function has(int $type): bool
    {
        $sql          = '
            SELECT *
            FROM user_appacman_notification
            WHERE id_user = :id_user AND id_appacman_notification = :id
        ';
        $params       = array(
            'id_user' => array('value' => $this->id_user, 'type' => PDO::PARAM_INT),
            'id'      => array('value' => $type, 'type' => PDO::PARAM_INT)
        );
        $notification = $this->mysql->query($sql, $params);

        if (count($notification)) {
            return true;
        }
        return false;
    }

    public function modifyNotifications(array $notifications): void
    {
        foreach ($notifications as $id => $activated) {
            if ($activated) {
                $this->addNotification($id);
            } else {
                $this->removeNotification($id);
            }
        }
    }

    public function addNotification(int $id): bool
    {
        if (!$this->has($id)) {
            $sql    = '
                INSERT INTO user_appacman_notification
                SET id_user = :id_user,
                    id_appacman_notification = :id
            ';
            $params = array(
                'id_user' => array('value' => $this->id_user, 'type' => PDO::PARAM_INT),
                'id'      => array('value' => $id, 'type' => PDO::PARAM_INT)
            );
            $this->mysql->query($sql, $params);
            return $this->mysql->getState();
        }
        return true;
    }

    public function removeNotification(int $id): bool
    {
        $sql    = '
            DELETE FROM user_appacman_notification
            WHERE id_user = :id_user AND
                id_appacman_notification = :id
        ';
        $params = array(
            'id_user' => array('value' => $this->id_user, 'type' => PDO::PARAM_INT),
            'id'      => array('value' => $id, 'type' => PDO::PARAM_INT)
        );
        $this->mysql->query($sql, $params);
        return $this->mysql->getState();
    }

}