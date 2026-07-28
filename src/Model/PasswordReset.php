<?php

namespace Webservice\Model;

use Core\Model\Model;
use PDO;

class PasswordReset extends Model
{

    private const int CODE_TTL_SECONDS = 900; // 15 minutes
    private const int MAX_ATTEMPTS     = 5;

    /**
     * same reasoning as UserToken::hash() - only the hash ever touches the
     * database, but unlike a session token a 6-digit code has real
     * brute-force surface, which is why redeem() also tracks attempts
     */
    public static function hash(string $code): string
    {
        return hash('sha256', $code);
    }

    /**
     * generates a fresh 6-digit code for $idUser, invalidating any
     * previous one (a user only ever has one active code at a time), and
     * returns the plaintext code to be emailed - it's never stored or
     * logged anywhere itself, only its hash
     */
    public function create(int $idUser): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->deleteForUser($idUser);

        $sql    = '
            INSERT INTO user_password_reset (id_user, code, expires_at)
            VALUES (:id_user, :code, :expires_at)
        ';
        $params = array(
            'id_user'    => array('value' => $idUser, 'type' => PDO::PARAM_INT),
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
     * true if $code is currently valid for $idUser - single-use (the row
     * is deleted on success) and locked out after MAX_ATTEMPTS wrong
     * guesses, bounding the brute-force risk a 6-digit code alone wouldn't
     */
    public function redeem(int $idUser, string $code): bool
    {
        $row = $this->find($idUser);
        if ($row === null || (int) $row['attempts'] >= self::MAX_ATTEMPTS || strtotime($row['expires_at']) <= time()) {
            return false;
        }

        if (!hash_equals($row['code'], self::hash($code))) {
            $this->incrementAttempts($idUser);
            return false;
        }

        $this->deleteForUser($idUser);
        return true;
    }

    private function find(int $idUser): ?array
    {
        $sql    = '
            SELECT *
            FROM user_password_reset
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
            UPDATE user_password_reset
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
            DELETE FROM user_password_reset
            WHERE id_user = :id_user
        ';
        $params = array(
            'id_user' => array('value' => $idUser, 'type' => PDO::PARAM_INT),
        );
        $this->mysql->query($sql, $params);
    }

}
