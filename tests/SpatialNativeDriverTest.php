<?php
include("SpatialShellDriverTest.php");

use Eslider\Spatial\Driver\NativeDriver;

/**
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialNativeDriverTest extends SpatialShellDriverTest
{
    /**
     * @var NativeDriver
     */
    protected $db;

    /**
     * @return NativeDriver
     * @beforeAsset
     */
    protected function setUp()
    {
        $this->db = new NativeDriver(self::DB_PATH);
    }

    /**
     * @skip
     */
    public function testJson1Extension()
    {
    }
}