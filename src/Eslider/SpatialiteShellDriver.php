<?php

namespace Eslider;

/**
 * Class Spatialite
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialiteShellDriver extends SpatialiteBaseDriver
{
    const SPLIT_CELL_CHAR               = '';
    const NULL_CHAR                     = '';
    const SPLIT_ROW_CHAR                = "\n";
    const SPATIALITE_CMD_RESULT_PADDING = 19;
    const SPATIALITE_MOD_RESULT_PADDING = 2;

    /** @var string SQL for loading spatialite library */
    protected $_libLoad;

    /** @var string command line*/
    protected $cmd;

    /**
     * Spatialite constructor.
     *
     * @param string $dbPath  Database file path.
     * @param string $libPath mod_spatialite path. Optional.
     * @param string $binPath sqlite binary path. Optional.
     */
    public function __construct($dbPath, $libPath = null, $binPath = null)
    {
        $osAlias = strtoupper(substr(PHP_OS, 0, 3));
        $isWin   = $osAlias == 'CYG' || $osAlias == 'WIN';
        $path    = __DIR__."/../../";

        if ($isWin) {
            $path .= 'bin/x32';
            $binPath = $binPath ? $binPath : $path . '/spatialite.exe';
        } else {
            $path .= 'bin/x64';
            $binPath = $binPath ? $binPath : $path . '/sqlite3';
            $libPath = $libPath ? $libPath : $path . '/mod_spatialite';
        }

        $this->cmd = escapeshellarg($binPath)
            //. ' -ascii '
            . ' -separator ' . escapeshellarg(self::SPLIT_CELL_CHAR)
            . ' -nullvalue ' . escapeshellarg(self::NULL_CHAR)
            . ' -header ' . escapeshellarg($dbPath);

        if ($libPath) {
            $this->_libLoad = escapeshellarg('SELECT load_extension(\'' . $libPath . '\');');
        }

        parent::__construct($dbPath);
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
        if ($this->_libLoad) {
            $sql = $this->_libLoad . escapeshellarg($sql);
        }
        $result = `$this->cmd $sql`;
        return $parse ? $this->parseResults($result) : $result;
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
        $headLineNumber = $this->_libLoad ? self::SPATIALITE_MOD_RESULT_PADDING : self::SPATIALITE_CMD_RESULT_PADDING;
        $rowResults     = explode(self::SPLIT_ROW_CHAR, $result);
        $rowCount       = count($rowResults) - 1;
        $headers        = $this->parseCells($rowResults[ $headLineNumber ]);
        $rows           = array();
        for ($i = $headLineNumber + 1; $i < $rowCount; $i++) {
            $data = array();
            foreach ($this->parseCells($rowResults[ $i ]) as $k => &$v) {
                $data[ $headers[ $k ] ] = $v == self::NULL_CHAR ? null : $v;
            }
            $rows[] = $data;
        }

        return $rows;
    }
}
