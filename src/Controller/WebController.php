<?php

namespace Webservice\Controller;

use Core\Controller\CacheManager;
use Core\Controller\Controller;
use Core\Utils\Config;

abstract class WebController extends Controller
{

    protected string $urlScheme       = '';
    protected string $openAppDeepLink = '';
    protected string $googlePlay      = '';
    protected string $appStore        = '';

    public function __construct(Config $config, CacheManager $modelCache)
    {
        parent::__construct($config, $modelCache);

        $webserviceConfig      = $this->config->get('webservice');
        $this->urlScheme       = $webserviceConfig['url_scheme'];
        $this->openAppDeepLink = $this->urlScheme . 'open';
        $this->googlePlay      = $webserviceConfig['google_play'];
        $this->appStore        = $webserviceConfig['app_store'];
    }

    public function build(): void
    {
        $this->assign('url_scheme', $this->urlScheme);
        $this->assign('open_app', $this->openAppDeepLink);
        $this->assign('google_play', $this->googlePlay);
        $this->assign('app_store', $this->appStore);

        $protocol = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://';
        $this->assign('canonical', $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

        $this->run();
    }

    abstract protected function run(): void;

}