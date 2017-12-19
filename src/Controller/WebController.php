<?php

namespace Webservice\Controller;

use Core\Controller\Controller;
use Core\Utils\Config;

abstract class WebController extends Controller {

    protected $openAppDeepLink = '';
    protected $googlePlay = '';
    protected $appStore = '';

    public function __construct() {
        parent::__construct();

        $config = Config::getInstance();
        $webserviceConfig = $config->get('webservice');
        $this->openAppDeepLink = $webserviceConfig['url_scheme'] . 'open';
        $this->googlePlay = $webserviceConfig['google_play'];
        $this->appStore = $webserviceConfig['app_store'];
    }

    public function build() {
        $this->assign('open_app', $this->openAppDeepLink);
        $this->assign('google_play', $this->googlePlay);
        $this->assign('app_store', $this->appStore);

        $this->run();
    }

    abstract protected function run();

}