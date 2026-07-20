<?php

namespace Webservice\Controller;

use Core\Routing\Attribute\Route;
use Webservice\Model\User;
use Webservice\Model\UserToken;

#[Route('/register', methods: ['POST'], name: 'webservice.register')]
class Register extends WebserviceController
{

    protected function requiresUserToken(): bool
    {
        return false;
    }

    protected function run(): void
    {
        $email    = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $name     = trim((string) ($_POST['name'] ?? ''));

        if (!$email || !$password || !$name) {
            $this->error = _('All fields are required.');
            return;
        }

        $user = new User();
        $id   = $user->register($email, $password, $name);
        if (!$id) {
            $this->error = _('That email is already registered.');
            return;
        }

        $token = (new UserToken())->issue($id);
        $this->assign('token', $token);
    }

}
