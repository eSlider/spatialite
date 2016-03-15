<?php

namespace Eslider\Spatial;

use Eslider\Spatial\Driver\Base;

/**
 * Class Geometry
 *
 * @author Andriy Oblivantsev <eslider@gmail.com>
 */
class Geometry
{
    const TYPE_WKT = 'WKT';
    const TYPE_WKB = 'WKB';
    const TYPE_HEX = 'HEX';

    protected $value;
    protected $type;

    /**
     * Geometry constructor.
     *
     * @param string $value WKT, WKB or HEX
     * @param string $type  Geometry type: WKT, WKB, HEX
     * @param string $srid  SRID
     */
    public function __construct($value, $type = self::TYPE_WKT, $srid = null)
    {
        $this->srid = intval($srid);
        $this->type = $type;
        $this->setValue($value);
    }

    /**
     * Get SRID
     *
     * @return int
     */
    public function getSrid()
    {
        return $this->srid;
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
    private function isEWKT()
    {
        return $this->type == self::TYPE_WKT && strpos($this->value, "SRID=") !== false;
    }

    /**
     * Prepare geometry
     */
    public function __toString()
    {
        // ST_TRANSFORM(ST_GEOMFROMTEXT('$geom'), $srid)
        if ($this->isWKT()) {
            return 'GeomFromText('
            . Base::escapeValue($this->getValue())
            . ','
            . Base::escapeValue($this->getSrid())
            . '
           )';
        }
        if ($this->type == self::TYPE_WKB) {
            return 'x' . $this->getValue();
        }
        return $this->getValue();
    }

    /**
     * @return bool
     */
    public function isWKT()
    {
        return $this->getType() == self::TYPE_WKT;
    }

    /**
     * @param string $value
     */
    public function setValue($value)
    {
        $matches = null;
        if ($this->isWKT() && preg_match("/^SRID=(\\d+);(.+)$/", $value, $matches)) {
            list($ewkt, $srid, $wkt) = $matches;
            $this->value = $wkt;
            $this->srid  = $srid;
        } else {
            $this->value = $value;
        }
    }
}