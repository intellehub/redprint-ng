<?php

namespace Shahnewaz\RedprintNg\Enums;

enum DataTypes: string 
{
    // String Types
    case STRING = 'string';
    case TEXT = 'text';
    case MEDIUMTEXT = 'mediumText';
    case LONGTEXT = 'longText';
    case CHAR = 'char';

    // Numeric Types
    case INTEGER = 'integer';
    case BIG_INTEGER = 'bigInteger';
    case SMALL_INTEGER = 'smallInteger';
    case TINY_INTEGER = 'tinyInteger';
    case FLOAT = 'float';
    case DOUBLE = 'double';
    case DECIMAL = 'decimal';
    case UNSIGNED_INTEGER = 'unsignedInteger';
    case UNSIGNED_BIG_INTEGER = 'unsignedBigInteger';

    // Boolean Type
    case BOOLEAN = 'boolean';

    // Date and Time Types
    case DATE = 'date';
    case DATETIME = 'datetime';
    case TIMESTAMP = 'timestamp';
    case TIME = 'time';
    case YEAR = 'year';

    // Special Types
    case JSON = 'json';
    case JSONB = 'jsonb';
    case ENUM = 'enum';
    case UUID = 'uuid';
    case IP_ADDRESS = 'ipAddress';
    case MAC_ADDRESS = 'macAddress';
    case BINARY = 'binary';

    /**
     * Get the migration type for this data type
     */
    public function getMigrationType(): string 
    {
        return $this->value;
    }

    /**
     * Get the form input type for Vue components
     */
    public function getFormInputType(): string
    {
        return match($this) {
            self::TEXT, self::MEDIUMTEXT, self::LONGTEXT => 'textarea',
            self::INTEGER, self::BIG_INTEGER, self::SMALL_INTEGER, 
            self::TINY_INTEGER, self::UNSIGNED_INTEGER, 
            self::UNSIGNED_BIG_INTEGER => 'number',
            self::FLOAT, self::DOUBLE, self::DECIMAL => 'number-decimal',
            self::BOOLEAN => 'switch',
            self::DATE => 'date',
            self::DATETIME, self::TIMESTAMP => 'datetime',
            self::TIME => 'time',
            self::YEAR => 'year',
            self::JSON, self::JSONB => 'json-editor',
            self::ENUM => 'select',
            self::IP_ADDRESS, self::MAC_ADDRESS => 'masked-input',
            default => 'text'
        };
    }

    /**
     * Get all available data types as array
     */
    public static function getAvailableTypes(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get human-readable name for the type
     */
    public function getDisplayName(): string
    {
        return match($this) {
            self::BIG_INTEGER => 'Big Integer',
            self::SMALL_INTEGER => 'Small Integer',
            self::TINY_INTEGER => 'Tiny Integer',
            self::UNSIGNED_INTEGER => 'Unsigned Integer',
            self::UNSIGNED_BIG_INTEGER => 'Unsigned Big Integer',
            self::IP_ADDRESS => 'IP Address',
            self::MAC_ADDRESS => 'MAC Address',
            self::MEDIUMTEXT => 'Medium Text',
            self::LONGTEXT => 'Long Text',
            default => ucfirst($this->value)
        };
    }
} 