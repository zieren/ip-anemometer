<?php
/*
Plugin Name: IP-Anemometer Charts
Description: Use [ipa] to show cool charts.
Author: J&ouml;rg Zieren
Author URI: http://zieren.de/
Version: 0.1
*/

add_shortcode( 'ipa', 'ipa' );

function get(&$value, $default=null) {
  return isset($value) ? $value : $default;
}

function ipa($atts) {
  $handlers = array(
    'url' => javascript,
  	'period' => periodSelector,
    'summary' => summary,
    'speed' => speed,
    'histogram' => histogram,
    'door' => door,
    'lag' => lag,
    'temperature' => temperature,
    'signal' => signalStrength,
    'network' => networkType,
    'transfer' => transferVolume
  );
  $code = '';
  foreach ($atts as $k => $v) {
    if (get($handlers[$k])) {
      $code .= $handlers[$k]($atts);
    } elseif (is_int($k) && get($handlers[$v])) {
      $code .= $handlers[$v]($atts);
    } else {
      $code .= '<p><b>Invalid attribute: '.$k.'='.$v.'</b></p>';
    }
  }
  return $code;
}

function javascript($atts) {
  $jsUrl = plugin_dir_url(__FILE__).'/wp-ipa.js';
  // XXX add CSS
  $ipaUrl = $atts['url'];
  return <<<THEEND
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript" src="$jsUrl"></script>
<script>

var ipaView = {};

ipaView.clearElement = function(element) {
  while (element.firstChild) {
    element.removeChild(element.firstChild);
  }
  return element;
}

ipaView.handleKeyPress = function(event) {
  if (event.keyCode == 13) {
    ipaView.requestStats();
  }
}

ipaView.options = {};

ipaView.requestStats = function() {
  var periodInput = document.getElementById('periodInput');
  if (periodInput) {
    ipaView.options.minutes = periodInput.value;
  }
  ipaView.options.url = '$ipaUrl';
  ipaView.chart = new ipa.Chart(ipaView.options);
  ipaView.chart.requestStats(ipaView.updateChart);
}

ipaView.draw = function(that, draw, id) {
  var element = document.getElementById(id);
  if (element) {
    draw.call(that, ipaView.clearElement(element));
  }
}

ipaView.updateChart = function(opt_request) {
  var c = ipaView.chart;
  ipaView.draw(c, c.drawSummary, 'ipaSummary');
  ipaView.draw(c, c.drawTimeSeries, 'ipaSpeed');
  ipaView.draw(c, c.drawHistogram, 'ipaHistogram');
  ipaView.draw(c, c.drawDoor, 'ipaDoor');
  ipaView.draw(c, c.drawLag, 'ipaLag');
  ipaView.draw(c, c.drawTemperature, 'ipaTemperature');
  ipaView.draw(c, c.drawSignalStrength, 'ipaSignalStrength');
  ipaView.draw(c, c.drawNetworkType, 'ipaNetworkType');
  ipaView.draw(c, c.drawTransferVolume, 'ipaTransferVolume');
}

google.setOnLoadCallback(ipaView.requestStats);

</script>
THEEND;
}

function periodSelector($atts) {
  return
    '<div id="periodSelector" class="ipaInput">
      Minutes: <input id="periodInput" type="text" maxlength="4" size="4"
          onkeypress="ipaView.handleKeyPress(event)" value="'.$atts['period'].'" />
      <button onclick="ipaView.requestStats()">Load</button>
    </div>';
}

function summary($atts) {
  return '<div id="ipaSummary" class="ipaText"></div>';
}

function speed($atts) {
  return '<div id="ipaSpeed" class="ipaChart"></div>';
}

function histogram($atts) {
  return '<div id="ipaHistogram" class="ipaChart"></div>';
}

function door($atts) {
  return '<div id="ipaDoor" class="ipaTimeline"></div>';
}

function lag($atts) {
  return '<div id="ipaLag" class="ipaChart"></div>';
}

function temperature($atts) {
  return '<div id="ipaTemperature" class="ipaChart"></div>';
}

function signalStrength($atts) {
  return '<div id="ipaSignalStrength" class="ipaChart"></div>';
}

function networkType($atts) {
  return '<div id="ipaNetworkType" class="ipaChart"></div>';
}

function transferVolume($atts) {
  return '<div id="ipaTransferVolume" class="ipaChart"></div>';
}
