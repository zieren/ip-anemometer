<html>
<head>
<title>IP Anemometer</title>
<link rel="stylesheet" type="text/css" href="ipa.css" />
<script type="text/javascript" src="https://www.google.com/jsapi"></script>
<script type="text/javascript" src="spin.min.js"></script>
<script type="text/javascript" src="ipa.js"></script>
<link rel="stylesheet" href="https://unpkg.com/flatpickr/dist/flatpickr.min.css">
<script src="https://unpkg.com/flatpickr"></script>
</head>

<body>

<script type="text/javascript">
var ipaView = {};
ipaView.periodInputs = {};
</script>

<?php
function periodSelector($keys, $default) {
  if (!is_array($keys)) {
    $keys = array($keys);
  }
  $id = 'idIpaVw-'.implode('_', $keys);
  foreach ($keys as $key) {
    echo '<script type="text/javascript">ipaView.periodInputs.'.$key.' = "'.$id.'-input";</script>'
      ."\n";
  }
  echo
'<div id="'.$id.'-div" class="ipaVwElementWidth ipaWpElementCenter ipaSpinnerContainer">
  Period: <input id="'.$id.'-input" type="text" maxlength="8" size="6"
      onkeypress="ipaView.handleKeyPress(event, \''.$id.'-div\')"
      value="'.$default.'" />
  <button onclick="ipaView.requestStats(\''.$id.'-div\')">Show</button>
</div>';
}
?>

<div id="idIpaVwStatus" class="ipaVwElementWidth"></div>

<div id="idIpaVwDivDate" class="ipaVwElementWidth ipaVwElementCenter ipaSpinnerContainer">
  Date/time: <input id="idIpaVwDateSelector" size="16" placeholder="up to date/time" />
  <button onclick="ipaView.setNow('idIpaVwDivDate')">Now</button>
</div>

<?php periodSelector('wind', '1h30'); ?>
<div id="idIpaVwWindSummary" class="ipaVwContainer ipaVwElementWidth"></div>
<div id="idIpaVwWindTimeSeries" class="ipaVwElementWidth ipaVwExpand"></div>
<div id="idIpaVwWindHist" class="ipaVwElementWidth ipaVwExpand"></div>

<h3>Pilot count</h3>
<?php periodSelector('pilots', '1d'); ?>
<div id="idIpaVwPilots" class="ipaVwElementWidthHeight ipaVwExpand"></div>

<h3>Weather</h3>
<?php periodSelector('temp_hum', '1d'); ?>
<div id="idIpaVwTempHum" class="ipaVwElementWidthHeight ipaVwExpand"></div>

<h3>Shed door</h3>
<?php periodSelector('door', '7d'); ?>
<div id="idIpaVwDoor" class="ipaVwElementWidthHeight ipaVwExpand"></div>

<h3>A/D converter channel 7</h3>
<?php periodSelector('adc', '1d'); ?>
<div id="idIpaVwAdc" data-channel="7" data-label="Volt" class="ipaVwElementWidthHeight ipaVwExpand">
</div>

<h3>System status</h3>
<?php periodSelector(array('lag', 'cpu_temp', 'signal', 'traffic', 'network'), '1d'); ?>
<div id="idIpaVwLag" class="ipaVwElementWidth ipaVwExpand"></div>
<div id="idIpaVwTemp" class="ipaVwElementWidth ipaVwExpand"></div>
<div id="idIpaVwSignalStrength" class="ipaVwElementWidth ipaVwExpand"></div>
<h4>Traffic</h4>
<div id="idIpaVwTraffic" class="ipaVwElementWidth ipaVwExpand"></div>
<div id="idIpaVwNwType" class="ipaVwElementWidth ipaVwExpand"></div>

<div id="idIpaVwDivExpand" class="ipaVwElementWidth ipaVwElementCenter ipaSpinnerContainer">
  <button onclick="ipaView.expand('idIpaVwDivExpand')">Max Width</button>
</div>

<script type="text/javascript">

//XXX magic period "today", "thisweek" or whatever to pick start of day?
//or postfix "+"/"-" to increase/decrease until next full day/week? maybe "+d"?

var ipaNow = new Date();
ipaView.endPickr = flatpickr("#idIpaVwDateSelector", {
  enableTime: true,
  time_24hr: true,
  defaultDate: ipaNow,
  maxDate: ipaNow,
  allowInput: true,
  locale: {
    firstDayOfWeek: 1
  },
  onClose: function(selectedDates, dateStr, instance) {
    ipaView.requestStats('idIpaVwDivDate');
  }
});

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

ipaView.options = {};

ipaView.getStartTimestamp = function(timestampNow, inputElementId) {
  var e = document.getElementById(inputElementId);
  return timestampNow - ipa.Tools.periodStringToMillies(e.value);
}

ipaView.requestStats = function(spinnerId) {
  var spinnerContainer = document.getElementById(spinnerId);
  var spinner = new Spinner({ scale: 5 }).spin(spinnerContainer);

  ipaView.options.endTimestamp = ipaView.endPickr.selectedDates[0].getTime();
  for (var i in ipaView.periodInputs) {
    ipaView.options[i] = {}
    ipaView.options[i].startTimestamp =
        ipaView.getStartTimestamp(ipaView.options.endTimestamp, ipaView.periodInputs[i]);
  }

  ipaView.chart = new ipa.Chart(ipaView.options);
  ipaView.chart.requestStats(function() { ipaView.updateChart(spinner); });
}

ipaView.updateChart = function(spinner) {
  spinner.stop();
  var chart = ipaView.chart;
  chart.drawStatus(ipaView.clearElement(document.getElementById('idIpaVwStatus')));
  chart.drawWindSummary(ipaView.clearElement(document.getElementById('idIpaVwWindSummary')));
  chart.drawWindTimeSeries(ipaView.clearElement(document.getElementById('idIpaVwWindTimeSeries')));
  chart.drawWindHistogram(ipaView.clearElement(document.getElementById('idIpaVwWindHist')));
  chart.drawTempHum(ipaView.clearElement(document.getElementById('idIpaVwTempHum')));
  chart.drawDoor(ipaView.clearElement(document.getElementById('idIpaVwDoor')));
  chart.drawAdcChannel(ipaView.clearElement(document.getElementById('idIpaVwAdc')));
  chart.drawPilots(ipaView.clearElement(document.getElementById('idIpaVwPilots')));
  chart.drawLag(ipaView.clearElement(document.getElementById('idIpaVwLag')));
  chart.drawCpuTemp(ipaView.clearElement(document.getElementById('idIpaVwTemp')));
  chart.drawSignalStrength(ipaView.clearElement(document.getElementById('idIpaVwSignalStrength')));
  chart.drawNetworkType(ipaView.clearElement(document.getElementById('idIpaVwNwType')));
  chart.drawTraffic(ipaView.clearElement(document.getElementById('idIpaVwTraffic')));
 }

ipaView.handleKeyPress = function(event, spinnerId) {
  if (event.keyCode == 13) {
    ipaView.requestStats(spinnerId);
  }
}

ipaView.expand = function(spinnerId) {
  ipaView.options.timeSeriesPoints = 123456;  // effectively disable downsampling
  var charts = document.getElementsByClassName('ipaVwExpand');
  for (var i = 0; i < charts.length; ++i) {
    charts[i].style.width = '100%';
  }
  ipaView.requestStats(spinnerId);
}

google.setOnLoadCallback(function() { ipaView.requestStats('idIpaVwDivDate'); });
</script>

</body>
</html>
