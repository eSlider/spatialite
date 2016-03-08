<?php

namespace Eslider;

/**
 * Class SpatialGeometry
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class SpatialGeometry
{
    const TYPE_WKT = 'WKT';
    const TYPE_WKB = 'WKB';
    const TYPE_HEX = 'HEX';

    protected $value;
    protected $type;

    /**
     * SpatialGeometry constructor.
     *
     * @param string $value WKT, WKB or HEX
     * @param string $type  Geometry type: WKT, WKB, HEX
     * @param string $srid  SRID
     */
    public function __construct($value, $type = self::TYPE_WKT, $srid = null)
    {
        $this->value = $value;
        $this->type  = $type;
        $this->srid = intval($srid);
        //if ($this->isEwkt($value, $type)) {
        //    //SRID = 4269;
        //    //if(self::TYPE_WKT
        //}
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Is Extended Well-Known Text/Binary?
     *
     * @return bool
     */
    private function isEwkt()
    {
        return $this->type  == self::TYPE_WKT && strpos("SRID=", $this->value);
    }

    /**
     * Prepare geometry
     */
    public function __toString()
    {
        // ST_TRANSFORM(ST_GEOMFROMTEXT('$geom'), $srid)
        if ($this->type == self::TYPE_WKT) {
            return 'GeomFromText('
            . SpatialiteShellDriver::escapeValue($this->value)
            . ','
            . SpatialiteShellDriver::escapeValue($this->srid)
            .'
           )';
        }
        if ($this->type == self::TYPE_WKB) {
            return 'x' . $this->value;
        }
        return $this->value;
    }
}