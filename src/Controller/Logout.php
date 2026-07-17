<?php

namespace Webservice\Controller;

use Core\Routing\Attribute\Route;
use Webservice\Model\UserToken;

#[Route('/logout', methods: ['POST'], name: 'webservice.logout')]
class Logout extends WebserviceController
{

    protected function run(): void
    {
        (new UserToken())->revoke($this->token);
    }

}
