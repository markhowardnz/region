How to install the new region module for IPv6
---------------------------------------------

1. Prepare the database with the new table structure
php <path to region module>updates/update_database_for_ipv6.php

2. Load the new CSV files from the MaxMind Geo-lite database
php <path to region module>updates/load_geolite_cvs_files.php

3. Install the module into eZ Publish



Steve Moseley
Clearfield Software Ltd
Sept 2018
