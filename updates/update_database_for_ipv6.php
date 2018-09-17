<?php
/**
 * This script prepares the philandteds database, ready for the new IPv6 regional checking.
 * - This script can only be run from the command line
 * - The database username supplied must have permissions to ALTER TABLES
 * - This script must be run prior to using the new region module, which needs the updated tables
 */

// Make sure this is not run from a web page
if (php_sapi_name() !== 'cli') {
    die ('Can only be run from command line');
}


class UpdateRegionalTables {

    const DATABASE_HOST     = 'localhost';
    const DATABASE_NAME     = 'phil_and_teds';
    const DATABASE_USERNAME = 'phil_and_teds';
    const DATABASE_PASSWORD = 'p&t';
    const DATABASE_PORT     = 3306;

    const COUNTRY_CODE_TABLE = '`ezx_i2c_cc`';
    const IP_ADDRESS_TABLE   = '`ezx_i2c_ip`';

    private static $db_connection;

    /**
     * Open a connection to the database
     */
    public static function init() {
        self::$db_connection = mysqli_connect(self::DATABASE_HOST, self::DATABASE_USERNAME, self::DATABASE_PASSWORD, self::DATABASE_NAME, self::DATABASE_PORT);
        if (mysqli_connect_errno()) {
            throw new RuntimeException("Failed to connect to MySQL: " . mysqli_connect_error());
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
     * Make necessary updates to the region database tables
     */
    public static function update_database_tables() {

        // Allow country table to store a larger integer
        echo "Alter country table...";
        $sql = "ALTER TABLE " . self::COUNTRY_CODE_TABLE . " CHANGE `ci` `ci` INT(4) UNSIGNED NOT NULL";
        self::run_query($sql, "alter `ci` field in table ".self::COUNTRY_CODE_TABLE);
        echo "...done\n";

        // Allow ip table to store a larger integer
        echo "Alter ip table...";
        $sql = "ALTER TABLE " . self::IP_ADDRESS_TABLE . " CHANGE `ci` `ci` INT(4) UNSIGNED NOT NULL";
        self::run_query($sql, "alter `ci` field in table ".self::IP_ADDRESS_TABLE);
        echo ".part1.";

        $sql = "ALTER TABLE " . self::IP_ADDRESS_TABLE . " ADD `network` VARCHAR(50) NOT NULL FIRST";
        self::run_query($sql, "add `network` field in table ".self::IP_ADDRESS_TABLE);
        echo ".part2.";

        $sql = "ALTER TABLE " . self::IP_ADDRESS_TABLE . " ADD `type` INT NOT NULL DEFAULT '0' FIRST";
        self::run_query($sql, "add `type` field in table ".self::IP_ADDRESS_TABLE);
        echo ".part3.";

        // Allow ip table to store varbinary IPv6 addresses
        $sql = "ALTER TABLE " . self::IP_ADDRESS_TABLE . " CHANGE `start` `start` VARBINARY(16) NOT NULL";
        self::run_query($sql, "alter `start` field in table ".self::IP_ADDRESS_TABLE);
        echo ".part4.";
        $sql = "ALTER TABLE " . self::IP_ADDRESS_TABLE . " CHANGE `end` `end` VARBINARY(16) NOT NULL";
        self::run_query($sql, "alter `end` field in table ".self::IP_ADDRESS_TABLE);
        echo "...done\n";

    }
}

UpdateRegionalTables::init();
UpdateRegionalTables::update_database_tables();
