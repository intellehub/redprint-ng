<?php

namespace Shahnewaz\Redprint;

use Illuminate\Database\Eloquent\Model;

class Database extends Model
{

    /**
     * Currently supported datatypes
     * */
    public function dataTypes()
    {
        return [
            'primary',
            'unique',
            // 'index',
            // 'spatialIndex',
            // 'foreign',
            'increments',
            'tinyIncrements',
            'smallIncrements',
            'mediumIncrements',
            'bigIncrements',
            'char',
            'string',
            'text',
            'mediumText',
            'longText',
            'integer',
            'tinyInteger',
            'smallInteger',
            'mediumInteger',
            'bigInteger',
            'unsignedInteger',
            'unsignedTinyInteger',
            'unsignedSmallInteger',
            'unsignedMediumInteger',
            'unsignedBigInteger',
            'float',
            'double',
            'decimal',
            'unsignedDecimal',
            'boolean',
            'enum',
            'json',
            'jsonb',
            'date',
            'dateTime',
            'dateTimeTz',
            'time',
            'timeTz',
            'timestamp',
            'timestampTz',
            // 'timestamps',
            // 'nullableTimestamps',
            // 'timestampsTz',
            // 'softDeletes',
            // 'softDeletesTz',
            'binary',
            'uuid',
            'ipAddress',
            'macAddress',
            'geometry',
            'point',
            'lineString',
            'polygon',
            'geometryCollection',
            'multiPoint',
            'multiLineString',
            'multiPolygon',
            // 'morphs',
            // 'nullableMorphs',
            // 'rememberToken',
        ];
    }

    public function timeTypes()
    {
        return [
            'date',
            'dateTime',
            'dateTimeTz',
            'time',
            'timeTz',
            'timestamp',
            'timestampTz',
            'timestamps',
            'nullableTimestamps',
            'timestampsTz',
            'softDeletes',
            'softDeletesTz'
        ];
    }

    public function numericTypes()
    {
        return [
            'integer',
            'tinyInteger',
            'smallInteger',
            'mediumInteger',
            'bigInteger',
            'unsignedInteger',
            'unsignedTinyInteger',
            'unsignedSmallInteger',
            'unsignedMediumInteger',
            'unsignedBigInteger',
            'float',
            'double',
            'decimal',
            'unsignedDecimal',
            'boolean',
        ];
    }

    public function integerTypes()
    {
        return [
            'integer',
            'tinyInteger',
            'smallInteger',
            'mediumInteger',
            'bigInteger',
            'unsignedInteger',
            'unsignedTinyInteger',
            'unsignedSmallInteger',
            'unsignedMediumInteger',
            'unsignedBigInteger',
            'boolean',
        ];
    }

    public function incrementTypes()
    {
        return [
            'increments',
            'tinyIncrements',
            'smallIncrements',
            'mediumIncrements',
            'bigIncrements',
        ];
    }


    public function longTextDataTypes()
    {
        return [
            'text',
            'mediumText',
            'longText'
        ];
    }

    public function relationshipIdentifiers()
    {
        return [
            'hasMany',
            'hasOne',
            'belongsTo',
            'belongsToMany',
            'hasManyThrough'
        ];
    }
}
