<?php

namespace Eslider;

/**
 * Class SpatialiteNativeDriver
 *
 * @package Eslider
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialiteNativeDriver extends SpatialiteBaseDriver
{
    /**
     * @var \SQLite3
     */
    public $db;

    /**
     * Check if the driver can be used.
     *
     * In order to get driver work, you need set absolute path of ´sqlite3.extension_dir´ variable in ´php.ini´ file
     * to the ´bin/x64´ directory, where ´mod_spatialite.so´ can be found.
     *
     * Example: 'sqlite3.extension_dir=/var/www/project_name/vendor/eslider/spatialite/bin/x64/mod_spatialite
     *
     * @return bool
     */
    public static function canBeUsed()
    {
        return file_exists(ini_get('sqlite3.extension_dir') . '/mod_spatialite.so');
    }

    /**
     * SpatialiteNativeDriver constructor.
     *
     * @param $filename
     */
    public function __construct($filename)
    {
        $isNewDatabase = !file_exists($filename);
        $this->db      = new \SQLite3($filename);
        $this->db->loadExtension('mod_spatialite.so');
        if ($isNewDatabase) {
            $this->initDbFile();
        }
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
        $result    = array();
        $statement = $this->db->query($sql);
        while ($row = &$statement->fetchArray(SQLITE3_ASSOC)) {
            $result[] = $row;
        }
        return $result;
    }


    /**
     * Insert data and get last insert id.
     * This method is secure course of transaction.
     *
     * @param string $tableName Table name
     * @param array  $data       Array with mixed values this escapes with `escapeValue` method.
     *
     * @param string $idColumn
     * @param bool   $debug
     * @return int Last insert id
     */
    public function insert($tableName, array $data, $idColumn = 'id', $debug = false)
    {
        $_tableName = $this->quote($tableName);
        $keys       = self::NAME_ESC_CHAR . implode(self::NAME_ESC_CHAR . ',' . self::NAME_ESC_CHAR, array_keys($data)) . self::NAME_ESC_CHAR;
        $values     = array();
        $id         = null;

        foreach ($data as $value) {
            $values[] = self::escapeValue($value);
        }

        $this->startTransaction();
        $sql = 'INSERT INTO ' . $_tableName . ' (' . $keys . ') VALUES (' . implode(', ', $values) . ')';
        $this->db->exec($sql);
        $id = $this->getLastInsertId($tableName, $idColumn);
        $this->stopTransaction();
        return $id;
    }

    /**
     * @param $sql
     * @return array
     */
    public function exec($sql)
    {
        return $this->db->exec($sql);
    }
}