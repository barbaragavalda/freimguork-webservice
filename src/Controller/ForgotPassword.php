<?php

namespace Webservice\Controller;

use Core\Controller\CacheManager;
use Core\Model\Utils\Mail;
use Core\Routing\Attribute\Route;
use Core\Utils\Config;
use Core\Utils\Language;
use Throwable;
use Webservice\Model\PasswordReset;
use Webservice\Model\User;

#[Route('/password/forgot', methods: ['POST'], name: 'webservice.password.forgot')]
class ForgotPassword extends WebserviceController
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
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!$email) {
            $this->error = $this->translate('Email is required.');
            return;
        }

        // deliberately the exact same response whether or not $email is
        // actually registered - anything else turns this endpoint into an
        // account-enumeration oracle
        $user = new User();
        if ($user->loadWithEmail($email)) {
            $code           = (new PasswordReset())->create($user->getID());
            $idAppacmanLang = $user->getInfo()['id_appacman_lang'] ?? null;
            $this->sendCode($user->getInfo()['email'], $code, $user->getID(), $idAppacmanLang);
        }
    }

    /**
     * sent in the language $idAppacmanLang resolves to (the one the
     * recipient registered with, see User::register()) rather than
     * whatever language this particular request happens to carry - a
     * password-reset request is often made from an unfamiliar device/
     * browser with no reason to share the account's own locale
     */
    private function sendCode(string $email, string $code, int $idUser, ?int $idAppacmanLang): void
    {
        $culture = $this->language->getCulture($idAppacmanLang);

        [$subject, $body] = $this->language->withCulture($culture, function () use ($code) {
            return array(
                $this->translate('Your password reset code'),
                $this->translate('Your password reset code is:') . ' <strong>' . $code . '</strong><br>'
                . $this->translate('It expires in 15 minutes.'),
            );
        });

        try {
            (new Mail())->send(
                array(),
                array(array('email' => $email, 'name' => '')),
                $subject,
                $body
            );
        } catch (Throwable $e) {
            // never surface a mail-sending/config problem to the client -
            // same "always the same response" reasoning as above; most
            // likely cause locally is config/mail.php not set up yet
            error_log('ForgotPassword: failed to send reset email - ' . $e->getMessage());
        }

        if (IS_DEV) {
            // config/mail.php isn't necessarily set up on every dev
            // machine - logging the code here (never in the HTTP response)
            // keeps this endpoint fully testable without real SMTP
            error_log("ForgotPassword[dev]: reset code for user $idUser is $code");
        }
    }

}
