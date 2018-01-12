<?php

namespace Webservice\Controller;

use Core\Controller\Controller;
use Core\Utils\Config;
use Webservice\Model\App;
use Webservice\Model\User;

abstract class WebserviceController extends Controller {

    const GET       = 'GET';
    const POST      = 'POST';
    const DELETE    = 'DELETE';

    /**
     * @var string      method used in this entity
     */
    protected $method;

    /**
     * @var \Webservice\Model\App   app configuration
     */
    private $app = null;

    /**
     * @var null \Webservice\Model\User   app user
     */
    protected $user = null;

    /**
     * @var bool|string     error
     */
    protected $error = false;

    public function __construct(){
        parent::__construct();

        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->parseParams();
    }

    /**
     * all entities pass through this method
     * the result is always a JSON object
     */
    public function build(){
        $this->removeInfo();

        $this->app = new App();
        if( !$this->checkMaintenance() && !$this->checkAppVersion() ){
            $error = $this->checkToken();

            if( $error === false ){
                $this->run();
            }
        }

        if( $this->error !== false ) $error = $this->error;
        $this->assign('error', $error);
        $this->json();
    }

    /**
     * load request for delete petitions
     */
    private function parseParams() {
        if( $this->method == self::DELETE ){
            parse_str(file_get_contents('php://input'), $this->params);
            $_REQUEST = $this->params + $_REQUEST;
        }
    }

    /**
     * is maintenance on?
     * @return bool
     */
    private function checkMaintenance(){
        if( $maintenance = $this->app->onMaintenance() ){
            $this->assign('maintenance', gettext($maintenance));
            return true;
        }
        return false;
    }

    /**
     * should update?
     * @return bool
     */
    private function checkAppVersion(){
        if( isset($_REQUEST['app_platform']) && isset($_REQUEST['app_version']) ){
            if( $this->app->shouldUpdate($_REQUEST['app_platform'], $_REQUEST['app_version']) ){
                $this->assign('should_update', true);
                return true;
            }
        }
        return false;
    }

    /**
     * check if user us allowed to access to this entity
     * @return bool|int
     */
    protected function checkToken(){
        $headers = getallheaders();
        $token = $headers['Authorization'];

        $correctToken = false;
        if( $token != '' ){
            $auxUser = new User();
            if( $auxUser->loadWithToken($token) ){
                // petitions with token (where users must be logged in)
                $this->user = $auxUser;
                $correctToken = true;
            }else{
                // petitions without token
                $config = Config::getInstance();
                $webserviceConfig = $config->get('webservice');
                $defaultToken = $webserviceConfig['default_token'];
                $entitiesWithoutToken = $webserviceConfig['entities_without_token'];
                if( !$correctToken && in_array($this->parts[0], $entitiesWithoutToken) ){
                    if( $defaultToken == $token ){
                        $correctToken = true;
                    }
                }
            }
        }

        if( !$correctToken ){
            return 401;
        }
        return false;
    }

    abstract protected function run();

}
