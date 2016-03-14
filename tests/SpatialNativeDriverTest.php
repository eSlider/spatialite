<?php
include("SpatialShellDriverTest.php");

use Eslider\SpatialiteNativeDriver;


/**
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialNativeDriverTest extends SpatialShellDriverTest
{
    /**
     * @var SpatialiteNativeDriver
     */
    protected $db;

    /**
     * @return SpatialiteNativeDriver
     * @beforeAsset
     */
    protected function setUp()
    {
        $this->db = new SpatialiteNativeDriver(self::DB_PATH);
    }

    public function testJson1Extension()
    {
    }
}