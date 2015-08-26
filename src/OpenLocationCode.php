<?php

namespace OpenLocationCode;

/**
 * OLC represenation
 * 
 * @see OpenLocationCode::encode
 * @see OpenLocationCode::decode
 */
class OpenLocationCode
{
    // A separator used to break the code into two parts to aid memorability.
    const SEPARATOR = '+';

    // The number of characters to place before the separator.
    const SEPARATOR_POSITION = 8;

    // The character used to pad codes.
    const PADDING_CHARACTER = '0';

    // The character set used to encode the values.
    const CODE_ALPHABET = '23456789CFGHJMPQRVWX';

    // The base to use to convert numbers to/from.
    const ENCODING_BASE = 20;

    // The maximum value for latitude in degrees.
    const LATITUDE_MAX = 90;

    // The maximum value for longitude in degrees.
    const LONGITUDE_MAX = 180;

    // Maxiumum code length using lat/lng pair encoding. The area of such a
    // code is approximately 13x13 meters (at the equator), and should be suitable
    // for identifying buildings. This excludes prefix and separator characters.
    const PAIR_CODE_LENGTH = 10;

    // Number of columns in the grid refinement method.
    const GRID_COLUMNS = 4;

    // Number of rows in the grid refinement method.
    const GRID_ROWS = 5;

    // Size of the initial grid in degrees.
    const GRID_SIZE_DEGREES = 0.000125;

    // Minimum length of a code that can be shortened.
    const MIN_TRIMMABLE_CODE_LEN = 6;


    /**
     * Determines if a code is valid.
     * To be valid, all characters must be from the Open Location Code character
     * set with at most one separator. The separator can be in any even-numbered
     * position up to the eighth digit.
     *
     * @return boolean is code valid?
     */
    public static function isValid($code)
    {
        if (!$code
        || strlen($code)==1) {
            return false;
        }

        $separatorIndex  = strpos($code, self::SEPARATOR);
        $separatorRindex = strrpos($code, self::SEPARATOR);

        // The separator is required.
        if ($separatorIndex === false
        || $separatorIndex != $separatorRindex
        || $separatorIndex > self::SEPARATOR_POSITION
        || ($separatorIndex % 2) == 1) {
            return false;
        }


        // We can have an even number of padding characters before the separator,
        // but then it must be the final character.
        $paddIndex = strpos($code, self::PADDING_CHARACTER);
        if ( $paddIndex !== false) {

          // Not allowed to start with them!
          if ($paddIndex == 0) {
              return false;
          }

          // There can only be one group and it must have even length.
          $padMatch = preg_match_all('|('. self::PADDING_CHARACTER .'+)|s',
                $code, $pocket, PREG_SET_ORDER);

          if ($padMatch > 1
          || strlen($pocket[0][1]) % 2 == 1
          || strlen($pocket[0][1]) > self::SEPARATOR_POSITION - 2) {
              return false;
          }

          // If the code is long enough to end with a separator, make sure it does.
          if ( substr($code, -1) != self::SEPARATOR) {
              return false;
          }
        }

        // If there are characters after the separator, make sure there isn't just
        // one of them (not legal).
        if (strlen($code) - $separatorIndex - 1 == 1) {
            return false;
        }

        // Strip the separator and any padding characters.
        $code       = str_replace(array(self::SEPARATOR, self::PADDING_CHARACTER), '', $code);
        $codeLength = strlen($code);

        $alphaMatch = preg_match('|^(['. self::CODE_ALPHABET .']+)$|s', $code, $pocket);

        if(!$alphaMatch
        || $codeLength != strlen($pocket[0])) {
            return false;
        }

        return true;
    }


    /**
     * Determines if a code is a valid short code.
     * A short Open Location Code is a sequence created by removing four or more
     * digits from an Open Location Code. It must include a separator
     * character.
     *
     * @return boolean is code valid + short?
     */
    public static function isShort($code)
    {
        // Check it's valid.
        if (!self::isValid($code)) {
            return false;
        }

        $separatorPos = strpos($code, self::SEPARATOR);

        // If there are less characters than expected before the SEPARATOR.
        if ($separatorPos !== false
        && $separatorPos >= 0
        && $separatorPos < self::SEPARATOR_POSITION) {
            return true;
        }

        return false;
    }


    /**
     * Determines if a code is a valid full Open Location Code.
     * Not all possible combinations of Open Location Code characters decode to
     * valid latitude and longitude values. This checks that a code is valid
     * and also that the latitude and longitude values are legal. If the prefix
     * character is present, it must be the first character. If the separator
     * character is present, it must be after four characters.
     *
     * @return boolean is code full?
     */
    public static function isFull($code)
    {
        // If it's short, it's not full.
        if (self::isShort($code)
        || !self::isValid($code)) {
            return false;
        }


        // Work out what the first latitude character indicates for latitude.
        $firstLatValue = self::getCodeCoord($code, 0);
        if( $firstLatValue > self::LATITUDE_MAX * 2 ) {
            // The code would decode to a latitude of > 90 degrees.
            return false;
        }


        if (strlen($code) > 1) {
            // Work out what the first longitude character indicates for longitude.
            $firstLngValue = self::getCodeCoord($code, 1);

            if ($firstLngValue > self::LONGITUDE_MAX * 2) {
                // The code would decode to a longitude of > 180 degrees.
                return false;
            }
        }
        return true;
    }


    /**
     * Coordinate from char
     * @param  string $code   olc
     * @param  ingeger $index char index
     * @return integer        olc char index
     */
    private static function getCodeCoord($code, $index)
    {
        return strpos(self::CODE_ALPHABET, strtoupper(
            substr($code, $index, 1)
        )) * self::ENCODING_BASE;
    }


    /**
      * Encode a location into an Open Location Code.
      * Produces a code of the specified length, or the default length if no length
      * is provided.
      * The length determines the accuracy of the code. The default length is
      * 10 characters, returning a code of approximately 13.5x13.5 meters. Longer
      * codes represent smaller areas, but lengths > 14 are sub-centimetre and so
      * 11 or 12 are probably the limit of useful codes.
      *
      * @param double $latitude A latitude in signed decimal degrees. Will be clipped to the
      * 	range -90 to 90.
      * @param double $longitude A longitude in signed decimal degrees. Will be normalised to
      * 	the range -180 to 180.
      * @param integer $codeLength The number of significant digits in the output code, not
      * 	including any separator characters.
      */
    public static function encode($latitude, $longitude, $codeLength = null)
    {
        if(is_null($codeLength)) {
            $codeLength = self::PAIR_CODE_LENGTH;
        }

        if($codeLength < 2
        || ($codeLength < self::SEPARATOR_POSITION
            && $codeLength % 2 == 1)) {
            throw new \Exception('Invalid Open Location Code length');
        }


        // Ensure that latitude and longitude are valid.
        $latitude  = self::clipLatitude($latitude);
        $longitude = self::normalizeLongitude($longitude);

        // Latitude 90 needs to be adjusted to be just less, so the returned code
        // can also be decoded.
        if ($latitude == 90) {
          $latitude = $latitude - self::computeLatitudePrecision($codeLength);
        }

        $code = self::encodePairs(
            $latitude,
            $longitude,
            min($codeLength, self::PAIR_CODE_LENGTH));

        // If the requested length indicates we want grid refined codes.
        if ($codeLength > self::PAIR_CODE_LENGTH) {
            $code .= self::encodeGrid(
                $latitude,
                $longitude,
                $codeLength - self::PAIR_CODE_LENGTH);
        }

        return $code;
    }

    /**
     * Clip a latitude into the range -90 to 90.
     *
     * @param double $latitude A latitude in signed decimal degrees.
     */
    public static function clipLatitude($latitude)
    {
        return min(90, max(-90, $latitude));
    }

    /**
     * Normalize a longitude into the range -180 to 180, not including 180.
     *
     * @param double $longitude A longitude in signed decimal degrees.
     */
    public static function normalizeLongitude($longitude)
    {
        while ($longitude < -180) {
          $longitude = $longitude + 360;
        }
        while ($longitude >= 180) {
          $longitude = $longitude - 360;
        }
        return $longitude;
    }


    /**
     * Compute the latitude precision value for a given code length. Lengths <=
     * 10 have the same precision for latitude and longitude, but lengths > 10
     * have different precisions due to the grid method having fewer columns than
     * rows.
     *
     * @param integer $codeLength code length
     * @return double precision multipler
     */
    public static function computeLatitudePrecision($codeLength)
    {
        if ($codeLength <= 10) {
            return pow(20, floor($codeLength / -2 + 2));
        }

        return pow(20, -3) / pow(self::GRID_ROWS, $codeLength - 10);
    }


    /**
     * The resolution values in degrees for each position in the lat/lng pair
     * encoding. These give the place value of each position, and therefore the
     * dimensions of the resulting area.
     *
     * @param integer $index pair position (in code)
     * @return double resolution multiper
     */
    public static function getPairResolution($index)
    {
        $resolutions = array(20.0, 1.0, 0.05, 0.0025, 0.000125);
        return $resolutions[$index];
    }


    /**
     * Encode a location into a sequence of OLC lat/lng pairs.
     * This uses pairs of characters (longitude and latitude in that order) to
     * represent each step in a 20x20 grid. Each code, therefore, has 1/400th
     * the area of the previous code.
     *
     * @param double $latitude A latitude in signed decimal degrees.
     * @param double $longitude A longitude in signed decimal degrees.
     * @param double $codeLength The number of significant digits in
     *             the output code, not including any separator characters.
     * @return string open location code
     */
    public static function encodePairs($latitude, $longitude, $codeLength)
    {
        $code = '';

        // Adjust latitude and longitude so they fall into positive ranges.
        $adjustedLatitude  = $latitude + self::LATITUDE_MAX;
        $adjustedLongitude = $longitude + self::LONGITUDE_MAX;

        // Count digits - can't use string length because it may include a separator
        // character.
        $digitCount = 0;
        while ($digitCount < $codeLength)
        {
          // Provides the value of digits in this place in decimal degrees.
          $placeValue = self::getPairResolution(floor($digitCount / 2));

          // Do the latitude - gets the digit for this place and subtracts that for
          // the next digit.
          $digitValue = floor($adjustedLatitude / $placeValue);
          $adjustedLatitude -= $digitValue * $placeValue;
          $code .= substr(self::CODE_ALPHABET, $digitValue, 1);
          $digitCount += 1;

          // And do the longitude - gets the digit for this place and subtracts that
          // for the next digit.
          $digitValue = floor($adjustedLongitude / $placeValue);
          $adjustedLongitude -= $digitValue * $placeValue;
          $code .= substr(self::CODE_ALPHABET, $digitValue, 1);
          $digitCount += 1;

          // Should we add a separator here?
          if ($digitCount == self::SEPARATOR_POSITION
          && $digitCount < $codeLength) {
              $code .= self::SEPARATOR;
          }
        }


        $realCodeLen = strlen($code);
        if ($realCodeLen < self::SEPARATOR_POSITION) {
            $code .= str_repeat(self::PADDING_CHARACTER,
                self::SEPARATOR_POSITION - $realCodeLen + 1);
        }

        if (strlen($code) == self::SEPARATOR_POSITION) {
            $code .= self::SEPARATOR;
        }

        return $code;
    }


    /**
     * Encode a location using the grid refinement method into an OLC string.
     * The grid refinement method divides the area into a grid of 4x5, and uses a
     * single character to refine the area. This allows default accuracy OLC codes
     * to be refined with just a single character.
     *
     * @param double $latitude A latitude in signed decimal degrees.
     * @param double $longitude A longitude in signed decimal degrees.
     * @param double $codeLength The number of characters required.
     * @return string open location code
     */
    public static function encodeGrid($latitude, $longitude, $codeLength)
    {
        $code = '';

        $latPlaceValue = self::GRID_SIZE_DEGREES;
        $lngPlaceValue = self::GRID_SIZE_DEGREES;

        // Adjust latitude and longitude so they fall into positive ranges and
        // get the offset for the required places.
        $adjustedLatitude  = fmod($latitude + self::LATITUDE_MAX, $latPlaceValue);
        $adjustedLongitude = fmod($longitude + self::LONGITUDE_MAX, $lngPlaceValue);

        for ($i = 0; $i < $codeLength; $i++)
        {
            // Work out the row and column.
            $row = floor($adjustedLatitude / ($latPlaceValue / self::GRID_ROWS));
            $col = floor($adjustedLongitude / ($lngPlaceValue / self::GRID_COLUMNS));

            $latPlaceValue /= self::GRID_ROWS;
            $lngPlaceValue /= self::GRID_COLUMNS;

            $adjustedLatitude -= $row * $latPlaceValue;
            $adjustedLongitude -= $col * $lngPlaceValue;
            $code .= substr(self::CODE_ALPHABET, $row * self::GRID_COLUMNS + $col, 1);
        }

        return $code;
    }


    /**
     * Decodes an Open Location Code into the location coordinates.
     * Returns a CodeArea object that includes the coordinates of the bounding
     * box - the lower left, center and upper right.
     *
     * @param string $code The Open Location Code to decode.
     * @return array A CodeArea object that provides the latitude and longitude
     *                 of two of the corners of the area, the center, and the
     *                 length of the original code.
     */
    public static function decode($code)
    {
        if (!self::isFull($code)) {
            throw new \Exception('Passed Open Location Code is not a valid full code');
        }

        // Strip out separator character (we've already established the code is
        // valid so the maximum is one), padding characters and convert to upper
        // case.
        $code = str_replace(array(self::SEPARATOR, self::PADDING_CHARACTER), '', $code);
        $code = strtoupper($code);

        // Decode the lat/lng pair component.
        $codeArea = self::decodePairs( substr($code, 0, self::PAIR_CODE_LENGTH) );

        // If there is a grid refinement component, decode that.
        if (strlen($code) <= self::PAIR_CODE_LENGTH) {
            return $codeArea;
        }

        $gridArea = self::decodeGrid( substr($code, self::PAIR_CODE_LENGTH) );

        return array(
            'latitudeLo'  => $codeArea['latitudeLo']  + $gridArea['latitudeLo'],
            'longitudeLo' => $codeArea['longitudeLo'] + $gridArea['longitudeLo'],
            'latitudeHi'  => $codeArea['latitudeHi']  + $gridArea['latitudeHi'],
            'longitudeHi' => $codeArea['longitudeHi'] + $gridArea['longitudeHi'],
            'codeLength'  => $codeArea['codeLength']  + $gridArea['codeLength'],
        );
    }



    /**
    * Decode an OLC code made up of lat/lng pairs.
    * This decodes an OLC code made up of alternating latitude and longitude
    * characters, encoded using base 20.
    *
    * @param string $code A valid OLC code, presumed to be full, but with
    *                     the separator removed.
    */
    public static function decodePairs($code)
    {
        // Get the latitude and longitude values. These will need correcting from
        // positive ranges.
        $latitude  = self::decodePairsSequence($code, 0);
        $longitude = self::decodePairsSequence($code, 1);

        // Correct the values and set them into the CodeArea.
        return array(
            'latitudeLo'  => $latitude[0]  - self::LATITUDE_MAX,
            'longitudeLo' => $longitude[0] - self::LONGITUDE_MAX,
            'latitudeHi'  => $latitude[1]  - self::LATITUDE_MAX,
            'longitudeHi' => $longitude[1] - self::LONGITUDE_MAX,
            'codeLength'  => strlen($code)
        );
    }


    /**
    * Decode either a latitude or longitude sequence.
    * This decodes the latitude or longitude sequence of a lat/lng pair encoding.
    * Starting at the character at position offset, every second character is
    * decoded and the value returned.
    *
    * @param string $code A valid OLC code, presumed to be full, with the
    *                     separator removed.
    * @param integer offset: The character to start from.
    *
    * @return array A pair of the low and high values. The low value comes from
    * decoding the characters. The high value is the low value plus the resolution of the
    * last position. Both values are offset into positive ranges and will need
    * to be corrected before use.
    */
    public static function decodePairsSequence($code, $offset)
    {
        $i          = 0;
        $value      = 0;
        $codeLength = strlen($code);

        while ($i * 2 + $offset < $codeLength)
        {
            $alphaIndex = strpos(
                self::CODE_ALPHABET,
                substr($code, $i * 2 + $offset, 1)
            );
            $value += $alphaIndex * self::getPairResolution($i);
            $i++;
        }

        return array(
            $value,
            $value + self::getPairResolution($i-1)
        );
    }


    /**
    * Decode the grid refinement portion of an OLC code.
    * This decodes an OLC code using the grid refinement method.
    *
    * @param string $code A valid OLC code sequence that is only the grid refinement
    *   portion. This is the portion of a code starting at position 11.
    *
    * @return array decoded grid
    */
    public static function decodeGrid($code)
    {
        $latitudeLo    = 0.0;
        $longitudeLo   = 0.0;
        $latPlaceValue = self::GRID_SIZE_DEGREES;
        $lngPlaceValue = self::GRID_SIZE_DEGREES;
        $i             = 0;
        $codeLength    = strlen($code);

        while ($i < $codeLength)
        {
            $codeIndex = strpos(
                self::CODE_ALPHABET,
                substr($code, $i, 1)
            );

            $row = floor($codeIndex / self::GRID_COLUMNS);
            $col = $codeIndex % self::GRID_COLUMNS;


            $latPlaceValue /= self::GRID_ROWS;
            $lngPlaceValue /= self::GRID_COLUMNS;

            $latitudeLo += $row * $latPlaceValue;
            $longitudeLo += $col * $lngPlaceValue;
            $i++;
        }

        return array(
            'latitudeLo'  => $latitudeLo,
            'longitudeLo' => $longitudeLo,
            'latitudeHi'  => $latitudeLo + $latPlaceValue,
            'longitudeHi' => $longitudeLo + $lngPlaceValue,
            'codeLength'  => $codeLength
        );
    }
}
