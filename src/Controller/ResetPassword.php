<?php

namespace Webservice\Controller;

use Core\Routing\Attribute\Route;
use Webservice\Model\PasswordReset;
use Webservice\Model\User;
use Webservice\Model\UserToken;

#[Route('/password/reset', methods: ['POST'], name: 'webservice.password.reset')]
class ResetPassword extends WebserviceController
{

    protected function requiresUserToken(): bool
    {
        return false;
    }

    protected function run(): void
    {
        $email       = trim((string) ($_POST['email'] ?? ''));
        $code        = trim((string) ($_POST['code'] ?? ''));
        $newPassword = (string) ($_POST['password'] ?? '');

        if (!$email || !$code || !$newPassword) {
            $this->error = $this->translate('All fields are required.');
            return;
        }

        $user = new User();
        if (!$user->loadWithEmail($email) || !(new PasswordReset())->redeem($user->getID(), $code)) {
            $this->error = $this->translate('Invalid or expired code.');
            return;
        }

        $user->updatePassword($newPassword);
        // the account may have been compromised (that's often *why* someone
        // forgets their password) - force every device to log in again
        // with the new password rather than trusting existing sessions
        (new UserToken())->revokeAllForUser($user->getID());
    }

}
