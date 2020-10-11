<?php
namespace Shahnewaz\RedprintNg\Services;

use Shahnewaz\RedprintNg\Database;

class RedprintService
{

    protected $database;

    public function __construct()
    {
        $this->database = new Database;
    }
    /**
     * Returns an array containing supported data types
     * Should reflect currently supported data types by Eloquent ORM
     * @return Array
     * */
    public function dataTypes()
    {
        return $this->database->dataTypes();
    }

    public function timeTypes()
    {
        return $this->database->timeTypes();
    }

    public function numericTypes()
    {
        return $this->database->numericTypes();
    }

    public function integerTypes()
    {
        return $this->database->integerTypes();
    }

    public function incrementTypes()
    {
        return $this->database->incrementTypes();
    }

    public function longTextDataTypes()
    {
        return $this->database->longTextDataTypes();
    }

    public function relationshipIdentifiers()
    {
        return $this->database->relationshipIdentifiers();
    }
}
