<?php

namespace Webservice\Model;

use Core\Model\Model;
use PDO;

/**
 * pending email-change confirmation - same shape as PasswordReset (6-digit
 * code, 15 min TTL, capped attempts), but also stages the requested new
 * email itself (see Controller\ForgotPassword's sibling design). Prevents
 * a stolen/shared-device session token from permanently hijacking an
 * account by pointing it at an attacker-controlled address: the change
 * only takes effect once the *new* address proves it received the code.
 */
class EmailChange extends Model
{

    private const int CODE_TTL_SECONDS = 900; // 15 minutes
    private const int MAX_ATTEMPTS     = 5;

    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * generates a fresh 6-digit code for $idUser's request to change to
     * $newEmail, invalidating any previous pending request for this user,
     * and returns the plaintext code to be emailed to $newEmail - never
     * stored or logged itself, only its hash
     */
    public function create(int $idUser, string $newEmail): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->deleteForUser($idUser);

        $sql    = '
            INSERT INTO email_change (id_user, new_email, code, expires_at)
            VALUES (:id_user, :new_email, :code, :expires_at)
        ';
        $params = array(
            'id_user'    => array('value' => $idUser, 'type' => PDO::PARAM_INT),
            'new_email'  => array('value' => $newEmail, 'type' => PDO::PARAM_STR),
            'code'       => array('value' => self::hash($code), 'type' => PDO::PARAM_STR),
            'expires_at' => array(
                'value' => date('Y-m-d H:i:s', time() + self::CODE_TTL_SECONDS),
                'type'  => PDO::PARAM_STR,
            ),
        );
        $this->mysql->query($sql, $params);

        return $code;
    }

    /**
     * validates $code for $idUser and, on success, returns the pending new
     * email - single-use (the row is deleted either way) and locked out
     * after MAX_ATTEMPTS wrong guesses, same reasoning as PasswordReset::
     * redeem(). Returns null on any failure (nothing pending/expired/too
     * many attempts/wrong code), deliberately not distinguishing which
     */
    public function redeem(int $idUser, string $code): ?string
    {
        $row = $this->find($idUser);
        if ($row === null || (int) $row['attempts'] >= self::MAX_ATTEMPTS || strtotime($row['expires_at']) <= time()) {
            return null;
        }

        if (!hash_equals($row['code'], self::hash($code))) {
            $this->incrementAttempts($idUser);
            return null;
        }

        $newEmail = $row['new_email'];
        $this->deleteForUser($idUser);
        return $newEmail;
    }

    private function find(int $idUser): ?array
    {
        $sql    = '
            SELECT *
            FROM email_change
            WHERE id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value' => $idUser, 'type' => PDO::PARAM_INT),
        );
        $rows   = $this->mysql->query($sql, $params);
        return $rows[0] ?? null;
    }

    private function incrementAttempts(int $idUser): void
    {
        $sql    = '
            UPDATE email_change
            SET attempts = attempts + 1
            WHERE id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value' => $idUser, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);
    }

    private function deleteForUser(int $idUser): void
    {
        $sql    = '
            DELETE FROM email_change
            WHERE id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value' => $idUser, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);
    }

}
