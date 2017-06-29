<?php
/**
 * Plugin Name: IP Anemometer
 * Plugin URI: http://zieren.de
 * Description: Embed wind speed charts in WordPress content. For use with the free <a href="http://zieren.de/">IP anemometer for the Raspberry Pi</a>.
 * Version: 0.3.2
 * Author: J&ouml;rg Zieren
 * Author URI: http://zieren.de
 * License: GPL v3
 */

add_shortcode('ipa', 'ipa');
wp_register_style('ipa', plugin_dir_url(__FILE__).'/ipa.css');
wp_enqueue_style('ipa');
wp_register_style('ipaFlatpickr', 'https://unpkg.com/flatpickr/dist/flatpickr.min.css', array(), null);
wp_enqueue_style('ipaFlatpickr');

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
    'url' => array('ipaView.options.url', quoteWithTrim),
    'spinner' => array('ipaView.optionsLocal.spinner', intvalWithTrim),
    'samples' => array('ipaView.options.timeSeriesPoints', intvalWithTrim)
  );
  $handlers = array(
    'date_selector' => dateSelector,
    'duration_selector' => durationSelector,
    'status' => status,
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
    // For all charts:
    'duration_id',  // duration_selector id
    'duration',     // fixed duration
    // For duration_selector:
    'default',  // initial value
    'id',       // ID for referencing in duration_id above
    // For status chart:
    'hideok',
    // For ADC chart:
    'channel',
    'label'
  );
  $code = '';
  $optionsJS = '';
  foreach ($atts as $k => $v) {
    if (get($options[$k])) {
      $optionsJS .= $options[$k][0].' = '.$options[$k][1]($v).';'."\n";
    } elseif (get($handlers[$k])) {
      $code .= $handlers[$k]($atts);
    } elseif (is_int($k) && get($handlers[$v])) {
      $code .= $handlers[$v]($atts);
    } elseif (in_array($k, $arguments)) {
      // Ignore here, will be retrieved from $atts in handler.
    } else {
      echo '<p><b>Invalid attribute: '.$k.'='.$v.'</b></p>';
    }
  }
  if (!$GLOBALS['ipaViewCommonJsInjected']) {
    $code = ipaViewCommonJs($optionsJS).$code;
    $GLOBALS['ipaViewCommonJsInjected'] = true;
  }
  return $code;
}

// TODO: Unify and extract code shared with index.html.
function ipaViewCommonJs($optionsJS) {
  $jsUrl = plugin_dir_url(__FILE__).'/ipa.js';
  return <<<THEEND
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript" src="http://fgnass.github.io/spin.js/spin.min.js"></script>
<script type="text/javascript" src="$jsUrl"></script>
<script>

var ipaView = {};

ipaView.options = {};
ipaView.optionsLocal = {};
// Defaults, may be overwritten:
ipaView.options.timeSeriesPoints = 100;
ipaView.optionsLocal.spinner = 1;
$optionsJS

ipaView.durations = {}

ipaView.setNow = function(spinnerId) {
  var now = new Date();
  ipaView.endPickr.set('maxDate', now);
  ipaView.endPickr.setDate(now);
  ipaView.requestStats(spinnerId);
}

ipaView.clearElement = function(element) {
  while (element.firstChild) {
    element.removeChild(element.firstChild);
  }
  return element;
}

ipaView.handleKeyPress = function(event, spinnerId) {
  if (event.keyCode == 13) {
    ipaView.requestStats(spinnerId);
  }
}

ipaView.getStartTimestamp = function(timestampNow, duration) {
  if ('id' in duration) {
    var e = document.getElementById(duration['id']);
    if (e) {
      return timestampNow - ipa.Tools.durationStringToMillies(e.value);
    }
  } else if ('fixed' in duration) {
    return timestampNow - ipa.Tools.durationStringToMillies(duration['fixed']);
  }
  return null;
}

ipaView.requestStats = function(spinnerId) {
  var spinnerContainer = ipaView.optionsLocal.spinner ? document.getElementById(spinnerId) : null;
  var spinner = spinnerContainer ? new Spinner({ scale: 5 }).spin(spinnerContainer) : null;

  ipaView.options.endTimestamp = 'endPickr' in ipaView
      ? ipaView.endPickr.selectedDates[0].getTime()
      : new Date().getTime();
  for (var i in ipaView.durations) {
    var startTimestamp =
        ipaView.getStartTimestamp(ipaView.options.endTimestamp, ipaView.durations[i]);
    if (startTimestamp) {
      ipaView.options[i] = {}
      ipaView.options[i].startTimestamp = startTimestamp;
    }
  }

  ipaView.chart = new ipa.Chart(ipaView.options);
  ipaView.chart.requestStats(function() { ipaView.updateChart(spinner); });
}

ipaView.draw = function(that, draw, id) {
  var element = document.getElementById(id);
  if (element) {
    draw.call(that, ipaView.clearElement(element));
  }
}

ipaView.updateChart = function(spinner) {
  if (spinner) {
    spinner.stop();
  }
  var c = ipaView.chart;
  ipaView.draw(c, c.drawStatus, 'idIpaWpStatus');
  ipaView.draw(c, c.drawWindSummary, 'idIpaWpSummary');
  ipaView.draw(c, c.drawWindTimeSeries, 'idIpaWpSpeed');
  ipaView.draw(c, c.drawWindHistogram, 'idIpaWpHistogram');
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

google.setOnLoadCallback(function() {
  var spinnerContainers = document.getElementsByClassName('ipaSpinnerContainer');
  ipaView.requestStats(spinnerContainers.length > 0 ? spinnerContainers[0].id : null);
});

</script>
THEEND;
}

function dateSelector($atts) {
  return
'<div id="idIpaWpDivDate" class="ipaVwElementWidth ipaSpinnerContainer">
Date/time: <input id="idIpaWpDateSelector" size="16" placeholder="up to date/time" />
<button onclick="ipaView.setNow(\'idIpaWpDivDate\')">Now</button></div>
<script src="https://unpkg.com/flatpickr"></script>
<script type="text/javascript">
var ipaNow = new Date();
ipaView.endPickr = flatpickr("#idIpaWpDateSelector", {
  enableTime: true,
  time_24hr: true,
  defaultDate: ipaNow,
  maxDate: ipaNow,
  allowInput: true,
  locale: {
    firstDayOfWeek: 1
  },
  onClose: function(selectedDates, dateStr, instance) {
    ipaView.requestStats("idIpaWpDivDate");
  }
});
</script>';
}

// XXX for="wind" -> "summary", "speed", "histogram" need renaming. "speed" is also too generic.
function durationSelector($atts) {
  if (!isset($atts['id'])) {
    return '<p><b>duration_selector requires id="[unique id]"</b></p>';
  }
  if (!isset($atts['default'])) {
    return '<p><b>duration_selector requires default="[default duration]"</b></p>';
  }
  $id = 'idIpa-'.$atts['id'];
  return
'<div id="'.$id.'-div" class="ipaWpElementCenter ipaSpinnerContainer">
  Period: <input id="'.$id.'-input" type="text" maxlength="8" size="6"
      onkeypress="ipaView.handleKeyPress(event, \''.$id.'-div\')"
      value="'.$atts['default'].'" />
  <button onclick="ipaView.requestStats(\''.$id.'-div\')">Show</button>
</div>';
}

function setDurationSource($name, $atts) {
  if (array_key_exists('duration_id', $atts)) {
    return '<script type="text/javascript">ipaView.durations.'.$name
        .' = { id: "idIpa-'.$atts['duration_id'].'-input" };</script>';
  }
  if (!array_key_exists('duration', $atts)) {
    echo '<p><b>Either duration_id or duration must be specified for '.$name.'</b></p>';
    // XXX Use under_score arguments in request, so names match.
    return '';
  }
  return '<script type="text/javascript">ipaView.durations.'.$name
      .' = { fixed: "'.$atts['duration'].'" };</script>';
}

function status($atts) {
  return '<div id="idIpaWpStatus" data-hideok="'.$atts['hideok'].'"></div>';
}

function summary($atts) {
  // XXX should we support separate durations for summary, speed, histogram? I think yes...
  return setDurationSource('wind', $atts).'<div id="idIpaWpSummary"></div>';
}

function speed($atts) {
  return setDurationSource('wind', $atts).'<div id="idIpaWpSpeed"></div>';
}

function histogram($atts) {
  return setDurationSource('wind', $atts).'<div id="idIpaWpHistogram"></div>';
}

function tempHum($atts) {
  return setDurationSource('tempHum', $atts).'<div id="idIpaWpTempHum"></div>';
}

function pilots($atts) {
  return '<div id="idIpaWpPilots"></div>';
}

function adc($atts) {
  return setDurationSource('adc', $atts)
      .'<div id="idIpaWpAdc" data-channel="'.$atts['channel']
      .'" data-label="'.$atts['label'].'"></div>';
}

function door($atts) {
  return '<div id="idIpaWpDoor"></div>';
}

function lag($atts) {
  return setDurationSource('lag', $atts).'<div id="idIpaWpLag"></div>';
}

function temperature($atts) {  // XXX this naming sucks
  return setDurationSource('cpuTemp', $atts).'<div id="idIpaWpTemperature"></div>';
}

function signalStrength($atts) {
  return setDurationSource('signal', $atts).'<div id="idIpaWpSignalStrength"></div>';
}

function networkType($atts) {
  return setDurationSource('network', $atts).'<div id="idIpaWpNetworkType"></div>';
}

function transferVolume($atts) {
  return setDurationSource('traffic', $atts).'<div id="idIpaWpTransferVolume"></div>';  // XXX fix name?
}
