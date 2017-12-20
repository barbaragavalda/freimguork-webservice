# README #

Webservice module for AppAqui projects.

## How do I get set up? ##
1. Follow the instructions of ihnstallations for freimguork-base.
2. This module does not work by himself, it needs extra configurations, routings and classes in order to work.
    1. Webservice configuration is mandatory. Place a config file with the following format:

    ```
    $config = array(
        'webservice' => array(
        	'app_name'                  => 'Your app name',
        	'webservice_web'            => 'http://url-to-webservice.com',
        	'google_play'               => 'http://url-to-google-play.com',
        	'app_store'                 => 'http://url-to-app-store.com',
        	'url_scheme'                => 'your-url-scheme://',
        
            'default_token'     => 'DeFaUlTtOkEn',
            'public_entities'   => array('signin', 'signup', 'forgot')
        )
    );
    ```
    2. Rounting and classes are up to you and the specification of your API.

## Who do I talk to? ##
Bàrbara Gavaldà, bgavalda@appaqui.com
AppAquí