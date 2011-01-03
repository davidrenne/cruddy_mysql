This directory contains all of the serialized objects/configurations that you populate using the web interface. 

Please click on "Productionize" when you are mostly done configuring to secure these files from being read if your .htaccess is somehow removed from this directory.

One main file called {SERVERNAME}.config.php will contain ALL TABLES you configure for the primary administration console.

{SERVERNAME}.connections.php contains your primary mysql server connections for any files generated in the pages folder.

{SERVERNAME}.custom.functions.php contains paths to your custom_processors directory where you can create custom actions for any CRUD update.

{SERVERNAME}.draw.functions.php is a system file used for the main console.

{CONFIGURATION_TABLE}.config.php is the main configuration file generated when you submit the Fields form on any php page located in the pages directory