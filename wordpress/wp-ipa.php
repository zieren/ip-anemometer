<?php
/**
 * Plugin Name: IP Anemometer
 * Plugin URI: http://zieren.de
 * Description: Embed wind speed charts in WordPress content. For use with the free <a href="http://zieren.de/">IP anemometer for the Raspberry Pi</a>.
 * Version: 0.3.0
 * Author: J&ouml;rg Zieren
 * Author URI: http://zieren.de
 * License: GPL v3
 */

add_shortcode('ipa', 'ipa');
wp_register_style('ipaCss', plugin_dir_url(__FILE__).'/ipa.css');
wp_enqueue_style('ipaCss');

function get(&$value, $default=null) {
  return isset($value) ? $value : $default;
}

/**
 * WordPress 4.4 (and possibly later) sometimes doesn't trim quotes, while earlier versions do. Be
 * robust and accept both.
 */
function trimQuotesAndSpace($s) {
  return trim($s, " '\"");
}

function quoteWithTrim($s) {
  return "'".trimQuotesAndSpace($s)."'";
}

function intvalWithTrim($s) {
  return intval(trimQuotesAndSpace($s));
}

function ipa($atts) {
  $options = array(
    'url' => array('url', quoteWithTrim),
    'period' => array('minutes', intvalWithTrim),
    'spinner' => array('showSpinner', intvalWithTrim)
  );
  $handlers = array(
    'status' => status,
    'period_selector' => periodSelector,
    'summary' => summary,
    'speed' => speed,
    'histogram' => histogram,
    'pilots' => pilots,
    'adc' => adc,
    'temp_hum' => tempHum,
    'door' => door,
    'lag' => lag,
    'temperature' => temperature,
    'signal' => signalStrength,
    'network' => networkType,
    'transfer' => transferVolume
  );
  $arguments = array(
    'hideok',   // status
    'channel',  // adc
    'label'     // adc
  );
  $code = '';
  $optionsJS = '';
  foreach ($atts as $k => $v) {
    if (get($options[$k])) {
      if ($optionsJS) {
        $optionsJS .= ',';
      }
      $optionsJS .= $options[$k][0].':'.$options[$k][1]($v);
    } elseif (get($handlers[$k])) {
      $code .= $handlers[$k]($atts);
    } elseif (is_int($k) && get($handlers[$v])) {
      $code .= $handlers[$v]($atts);
    } elseif (in_array($k, $arguments)) {
      // Ignore here, will be retrieved from $atts in handler.
    } else {
      $code .= '<p><b>Invalid attribute: '.$k.'='.$v.'</b></p>';
    }
  }
  if (!$GLOBALS['ipaViewJS']) {
    $code .= ipaViewJS($optionsJS);
    $GLOBALS['ipaViewJS'] = true;
  }
  return $code;
}

// TODO: Unify and extract code shared with index.html.
function ipaViewJS($optionsJS) {
  $jsUrl = plugin_dir_url(__FILE__).'/ipa.js';
  return <<<THEEND
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript" src="http://fgnass.github.io/spin.js/spin.min.js"></script>
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

ipaView.options = { $optionsJS };

ipaView.requestStats = function() {
  var periodInput = document.getElementById('idIpaWpPeriodInput');
  if (periodInput) {
    ipaView.options.minutes = ipa.Tools.durationStringToMinutes(periodInput.value);
  }
  ipaView.options.timeSeriesPoints = 100;
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
  ipaView.draw(c, c.drawStatus, 'idIpaWpStatus');
  ipaView.draw(c, c.drawWindSummary, 'idIpaWpSummary');
  ipaView.draw(c, c.drawTimeSeries, 'idIpaWpSpeed');
  ipaView.draw(c, c.drawHistogram, 'idIpaWpHistogram');
  ipaView.draw(c, c.drawTempHum, 'idIpaWpTempHum');
  ipaView.draw(c, c.drawAdcChannel, 'idIpaWpAdc');
  ipaView.draw(c, c.drawDoor, 'idIpaWpDoor');
  ipaView.draw(c, c.drawPilots, 'idIpaWpPilots');
  ipaView.draw(c, c.drawLag, 'idIpaWpLag');
  ipaView.draw(c, c.drawTemperature, 'idIpaWpTemperature');
  ipaView.draw(c, c.drawSignalStrength, 'idIpaWpSignalStrength');
  ipaView.draw(c, c.drawNetworkType, 'idIpaWpNetworkType');
  ipaView.draw(c, c.drawTransferVolume, 'idIpaWpTransferVolume');
}

google.setOnLoadCallback(ipaView.requestStats);

</script>
THEEND;
}

function status($atts) {
  return '<div id="idIpaWpStatus" data-hideok="'.$atts['hideok'].'"></div>';
}

function periodSelector($atts) {
  return
    '<div id="idIpaWpPeriodSelector" class="ipaInput">
      Minutes: <input id="idIpaWpPeriodInput" type="text" maxlength="4" size="4"
          onkeypress="ipaView.handleKeyPress(event)" value="'.$atts['period_selector'].'" />
      <button onclick="ipaView.requestStats()">Load</button>
      <div id="idIpaSpinnerContainer"></div>
    </div>';
}

function summary($atts) {
  return '<div id="idIpaWpSummary"></div>';
}

function speed($atts) {
  return '<div id="idIpaWpSpeed"></div>';
}

function histogram($atts) {
  return '<div id="idIpaWpHistogram"></div>';
}

function pilots($atts) {
  return '<div id="idIpaWpPilots"></div>';
}

function tempHum($atts) {
  return '<div id="idIpaWpTempHum"></div>';
}

function adc($atts) {
  return '<div id="idIpaWpAdc" data-channel="'.$atts['channel']
      .'" data-label="'.$atts['label'].'"></div>';
}

function door($atts) {
  return '<div id="idIpaWpDoor"></div>';
}

function lag($atts) {
  return '<div id="idIpaWpLag"></div>';
}

function temperature($atts) {
  return '<div id="idIpaWpTemperature"></div>';
}

function signalStrength($atts) {
  return '<div id="idIpaWpSignalStrength"></div>';
}

function networkType($atts) {
  return '<div id="idIpaWpNetworkType"></div>';
}

function transferVolume($atts) {
  return '<div id="idIpaWpTransferVolume"></div>';
}
