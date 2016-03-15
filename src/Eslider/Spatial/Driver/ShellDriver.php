<?php

namespace Eslider\Spatial\Driver;


/**
 * Class Spatialite
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class ShellDriver extends Base
{
    const SPLIT_CELL_CHAR = '';
    const NULL_CHAR       = '';
    const SPLIT_ROW_CHAR  = "\n";

    /** @var string SQL for loading spatialite library */
    protected $_libLoad;

    /**
     * Spatialite constructor.
     *
     * @param string $dbPath  Database file path.
     * @param string $libPath mod_spatialite path. Optional.
     * @param string $binPath sqlite binary path. Optional.
     */
    public function __construct($dbPath, $libPath = null, $binPath = null)
    {
        $path    = __DIR__ . '/../../../../bin/x64';
        $binPath = $binPath ? $binPath : $path . '/sqlite3';
        $libPath = $libPath ? $libPath : $path . '/mod_spatialite';

        $this->cmd = escapeshellarg($binPath)
            //. ' -ascii '
            . ' -separator ' . escapeshellarg(self::SPLIT_CELL_CHAR)
            . ' -nullvalue ' . escapeshellarg(self::NULL_CHAR)
            . ' -header ' . escapeshellarg($dbPath);

        $this->_libLoad = escapeshellarg('SELECT load_extension(\'' . $libPath . '\');');

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
        $sql    = $this->_libLoad . escapeshellarg($sql);
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
}
