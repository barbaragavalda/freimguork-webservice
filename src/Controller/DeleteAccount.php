<?php

namespace Webservice\Controller;

use Core\Routing\Attribute\Route;
use Webservice\Model\User;
use Webservice\Model\UserToken;

#[Route('/account', methods: ['DELETE'], name: 'webservice.account.delete')]
class DeleteAccount extends WebserviceController
{

    protected function run(): void
    {
        $userID = $this->user->getID();

        // revoke every device token first - same composition pattern as
        // Register's register() + UserToken::issue(), kept in the
        // controller rather than inside User::delete() itself
        (new UserToken())->revokeAllForUser($userID);
        (new User())->delete($userID);
    }

}
