<?php
if(!file_exists('../settings.php')) die("Sorry, settings.php is missing. Please duplicate settings-sample.php as settings.php and edit to suit.");
require('../settings.php');

$GLOBALS['errors'] = array(); //todo convert to real error handling
$result = '';

try { //for error message handling
  
  //Default values for text fields in the form
  foreach(array('coords','wfo','gridxy','station') as $key) { //sanitize in place
    if(isset($_REQUEST[$key])) { /*$_REQUEST[$key] = urldecode($_REQUEST[$key]);*/ } //is this even necessary?
    else $_REQUEST[$key] = '';
  }
  if($_REQUEST['coords']=='' && $_REQUEST['wfo']=='' && $_REQUEST['gridxy']=='' && $_REQUEST['station']=='') {
    $_REQUEST['coords'] = $GLOBALS['coords'];
    $_REQUEST['wfo'] = $GLOBALS['wfo'];
    $_REQUEST['gridxy'] = $GLOBALS['gridxy'];
    $_REQUEST['station'] = $GLOBALS['station'];
  }
  
  //Sanitize lat,long further: if there is a trailing zero, or if they are more than 4 decimal places, it won't work
  if($_REQUEST['coords']!='') {
    $coords = explode(',',$_REQUEST['coords']);
    if(sizeof($coords)!=2) throw new Exception("Please specify latitude and longitude in the format \"32.783,-96.8116\"");
    foreach($coords as $k=>$v) $coords[$k] = trimnum($v);
    $_REQUEST['coords'] = implode(',',$coords);
  }
  
  //Get data if requested, and do some validation and lookups
  if(isset($_REQUEST['reqtype']) && !sizeof($GLOBALS['errors'])) {
    switch(urldecode($_REQUEST['reqtype'])) {
      case 'Raw grid data':
      case 'Daily forecast':
      case 'Hourly forecast':
      case 'Stations':
          //For these we'll need either coords or both wfo and gridpoint.
          if(!$_REQUEST['coords'] && (!$_REQUEST['gridxy'] || !$_REQUEST['wfo'])) throw new Exception("Please specify either Lat,Long &mdash;or&mdash; both WFO and Grid X,Y");
          if(!$_REQUEST['gridxy'] || !$_REQUEST['wfo']) $result .= getGridpoint(false); //Coords are only used to look up wfo and gridpoint, if they aren't specified.
          switch(urldecode($_REQUEST['reqtype'])) {
            case 'Raw grid data': $result .= getRawGridData(); break;
            case 'Daily forecast': $result .= getDailyForecast(); break;
            case 'Hourly forecast': $result .= getHourlyForecast(); break;
            //While we can view all a gridpoint's stations, it is primarily used to get the closest station for local observations, if not specified.
            case 'Stations': $result .= getStations(true); break;
            default: break;
          }
      case 'Latest observation':
      case 'Recent observations':
          //For these we'll need the station, or the data above.
          if(!$_REQUEST['station'] && !$_REQUEST['coords'] && (!$_REQUEST['gridxy'] || !$_REQUEST['wfo'])) throw new Exception("Please specify Station &mdash;or&mdash; Lat,Long &mdash;or&mdash; both WFO and Grid X,Y");
          if(!$_REQUEST['station']) {
            if(!$_REQUEST['gridxy'] || !$_REQUEST['wfo']) $result .= getGridpoint(false); //Coords are only used to look up wfo and gridpoint, if they aren't specified.
            getStations(false);
          }
          switch(urldecode($_REQUEST['reqtype'])) {
            case 'Latest observation': $result .= getLatestObservation(); break;
            case 'Recent observations': $result .= getRecentObservations(); break;
            default: break;
          }
      default: break;
    }
  }
} catch(Exception $e) {
  $GLOBALS['errors'][] = $e->getMessage();
}

//Each get function will call the API, then set request variables, do a nicely formatted output, and/or do a raw output.
function getGridpoint($showRealResults) {
  $r = callAPI('points/'.$_REQUEST['coords']);
  $_REQUEST['wfo'] = $r->properties->cwa;
  $_REQUEST['gridxy'] = $r->properties->gridX.','.$r->properties->gridY;
  return ($showRealResults? format($r): "<p>(Looked up gridpoint from lat/long)</p>");
}
function getRawGridData() {
  return format(callAPI('gridpoints/'.$_REQUEST['wfo'].'/'.$_REQUEST['gridxy']));
}
function getDailyForecast() {
  $r = callAPI('gridpoints/'.$_REQUEST['wfo'].'/'.$_REQUEST['gridxy'].'/forecast');
  $return = '';
  foreach($r->properties->periods as $t) $return .= "<tr><th>".str_replace(" ","&nbsp;",$t->name).":</th><td><img class='icon' src='".$t->icon."' title='".str_replace('https://api.weather.gov/icons/','',strtok($t->icon,'?'))."' /></td><td>".str_replace('|','<span class="divider">/</span>',$t->detailedForecast)."</td>";
  return "<h4>Daily Forecast for ".$_REQUEST['wfo'].'/'.$_REQUEST['gridxy'].":</h4><p>As of ".date("Y-m-d D H:i:s T",strtotime($r->properties->updated))."</p><table class='formatted dailyforecast'>".$return."</table>".format($r);
}
function getHourlyForecast() {
  $r = callAPI('gridpoints/'.$_REQUEST['wfo'].'/'.$_REQUEST['gridxy'].'/forecast/hourly');
  $return = '';
  foreach($r->properties->periods as $t) $return .= "<tr><th>".str_replace(" ","&nbsp;",date("D H",strtotime($t->startTime)).'h').":</th><td><img class='icon' src='".$t->icon."' title='".str_replace('https://api.weather.gov/icons/','',strtok($t->icon,'?'))."' /></td><td>".str_replace(' ','&nbsp;',$t->shortForecast)."</td><td>".str_replace(' ','&nbsp;',$t->temperature.'&deg;')."</td><td>".str_replace(' ','&nbsp;',$t->windDirection.' '.$t->windSpeed)."</td>";
  return "<h4>Hourly Forecast for ".$_REQUEST['wfo'].'/'.$_REQUEST['gridxy'].":</h4><p>As of ".date("Y-m-d D H:i:s T",strtotime($r->properties->updated))."</p><table class='formatted hourlyforecast'>".$headers.$return."</table>".format($r);
}
function getStations($showRealResults) {
  $r = callAPI('gridpoints/'.$_REQUEST['wfo'].'/'.$_REQUEST['gridxy'].'/stations');
  $_REQUEST['station'] = $r->features[0]->properties->stationIdentifier;
  return ($showRealResults? format($r): "<p>(Looked up station from gridpoint)</p>");
}
function getLatestObservation() {
  $r = callAPI('stations/'.$_REQUEST['station'].'/observations/current');
  $returnar = array();
  $t = $r->properties;
  $returnar['Local Time'] = date("Y-m-d D H:i:s T",strtotime($t->timestamp));
  $returnar['Condition'] = "<img class='icon' src='".$t->icon."' title='".str_replace('https://api.weather.gov/icons/','',strtok($t->icon,'?'))."' /> ".$t->textDescription;
  $returnar['Temperature'] = "<span class='highlight'>".number_format(floatval($t->temperature->value)*1.8 + 32, 1)."&nbsp;&deg;F</span> | ".number_format(floatval($t->temperature->value), 1)."&nbsp;&deg;C";
  $returnar['Dewpoint'] = number_format(floatval($t->dewpoint->value)*1.8 + 32, 1)."&nbsp;&deg;F | ".number_format(floatval($t->dewpoint->value), 1)."&nbsp;&deg;C";
  if($t->windChill->value) { $returnar['Wind Chill'] = number_format(floatval($t->windChill->value)*1.8 + 32, 1)."&nbsp;&deg;F | ".number_format(floatval($t->windChill->value), 1)."&nbsp;&deg;C"; }
  if($t->heatIndex->value) { $returnar['Heat Index'] = number_format(floatval($t->heatIndex->value)*1.8 + 32, 1)."&nbsp;&deg;F | ".number_format(floatval($t->heatIndex->value), 1)."&nbsp;&deg;C"; }
  $returnar['Wind'] = $t->windDirection->value."&deg;&nbsp;".getCompassPoint($t->windDirection->value)." | ".number_format(floatval($t->windSpeed->value), 1)."&nbsp;m/s | ".number_format(floatval($t->windSpeed->value)*2.23694, 1)."&nbsp;mph";
  if($t->windGust->value) { $returnar['Wind Gust'] = number_format(floatval($t->windGust->value), 1)."&nbsp;m/s | ".number_format(floatval($t->windGust->value)*2.23694, 1)."&nbsp;mph"; }
  $kpa = floatval($t->barometricPressure->value)/1000; //https://www.weather.gov/media/epz/wxcalc/pressureConversion.pdf
  $returnar['Pressure'] = "<span class='highlight'>".number_format($kpa,2)."&nbsp;kPa</span> | ".number_format($kpa*10,1)."&nbsp;mb | ".number_format($kpa*7.50062,1)."&nbsp;mmHg | ".number_format($kpa*0.2953,2)."&nbsp;inHg";
  $returnar['Rel Humidity'] = "<span class='highlight'>".number_format(floatval($t->relativeHumidity->value),1)."&nbsp;%</span>";
  $returnar['Visibility'] = number_format(floatval($t->visibility->value)/1000,2)."&nbsp;km | ".number_format((floatval($t->visibility->value)/1000)*0.621371,2)."&nbsp;mi";
  $return = "";
  foreach($returnar as $k=>$v) $return .= "<tr><th>".str_replace(" ","&nbsp;",$k)."</th><td>".str_replace('|','<span class="divider">/</span>',$v)."</td>";
  return "<h4>Latest Observation for ".substr(strrchr($t->station,'/'),1).":</h4><table class='formatted latestobservation'>".$return."</table>".format($r);
}
function getRecentObservations() {
  return format(callAPI('stations/'.$_REQUEST['station'].'/observations'));
}

function format($results) {
  return "<h4>Endpoint: <pre style='display: inline;'>".$results->originalEndpoint."</pre></h4><h4>Raw results:</h4>"."<pre>".str_replace("    ","  ",print_r($results,true))."</pre>";
}

function getCompassPoint($a){
  $a = floatval($a);
  $firstHalf = ''; $secondHalf = '';
  if($a>=326.25&& $a<=33.75) $firstHalf = 'N';
  else if($a>=56.25&& $a<=123.75) $firstHalf = 'E';
  else if($a>=146.25&& $a<=213.75) $firstHalf = 'S';
  else if($a>=236.25&& $a<=303.75) $firstHalf = 'W';
  if($a>=11.25&& $a<=78.75) $secondHalf = 'NE';
  else if($a>=101.25&& $a<=168.75) $secondHalf = 'SE';
  else if($a>=191.25&& $a<=258.75) $secondHalf = 'SW';
  else if($a>=281.25&& $a<=348.75) $secondHalf = 'NW';
  return $firstHalf.$secondHalf;
}

function trimnum($numstr) {
  $numstr = number_format(floatval($numstr),4); //enforce four decimals
  //Remove trailing zeroes, but only if it's past a decimal
  if(strpos($numstr,'.')) {
    for($i=0; $i<strlen($numstr); $i++) {
      if(substr($numstr,-1)!='0') break; //if the last digit isn't a zero, stop
      $numstr = substr($numstr,0,strlen($numstr-1));
    }
    if(substr($numstr,-1)=='.') substr($numstr,0,strlen($numstr-1)); //if left with just a decimal, remove
  }
  //Remove leading zeroes
  for($i=0; $i<strlen($numstr); $i++) {
    if(substr($numstr,0,1)!='0') break; //if the first digit isn't a zero, stop
    $numstr = substr($numstr,1);
  }
  return $numstr;
}

function callAPI($endpoint) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, "https://api.weather.gov/".$endpoint);
  $headers = array('User-Agent: luke@theclockspot.com');
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);  
  curl_setopt($ch, CURLOPT_HEADER, 0);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $out = curl_exec($ch);
  curl_close($ch);
  $r = json_decode($out);
  if(property_exists($r,'status') && property_exists($r,'detail')) { throw new Exception($r->detail); return false; }
  $r->originalEndpoint = $endpoint; //for display purposes
  return $r;
}

?><!DOCTYPE html>
<html>
<head>
  <title>National Weather Service API Tool</title>
  <style>
    @import url('https://rsms.me/inter/inter-ui.css');
  </style>
  <style>
    body { background-color: white; sans-serif; color: #333; font-family: 'Inter UI'; font-feature-settings: "tnum"; font-size: 16px; line-height: 1.45em; }
    code, pre, q { font-family: "SFMono-Regular", Menlo, Consolas, Inconsolata, monospace; font-size: 12px; line-height: 1.1em; font-weight: normal; }
    input { font-family: 'Inter UI'; font-feature-settings: "tnum"; font-size: 16px; }
    input[type='text'] { width: 120px; }
    input[type='submit'] { color: white; background-color: #484; border: 0; border-radius: 5px; cursor: pointer; }
    input[type='submit'].ehh { background-color: #6a6; } /* not formatted yet */
    form { background-color: #eee; padding: 15px; margin-bottom: 20px; }
    form label { display: inline-block; width: 80px; }
    form ul { list-style-type: none; padding-left: 0; margin: 0; }
    form li.break { margin-top: 1.5em; }
    table { border-collapse: collapse; }
    td, th { padding: 0.1em 0.5em 0.1em 0; text-align: left; vertical-align: top; }
    th { font-weight: bold; }
    table.latestobservation th { font-weight: normal; }
    table.latestobservation td { font-weight: bold; }
    img.icon { width: 1.2em; height: 1.2em; vertical-align: middle; position: relative; top: -1px; }
    .divider { font-weight: normal; color: #aaa; margin: 0 0.1em; }
    .highlight { background-color: #fe5; }
    @media only screen and (min-width : 601px) { /* two columns on desktop only */
      body { margin: 50px; }
      form#controls { float: left; max-width: 250px; }
      div#results { margin-left: 270px; }
    }
    /*@media only screen and (max-width : 500px) {
      form#controls {  }
      div#results {  }
    }*/
  </style>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <script type='text/javascript'>
    $(function(){
      //Clear fields when a "matching" field is edited
      $('#coords').on('change input',function(){ $('#wfo, #gridxy, #station').val(''); });
      $('#wfo, #gridxy').on('change input',function(){ $('#coords, #station').val(''); });
      $('#station').on('change input',function(){ $('#coords, #wfo, #gridxy').val(''); });
      $('a#clearform').click(function(e){
        e.preventDefault(); $('#coords, #wfo, #gridxy, #station').val('');
      });
    });
  </script>
</head>
<body>
  <h3><a href="<?php echo parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH); ?>">National Weather Service API Tool</a></h3>
  <p>
  <form id='controls' method='GET'>
    <ul>
      <li><label for='coords'>Lat,Long:</label> <input type='text' id='coords' name='coords' value='<?php echo $_REQUEST['coords']; ?>' /></li>
      <li>&mdash; or &mdash;</li>
      <li><label for='wfo'><a href="https://en.wikipedia.org/wiki/List_of_National_Weather_Service_Weather_Forecast_Offices" target="_new">WFO</a>:</label> <input type='text' id='wfo' name='wfo' value='<?php echo $_REQUEST['wfo']; ?>' /></li>
      <li><label for='gridxy'>Grid X,Y:</label> <input type='text' id='gridxy' name='gridxy' value='<?php echo $_REQUEST['gridxy']; ?>' /></li>
      <li><input type='submit' name='reqtype' value='Daily forecast' /></li>
      <li><input type='submit' name='reqtype' value='Hourly forecast' /></li>
      <li><input type='submit' name='reqtype' value='Raw grid data' class='ehh' /></li>
      <li><input type='submit' name='reqtype' value='Stations' class='ehh' /></li>
      <li class='break'><label for='station'>Station:</label> <input type='text' id='station' name='station' value='<?php echo $_REQUEST['station']; ?>' /></li>
      <li><input type='submit' name='reqtype' value='Latest observation' /></li>
      <li><input type='submit' name='reqtype' value='Recent observations' class='ehh' /></li>
      <li class='break'><a href='<?php echo parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH); ?>'>Reset</a> <a href='#' id='clearform'>Clear</a> <a href='https://www.weather.gov/documentation/services-web-api' target='_new'>API Docs</a></li>
    </ul>
  </form>
  <div id='results'><?php
    if(sizeof($GLOBALS['errors'])) { echo "<ul class='errors'>"; foreach($GLOBALS['errors'] as $e) echo "<li>".$e."</li>"; echo "</ul>"; }
    echo $result;
  ?></div>
</body>
</html>