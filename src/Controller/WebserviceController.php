<?php

namespace Webservice\Controller;

use Core\Controller\Controller;
use Core\Utils\Config;
use Webservice\Model\App;
use Webservice\Model\User;

abstract class WebserviceController extends Controller
{

    const string GET    = 'GET';
    const string POST   = 'POST';
    const string DELETE = 'DELETE';

    protected string $method;

    private ?App $app = null;

    protected ?User $user = null;

    protected bool|string $error = false;

    public function __construct()
    {
        parent::__construct();

        $this->method = $_SERVER['REQUEST_METHOD'];
        $headers      = getallheaders();
        if (array_key_exists('X-Http-Method-Override', $headers)) {
            $this->method = $headers['X-Http-Method-Override'];
        }
    }

    /**
     * all entities pass through this method,
     * the result is always a JSON object
     */
    public function build(): void
    {
        $error = false;
        $this->removeInfo();

        $this->app = new App();
        if (count($this->parts) && $this->parts[0] == 'environment') {
            $this->run();
        } else {
            if (!$this->checkMaintenance() && !$this->checkAppVersion()) {
                $error = $this->checkToken();

                if ($error === false) {
                    $this->run();
                }
            }
        }

        if ($this->error !== false) {
            $error = $this->error;
        }
        $this->assign('error', $error);
        $this->json();
    }

    /**
     * is maintenance on?
     * @return bool
     */
    private function checkMaintenance(): bool
    {
        if ($maintenance = $this->app->onMaintenance()) {
            $this->assign('maintenance', _($maintenance));
            return true;
        }
        return false;
    }

    /**
     * should update?
     * @return bool
     */
    private function checkAppVersion(): bool
    {
        if (isset($_REQUEST['app_platform']) && isset($_REQUEST['app_version'])) {
            if ($this->app->shouldUpdate($_REQUEST['app_platform'], $_REQUEST['app_version'])) {
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
    protected function checkToken(): bool|int
    {
        $headers = getallheaders();
        $token   = false;
        if (array_key_exists('Authorization', $headers)) {
            $token = $headers['Authorization'];
        }
        // duplicate Authorization just in case server does not allow normal Authorization
        if (array_key_exists('Authorization-Alias', $headers)) {
            $token = $headers['Authorization-Alias'];
        }

        $correctToken = false;
        if ($token != '') {
            $auxUser          = new User();
            $config           = Config::getInstance();
            $webserviceConfig = $config->get('webservice');
            $defaultToken     = $webserviceConfig['default_token'];

            if ($token != $defaultToken && $auxUser->loadWithToken($token)) {
                // petitions with token (where users must be logged in)
                $this->user   = $auxUser;
                $correctToken = true;
            } else {
                // petitions without token
                $entitiesWithoutToken = $webserviceConfig['entities_without_token'];
                if (count($this->parts) && in_array($this->parts[0], $entitiesWithoutToken)) {
                    if ($defaultToken == $token) {
                        $correctToken = true;
                    }
                }
            }
        }

        if (!$correctToken) {
            return 401;
        }
        return false;
    }

    abstract protected function run(): void;

}
