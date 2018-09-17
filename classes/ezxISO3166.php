<?php

class ezxISO3166
{
    const TYPE_IPV4 = FALSE;
    const TYPE_IPV6 = TRUE;

    var $address;
    var $type;

    /**
     * Ranges of reserved IPv6 addresses that cannot possibly be valid IP addresses on the open internet
     * SEE: https://en.wikipedia.org/wiki/IPv6_address#Special_addresses
     */
    private static $ipv6ReservedRanges = null;

    private static function getIpv6ReservedRanges() {
        if (self::$ipv6ReservedRanges === null) {
            self::$ipv6ReservedRanges = array(
                self::convertIpv6PrefixToRange('::/128'),
                self::convertIpv6PrefixToRange('::1/128'),
                self::convertIpv6PrefixToRange('::ffff:0:0/96'),
                self::convertIpv6PrefixToRange('::ffff:0:0:0/96'),
                self::convertIpv6PrefixToRange('64:ff9b::/96'),
                self::convertIpv6PrefixToRange('100::/64'),
                self::convertIpv6PrefixToRange('2001::/32'),
                self::convertIpv6PrefixToRange('2001:20::/28'),
                self::convertIpv6PrefixToRange('2001:db8::/32'),
                self::convertIpv6PrefixToRange('fc00::/7'),
                self::convertIpv6PrefixToRange('fe80::/10'),
                self::convertIpv6PrefixToRange('ff00::/8'),
            );
        }
        return self::$ipv6ReservedRanges;
    }

    function __construct($address = null )
    {
        if ( ! $address ) {
            $this->address = ezxISO3166::getRealIpAddr();
        } else {
            $this->address = $address;
        }
        if (strpos($this->address, ':') !== FALSE) {
            $this->type = self::TYPE_IPV6;
        } else {
            $this->type = self::TYPE_IPV4;
        }
    }

    /**
     * Convert an IPv4 CIDR prefix to a start-end range
     * REF: https://stackoverflow.com/questions/4931721/getting-list-ips-from-cidr-notation-in-php
     * @param $cidr_prefix string CIDR prefix eg '73.35.143.32/27'
     * @return array($start_address, $end_address) eg array('73.35.143.32', '73.35.143.63')
     */
    public static function convertIpv4PrefixToRange($cidr_prefix)
    {
        if ($ip = strpos($cidr_prefix, '/')) {
            $n_ip = (1 << (32 - substr($cidr_prefix, 1 + $ip))) - 1;
            $ip_dec = ip2long(substr($cidr_prefix, 0, $ip));
        } else {
            $n_ip = 0;
            $ip_dec = ip2long($cidr_prefix);
        }
        $ip_min = $ip_dec & ~$n_ip;
        $ip_max = $ip_min + $n_ip;
        return array(long2ip($ip_min), long2ip($ip_max));
    }


    /**
     * Convert an IPv6 CIDR prefix to a start-end range
     * REF: https://stackoverflow.com/questions/10085266/php5-calculate-ipv6-range-from-cidr-prefix
     * @param $cidr_prefix string CIDR prefix eg '2001:db8:abc:1400::/54'
     * @return array($start_address, $end_address) eg array('2001:db8:abc:1400::', '2001:db8:abc:17ff:ffff:ffff:ffff:ffff')
     */
    public static function convertIpv6PrefixToRange($cidr_prefix)
    {
        // Split in address and prefix length
        list($first_address_str, $prefix_len) = explode('/', $cidr_prefix);

        // Parse the address into a binary string
        $first_address_bin = inet_pton($first_address_str);

        // Convert the binary string to a string with hexadecimal characters
        # unpack() can be replaced with bin2hex()
        # unpack() is used for symmetry with pack() below
        $unpacked = unpack('H*', $first_address_bin);
        $first_address_hex = reset($unpacked);

        // Overwriting first address string to make sure notation is optimal
        $first_address_str = inet_ntop($first_address_bin);

        // Calculate the number of 'flexible' bits
        $flex_bits = 128 - $prefix_len;

        // Build the hexadecimal string of the last address
        $last_address_hex = $first_address_hex;

        // We start at the end of the string (which is always 32 characters long)
        $pos = 31;
        while ($flex_bits > 0) {
            // Get the character at this position
            $orig = substr($last_address_hex, $pos, 1);

            // Convert it to an integer
            $orig_val = hexdec($orig);

            // OR it with (2^flexbits)-1, with flexbits limited to 4 at a time
            $new_val = $orig_val | (pow(2, min(4, $flex_bits)) - 1);

            // Convert it back to a hexadecimal character
            $new = dechex($new_val);

            // And put that character back in the string
            $last_address_hex = substr_replace($last_address_hex, $new, $pos, 1);

            // We processed one nibble, move to previous position
            $flex_bits -= 4;
            $pos -= 1;
        }
        // Convert the hexadecimal string to a binary string
        # Using pack() here
        # Newer PHP (>= 5.4) can use hex2bin()
        $last_address_bin = pack('H*', $last_address_hex);

        // And create an IPv6 address from the binary string
        $last_address_str = inet_ntop($last_address_bin);

        return (array($first_address_str, $last_address_str));
    }

    /**
     * Return TRUE if the IPv6 address supplied is within the range specified by the start and end IP addresses
     * @param $ip string IP address to test
     * @param $ip_start string start of range
     * @param $ip_end string end of range
     * @return bool TRUE if within range
     */
    public static function isIpV6WithinRange($ip, $ip_start, $ip_end)
    {
        $packed_ip    = current(unpack("a4",inet_pton($ip)));
        $packed_start = current(unpack("a4",inet_pton($ip_start)));
        $packed_end   = current(unpack("a4",inet_pton($ip_end)));

        return ($packed_ip >= $packed_start) && ($packed_ip <= $packed_end);
    }

    static function validip( $ip )
    {
        if (empty ($ip)) {
            return false;
        }
        // Check if it contains a dot or colon, to see if it's an IPv4 or IPv6 IP address
        if (strpos($ip, '.') !== false) {
            // Most likely an IPv4 address
            if ( ip2long( $ip ) !== FALSE )
            {
                $reserved_ips = array(
                    array(
                        '0.0.0.0' ,
                        '0.255.255.255'
                    ) ,
                    array(
                        '10.0.0.0' ,
                        '10.255.255.255'
                    ) ,
                    array(
                        '100.64.0.0' ,
                        '100.127.255.255'
                    ) ,
                    array(
                        '127.0.0.0' ,
                        '127.255.255.255'
                    ) ,
                    array(
                        '169.254.0.0' ,
                        '169.254.255.255'
                    ) ,
                    array(
                        '172.16.0.0' ,
                        '172.31.255.255'
                    ) ,
                    array(
                        '192.0.0.0' ,
                        '192.0.0.7'
                    ) ,
                    array(
                        '192.0.2.0' ,
                        '192.0.2.255'
                    ) ,
                    array(
                        '192.88.99.0' ,
                        '192.88.99.255'
                    ) ,
                    array(
                        '192.168.0.0' ,
                        '192.168.255.255'
                    ) ,
                    array(
                        '198.18.0.0' ,
                        '198.19.255.255'
                    ) ,
                    array(
                        '198.51.100.0' ,
                        '198.51.100.255'
                    ) ,
                    array(
                        '203.0.113.0' ,
                        '203.0.113.255'
                    ) ,
                    array(
                        '224.0.0.0' ,
                        '239.255.255.255'
                    ) ,
                    array(
                        '240.0.0.0' ,
                        '255.255.255.255'
                    )
                );

                foreach ( $reserved_ips as $r )
                {
                    $min = ip2long( $r[0] );
                    $max = ip2long( $r[1] );
                    if ( ( ip2long( $ip ) >= $min ) && ( ip2long( $ip ) <= $max ) )
                        return false;
                }
                return true;
            }
            else
            {
                return false;
            }
        } else
        if (strpos($ip, ':') !== false) {
            // Most likely an IPv6 address
            $packed_ip = inet_pton($ip);
            if ($packed_ip !== FALSE) {
                // Check it's not a known reserved address
                $reserved_ranges = ezxISO3166::getIpv6ReservedRanges();
                foreach ($reserved_ranges as $range) {
                    $in_range = ezxISO3166::isIpV6WithinRange($ip, $range[0], $range[1]);
                    if ($in_range) {
                        eZDebug::writeDebug( 'Failed IP address validation: '.$ip, 'ezxISO3166::validip()' );
                        return false;
                    }
                }
                // Doesn't match any reserved ranges, looks ok
                return true;

            } else {
                return false;
            }

        } else {
            // Neither IPv4 nor IPv6
            return false;
        }
    }

    static function getRealIpAddr()
    {
        if ( array_key_exists( "HTTP_CLIENT_IP", $_SERVER ) and ezxISO3166::validip( $_SERVER["HTTP_CLIENT_IP"] ) )
        {
            return $_SERVER["HTTP_CLIENT_IP"];
        }
        if( array_key_exists( "HTTP_X_FORWARDED_FOR", $_SERVER ) )
        {
        foreach ( explode( ",", $_SERVER["HTTP_X_FORWARDED_FOR"] ) as $ip )
        {
            if ( ezxISO3166::validip( trim( $ip ) ) )
            {
                return $ip;
            }
        }
        }
        if ( array_key_exists( "HTTP_X_FORWARDED", $_SERVER ) and ezxISO3166::validip( $_SERVER["HTTP_X_FORWARDED"] ) )
        {
            return $_SERVER["HTTP_X_FORWARDED"];
        }
        elseif ( array_key_exists( "HTTP_FORWARDED_FOR", $_SERVER ) and ezxISO3166::validip( $_SERVER["HTTP_FORWARDED_FOR"] ) )
        {
            return $_SERVER["HTTP_FORWARDED_FOR"];
        }
        elseif ( array_key_exists( "HTTP_FORWARDED", $_SERVER ) and ezxISO3166::validip( $_SERVER["HTTP_FORWARDED"] ) )
        {
            return $_SERVER["HTTP_FORWARDED"];
        }
        elseif ( array_key_exists( "HTTP_X_FORWARDED", $_SERVER ) and ezxISO3166::validip( $_SERVER["HTTP_X_FORWARDED"] ) )
        {
            return $_SERVER["HTTP_X_FORWARDED"];
        }
        else
        {
            return $_SERVER["REMOTE_ADDR"];
        }
    }

    static function defaultCountryCode()
    {
        $regionini = eZINI::instance( 'region.ini' );
        return strtoupper( $regionini->variable( 'Settings', 'DefaultCountryCode' ) );
    }

    function getALLfromIP()
    {
        $query = "SELECT cc, cn, network FROM ezx_i2c_ip NATURAL JOIN ezx_i2c_cc WHERE `type` = ".(int)$this->type." AND INET6_ATON('" . $this->address . "') BETWEEN start AND end";
        $db = eZDB::instance();
        $result = $db->arrayQuery( $query );
        if ( isset( $result[0] ) )
            return $result[0];
    }

    function getCCfromIP()
    {
        $data = $this->getALLfromIP();
        if ( isset( $data['cc'] ) )
            return $data['cc'];
        else
            return false;
    }

    function getCOUNTRYfromIP()
    {
        $data = $this->getALLfromIP();
        if ( isset( $data['cn'] ) )
        {
            return $data['cn'];
        }
        else
            return false;
    }

    function getCCfromNAME( $name )
    {
        $ip2country = new ip2country( gethostbyname( $name ) );
        return $ip2country->getCCfromIP();
    }

    function getCOUNTRYfromNAME( $name )
    {
        $ip2country = new ip2country( gethostbyname( $name ) );
        return $ip2country->getCOUNTRYfromIP();
    
    }

    function getCountryCodeFromAccess( $accessname )
    {
        $list = preg_split( '/[_-]/', $accessname, 2 );
        return $list[0];
    }

    static function getPrimaryLocales( $Code = null, $exceptCurrent = true )
    {
        $regionini = eZINI::instance( 'region.ini' );
        $list = preg_split( '/[_-]/', $Code, 2 );
        $regionini = eZINI::instance( 'region.ini' );
        $regions = $regionini->groups();
        unset( $regions['Settings'] );
        $locales = array();
        foreach ( $regions as $key => $region )
        {
            $list2 = preg_split( '/[_-]/', $key, 2 );
            if ( array_key_exists( 1, $list2 ) and ! isset( $locales[$list2[1]] ) )
            {
                /* @TODO $exceptCurrent
                if ( $exceptCurrent and ( $Code != $region['Siteaccess'] ) )
                {

                }
                elseif( $exceptCurrent === false )
                {

                }
                */
                $region['code'] = $list2[0] . '-' . $list2[1];
                if ( $region['code'] != '*-*' )
                {
                    $region['possible_languagecodes'] = array();
                    array_push( $region['possible_languagecodes'], $list2[0] . '-' . $list2[1] );
                    array_push( $region['possible_languagecodes'], $list2[0] );
                }
                else
                {
                    $region['possible_languagecodes'] = array();
                    array_push( $region['possible_languagecodes'], $region['Siteaccess'] );
                    
                    $extralang = $regionini->variable( '*_*', 'AdditionalLanguageList' );
                    foreach ( $extralang as $lang )
                    {
                        array_push( $region['possible_languagecodes'], $lang );
                    }
                }
                $locales[$list2[1]] = $region;
            }
        }
        return $locales;
    }

    static function getLanguagesFromLocalCode( $Code, $exceptCurrent = true )
    {
        $list = preg_split( '/[_-]/', $Code, 2 );
        $regionini = eZINI::instance( 'region.ini' );
        $regions = $regionini->groups();
        unset( $regions['Settings'] );
        $languages = array();
        foreach ( $regions as $key => $region )
        {
            $list2 = preg_split( '/[_-]/', $key, 2 );
            if ( $list[1] == $list2[1] )
            {
                if ( $exceptCurrent and ( $Code != $region['Siteaccess'] ) )
                {
                    $languages[$region['Siteaccess']] = $region;
                }
                elseif ( $exceptCurrent === false )
                {
                    $languages[$region['Siteaccess']] = $region;
                }
            }
        
        }
        return $languages;
    }

    static function countries()
    {
        $regionini = eZINI::instance( 'region.ini' );
        $regions = $regionini->groups();
        unset( $regions['Settings'] );
        $countries = array();
        foreach ( $regions as $key => $region )
        {
            $list = preg_split( '/[_-]/', $key, 2 );
            if ( isset( $list[1] ) )
                $countries[$list[1]] = $list[1];
        }
        return $countries;
    }

    static function preferredCountry( $address = null )
    {
        $ip = new ezxISO3166( $address );
        $code = $ip->getCCfromIP();
        
        if ( ! $code )
            $code = ezxISO3166::defaultCountryCode();
        //$countries = ezxISO3166::countries();
//        if ( in_array( $code, $countries ) )
            return $code;
/*        else 
            if ( $code )
                return true;
            else
                return false; */
    }
}
?>
