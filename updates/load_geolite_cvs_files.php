<?php
/**
 * This script loads the regional IP address tables from xx into the philandteds database
 * - This script can only be run from the command line
 */

// Make sure this is not run from a web page
if (php_sapi_name() !== 'cli') {
    die ('Can only be run from command line');
}

require_once(__DIR__.'/../classes/ezxISO3166.php');


class NetworkBlock {
    public $network;
    public $geoname_id;
    public $start_address;
    public $end_address;
}
class Country {
    public $geoname_id;
    public $locale_code;
    public $country_iso_code;
    public $country_name;
}

class LoadRegionalCSVFiles {

    const IPV4_CSV_FILENAME = 'GeoLite2-Country-Blocks-IPv4.csv';
    const IPV6_CSV_FILENAME = 'GeoLite2-Country-Blocks-IPv6.csv';
    const COUNTRY_CSV_FILENAME = 'GeoLite2-Country-Locations-en.csv';

    const COUNTRY_CODE_TABLE = '`ezx_i2c_cc`';
    const IP_ADDRESS_TABLE   = '`ezx_i2c_ip`';

    const TEST_MODE = false; // If testing, only load max of 100 rows

    private static $ipv4_blocks;
    private static $ipv6_blocks;
    private static $country_locations;

    private static $db_connection;

    /**
     * Connect to the database
     * @param $database_host
     * @param $database_name
     * @param $database_username
     * @param $database_password
     * @param int $port
     */
    public static function init($database_host, $database_name, $database_username, $database_password, $port = 3306) {
        self::$db_connection = mysqli_connect($database_host, $database_username, $database_password, $database_name, $port);
        if (mysqli_connect_errno()) {
            throw new RuntimeException("Failed to connect to MySQL: " . mysqli_connect_error());
        }
    }

    /**
     * Assert that expected and actual values are equal, and if not, raise an exception
     * @param $expected
     * @param $actual
     * @param $message
     */
    private static function assert_equals($expected, $actual, $message) {
        if ($actual !== $expected) {
            throw new RuntimeException("$message\n    Expected: $expected\n    Actual: $actual\n\n");
        }
    }

    /**
     * Run an SQL statement and throw an exception if there on error
     * @param $sql string
     * @param $where string
     * @RuntimeException
     */
    private static function run_query($sql, $where) {
        $result = mysqli_query(self::$db_connection, $sql);
        $error = mysqli_error(self::$db_connection);
        if (!empty($error)) {
            throw new RuntimeException("Database error: $error\nAt: $where\n\n");
        }
    }
    /**
     * Load a file of IP addresses (IPv4 or IPv6) into an array of NetworkBlock
     * @param $ip_filename
     * @param $what
     * @return array of NetworkBlock
     */
    private static function load_ip_file($ip_filename, $what) {
        // Load IPv4 or v6 file
        $file = fopen($ip_filename,"r");

        // Validate header
        $header = fgetcsv($file);
        self::assert_equals('network',    $header[0], "$what CSV file header mismatch");
        self::assert_equals('geoname_id', $header[1], "$what CSV file header mismatch");

        $row_count = 0;
        $ip_blocks = array();
        while (($row = fgetcsv($file, 1000, ",")) !== FALSE) {
            $column_count = count($row);
            self::assert_equals(6, $column_count, "$what CSV file column count");
            $ip_block = new NetworkBlock();
            $ip_block->network = $row[0];
            $ip_block->geoname_id = (int)$row[1];
            $ip_blocks[] = $ip_block;
            $row_count++;
            if (self::TEST_MODE && $row_count > 100) {
                break;
            }
        }
        fclose($file);
        return $ip_blocks;
    }

    /**
     * Load a file of country codes into an array of Country
     * @param $country_filename
     * @param $what
     * @return array of Country
     */
    private static function load_country_file($country_filename, $what) {
        // Load country file
        $file = fopen($country_filename,"r");

        // Validate header
        $header = fgetcsv($file);
        self::assert_equals('geoname_id',        $header[0], "$what CSV file header mismatch");
        self::assert_equals('locale_code',       $header[1], "$what CSV file header mismatch");
        self::assert_equals('continent_code',    $header[2], "$what CSV file header mismatch");
        self::assert_equals('country_iso_code',  $header[4], "$what CSV file header mismatch");
        self::assert_equals('country_name',      $header[5], "$what CSV file header mismatch");


        $ip_blocks = array();
        while (($row = fgetcsv($file, 1000, ",")) !== FALSE) {
            $column_count = count($row);
            self::assert_equals(7, $column_count, "$what CSV file column count");
            $country = new Country();
            $country->geoname_id        = (int)$row[0];
            $country->locale_code       = $row[1];
            // If country code is empty, use continent code instead
            $country->country_iso_code  = empty($row[4]) ? $row[2] : $row[4];
            $country->country_name      = $row[5];
            $ip_blocks[] = $country;
        }
        fclose($file);
        return $ip_blocks;
    }

    /**
     * Load the CSV files we need into memory
     */
    public static function load_csv_files() {

        $directory = __DIR__ . '/geolite_csv_files/';

        $ipv4_filename    = $directory . self::IPV4_CSV_FILENAME;
        $ipv6_filename    = $directory . self::IPV6_CSV_FILENAME;
        $country_filename = $directory . self::COUNTRY_CSV_FILENAME;

        // Load IPv4 file
        echo "Reading IPv4 CSV file...\n";
        self::$ipv4_blocks = self::load_ip_file($ipv4_filename, 'IPv4');
        echo "... read " . count(self::$ipv4_blocks) . " entries\n\n";

        // Load IPv6 file
        echo "Reading IPv6 CSV file...\n";
        self::$ipv6_blocks = self::load_ip_file($ipv6_filename, 'IPv6');
        echo "... read " . count(self::$ipv6_blocks) . " entries\n\n";

        // Load country file
        echo "Reading Country CSV file...\n";
        self::$country_locations = self::load_country_file($country_filename, 'Country');
        echo "... read " . count(self::$country_locations) . " entries\n\n";
    }

    /**
     * Update the IP address blocks by adding IP address ranges to them
     */
    public static function add_ip_address_ranges() {

        echo "Converting IPv4 addresses...\n";
        /** @var NetworkBlock $block */
        foreach (self::$ipv4_blocks as $block) {
            $network = $block->network;
            list($start_address, $end_address) = ezxISO3166::convertIpv4PrefixToRange($network);
            $block->start_address = $start_address;
            $block->end_address = $end_address;
        }
        echo "... converted\n\n";

        echo "Converting IPv6 addresses...\n";
        /** @var NetworkBlock $block */
        foreach (self::$ipv6_blocks as $block) {
            $network = $block->network;
            list($start_address, $end_address) = ezxISO3166::convertIpv6PrefixToRange($network);
            $block->start_address = $start_address;
            $block->end_address = $end_address;
        }
        echo "... converted\n\n";
    }

    /**
     * Save the country code and IP address data to the database
     */
    public static function save_to_database() {
        echo "Clearing tables ...\n";
        $sql = "DELETE FROM " . self::COUNTRY_CODE_TABLE;
        self::run_query($sql, "Clear database table ".self::COUNTRY_CODE_TABLE);
        $sql = "DELETE FROM " . self::IP_ADDRESS_TABLE;
        self::run_query($sql, "Clear database table ".self::IP_ADDRESS_TABLE);
        echo "...done\n\n";

        echo "Saving country table ...\n";
        $sql = "INSERT INTO " . self::COUNTRY_CODE_TABLE . " VALUES(?,?,?)";
        $statement = mysqli_prepare(self::$db_connection, $sql);
        mysqli_stmt_bind_param($statement, "iss", $id, $code, $name);

        /** @var Country $country */
        foreach (self::$country_locations as $country) {
            $id = $country->geoname_id;
            $code = $country->country_iso_code;
            $name = $country->country_name;
            mysqli_stmt_execute($statement);
        }
        mysqli_stmt_close($statement);
        echo "...done\n\n";

        echo "Saving IP address table ...\n";
        $sql = "INSERT INTO " . self::IP_ADDRESS_TABLE . " VALUES(?,?,INET6_ATON(?),INET6_ATON(?),?)";
        $statement = mysqli_prepare(self::$db_connection, $sql);
        mysqli_stmt_bind_param($statement, "isssi", $type, $network, $start, $end, $id);

        /** @var NetworkBlock $ip_block */
        foreach (self::$ipv4_blocks as $ip_block) {
            $type = ezxISO3166::TYPE_IPV4;
            $network = $ip_block->network;
            $start = $ip_block->start_address;
            $end = $ip_block->end_address;
            $id = $ip_block->geoname_id;
            mysqli_stmt_execute($statement);
            $error = mysqli_stmt_error($statement);
            if (!empty($error)) {
                throw new RuntimeException("Database statement error: $error\n");
            }
        }
        /** @var NetworkBlock $ip_block */
        foreach (self::$ipv6_blocks as $ip_block) {
            $type = ezxISO3166::TYPE_IPV6;
            $network = $ip_block->network;
            $start = $ip_block->start_address;
            $end = $ip_block->end_address;
            $id = $ip_block->geoname_id;
            mysqli_stmt_execute($statement);
            $error = mysqli_stmt_error($statement);
            if (!empty($error)) {
                throw new RuntimeException("Database statement error: $error\n");
            }
        }
        mysqli_stmt_close($statement);
        echo "...done\n\n\n";

        mysqli_close(self::$db_connection);
    }
}

LoadRegionalCSVFiles::init(
    $database_host = 'localhost',
    $database_name = 'phil_and_teds',
    $database_username = 'phil_and_teds',
    $database_password = 'p&t'
);

LoadRegionalCSVFiles::load_csv_files();
LoadRegionalCSVFiles::add_ip_address_ranges();
LoadRegionalCSVFiles::save_to_database();

