<?php

namespace Webservice\Controller;

use Core\Controller\CacheManager;
use Core\Routing\Attribute\Route;
use Core\Utils\Config;
use Core\Utils\Language;
use Webservice\Model\User;
use Webservice\Model\UserToken;

#[Route('/register', methods: ['POST'], name: 'webservice.register')]
class Register extends WebserviceController
{

    public function __construct(Config $config, CacheManager $modelCache, private readonly Language $language)
    {
        parent::__construct($config, $modelCache);
    }

    protected function requiresUserToken(): bool
    {
        return false;
    }

    protected function run(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $username = trim((string) ($_POST['username'] ?? ''));

        if (!$email || !$password || !$username) {
            $this->error = $this->translate('All fields are required.');
            return;
        }
        if (!preg_match(User::USERNAME_PATTERN, $username)) {
            $this->error = $this->translate(
                'Username must be 3-20 characters long and contain only letters, numbers, "_" or "."'
            );
            return;
        }

        $user = new User();
        // checked separately (rather than just relying on register()'s own
        // internal check) so each collision gets its own clear message
        if ($user->loadWithEmail($email)) {
            $this->error = $this->translate('That email is already registered.');
            return;
        }
        if ($user->loadWithUsername($username)) {
            $this->error = $this->translate('That username is already taken.');
            return;
        }

        // stored so a later notification (e.g. Controller\ForgotPassword's
        // reset email) can be sent in the language this user registered
        // with - see User::register()'s own docblock
        $idAppacmanLang = $this->language->getLanguageID($this->config->getLanguage());

        $id = $user->register($email, $password, $username, $idAppacmanLang);
        if (!$id) {
            $this->error = $this->translate('Registration failed.');
            return;
        }

        $token = (new UserToken())->issue($id);
        $this->assign('token', $token);
    }

}
