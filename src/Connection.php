<?php

namespace Finesse\MicroDB;

use Finesse\MicroDB\Exceptions\InvalidArgumentException;
use Finesse\MicroDB\Exceptions\PDOException;
use PDOException as BasePDOException;

/**
 * Wraps a PDO object for more convenient usage.
 *
 * @author Surgie
 */
class Connection
{
    /**
     * @var \PDO The PDO instance. Throws exceptions on errors. The default fetch mode is FETCH_ASSOC.
     */
    protected $pdo;

    /**
     * Connection constructor.
     *
     * @param \PDO $pdo A PDO instance to work with. The given object WILL BE MODIFIED. You MUST NOT MODIFY the given
     *     object.
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
    }

    /**
     * Creates the self instance.
     *
     * @param array ...$pdoArgs Arguments for the PDO constructor
     * @return static
     * @throws PDOException
     * @see http://php.net/manual/en/pdo.construct.php Arguments reference
     */
    public static function create(...$pdoArgs): self
    {
        try {
            return new static(new \PDO(...$pdoArgs));
        } catch (\Throwable $exception) {
            throw static::wrapException($exception);
        }
    }

    /**
     * Performs a select query and returns the query results.
     *
     * @param string $query Full SQL query
     * @param array $values Values to bind. The indexes are the names or numbers of the values.
     * @return array[] Array of the result rows. Result row is an array indexed by columns.
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public function select(string $query, array $values = []): array
    {
        try {
            return $this->executeQuery($query, $values)->fetchAll();
        } catch (\Throwable $exception) {
            throw static::wrapException($exception, $query, $values);
        }
    }

    /**
     * Performs a select query and returns the first query result.
     *
     * @param string $query Full SQL query
     * @param array $values Values to bind. The indexes are the names or numbers of the values.
     * @return array|null An array indexed by columns. Null if nothing is found.
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public function selectFirst(string $query, array $values = [])
    {
        try {
            $row = $this->executeQuery($query, $values)->fetch();
            return $row === false ? null : $row;
        } catch (\Throwable $exception) {
            throw static::wrapException($exception, $query, $values);
        }
    }

    /**
     * Performs an insert query and returns the number of inserted rows.
     *
     * @param string $query Full SQL query
     * @param array $values Values to bind. The indexes are the names or numbers of the values.
     * @return int
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public function insert(string $query, array $values = []): int
    {
        try {
            return $this->executeQuery($query, $values)->rowCount();
        } catch (\Throwable $exception) {
            throw static::wrapException($exception, $query, $values);
        }
    }

    /**
     * Performs an insert query and returns the identifier of the last inserted row.
     *
     * @param string $query Full SQL query
     * @param array $values Values to bind. The indexes are the names or numbers of the values.
     * @param string|null $sequence Name of the sequence object from which the ID should be returned
     * @return int|string
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public function insertGetId(string $query, array $values = [], string $sequence = null)
    {
        try {
            $this->executeQuery($query, $values);
            $id = $this->pdo->lastInsertId($sequence);
            return is_numeric($id) ? (int)$id : $id;
        } catch (\Throwable $exception) {
            throw static::wrapException($exception, $query, $values);
        }
    }

    /**
     * Performs an update query.
     *
     * @param string $query Full SQL query
     * @param array $values Values to bind. The indexes are the names or numbers of the values.
     * @return int The number of updated rows
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public function update(string $query, array $values = []): int
    {
        try {
            return $this->executeQuery($query, $values)->rowCount();
        } catch (\Throwable $exception) {
            throw static::wrapException($exception, $query, $values);
        }
    }

    /**
     * Performs a delete query.
     *
     * @param string $query Full SQL query
     * @param array $values Values to bind. The indexes are the names or numbers of the values.
     * @return int The number of deleted rows
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public function delete(string $query, array $values = []): int
    {
        try {
            return $this->executeQuery($query, $values)->rowCount();
        } catch (\Throwable $exception) {
            throw static::wrapException($exception, $query, $values);
        }
    }

    /**
     * Performs a general query.
     *
     * @param string $query Full SQL query
     * @param array $values Values to bind. The indexes are the names or numbers of the values.
     * @throws InvalidArgumentException
     * @throws PDOException
     */
    public function statement(string $query, array $values = [])
    {
        try {
            $this->executeQuery($query, $values);
        } catch (\Throwable $exception) {
            throw static::wrapException($exception, $query, $values);
        }
    }

    /**
     * Returns the used PDO instance.
     *
     * @return \PDO You MUST NOT MODIFY it
     */
    public function getPDO(): \PDO
    {
        return $this->pdo;
    }

    /**
     * Executes a SQL query and returns the corresponding PDO statement.
     *
     * @param string $query Full SQL query
     * @param array $values Values to bind. The indexes are the names or numbers of the values.
     * @return \PDOStatement
     * @throws InvalidArgumentException
     * @throws BasePDOException
     */
    protected function executeQuery(string $query, array $values = []): \PDOStatement
    {
        $statement = $this->pdo->prepare($query);
        $this->bindValues($statement, $values);
        $statement->execute();
        return $statement;
    }

    /**
     * Binds parameters to a PDO statement.
     *
     * @param \PDOStatement $statement PDO statement
     * @param array $values Parameters. The indexes are the names or numbers of the values.
     * @throws InvalidArgumentException
     * @throws BasePDOException
     */
    protected function bindValues(\PDOStatement $statement, array $values)
    {
        $number = 1;

        foreach ($values as $name => $value) {
            $this->bindValue($statement, is_string($name) ? $name : $number, $value);
            $number += 1;
        }
    }

    /**
     * Binds a value to a PDO statement.
     *
     * @param \PDOStatement $statement PDO statement
     * @param string|int $name Value placeholder name or index (if the placeholder is not named)
     * @param string|int|float|boolean|null $value Value to bind
     * @throws InvalidArgumentException
     * @throws BasePDOException
     */
    protected function bindValue(\PDOStatement $statement, $name, $value)
    {
        if ($value !== null && !is_scalar($value)) {
            throw new InvalidArgumentException(sprintf(
                'Bound value %s expected to be scalar or null, a %s given',
                is_int($name) ? '#'.$name : '`'.$name.'`',
                gettype($value)
            ));
        }

        if ($value === null) {
            $type = \PDO::PARAM_NULL;
        } elseif (is_bool($value)) {
            $type = \PDO::PARAM_BOOL;
        } elseif (is_integer($value)) {
            $type = \PDO::PARAM_INT;
        } else {
            $type = \PDO::PARAM_STR;
        }

        $statement->bindValue($name, $value, $type);
    }

    /**
     * Creates a library exception from a PHP exception if possible.
     *
     * @param \Throwable $exception
     * @param string|null $query SQL query which caused the error (if caused by a query)
     * @param array|null $values Bound values (if caused by a query)
     * @return IException|\Throwable
     */
    protected static function wrapException(
        \Throwable $exception,
        string $query = null,
        array $values = null
    ): \Throwable {
        if ($exception instanceof BasePDOException) {
            return PDOException::wrapBaseException($exception, $query, $values);
        }

        return $exception;
    }
}
