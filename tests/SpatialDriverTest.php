<?php
use Eslider\SpatialGeometry;
use Eslider\SpatialiteShellDriver;

/**
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialDriverTest extends \PHPUnit_Framework_TestCase
{
    //const DB_PATH          = "data/test-1-1.db";
    const DB_PATH          = "data/spatialite.sqlite";
    const POLYGON_WKT      = 'POLYGON((761808.155309 4966649.458816,
                                762432.549628 4966393.94736,
                                764168.27812 4966137.407299,
                                763486.212544 4966081.379442,
                                762462.485356 4966252.975485,
                                761808.155309 4966649.458816))';
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

    public function testInsertPois()
    {
        $pointWkt       = "POINT(-74.00153 40.719885)";
        $idKey          = 'PK_UID';
        $pointTableName = 'poi';
        $pointGeomName  = 'Geometry';
        $srid           = $this->db->getSrid($pointTableName, $pointGeomName);

        $this->db->query("DELETE FROM " . $pointTableName);

        for ($i = 0; $i < 3; $i++) {
            $id   = $this->db->insert($pointTableName, array(
                $pointGeomName => new SpatialGeometry($pointWkt, SpatialGeometry::TYPE_WKT, $srid)
            ), $idKey);
            $data = $this->db->fetchRow("SELECT *,
                ST_AsText($pointGeomName) as $pointGeomName,
                Hex(ST_AsBinary($pointGeomName)) as wkb

                FROM " . $pointTableName . "
                ORDER BY $idKey
                DESC LIMIT 1");

            $this->assertEquals($this->db->wkbFromWkt($pointWkt), $data["wkb"]);
            $this->assertEquals($data[ $idKey ], $id);
            $this->assertEquals($data[ $pointGeomName ], $pointWkt);
        }

    }

    /**
     *
     */
    public function testInsertion()
    {
        $lastId = $this->db->getLastInsertId(self::TABLE_NAME);
        for ($i = 0; $i < self::INSERT_COUNT; $i++) {

            $geom = new SpatialGeometry(self::POLYGON_WKT, SpatialGeometry::TYPE_WKT, self::SRID);
            $id   = $this->db->insert(self::TABLE_NAME, array(
                self::GEOM_COLUMN_NAME => $geom //self::WKB
            ), self::ID_COLUMN_NAME);
            $data = $this->db->fetchRow("SELECT"
                . ' *'
                . " ,ST_AsText(" . self::GEOM_COLUMN_NAME . ") AS wkt"
                . " FROM " . self::TABLE_NAME
                . " WHERE " . self::ID_COLUMN_NAME . "=" . $id);

            $this->assertEquals($id, $data[ self::ID_COLUMN_NAME ]);
            //$this->assertEquals($data[ self::GEOM_COLUMN_NAME ], self::POLYGON_WKT);
            $this->assertTrue($lastId < $id);
            $lastId = $id;
        }
    }

    /**
     * Sqlite v.3.8.2
     */
    public function testSpatialite()
    {
        if ($this->db->hasTable(self::TABLE_NAME)) {
            $tableInfo = $this->db->getTableInfo(self::TABLE_NAME);
            $this->assertEquals($this->db->getSrid(self::TABLE_NAME, self::GEOM_COLUMN_NAME), self::SRID);
            $this->assertTrue(is_array($tableInfo) && count($tableInfo) > 0);
            $this->assertTrue(is_array(current($tableInfo)));
            //var_dump($this->db->getLastInsertId(self::TEST_TABLE_NAME));
            //$results = $this->db->emptyTable(self::TEST_TABLE_NAME);
            //$r = $this->db->getLastInsertId(self::TEST_TABLE_NAME);
            //var_dump($r);
            //$results = $this->db->query("CREATE VIRTUAL TABLE ft1 USING fts5(a, b, c)");
            //$results = $this->db->query("PRAGMA public.auto_vacuum=NONE");

            //var_dump($results);
            $results = $this->db->query("
              SELECT *
              FROM `" . self::TABLE_NAME . "`
              ORDER BY id DESC
              LIMIT 3
            ");

            //var_dump($results);
            //$resutls = $this->db->fetchAll(self::TEST_TABLE_NAME);
            //$db->insert(array(
            //    'geom' => ''
            //))
        } else {
            var_dump($this->db->createTable(self::TABLE_NAME));
            var_dump($this->db->addGeometryColumn(self::TABLE_NAME, self::GEOM_COLUMN_NAME, self::SRID));
        }
    }

    public function testVersions()
    {
        $versions = $this->db->getVersions();
        foreach (array('geos', 'proj4', 'sqlite', 'spatialite') as $key) {
            $this->assertTrue(isset($versions[ $key ]));
        }
    }

    public function testConversion()
    {
        $this->assertEquals($this->db->wktFromWkb(self::WKB), self::WKT);
        $this->assertEquals($this->db->wkbFromWkt(self::WKT), self::WKB);
        $this->assertEquals($this->db->wktFromHex(self::HEX), self::WKT);
        //var_dump($this->db->getSridFromWkb(self::TEST_WKB));
    }




    ///**
    // * @group Doctrine driver
    // */
    //public function testExtendedSqliteDriver()
    //{
    //    $driver = new WhereGroup\Spatialite\Driver();
    //    $config = new \Doctrine\DBAL\Configuration();
    //    $conn = DriverManager::getConnection(array(
    //        'path'        => 'data/spatialite.sqlite',
    //        'driverClass' => 'WhereGroup\Spatialite\Driver'
    //    ), $config
    //    );
    //    $var = $conn->exec("SELECT sqlite_version()");
    //    return $var;
    //}

    ///**
    // * @group native manager
    // */
    //public function nativeManager()
    //{
    //    $dbSrc = "data/test".round(100).".sqlite";
    //    $cache = new NativeManager($dbSrc);
    //}

    //
    //    public static function testGeo()
    //    {
    //        $db    = new NativeManager('../data/spatialite.sqlite');
    //        $memDb = (new NativeManager());
    //
    //        echo "<div style='clear: both'/>";
    //
    //        foreach ($db->fetchAll('SELECT PK_UID, label,
    //         AsGml(Geometry) as GML,
    //         AsGeoJSON(Geometry) as JSON,
    //         AsSVG(Geometry) as SVG,
    //         AsKML(Geometry) as KML,
    //         ST_AsText(Geometry) as WKT
    //         FROM roads LIMIT 100'
    //        ) as $row) {
    //            $geom = $row['WKT'];
    //            $svg  = '<svg style="stroke: #000000; fill:#00ff00; width: 100px; height: 100px; display:block; float: left; border: 1px solid #c0c0c0">
    //            <path d="' . $row['SVG'] . '"/>
    //            </svg>';
    //
    //        };
    //
    //        echo "<div style='clear: both'/>";
    //        var_dump($db->getSrid('roads'));
    //        var_dump($db->fetchColumn("
    //  SELECT COUNT(*) FROM roads
    // WHERE ST_INTERSECTS(Geometry, ST_TRANSFORM(ST_GEOMFROMTEXT('$geom',31467),31467))"));
    //    }
}