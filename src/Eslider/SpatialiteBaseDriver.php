<?php

namespace Eslider;

/**
 * Class SpatialiteBaseDriver
 *
 * @package Eslider
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
abstract class SpatialiteBaseDriver
{
    const VALUE_ESC_CHAR = '\'';
    const NAME_ESC_CHAR  = '`';
    const NULL           = 'NULL';

    /**
     * SpatialiteBaseDriver constructor.
     *
     * @param $dbPath
     */
    public function __construct($dbPath)
    {
        if (!file_exists($dbPath)) {
            $this->initDbFile();
        };
    }

    /**
     * This need to prepare spatialite database file
     * working with geometries. If running this first time,
     * the script holds for a while.
     */
    public function initDbFile()
    {
        return $this->query("SELECT InitSpatialMetadata()", false);
    }

    /**
     * Query and get results
     *
     * @param string $sql   SQL
     * @param bool   $parse Parse or return as is
     * @param bool   $debug
     * @return array|null
     */
    public function query($sql, $parse = true, $debug = false)
    {
        return null;
    }

    /**
     * @param $sql
     * @return array
     */
    public function exec($sql)
    {
        return $this->query($sql, false);
    }

    /**
     * @param string $sql SQL
     * @param bool   $debug
     * @return array
     */
    public function fetchColumn($sql, $debug = false)
    {
        return current($this->fetchRow($sql, $debug));
    }

    /**
     * @param string $sql
     * @param bool   $debug
     * @return mixed
     */
    public function fetchRow($sql, $debug = false)
    {
        return current($this->query($sql, true, $debug));
    }

    /**
     * Escape value
     *
     * @param $value
     * @return null|string
     */
    public static function escapeValue($value)
    {
        $r = null;
        if (is_string($value)) {
            $r = self::VALUE_ESC_CHAR . $value . self::VALUE_ESC_CHAR;
        } elseif (is_null($value)) {
            $r = self::NULL;
        } elseif (is_bool($value)) {
            $r = $value ? 1 : 0;
        } else {
            $r = $value;
        }
        return $r;
    }

    /**
     * Start transaction
     */
    public function startTransaction()
    {
        $this->exec("BEGIN");
    }

    /**
     * Stop transaction
     */
    public function stopTransaction()
    {
        $this->exec("END");
    }

    /**
     * Get table name
     *
     * @param $name
     * @return array
     */
    public function getTableInfo($name)
    {
        return $this->query("PRAGMA TABLE_INFO(" . $this->quote($name) . ")");
    }


    /**
     * Get driver info
     *
     * @return array
     */
    public function getVersions()
    {
        return $this->fetchRow("SELECT
            geos_version() as geos,
            proj4_version() as proj4,
            sqlite_version() as sqlite,
            spatialite_version() as spatialite,
            spatialite_target_cpu() as targetCpu");
    }

    /**
     * Add geometry field
     *
     * @param        $tableName
     * @param string $columnName
     * @param int    $srid
     * @param string $type
     * @return mixed
     */
    public function addGeometryColumn($tableName, $columnName = "geom", $srid = 4326, $type = "POLYGON")
    {
        return $this->query("SELECT AddGeometryColumn("
            . $this->escapeValue($tableName)
            . ", "
            . $this->escapeValue($columnName)
            . ", $srid, "
            . $this->escapeValue($type)
            . ", 'XY')");
    }

    /**
     * Get table SRID
     *
     * @param      $tableName
     * @param null $columnName
     * @return null
     */
    public function getSrid($tableName, $columnName = null)
    {
        $tableName  = $this->escapeValue($tableName);
        $columnName = $this->escapeValue(empty($columnName) ? false : $columnName);
        return $this->fetchColumn("SELECT srid
          FROM geometry_columns
          WHERE f_table_name
          LIKE $tableName"
            . ($columnName ? " AND f_geometry_column LIKE {$columnName}" : ''));
    }

    /**
     * TODO: doesn't work
     *
     * @param $wkb
     */
    public function getSridFromWkb($wkb)
    {
        $this->fetchColumn("SELECT ST_Srid('$wkb')");
    }

    /**
     * @param $wkt
     * @return null
     */
    public function hexFromWkt($wkt)
    {
        return $this->fetchColumn("SELECT Hex(ST_GeomFromText('$wkt'))");
    }

    /**
     * @param $wkt
     * @return null
     */
    public function wkbFromWkt($wkt)
    {
        return $this->fetchColumn("SELECT Hex(ST_AsBinary(ST_GeomFromText('$wkt')))");
    }

    /**
     * @param $wkb
     * @return null
     */
    public function wktFromWkb($wkb)
    {
        return $this->fetchColumn("SELECT ST_AsText(ST_GeomFromWKB(x'$wkb'));");
    }

    /**
     * @param $wkb
     * @return null
     */
    public function wktFromHex($wkb)
    {
        return $this->fetchColumn("SELECT ST_AsText(x'$wkb')");
    }


    /**
     * Quote name
     *
     * @param $name
     * @return string
     */
    public function quote($name)
    {
        return self::NAME_ESC_CHAR . $name . self::NAME_ESC_CHAR;
    }

    /**
     * @param $name
     * @return array
     */
    public function emptyTable($name)
    {
        return $this->query("DELETE FROM " . $this->quote($name));
    }

    /**
     * @param $name
     * @return array
     */
    public function dropTable($name)
    {
        return $this->query("DROP TABLE  " . $this->quote($name));
    }


    /**
     * Add column by table name and column name
     *
     * Types:
     *
     * * NULL. The value is a NULL value.
     * * INTEGER. The value is a signed integer, stored in 1, 2, 3, 4, 6, or 8 bytes depending on the magnitude of the
     * value.
     * * REAL. The value is a floating point value, stored as an 8-byte IEEE floating point number.
     * * TEXT. The value is a text string, stored using the database encoding (UTF-8, UTF-16BE or UTF-16LE).
     * * BLOB. The value is a blob of data, stored exactly as it was input.
     *
     * @see https://www.sqlite.org/datatype3.html
     *
     * @param string $tableName  Table name
     * @param string $columnName Column name
     * @param string $type       Type as string (https://www.sqlite.org/datatype3.html)
     *
     * @return array
     */
    public function addColumn($tableName, $columnName, $type = "TEXT")
    {
        return $this->query("ALTER TABLE " . $this->quote($tableName) . " ADD COLUMN " . $this->quote($columnName));
    }


    /**
     * Create table
     *
     * @param string $name Table name
     * @return array
     */
    public function createTable($name)
    {
        return $this->query("CREATE TABLE " . $this->quote($name) . " (id INTEGER NOT NULL PRIMARY KEY)");
    }


    /**
     * Check if database has table
     *
     * @param string $name Table name
     * @return bool
     */
    public function hasTable($name)
    {
        return count($this->getTableInfo($name)) > 0;
    }

    /**
     * Get last insert ID
     *
     * @param string      $tableName Table name
     * @param null|string $idColumn  ID column name
     * @return array
     */
    public function getLastInsertId($tableName, $idColumn = 'id')
    {
        if (!$idColumn) {
            $column   = current($this->getTableInfo($tableName));
            $idColumn = $column["name"];
        }
        $sql = "SELECT
            max(" . $this->quote($idColumn) . ")
            FROM " . $this->quote($tableName);
        return $this->fetchColumn($sql);
    }

    /**
     * Insert data and get last insert id.
     * This method is secure course of transaction.
     *
     * @param string $_tableName Table name
     * @param array  $data       Array with mixed values this escapes with `escapeValue` method.
     *
     * @param string $idColumn
     * @param bool   $debug
     * @return int Last insert id
     */
    public function insert($_tableName, array $data, $idColumn = 'id', $debug = false)
    {
        $_tableName = $this->quote($_tableName);
        $keys       = self::NAME_ESC_CHAR . implode(self::NAME_ESC_CHAR . ',' . self::NAME_ESC_CHAR, array_keys($data)) . self::NAME_ESC_CHAR;
        $values     = array();

        foreach ($data as $value) {
            $values[] = self::escapeValue($value);
        }

        return $this->fetchColumn('
            BEGIN;
                INSERT INTO ' . $_tableName . ' (' . $keys . ') VALUES (' . implode(', ', $values) . ');
                SELECT max(' . $this->quote($idColumn) . ') FROM ' . $_tableName . ';
            END
            ', $debug);
    }
}