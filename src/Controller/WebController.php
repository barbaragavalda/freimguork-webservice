<?php

namespace Webservice\Controller;

use Core\Controller\Controller;
use Core\Utils\Config;

abstract class WebController extends Controller {

    protected $urlScheme = '';
    protected $openAppDeepLink = '';
    protected $googlePlay = '';
    protected $appStore = '';

    public function __construct() {
        parent::__construct();

        $config = Config::getInstance();
        $webserviceConfig = $config->get('webservice');
        $this->urlScheme = $webserviceConfig['url_scheme'];
        $this->openAppDeepLink = $this->urlScheme . 'open';
        $this->googlePlay = $webserviceConfig['google_play'];
        $this->appStore = $webserviceConfig['app_store'];
    }

    public function build() {
        $this->assign('url_scheme', $this->urlScheme);
        $this->assign('open_app', $this->openAppDeepLink);
        $this->assign('google_play', $this->googlePlay);
        $this->assign('app_store', $this->appStore);

        $this->run();
    }

    abstract protected function run();

}