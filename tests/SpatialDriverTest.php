<?php
use Eslider\SpatialGeometry;
use Eslider\SpatialiteShellDriver;

/**
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialDriverTest extends \PHPUnit_Framework_TestCase
{
    const DB_PATH          = "spatialite.sqlite";
    const POLYGON_WKT      = 'POLYGON((761808.155309 4966649.458816, 762432.549628 4966393.94736, 764168.27812 4966137.407299, 763486.212544 4966081.379442, 762462.485356 4966252.975485, 761808.155309 4966649.458816))';
    const WKB              = '01010000008D976E1283C0F33F16FBCBEEC9C30240';
    const HEX              = '0001FFFFFFFF8D976E1283C0F33F16FBCBEEC9C302408D976E1283C0F33F16FBCBEEC9C302407C010000008D976E1283C0F33F16FBCBEEC9C30240FE';
    const WKT              = 'POINT(1.2345 2.3456)';
    const TABLE_NAME       = "geosxs";
    const GEOM_COLUMN_NAME = 'geom';
    const SRID             = 4326;
    const INSERT_COUNT     = 3;
    const ID_COLUMN_NAME   = 'id';

    /**
     * @var SpatialiteShellDriver
     */
    protected $db;

    /**
     * @return SpatialiteShellDriver
     * @bedoreAsset
     */
    protected function setUp()
    {
        $this->db = new SpatialiteShellDriver(self::DB_PATH);
    }

    public function testDriverSplitters()
    {
        $this->assertEquals('', SpatialiteShellDriver::SPLIT_CELL_CHAR);
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

        for ($i = 0; $i < 3; $i++) {
            $id   = $db->insert($tableName, array(
                $pointGeomName => new SpatialGeometry($pointWkt, SpatialGeometry::TYPE_WKT, $srid)
            ), $idKey);
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
        $tableName      = self::TABLE_NAME;
        $geomColumnName = self::GEOM_COLUMN_NAME;
        $idColumnName   = self::ID_COLUMN_NAME;
        $srid           = self::SRID;
        $polygonWkt     = self::POLYGON_WKT;
        $type           = 'POLYGON';

        if ($this->db->hasTable($tableName)) {
            $tableInfo = $this->db->getTableInfo($tableName);
            $this->assertEquals($this->db->getSrid($tableName, $geomColumnName), $srid);
            $this->assertTrue(is_array($tableInfo) && count($tableInfo) > 0);
            $this->assertTrue(is_array(current($tableInfo)));
            //))
        } else {
            $this->db->createTable($tableName);
            $this->db->addGeometryColumn($tableName, $geomColumnName, $srid, $type);
        }

        $lastId = $this->db->getLastInsertId($tableName);
        for ($i = 0; $i < self::INSERT_COUNT; $i++) {
            $geom = new SpatialGeometry($polygonWkt, SpatialGeometry::TYPE_WKT, $srid);
            $id   = $this->db->insert($tableName, array(
                $geomColumnName => $geom //self::WKB
            ), $idColumnName);
            $data = $this->db->fetchRow("SELECT "
                . ' *'
                . " ,ST_AsText(" . $geomColumnName . ") AS " . $this->db->quote($geomColumnName)
                . " FROM " . $tableName
                . " WHERE " . $idColumnName . "=" . $id);

            $this->assertEquals($id, $data[ $idColumnName ]);
            $this->assertEquals($data[ $geomColumnName ], $polygonWkt);
            $this->assertTrue($lastId < $id);
            $lastId = $id;
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
}