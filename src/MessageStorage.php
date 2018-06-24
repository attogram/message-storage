<?php
declare(strict_types = 1);

namespace Attogram;

use Exception;
use PDO;
use Throwable;
use function gmdate;
use function implode;
use function in_array;
use function is_writable;

/**
 * Attogram Message Storage
 */
class MessageStorage
{
    /** @var array - message table columns */
    private $columns = [
        'message',
        'status',
        'tag',
        'uri',
        'server',
        'time',
        'ip',
        'agent',
    ];

    /** @var PDO|bool - database PDO object, or false on error */
    private $database;

    /** @var array - list of errors */
    private $errors = [];

    /**
     * @param string $databaseFile
     */
    public function __construct(string $databaseFile = '')
    {
        $this->initDatabase($databaseFile);
    }

    /**
     * @return bool
     */
    public function isAlive(): bool
    {
        if ($this->database) {
            return true;
        }

        return false;
    }

    /**
     * @param string $message - message content
     * @param string $tag     - optional
     * @return array          - return array of saved data
     *                          or empty array on error
     */
    public function save(string $message, string $tag = ''): array
    {
        $sql = 'INSERT INTO messages (' . implode(', ', $this->columns) . ') '
            . 'VALUES (:' . implode(', :', $this->columns) . ')';
        $bind = [
            ':message' => $message,
            ':time' => gmdate('Y-m-d H:i:s'),
            ':ip' => $this->getServerVar('REMOTE_ADDR'),
            ':agent' => $this->getServerVar('HTTP_USER_AGENT'),
            ':tag' => $tag,
            ':uri' => $this->getServerVar('REQUEST_URI'),
            ':server' => $this->getServerVar('SERVER_NAME'),
            ':status' => 'new',
        ];
        $result = $this->queryBool($sql, $bind);
        if (!$result) {
            return [];
        }
        $bind[':id'] = $this->database->lastInsertId();

        return $bind;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param string $databaseFile
     */
    private function initDatabase(string $databaseFile)
    {
        $this->database = false;
        if (!in_array('sqlite', PDO::getAvailableDrivers())) {
            $this->errors[] = 'initDatabase: sqlite PDO drive not available';

            return;
        }
        if (empty($databaseFile)) {
            $this->errors[] = 'initDatabase: Database file not defined';

            return;
        }
        if (!is_writable($databaseFile)) {
            $this->createDatabase($databaseFile);
        }
        if (!is_writable($databaseFile)) {
            $this->errors[] = 'initDatabase: Create Database failed: ' . $databaseFile;

            return;
        }
        try {
            $this->database = new PDO('sqlite:' . $databaseFile);
            if (!$this->database instanceof PDO) {
                throw new Exception('Database not instanceof PDO');
            }
        } catch (Throwable $error) {
            $this->database = false;
            $this->errors[] = 'initDatabase: ' . $error->getMessage();

            return;
        }
        if ($this->hasTable()) {
            return;
        }
        if ($this->createTable()) {
            return;
        }
        $this->errors[] = 'initDatabase: Can Not Create Table: messages';
        $this->database = false;

        return;
    }

    /**
     * @param string $databaseFile
     * @return bool
     */
    private function createDatabase(string $databaseFile): bool
    {
        @touch($databaseFile);
        if (is_writable($databaseFile)) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    private function hasTable(): bool
    {
        $hasTable = $this->queryArray(
            'SELECT name FROM sqlite_master WHERE type = "table" AND name = "messages"'
        );
        if (!empty($hasTable[0]['name']) && $hasTable[0]['name'] == 'messages') {
            return true;
        }

        return false;
    }

    /**
     * @return array|bool
     */
    private function createTable() {
        $sql = "CREATE TABLE 'messages' ('id' INTEGER PRIMARY KEY";
        foreach ($this->columns as $column) {
            $sql .= ", '$column' TEXT";
        }
        $sql .= ')';
        if ($this->queryBool($sql)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $sql
     * @param array $bind
     * @return array
     */
    private function queryArray(string $sql, array $bind = [])
    {
        $statement = $this->queryExecute($sql, $bind);
        if (!$statement) {
            return [];
        }
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($result || $this->database->errorCode() == '00000') {
            return $result;
        }
        $this->errors[] = 'queryArray: '
            . implode(', ', $this->database->errorInfo());

        return [];
    }

    /**
     * @param $sql
     * @param array $bind
     * @return array|bool
     */
    private function queryBool(string $sql, array $bind = [])
    {
        if ($this->queryExecute($sql, $bind)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $sql
     * @param array $bind
     * @return bool|\PDOStatement
     */
    private function queryExecute(string $sql, array $bind = [])
    {
        if (!$this->isAlive()) {
            $this->errors[] = 'queryExecute: database not alive';

            return false;
        }
        $statement = $this->database->prepare($sql);
        if (!$statement) {
            $this->errors[] = 'queryExecute: prepare failed: '
                . implode(', ', $this->database->errorInfo());

            return false;
        }
        foreach ($bind as $name => $value) {
            $statement->bindValue($name, $value);
        }
        $result = $statement->execute();
        if ($result) {
            return $statement;
        }
        $this->errors[] = 'queryExecute: execute failed: '
            . implode(', ', $this->database->errorInfo());

        return false;
    }

    /**
     * @param string $var
     * @return string
     */
    private function getServerVar(string $var): string
    {
        if (!empty($_SERVER[$var])) {
            return $_SERVER[$var];
        }

        return '';
    }
}
