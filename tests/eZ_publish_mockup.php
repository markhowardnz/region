<?php
class eZDebug {
    private static $debug;

    public static function writeDebug($message, $description = "") {
        if (self::$debug) {
            if (is_array($message)) {
                $message = print_r($message, true);
            }
            echo "MOCKUP: eZDebug: $description - $message\n";
        }
    }
    public static function mock_init($debug) {
        self::$debug = $debug;
    }
}
class eZINI {
    private static $defaultCountryCode;
    private static $instance;
    public static function instance($filename) {
        if (self::$instance === NULL) {
            self::$instance = new eZINI();
        }
        return self::$instance;
    }
    public function variable($name, $default) {
        switch ("$name:$default") {
            case 'Settings:DefaultCountryCode':
                return self::$defaultCountryCode; // from region.ini
            case 'RegionalSettings:LanguageSA':
                return false; // from site.ini
            default:
                throw new RuntimeException("MOCKUP: eZINI->variable undefined: '$name:$default'");
        }
    }
    public function variableArray($name, $default) {
        switch ("$name:$default") {
            case 'RegionalSettings:LanguageSA':
                return false; // from site.ini
            case 'Regions:LocaleCountryList':
                return array(); // from region.ini
            default:
                throw new RuntimeException("MOCKUP: eZINI->variableArray undefined: '$name:$default'");
        }
    }
    public static function mock_init($defaultCountryCode) {
        self::$defaultCountryCode = $defaultCountryCode;
    }
}
class eZDB {
    private static $instance;

    private $connection;

    public static function mock_init($host, $user, $password, $database, $port = 3306) {
        self::$instance = new eZDB();
        self::$instance->connection = mysqli_connect($host, $user, $password, $database, $port);
        if (mysqli_connect_errno()) {
            throw new RuntimeException("MOCKUP: eZDB Failed to connect to MySQL: " . mysqli_connect_error());
        }
    }

    public static function instance() {
        return self::$instance;
    }
    public static function arrayQuery($sql) {
        // SELECT cc, cn FROM ezx_i2c_ip NATURAL JOIN ezx_i2c_cc WHERE 3526388609 BETWEEN start AND end
        $result = mysqli_query(self::$instance->connection, $sql);
        if ($result === FALSE) {
            throw new RuntimeException("MOCKUP: eZDB query error: " . mysqli_error(self::$instance->connection));
        }
        // fetch all rows
        $rows = mysqli_fetch_all($result,MYSQLI_ASSOC);
        return $rows;
    }
}
class MockupHandlerClass {
    public function setDestinationSiteAccess($selection) {
    }
    public function process() {
    }
    public function destinationUrl() {
        return "";
    }
}
class MockupModule {

    public function redirectTo($destinationUrl) {
    }
}
class eZExtension {
    public static function getHandlerClass($handlerOptions) {
        return new MockupHandlerClass();
    }
}
class ezpExtensionOptions {

    public $iniFile;
    public $iniSection;
    public $iniVariable;
    public $handlerParams;
}
