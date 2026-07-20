<?php

namespace Webservice\Tests\Fixtures;

use Core\Model\MySQL\PDO;

/**
 * Class FixturePdo
 * a real (not mocked) Core\Model\MySQL\PDO subclass returning canned rows
 * for query(), in order, one array per call - so a test can drive a Model
 * through a specific sequence of DB results (e.g. "email not found, then
 * insert succeeds, then re-load finds the new row") without a real
 * database connection. Also records every query()/getState() call for
 * assertions.
 */
class FixturePdo extends PDO
{

    /** @var array<array<array<string, mixed>>> */
    private array $rowsQueue;

    private bool $state;

    private string|false $lastInsertId;

    /** @var array<array{sql: string, params: array}> */
    public array $queries = array();

    /**
     * @param array<array<array<string, mixed>>> $rowsQueue one row-set per
     *                                                       expected query() call, consumed in order
     */
    public function __construct(array $rowsQueue = array(), bool $state = true, string|false $lastInsertId = '1')
    {
        parent::__construct(array(), false);
        $this->rowsQueue    = $rowsQueue;
        $this->state        = $state;
        $this->lastInsertId = $lastInsertId;
    }

    public function query(string $sql, array $params = array()): array
    {
        $this->queries[] = array('sql' => $sql, 'params' => $params);
        return array_shift($this->rowsQueue) ?? array();
    }

    public function getState(): bool
    {
        return $this->state;
    }

    public function lastInsertId(): string|false
    {
        return $this->lastInsertId;
    }

}
