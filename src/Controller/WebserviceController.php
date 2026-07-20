<?php

namespace Webservice\Controller;

use Core\Controller\CacheManager;
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

    protected ?string $token = null;

    protected bool|string $error = false;

    public function __construct(Config $config, CacheManager $modelCache)
    {
        parent::__construct($config, $modelCache);

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
        // Apache's front-controller .htaccess does an internal redirect to
        // index.dev.php/index.php, which renames any already-set
        // HTTP_AUTHORIZATION env var to REDIRECT_HTTP_AUTHORIZATION - a well
        // known Apache + mod_rewrite quirk, see
        // https://github.com/symfony/symfony/issues/19693 for the same issue
        // in another framework.
        if ($token === false && array_key_exists('REDIRECT_HTTP_AUTHORIZATION', $_SERVER)) {
            $token = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
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
                $this->token  = $token;
                $correctToken = true;
            } elseif ($defaultToken == $token && !$this->requiresUserToken()) {
                // the app's own shared secret is enough for a controller
                // that declares it doesn't need a user token yet (e.g.
                // Register/Login)
                $correctToken = true;
            }
        }

        if (!$correctToken) {
            return 401;
        }
        return false;
    }

    /**
     * true (the default) if this entity needs a logged-in user's own token;
     * override to false for entities that can't have one yet, like
     * Register/Login - keeps that fact next to the controller it describes
     * instead of a project having to remember to list it in its own config
     */
    protected function requiresUserToken(): bool
    {
        return true;
    }

    abstract protected function run(): void;

}
