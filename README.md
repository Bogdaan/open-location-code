[![Build
Status](https://secure.travis-ci.org/Bogdaan/open-location-code.png)](http://travis-ci.org/Bogdaan/open-location-code)


# Open location code for php

> Open Location Codes are a way of encoding location into a form that is easier to use than latitude and longitude.
>
> They are designed to be used as a replacement for street addresses, especially in places where buildings aren't numbered or > streets aren't named.
>
> Open Location Codes represent an area, not a point. As digits are added to a code, the area shrinks, so a long code is more accurate than a short code.
>
> A location can be converted into a code, and a code can be converted back to a location completely offline.


Based on javascript version from [this repo](https://github.com/google/open-location-code).

# Usage

Install via [composer](http://getcomposer.org):
```
$ composer require bogdaan/open-location-code
```

Examples:
```php

use OpenLocationCode\OpenLocationCode;

// encode
var_dump(OpenLocationCode::encode(48.41, 34.81));

// decode (return area array)
var_dump(OpenLocationCode::decode("44870000+"));

```

Method OpenLocationCode::decode returns array with following keys:

+ **latitudeLo**, **longitudeLo** - the coordinates of the lower left corner of the square
+ **latitudeHi**, **longitudeHi** - the coordinates of the top right corner of the square
+ **codeLength** - decoded length



# Links

+ [OLC format, documentation](https://github.com/google/open-location-code/blob/master/docs/olc_definition.adoc)
+ [Demo site](https://plus.codes/)
