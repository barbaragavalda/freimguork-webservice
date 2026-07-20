<?php

namespace Webservice\Controller;

use Core\Routing\Attribute\Route;
use Webservice\Model\User;
use Webservice\Model\UserToken;

#[Route('/login', methods: ['POST'], name: 'webservice.login')]
class Login extends WebserviceController
{

    protected function requiresUserToken(): bool
    {
        return false;
    }

    protected function run(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $user = new User();
        if (!$email || !$password || !$user->authenticate($email, $password)) {
            $this->error = _('Invalid email or password.');
            return;
        }

        $token = (new UserToken())->issue($user->getInfo()['id_user']);
        $this->assign('token', $token);
    }

}
