<?php
use Eslider\SpatialGeometry;
use Eslider\SpatialiteBaseDriver;
use Eslider\SpatialiteShellDriver;
use Eslider\SpatialiteNativeDriver;

/**
 * Class SpatialDriverTest
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialTest extends \PHPUnit_Framework_TestCase
{
    const GEOM_COLUMN_NAME = 'geom';
    const SRID             = 4326;
    const INSERT_COUNT     = 10;

    /** @var SpatialiteNativeDriver */
    protected $nativeDb;

    /** @var  SpatialiteNativeDriver */
    protected $shellDb;

    /**
     * @beforeAsset
     */
    protected function setUp()
    {
        $this->nativeDb = new SpatialiteNativeDriver("spatialite.sqlite");
        $this->shellDb  = new SpatialiteShellDriver("spatialite.sqlite");
    }

    public function testNative()
    {
        $this->getPoints($this->nativeDb);
        $this->handlePointsAsWkt($this->nativeDb);
    }

    public function testShell()
    {
        $this->getPoints($this->shellDb);
        $this->handlePointsAsWkt($this->shellDb);

    }

    private function getPoints(SpatialiteBaseDriver $db)
    {
        return $db->query("SELECT id,
         st_astext(geom) as geom,
            st_srid(geom) as srid
            FROM test_polygon");
    }

    /**
     * Test insert and fetch point geometries
     *
     * @param SpatialiteBaseDriver $db
     */
    public function handlePointsAsWkt(SpatialiteBaseDriver $db)
    {
        $idKey         = 'id';
        $tableName     = 'pois';
        $pointGeomName = 'Geometry';
        $type          = "POINT";
        $srid          = self::SRID;
        // SRID=$srid;
        $pointWkt      = "POINT(-74.00153 40.719885)";

        if (!$db->hasTable($tableName)) {
            $db->createTable($tableName);
            $db->addGeometryColumn($tableName, $pointGeomName, $srid, $type);
        }

        $db->emptyTable($tableName);

        for ($i = 0; $i < self::INSERT_COUNT; $i++) {
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
            $var = $data[ $pointGeomName ];
            $this->assertEquals($var, $pointWkt);
        }
    }
}
