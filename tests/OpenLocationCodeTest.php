<?php

namespace OpenLocationCode\Tests;

use OpenLocationCode\OpenLocationCode;

/**
 * @author hcbogdan
 */
class OpenLocationCodeTest extends \PHPUnit_Framework_TestCase
{
    public function testIsValid()
    {
        $wrongCodes = array(
            // invalid length
            '',
            'Q',

            // invalid seprator
            'Q+',
            'QQQQQQQQQQQQQQQ+',
            'Q++',

            // invalid padding
            'Q0+',
            // '+QQQ',
            'QQ00000+', // odd
            'QQ00100+',

            // alphabet
            'BBGPBP00+',
        );
        foreach($wrongCodes as $i)
            $this->assertFalse( OpenLocationCode::isValid( $i ),
                'Code valid: '.$i );


        $trueCodes = array(
            // diff resolution
            '86GPQP00+',
            '2GHG5MRC+',
            '2GHG5MQJ+V7',
            '2GHG5MQJ+V75',
        );
        foreach($trueCodes as $i)
            $this->assertTrue( OpenLocationCode::isValid( $i ),
                'Code invalid: '.$i  );
    }


    public function testIsShort()
    {
        $wrongCodes = array(
            '3WCH',
            '2GHG5MQJ+V75',
        );
        foreach($wrongCodes as $i)
            $this->assertFalse( OpenLocationCode::isShort( $i ),
                'Code short: '.$i );

        $trueCodes = array(
            '3WCH+',
            '8F+GG',
            '6C8F+GG',
        );
        foreach($trueCodes as $i)
            $this->assertTrue( OpenLocationCode::isShort( $i ),
                'Code not short: '.$i );
    }


    public function testIsFull()
    {
        $wrongCodes = array(
            '3WCH',
            '6C8F+GG',
        );
        foreach($wrongCodes as $i)
            $this->assertFalse( OpenLocationCode::isFull( $i ),
                'Code full: '.$i );

        $trueCodes = array(
            '2GHG5MQJ+',
        );
        foreach($trueCodes as $i)
            $this->assertTrue( OpenLocationCode::isFull( $i ),
                'Code not full: '.$i );
    }


    public function testEncodeDecode()
    {
        $coords = array(
            array(48.492391, 34.961926, 11),
            array(48.492391, 34.961926, 12),
            array(48.492391, 34.961926, 13),
            array(48.492391, 34.961926, 14),
        );

        foreach($coords as $row)
        {
            $lat = $row[0];
            $lng = $row[1];
            $codeLen = $row[2];

            $encoded = OpenLocationCode::encode($lat, $lng, $codeLen);
            $decoded = OpenLocationCode::decode($encoded);

            $this->assertTrue( $lat >= $decoded['latitudeLo'] );
            $this->assertTrue( $lat <= $decoded['latitudeHi'] );
            
            $this->assertTrue( $lng >= $decoded['longitudeLo'] );
            $this->assertTrue( $lng <= $decoded['longitudeHi'] );
        }
    }

}
