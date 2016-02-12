<?php

namespace OpenLocationCode\Tests;

use OpenLocationCode\OpenLocationCode;

/**
 * @author hcbogdan
 */
class OpenLocationCodeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @covers OpenLocationCode::decode
     * @covers OpenLocationCode::encode
     */
    public function testEncodeDecode()
    {
        $data = array(
            '7FG49Q00+,20.375,2.775,20.35,2.75,20.4,2.8',
            '7FG49QCJ+2V,20.3700625,2.7821875,20.37,2.782125,20.370125,2.78225',
            '7FG49QCJ+2VX,20.3701125,2.782234375,20.3701,2.78221875,20.370125,2.78225',
            '7FG49QCJ+2VXGJ,20.3701135,2.78223535156,20.370113,2.782234375,20.370114,2.78223632813',
            '8FVC2222+22,47.0000625,8.0000625,47.0,8.0,47.000125,8.000125',
            '4VCPPQGP+Q9,-41.2730625,174.7859375,-41.273125,174.785875,-41.273,174.786',
            '62G20000+,0.5,-179.5,0.0,-180.0,1,-179',
            '22220000+,-89.5,-179.5,-90,-180,-89,-179',
            '7FG40000+,20.5,2.5,20.0,2.0,21.0,3.0',
            '22222222+22,-89.9999375,-179.9999375,-90.0,-180.0,-89.999875,-179.999875',
            '6VGX0000+,0.5,179.5,0,179,1,180',
            // Special cases over 90 latitude and 180 longitude
            'CFX30000+,90,1,89,1,90,2',
            'CFX30000+,92,1,89,1,90,2',
            '62H20000+,1,180,1,-180,2,-179',
            '62H30000+,1,181,1,-179,2,-178',
        );

        foreach ($data as $row) {
            $item = explode(',', $row);

            for ($i=1; $i<=6; $i++)
                $item[$i] = floatval($item[$i]);

            $codeArea = OpenLocationCode::decode($item[0]);
            $code = OpenLocationCode::encode($item[1], $item[2], $codeArea['codeLength']);

            $this->assertEquals($item[0], $code, 'encode failed: '.$row);

            $this->assertEquals($item[3], $codeArea['latitudeLo'], '', 0.001);
            $this->assertEquals($item[4], $codeArea['longitudeLo'], '', 0.001);
            $this->assertEquals($item[5], $codeArea['latitudeHi'], '', 0.001);
            $this->assertEquals($item[6], $codeArea['longitudeHi'], '', 0.001);
        }
    }

    /**
     * @covers OpenLocationCode::isValid
     * @covers OpenLocationCode::isShort
     * @covers OpenLocationCode::isFull
     */
    public function testValidtors()
    {
        $data = array(
            // Valid full codes:
            '8FWC2345+G6,true,false,true',
            '8FWC2345+G6G,true,false,true',
            '8FWc2345+,true,false,true',
            '8FWCX400+,true,false,true',
            // Valid short codes:
            'WC2345+G6g,true,true,false',
            '2345+G6,true,true,false',
            '45+G6,true,true,false',
            '+G6,true,true,false',
            // Invalid codes
            'G+,false,false,false',
            '+,false,false,false',
            '8FWC2345+G,false,false,false',
            '8FWC2_45+G6,false,false,false',
            '8FWC2η45+G6,false,false,false',
            '8FWC2345+G6+,false,false,false',
            '8FWC2300+G6,false,false,false',
            'WC2300+G6g,false,false,false',
            'WC2345+G,false,false,false',
        );

        foreach ($data as $row) {
            $item = explode(',', $row);

            $this->assertEquals(
                OpenLocationCode::isValid($item[0]),
                ($item[1]  === 'true'),
                'isValid failed: '.$row
            );

            $this->assertEquals(
                OpenLocationCode::isShort($item[0]),
                ($item[2]  === 'true'),
                'isShort failed: '.$row
            );

            $this->assertEquals(
                OpenLocationCode::isFull($item[0]),
                ($item[3]  === 'true'),
                'isFull failed: '.$row
            );
        }
    }

    /**
     * @covers OpenLocationCode::shorten
     */
    public function testShorten()
    {
        // format
        // full code,lat,lng,shortcode
        $data = array(
            '9C3W9QCJ+2VX,51.3701125,-1.217765625,+2VX',
            '9C3W9QCJ+2VX,51.3708675,-1.217765625,CJ+2VX',
            '9C3W9QCJ+2VX,51.3693575,-1.217765625,CJ+2VX',
            '9C3W9QCJ+2VX,51.3701125,-1.218520625,CJ+2VX',
            '9C3W9QCJ+2VX,51.3701125,-1.217010625,CJ+2VX',

            '9C3W9QCJ+2VX,51.3852125,-1.217765625,9QCJ+2VX',
            '9C3W9QCJ+2VX,51.3701125,-1.232865625,9QCJ+2VX',
            '9C3W9QCJ+2VX,51.3550125,-1.217765625,9QCJ+2VX',
            '9C3W9QCJ+2VX,51.3701125,-1.202665625,9QCJ+2VX',

            '8FJFW222+,42.899,9.012,22+',
            '796RXG22+,14.95125,-23.5001,22+',
        );

        foreach ($data as $row) {
            $item = explode(',', $row);

            $lat = floatval($item[1]);
            $lng = floatval($item[2]);

            $shortCode = OpenLocationCode::shorten($item[0], $lat, $lng);
            $this->assertEquals($item[3], $shortCode, 'shorten failed: '.$row);

            $expanded = OpenLocationCode::recoverNearest($shortCode, $lat, $lng);
            $this->assertEquals($item[0], $expanded, 'recoverNearest failed: '.$row);
        }
    }
}
