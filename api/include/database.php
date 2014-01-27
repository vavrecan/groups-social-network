<?php

class DatabaseException extends Exception {
    public function __construct($pdoErrorInfo) {
        $message = "";

        // pdo error info at position 2 contains some useful message
        // http://php.net/manual/en/pdo.errorinfo.php
        if (isset($pdoErrorInfo[2]))
            $message = (string)$pdoErrorInfo[2];

        parent::__construct($message);
    }
}

class Database
{
    /**
     * @var PDO
     */
    protected $db = null;

    protected $dns;
    protected $user;
    protected $password;

    public function __construct($dns, $user, $password) {
        $this->dns = $dns;
        $this->user = $user;
        $this->password = $password;
    }

    public function connect() {
        if (is_null($this->db)) {
            $this->db = new PDO($this->dns, $this->user, $this->password,
                array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES \'UTF8\'', PDO::ATTR_PERSISTENT => true));

            //  paranoid, huh, we will not need these anymore
            unset($this->user);
            unset($this->password);
        }
    }

    public function isConnected() {
        return !is_null($this->db);
    }

    public function exec($sql, $params = null, $returnRowCount = false) {
        $statement = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        if (!($return = $statement->execute($params))) {
            throw new DatabaseException($statement->errorInfo());
        }

        if ($returnRowCount)
            return $statement->rowCount();

        return $return;
    }

    public function lastInsertId($name = null) {
        return $this->db->lastInsertId($name);
    }

    public function fetch($sql, $params = array()) {
        $statement = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        if (!$statement->execute($params)) {
            throw new DatabaseException($statement->errorInfo());
        }

        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    public function fetchObject($class, $sql, $params = array()) {
        $statement = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        if (!$statement->execute($params)) {
            throw new DatabaseException($statement->errorInfo());
        }

        return $statement->fetchObject($class);
    }

    public function fetchColumn($sql, $params = null) {
        $statement = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        if (!$statement->execute($params)) {
            throw new DatabaseException($statement->errorInfo());
        }

        return $statement->fetchColumn();
    }

    public function fetchAll($sql, $params = array()) {
        $statement = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        if (!$statement->execute($params)) {
            throw new DatabaseException($statement->errorInfo());
        }

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function fetchAllColumn($sql, $params = null) {
        $statement = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        if (!$statement->execute($params)) {
            throw new DatabaseException($statement->errorInfo());
        }

        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public function fetchAllObject($class, $sql, $params = null) {
        $statement = $this->db->prepare($sql, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));

        if (!$statement->execute($params)) {
            throw new DatabaseException($statement->errorInfo());
        }

        return $statement->fetchAll(PDO::FETCH_CLASS, $class);
    }

    public function beginTransaction() {
        $this->db->beginTransaction();
    }

    public function rollBack() {
        $this->db->rollBack();
    }

    public function commit() {
        $this->db->commit();
    }

    public function inTransaction() {
        return $this->db->inTransaction();
    }
}