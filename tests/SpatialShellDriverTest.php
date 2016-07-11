<?php
use Eslider\SpatialGeometry;
use Eslider\SpatialiteShellDriver;

/**
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialShellDriverTest extends \PHPUnit_Framework_TestCase
{
    const DB_PATH          = "spatialite.sqlite";
    const POLYGON_WKT      = 'POLYGON((761808.155309 4966649.458816, 762432.549628 4966393.94736, 764168.27812 4966137.407299, 763486.212544 4966081.379442, 762462.485356 4966252.975485, 761808.155309 4966649.458816))';
    const WKB              = '01010000008D976E1283C0F33F16FBCBEEC9C30240';
    const HEX              = '0001FFFFFFFF8D976E1283C0F33F16FBCBEEC9C302408D976E1283C0F33F16FBCBEEC9C302407C010000008D976E1283C0F33F16FBCBEEC9C30240FE';
    const WKT              = 'POINT(1.2345 2.3456)';
    const TABLE_NAME       = "geosxs";
    const GEOM_COLUMN_NAME = 'geom';
    const SRID             = 4326;
    const INSERT_COUNT     = 5;
    const ID_COLUMN_NAME   = 'id';
    const FULL_SYNC        = 2;

    /**
     * @var SpatialiteShellDriver
     */
    protected $db;

    /**
     * @return SpatialiteShellDriver
     * @beforeAsset
     */
    protected function setUp()
    {
        $this->db = new SpatialiteShellDriver(self::DB_PATH);
    }

    public function testDriverSplitters()
    {
        $this->assertEquals('', SpatialiteShellDriver::SPLIT_CELL_CHAR);
    }

    public function testJson1Extension()
    {
        $json = $this->db->fetchColumn("SELECT json_object('ex','[52,3.14159]')");
        $this->assertEquals($json, '{"ex":"[52,3.14159]"}');
    }

    /**
     * Test insert and fetch point geometries
     */
    public function testPointWktHandling()
    {
        $pointWkt      = "POINT(-74.00153 40.719885)";
        $idKey         = 'id';
        $tableName     = 'pois';
        $pointGeomName = 'Geometry';
        $type          = "POINT";
        $srid          = self::SRID;
        $db            = $this->db;

        if (!$db->hasTable($tableName)) {
            $db->createTable($tableName);
            $db->addGeometryColumn($tableName, $pointGeomName, $srid, $type);
        }

        $db->emptyTable($tableName);

        for ($i = 0; $i < self::INSERT_COUNT; $i++) {
            $id   = $db->insert($tableName, array(
                $pointGeomName => new SpatialGeometry($pointWkt, SpatialGeometry::TYPE_WKT, $srid)
            ));
            $data = $db->fetchRow("SELECT *,
                ST_AsText($pointGeomName) as $pointGeomName,
                Hex(ST_AsBinary($pointGeomName)) as wkb

                FROM " . $tableName . "
                ORDER BY $idKey
                DESC LIMIT 1");

            $this->assertEquals($db->wkbFromWkt($pointWkt), $data["wkb"]);
            $this->assertEquals($data[ $idKey ], $id);
            $this->assertEquals($data[ $pointGeomName ], $pointWkt);
        }
    }

    /**
     * Test insert and fetch polygon geometries
     */
    public function testPolygons()
    {
        $geomColumnName = self::GEOM_COLUMN_NAME;
        $idColumnName   = self::ID_COLUMN_NAME;
        $srid           = self::SRID;
        $polygonWkt     = self::POLYGON_WKT;
        $type           = 'POLYGON';
        $tableName      = "test_" . strtolower($type);
        $db             = $this->db;

        if ($db->hasTable($tableName)) {
            $tableInfo = $db->getTableInfo($tableName);
            $this->assertEquals($db->getSrid($tableName, $geomColumnName), $srid);
            $this->assertTrue(is_array($tableInfo) && count($tableInfo) > 0);
            $this->assertTrue(is_array(current($tableInfo)));
            //))
        } else {
            $db->createTable($tableName);
            $db->addGeometryColumn($tableName, $geomColumnName, $srid, $type);
        }

        for ($i = 0; $i < self::INSERT_COUNT; $i++) {
            $geom = new SpatialGeometry($polygonWkt, SpatialGeometry::TYPE_WKT, $srid);
            $id   = $db->insert($tableName, array(
                $geomColumnName => $geom //self::WKB
            ), $idColumnName);
            $data = $db->fetchRow("SELECT "
                . ' *'
                . " ,ST_AsText(" . $geomColumnName . ") AS " . $db->quote($geomColumnName)
                . " FROM " . $tableName
                . " WHERE " . $idColumnName . "=" . $id);

            $this->assertEquals($id, $data[ $idColumnName ]);
            $this->assertEquals($id, $db->getLastInsertId($tableName));
            $this->assertEquals($data[ $geomColumnName ], $polygonWkt);
        }

    }

    /**
     * Test versions
     */
    public function testVersions()
    {
        $versions = $this->db->getVersions();
        foreach (array('geos', 'proj4', 'sqlite', 'spatialite') as $key) {
            $this->assertTrue(isset($versions[ $key ]));
        }
    }

    /**
     * Test geometries conversion
     */
    public function testConversion()
    {
        $this->assertEquals($this->db->wktFromWkb(self::WKB), self::WKT);
        $this->assertEquals($this->db->wkbFromWkt(self::WKT), self::WKB);
        $this->assertEquals($this->db->wktFromHex(self::HEX), self::WKT);
    }

    public function testTableNames()
    {
        $this->assertTrue(in_array("geometry_columns", $this->db->listTableNames()));
    }

    /**
     * The synchronous pragma gets or sets the current disk synchronization mode
     * which controls how aggressively SQLite will write data all the way out to physical storage.
     *
     * 0 or OFF    No syncs at all
     * 1 or NORMAL    Sync after each sequence of critical disk operations
     * 2 or FULL    Sync after each critical disk operation
     */
    public function testSynchronous()
    {
        $this->assertEquals($this->db->fetchColumn("PRAGMA synchronous"), self::FULL_SYNC);
    }

    public function testFTS()
    {
        $tableName = "test_fts4";
        if (!$this->db->hasTable($tableName)) {
            $this->db->fetchColumn("CREATE VIRTUAL TABLE {$tableName} USING fts4(subject, body)");
            for ($i = 1; $i <= 100; $i++) {
                $id = $this->db->insert($tableName, array(
                    'subject' => $i . ' test ' . $i,
                    'body'    => $i . 'hello hollad cccdd aayy' . $i
                ));
            }
        }

        $row = $this->db->query("SELECT * FROM $tableName WHERE subject MATCH 'test'");
        return $row;
    }
}