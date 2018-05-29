<?php
date_default_timezone_set('America/Chicago');

//Coordinates to start the form with; or leave blank
$GLOBALS['coords'] = '32.7830,-96.8116';
//Weather Forecast Office, gridpoint X/Y coordinates, and/or nearest station; or leave blank
//If left blank, these will be determined from the coordinates, but specifying here will save API request time.
$GLOBALS['wfo'] = 'FWD';
$GLOBALS['gridxy'] = '88,103';
$GLOBALS['station'] = 'KDAL';

$GLOBALS['highlights'] = true; //Highlight some stuff that Luke cares about especially
?>