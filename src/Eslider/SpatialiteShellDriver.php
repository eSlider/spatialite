<?php

namespace Eslider;

/**
 * Class Spatialite
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialiteShellDriver
{
    const SPLIT_CELL_CHAR = '';
    const NULL_CHAR       = '';
    const SPLIT_ROW_CHAR  = "\n";
    const VALUE_ESC_CHAR  = "'";
    const NAME_ESC_CHAR   = '`';
    const NULL            = 'NULL';

    protected $libPath;
    protected $_libLoad;

    /**
     * Spatialite constructor.
     *
     * @param string $dbPath Database file path
     * @param string $libPath
     * @param string $binPath
     */
    public function __construct($dbPath,
        $libPath = "bin/x64/mod_spatialite",
        $binPath = "bin/x64/sqlite3") // spatialite
    {
        $this->libPath = $libPath;
        $this->cmd     = escapeshellarg($binPath)
            //. ' -ascii '
            . ' -separator ' . escapeshellarg(self::SPLIT_CELL_CHAR)
            . ' -nullvalue ' . escapeshellarg(self::NULL_CHAR)
            . ' -header ' . escapeshellarg($dbPath);

        $this->_libLoad = escapeshellarg('SELECT load_extension(\'' . $libPath . '\');');

        if (!file_exists($dbPath)) {
            $this->initDbFile();
        }
    }

    /**
     * This need to prepare spatialite database file
     * working with geometries. Wired. But if runng this, the script holds...
     */
    public function initDbFile()
    {
        return $this->query("SELECT InitSpatialMetadata()", false);
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
     * Query and get results
     *
     * @param string $sql
     * @param bool   $parse Parse or return as is
     * @param bool   $debug
     * @return array
     */
    public function query($sql, $parse = true, $debug = false)
    {
        if ($debug) {
            var_dump($sql);
        }
        $sql    = $this->_libLoad . escapeshellarg($sql);
        $result = `$this->cmd $sql`;
        return $parse ? $this->parseResults($result) : $result;
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
     * Insert data and get last insert id.
     * This method is secure course of transaction.
     *
     * @param string $tableName Table name
     * @param array  $data      Array with mixed values this escapes with `escapeValue` method.
     *
     * @param string $idColumn
     * @param bool   $debug
     * @return int Last insert id
     */
    public function insert($tableName, array $data, $idColumn = 'id', $debug = false)
    {
        $tableName = $this->quote($tableName);
        $keys      = self::NAME_ESC_CHAR . implode(self::NAME_ESC_CHAR . ',', array_keys($data)) . self::NAME_ESC_CHAR;
        $values    = array();

        foreach ($data as $value) {
            $values[] = self::escapeValue($value);
        }

        return $this->fetchColumn('
            BEGIN;
                INSERT INTO ' . $tableName . ' (' . $keys . ') VALUES (' . implode(', ', $values) . ');
                SELECT max(' . $this->quote($idColumn) . ') FROM ' . $tableName . ';
            END
            ', $debug);
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
     * Parse cells
     *
     * @param $rawCells
     * @return array
     */
    protected function parseCells(&$rawCells)
    {
        return explode(self::SPLIT_CELL_CHAR, $rawCells);
    }

    /**
     * Parse row results
     *
     * @param $result
     * @return array
     */
    public function parseResults(&$result)
    {
        $rowResults = explode(self::SPLIT_ROW_CHAR, $result);
        $rowCount   = count($rowResults) - 1;
        $headers    = $this->parseCells($rowResults[2]);
        $rows       = array();

        for ($i = 3; $i < $rowCount; $i++) {
            $data = array();
            foreach ($this->parseCells($rowResults[ $i ]) as $k => &$v) {
                $data[ $headers[ $k ] ] = $v == self::NULL_CHAR ? null : $v;
            }
            $rows[] = $data;
        }

        return $rows;
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
        return $this->fetchColumn("SELECT
            max(" . $this->quote($idColumn) . ")
            FROM " . $this->quote($tableName));
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
        return $this->query("SELECT AddGeometryColumn('$tableName', '$columnName', $srid, '$type', 'XY')");
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
        return $this->fetchColumn("SELECT srid FROM geometry_columns WHERE f_table_name LIKE '$tableName'" . ($columnName ? " AND f_geometry_column LIKE '{$columnName}'" : ''));
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
     * @param      $sql
     * @param bool $debug
     * @return array
     */
    public function fetchColumn($sql, $debug = false)
    {
        return current($this->fetchRow($sql, $debug));
    }

    /**
     * @param      $sql
     * @param bool $debug
     * @return mixed
     */
    public function fetchRow($sql, $debug = false)
    {
        return current($this->query($sql, true, $debug));
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
}
