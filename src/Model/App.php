<?php

namespace Webservice\Model;

use Core\Model\Model;
use Core\Utils\Config;

class App extends Model {

    public function onMaintenance(){
        $sql = '
            SELECT `value`
            FROM appacman_app_config
            WHERE `name` = "MAINTENANCE"
        ';
        $maintenance = $this->mysql->query($sql);

        if( count($maintenance) ){
            if( $text = $maintenance[0]['value'] ){
                return $text;
            }
        }
        return false;
    }

    public function shouldUpdate($platform, $appVersion){
        $sql = '
            SELECT *
            FROM appacman_app_config
            WHERE `name` = "VERSION" AND `platform` = :platform AND `value` > :app_version
        ';
        $params = array(
            'platform'     => array('value'=>mb_strtolower($platform,'UTF-8'),  'type'=>\PDO::PARAM_STR),
            'app_version'  => array('value'=>$appVersion,                       'type'=>\PDO::PARAM_STR)
        );
        $app = $this->mysql->query($sql, $params);

        if( count($app) ){
            return true;
        }
        return false;
    }

    public function environment(){
        $platform = null;
        if( isset($_GET['app_platform']) ){
            $platform = strtolower( $_GET['app_platform'] );
        }

        $version = null;
        if( isset($_GET['app_version']) ){
            $version = floatval($_GET['app_version']);
        }

        $config = Config::getInstance();
        $configWS = $config->get('webservice');
        if( count($configWS) ){
            if( $platform && $version ){
                if( $version > $configWS['prod_version']['ios'] ){
                    return $configWS['environments']['pre'];
                }
            }
            return $configWS['environments']['prod'];
        }

        return null;
    }

}