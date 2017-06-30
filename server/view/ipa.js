google.load('visualization', '1.0', {'packages': ['corechart', 'timeline']});

var ipa = {};

// Options and their defaults.
ipa.Options = function() {
  // --- Global options ---

  // Default in same directory, but can be an absolute URL.
  this.url = 'ipa.php';
  // Output inconsistent dummy data for testing.
  this.dummy = false;
  // Show data up to this point in time. -1 means now.
  this.endTimestamp = -1;
  // Maximum acceptable latency. A warning is shown when this is exceeded. It depends on your
  // effective upload frequency and may be higher for flaky uplinks.
  this.maxLatencyMinutes = 15;
  // Downsample time series to make charts readable. Increase for wider charts.
  this.timeSeriesPoints = 30;
  // Textual output precision. Currently only used for wind.
  this.fractionalDigits = 1;

  // --- Options for specific data ---

  var timestampNow = new Date().getTime();

  // If startTimestamp for a key is absent, no data is returned.
  this.wind = {
    // Show timestamp of maximum wind speed.
    showTimeOfMax: false /* ,
    startTimestamp: timestampNow - ipa.Tools.minutesToMillis(60) */
  }
  // this.temp_hum = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(60) };
  // this.adc = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(24 * 60) };
  // this.door = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(24 * 60) };
  // this.pilots = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(24 * 60) };
  // this.cpu_temp = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(24 * 60) };
  // this.signal = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(24 * 60) };
  // this.network = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(24 * 60) };
  // this.traffic = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(7 * 24 * 60) };
  // this.lag = { startTimestamp: timestampNow - ipa.Tools.minutesToMillis(24 * 60) };
}

ipa.Tools = {}

ipa.Tools.sorted = function(obj) {
  var array = [];
  for (var key in obj) {
    array.push([key, obj[key]]);
  }
  var cmp = function(a, b) {
    return a[0] - b[0];
  }
  array.sort(cmp);
  return array;
}

ipa.Tools.alphasorted = function(obj) {
  var array = [];
  for (var key in obj) {
    array.push([key, obj[key]]);
  }
  array.sort();
  return array;
}

ipa.Tools.millisToMinutes = function(millis) {
  return millis / (1000 * 60);
}

ipa.Tools.minutesToMillis = function(minutes) {
  return minutes * 1000 * 60;
}

ipa.Tools.millisToDays = function(millis) {
  return millis / (1000 * 60 * 60 * 24);
}

ipa.Tools.bytesToMiB = function(bytes) {
  return (bytes / (1024 * 1024)).toFixed(1);
}

ipa.Tools.PERIOD_REGEX = /([0-9]+)([wdhm]?) */g
ipa.Tools.UNIT_TO_MINUTES = {
    'w': 7 * 24 * 60,
    'd': 24 * 60,
    'h': 60,
    'm': 1
}
/**
 * Parses a human readable period string, e.g. "2h5" for 2 hours and 5 minutes. Supports w, d, h
 * and m (default).
 */
ipa.Tools.periodStringToMillies = function(s) {
  s = s.trim();
  var minutes = 0;
  var part;
  while ((part = ipa.Tools.PERIOD_REGEX.exec(s)) !== null) {
    var unit = part[2];
    if (unit in ipa.Tools.UNIT_TO_MINUTES) {
      unit = ipa.Tools.UNIT_TO_MINUTES[unit];
    } else {
      unit = 1;
    }
    minutes += parseInt(part[1]) * unit;
  }
  return ipa.Tools.minutesToMillis(minutes);
}

ipa.Tools.isSameDate = function(dateA, dateB) {
  return dateA.getFullYear() == dateB.getFullYear()
      && dateA.getMonth() == dateB.getMonth()
      && dateA.getDate() == dateB.getDate();
}

/**
 * Returns time if date is today, otherwise returns date and time.
 */
ipa.Tools.compactDateString = function(date) {
  if (ipa.Tools.isSameDate(date, new Date())) {
    return date.toLocaleTimeString();
  }
  return date.toLocaleString();
}


ipa.Chart = function(options) {
  this.options = new ipa.Options();  // Set all defaults.
  ipa.Chart.copyProperties_(options, this.options);  // Overwrite with specified values.
}

ipa.Chart.prototype.requestArgument = function(dataKey) {
  if (dataKey in this.options && 'startTimestamp' in this.options[dataKey]) {
    return '&' + dataKey + '=' + this.options[dataKey].startTimestamp;
  }
  return '';
}

/**
 * Request stats from the server.
 *
 * @param {function} opt_callback
 *     If specified, the request is asynchronous and this callback is run on completion (with the
 *     XMLHttpRequest object as its argument). Otherwise the request is synchronous.
 */
ipa.Chart.prototype.requestStats = function(opt_callback) {
  var isAsync = typeof(opt_callback) !== 'undefined';
  var request = new XMLHttpRequest();
  var url = this.options.url + '?samples=' + this.options.timeSeriesPoints;
  url += this.requestArgument('wind');
  url += this.requestArgument('temp_hum');
  url += this.requestArgument('adc');
  url += this.requestArgument('door');
  url += this.requestArgument('pilots');
  url += this.requestArgument('cpu_temp');
  url += this.requestArgument('signal');
  url += this.requestArgument('network');
  url += this.requestArgument('traffic');
  url += this.requestArgument('lag');
  if (this.options.dummy) {
    url += '&dummy=1';
  }
  if (this.options.endTimestamp >= 0) {
    url += '&upTo=' + this.options.endTimestamp;
  }
  request.open('GET', url, isAsync);
  if (isAsync) {
    var chart = this;
    request.onreadystatechange = function() {
      if (request.readyState == 4 && request.status == 200) {
        chart.stats = JSON.parse(request.responseText);
        // TODO: Should we run the callback for all states instead of just success?
        opt_callback(request);
      }
    }
  }
  request.send();
  // TODO: Handle request error (500).
  if (!isAsync) {
    this.stats = JSON.parse(request.responseText);
  }
}

ipa.Chart.prototype.drawWindSummary = function(element) {
  if (ipa.Chart.showNoData_(this.stats.wind, element, 'no wind data available')) {
    return;
  }
  var table = document.createElement('table');
  table.className = 'ipaSummary';
  ipa.Chart.insertCells_(table.insertRow())('avg',
      this.stats.wind.avg.toFixed(this.options.fractionalDigits) + ' km/h');
  table.firstChild.lastChild.children[0].className = 'ipaAvgLabel';
  table.firstChild.lastChild.children[1].className = 'ipaAvgValue';
  ipa.Chart.insertCells_(table.insertRow())('max',
      this.stats.wind.max.toFixed(this.options.fractionalDigits) + ' km/h');
  table.firstChild.lastChild.children[0].className = 'ipaMaxLabel';
  table.firstChild.lastChild.children[1].className = 'ipaMaxValue';
  if (this.options.showTimeOfMaxWindSpeed) {
    ipa.Chart.insertCells_(table.insertRow())('max@',
        ipa.Tools.compactDateString(new Date(parseInt(this.stats.wind.max_ts))));
    table.firstChild.lastChild.children[0].className = 'ipaMaxtsLabel';
    table.firstChild.lastChild.children[1].className = 'ipaMaxtsValue';
  }
  ipa.Chart.insertCells_(table.insertRow())('from',
      ipa.Tools.compactDateString(new Date(parseInt(this.stats.wind.start_ts))));
  table.firstChild.lastChild.children[0].className = 'ipaFromLabel';
  table.firstChild.lastChild.children[1].className = 'ipaFromValue';
  ipa.Chart.insertCells_(table.insertRow())('to',
      ipa.Tools.compactDateString(new Date(parseInt(this.stats.wind.end_ts))));
  table.firstChild.lastChild.children[0].className = 'ipaToLabel';
  table.firstChild.lastChild.children[1].className = 'ipaToValue';
  element.appendChild(table);

  this.indicateStaleData_(this.stats.wind.end_ts, this.options.maxWindLatencyMinutes, element);
}

ipa.Chart.prototype.drawWindTimeSeries = function(element) {
  if (ipa.Chart.showNoData_(this.stats.wind, element, 'no wind data available')) {
    return;
  }
  var timeSeriesTable = new google.visualization.DataTable();
  timeSeriesTable.addColumn('datetime', 't');
  timeSeriesTable.addColumn('number', 'avg');
  timeSeriesTable.addColumn('number', 'max');
  var timeSeries = this.stats.wind.time_series;
  for (var i = 0; i < timeSeries.length; ++i) {
    var row = timeSeries[i];
    row[0] = new Date(parseInt(row[0]));  // convert timestamp to Date object
    timeSeriesTable.addRow(row);
  }
  var options = {
    title: 'Wind [km/h]',
    hAxis: {format: 'HH:mm'},
    // If all values are 0 the chart shows a value range of [-1, 1]. So we specify a range of [0, 1]
    // to render a pretty chart in that case. If values exceed the max of 1 it will be ignored.
    vAxis: {minValue: 0, maxValue: 1},
    legend: {position: 'top'}
  };
  var timeSeriesChart = new google.visualization.LineChart(element);
  timeSeriesChart.draw(timeSeriesTable, options);

  this.indicateStaleData_(this.stats.wind.end_ts, this.options.maxWindLatencyMinutes, element);
}

ipa.Chart.prototype.drawWindHistogram = function(element) {
  if (ipa.Chart.showNoData_(this.stats.wind, element, 'no wind data available')) {
    return;
  }
  var histogramDataTable = new google.visualization.DataTable();
  histogramDataTable.addColumn('number', 'km/h');
  histogramDataTable.addColumn('number', '%');
  histogramDataTable.addColumn('number', '%>=');
  var totalPercent = 100;
  var histogram = ipa.Tools.sorted(this.stats.wind.hist);
  for (var i = 0; i < histogram.length; i++) {
    var kmh = parseInt(histogram[i][0]);
    var percent = histogram[i][1] * 100;
    histogramDataTable.addRow([kmh, percent, totalPercent]);
    totalPercent -= percent;
  }
  options = {
    title: 'Wind [km/h]',
    hAxis: {gridlines: {count: -1}},  // -1 means automatic
    series: {
      0: {targetAxisIndex: 0, type: 'bars'},
      1: {targetAxisIndex: 1, type: 'lines'}
    },
    vAxes: {
      0: {minValue: 0},
      1: {minValue: 0, maxValue: 100}
    }
  };
  // If there's only one bar we need to force a range of 0..100.
  if (Object.keys(histogram).length == 1) {
    options.vAxes[0].maxValue = 100;
  }
  var histogramChart = new google.visualization.ComboChart(element);
  histogramChart.draw(histogramDataTable, options);

  this.indicateStaleData_(this.stats.wind.end_ts, this.options.maxWindLatencyMinutes, element);
}

ipa.Chart.prototype.drawTextHistogram = function(element) {
  if (ipa.Chart.showNoData_(this.stats.wind, element, 'no wind data available')) {
    return;
  }
  table = document.createElement('table');
  var trSpeed = ipa.Chart.insertCells_(table.insertRow());
  var trPercent = ipa.Chart.insertCells_(table.insertRow());
  var trCumulative = ipa.Chart.insertCells_(table.insertRow());
  trSpeed('km/h');
  trPercent('%');
  trCumulative('%>=');
  var totalPercent = 100;
  var histogram = ipa.Tools.sorted(this.stats.wind.hist);
  for (var i = 0; i < histogram.length; i++) {
    var percent = histogram[i][1] * 100;
    trSpeed(histogram[i][0]);
    trPercent(percent.toFixed(this.options.fractionalDigits));
    trCumulative(totalPercent.toFixed(this.options.fractionalDigits));
    totalPercent -= percent;
  }
  element.appendChild(table);

  this.indicateStaleData_(this.stats.wind.end_ts, this.options.maxWindLatencyMinutes, element);
}

ipa.Chart.prototype.drawStatus = function(element) {
  if (ipa.Chart.showNoData_(
      this.stats.status && this.stats.status.length > 0, element, 'no status available')) {
    return;
  }
  var statusText = this.stats.status[1];
  var statusDate = ipa.Tools.compactDateString(new Date(parseInt(this.stats.status[0])));
  var statusLabel = document.createElement('div');
  statusLabel.className = 'ipaStatus';
  if (this.stats.status[1] == 'ok') {
    statusLabel.className += ' ipaStatusOk';
    if (element.dataset.hideok != 0) {
      statusLabel.className += ' ipaStatusHide';
    }
  }
  statusLabel.appendChild(document.createTextNode(statusText));
  statusLabel.appendChild(document.createElement('br'));
  statusLabel.appendChild(document.createTextNode(statusDate));
  element.appendChild(statusLabel);
}

ipa.Chart.prototype.drawDoor = function(element) {
  var doorTable = new google.visualization.DataTable();
  doorTable.addColumn({type: 'string'});  // day, e.g. 'Fr 13'
  doorTable.addColumn({type: 'string'});  // 'open' or 'closed'
  doorTable.addColumn({type: 'date'});  // from
  doorTable.addColumn({type: 'date'});  // to
  var statusToString = ['closed', 'open'];
  var rows = [];
  var addRow = function(status, a, b) {
    var dayNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    var label = dayNames[a.getDay()] + ' ' + a.getDate();
    a = new Date(0, 0, 0, a.getHours(), a.getMinutes(), a.getSeconds());
    b = (typeof b === 'undefined') ? ipa.Chart.endOfDay_
        : new Date(0, 0, 0, b.getHours(), b.getMinutes(), b.getSeconds());
    // Omit entire closed days.
    if (!(ipa.Chart.isFullDay_(a, b) && status == statusToString[0])) {
      rows.push([label, status, a, b]);
    }
  }
  var sameDate = function(a, b) {
    return a.getMonth() == b.getMonth() && a.getDate() == b.getDate();
  }
  var door = ipa.Tools.sorted(this.stats.door);
  if (door.length == 0) {
    return;  // TODO: We should generally indicate "no data" better.
  }
  // The last element is always synthesized at the end timestamp.
  // We need to determine the initial status of the newest day because this will receive the first
  // color (the logic being that it's located in the top left).
  var newestDayInitialStatus = door[0][1];
  for (var i = 1; i < door.length; i++) {
    var cursor = new Date(parseInt(door[i-1][0]));
    var end = new Date(parseInt(door[i][0]));
    var status = door[i-1][1];
    while (!sameDate(cursor, end)) {
      addRow(statusToString[status], cursor);
      cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1, 0, 0, 0);
      newestDayInitialStatus = status;  // We found a newer day.
    }
    addRow(statusToString[status], cursor, end);
  }
  rows.reverse();
  doorTable.addRows(rows);
  var options = {
    title: 'Door open',
    hAxis: {format: 'HH:mm'},
    legend: 'none'
  };
  // Make sure 'open' is red and 'closed' is gray.
  if (newestDayInitialStatus == 1) {
    options.colors = ['#dd0000', '#999999'];
  } else {
    options.colors = ['#999999', '#dd0000'];
  }
  var doorChart = new google.visualization.Timeline(element);
  doorChart.draw(doorTable, options);
}

ipa.Chart.prototype.drawPilots = function(element) {
  var pilotsTable = new google.visualization.DataTable();
  pilotsTable.addColumn('datetime');
  pilotsTable.addColumn('number');
  var pilots = ipa.Tools.sorted(this.stats.pilots);
  var maxPilots = 1;  // avoid vAxis from 0 to 0
  for (var i = 0; i < pilots.length; i++) {
    var ts = parseInt(pilots[i][0]);
    var count = pilots[i][1];
    if (count > maxPilots) {
      maxPilots = count;
    }
    if (i > 0 && count != pilots[i-1][1]) {
      pilotsTable.addRow([new Date(ts-1), pilots[i-1][1]]);
    }
    pilotsTable.addRow([new Date(ts), count]);
  }
  var options = {
    title: 'Pilots',
    hAxis: {format: 'HH:mm'},
    vAxis: {
      minValue: 0,
      maxValue: maxPilots,
      viewWindow: {min: 0, max: maxPilots},
      gridlines: {count: maxPilots + 1}
    },
    legend: 'none'
  };
  var pilotsChart = new google.visualization.LineChart(element);
  pilotsChart.draw(pilotsTable, options);
}

ipa.Chart.prototype.drawCpuTemp = function(element) {
  var temperatureTable = new google.visualization.DataTable();
  temperatureTable.addColumn('datetime');
  temperatureTable.addColumn('number');
  var temperature = ipa.Tools.sorted(this.stats.cpu_temp);
  for (var i = 0; i < temperature.length; i++) {
    temperatureTable.addRow([new Date(parseInt(temperature[i][0])), temperature[i][1]]);
  }
  var options = {
      title: 'CPU Temperature [\u00B0C]',  // \u00B0 is Unicode for the degree sign
    hAxis: {format: 'HH:mm'},
    legend: 'none'
  };
  var temperatureChart = new google.visualization.LineChart(element);
  temperatureChart.draw(temperatureTable, options);
}

ipa.Chart.prototype.drawTempHum = function(element) {
  if (!('temp_hum' in this.stats)) {
    return;  // XXX report this; also handle in other drawers?
  }
  var tempHumTable = new google.visualization.DataTable();
  tempHumTable.addColumn('datetime', 't');
  // Make sure temperature vAxis label is more prominent; put it on the right, where the latest
  // value is.
  tempHumTable.addColumn('number', 'humidity [%]');
  tempHumTable.addColumn('number', 'temperature [\u00B0C]');
  var temp = ipa.Tools.sorted(this.stats.temp_hum[0]);
  var hum = ipa.Tools.sorted(this.stats.temp_hum[1]);
  // TODO: Assert that both match?
  for (var i = 0; i < temp.length; i++) {
    // Timestamps (index 0) are identical, use temp arbitrarily.
    tempHumTable.addRow([new Date(parseInt(temp[i][0])), hum[i][1], temp[i][1]]);
  }
  var options = {
    title: 'Temperature/Humidity',
    hAxis: {format: 'HH:mm'},
    legend: {position: 'top'},
    series: {
      0: {targetAxisIndex: 0, color: '#0000ff'},
      1: {targetAxisIndex: 1, color: '#ff0000'}
    }
  };
  var tempHumChart = new google.visualization.LineChart(element);
  tempHumChart.draw(tempHumTable, options);
}

ipa.Chart.prototype.drawAdcChannel = function(element) {
  if (!('adc' in this.stats)) {
    return;  // XXX report this; also handle in other drawers?
  }
  var adcTable = new google.visualization.DataTable();
  adcTable.addColumn('datetime', 't');
  adcTable.addColumn('number', element.dataset.label);
  var values = ipa.Tools.sorted(this.stats.adc[element.dataset.channel]);
  for (var i = 0; i < values.length; i++) {
    adcTable.addRow([new Date(parseInt(values[i][0])), values[i][1]]);
  }
  var options = {
    hAxis: {format: 'HH:mm'},
    legend: {position: 'top'},
  };
  var adcChart = new google.visualization.LineChart(element);
  adcChart.draw(adcTable, options);
}

ipa.Chart.prototype.drawSignalStrength = function(element) {
  var strengthTable = new google.visualization.DataTable();
  strengthTable.addColumn('datetime');
  strengthTable.addColumn('number');
  var signalStrength = ipa.Tools.sorted(this.stats.signal_strength);
  for (var i = 0; i < signalStrength.length; i++) {
    strengthTable.addRow([new Date(parseInt(signalStrength[i][0])), signalStrength[i][1]]);
  }
  var options = {
    title: 'Signal strength [%]',
    hAxis: {format: 'HH:mm'},
    legend: 'none'
  };
  var strengthChart = new google.visualization.LineChart(element);
  strengthChart.draw(strengthTable, options);
}

ipa.Chart.prototype.drawNetworkType = function(element) {
  var nwTypeTable = new google.visualization.DataTable();
  nwTypeTable.addColumn('string', 'Type');
  nwTypeTable.addColumn('number', '%');
  var nwTypes = ipa.Tools.alphasorted(this.stats.network_type);
  for (var i = 0; i < nwTypes.length; i++) {
    nwTypeTable.addRow([nwTypes[i][0], nwTypes[i][1]]);
  }
  var options = {
    title: 'Network type'
  };
  var nwTypeChart = new google.visualization.PieChart(element);
  nwTypeChart.draw(nwTypeTable, options);
}

ipa.Chart.prototype.drawTraffic = function(element) {
  if (ipa.Chart.showNoData_(this.stats.traffic, element, 'no traffic data available')) {
    return;
  }
  var table = document.createElement('table');
  table.className = 'ipaTraffic';
  // XXX Is this date really needed? We'd have a staleness indicator if it's off, right?
  // (Same applies to wind summary.)
  ipa.Chart.insertCells_(table.insertRow())('date/time',
      ipa.Tools.compactDateString(new Date(parseInt(this.stats.traffic.end_ts))));
  table.firstChild.lastChild.children[0].className = 'ipaTrafficDateTime';
  table.firstChild.lastChild.children[1].className = 'ipaTrafficDateTime';
  ipa.Chart.insertCells_(table.insertRow())('period',
      ipa.Tools.millisToDays(
          parseInt(this.stats.traffic.end_ts) - parseInt(this.stats.traffic.start_ts)).toFixed(1)
      + ' days');
  table.firstChild.lastChild.children[0].className = 'ipaTrafficPeriod';
  table.firstChild.lastChild.children[1].className = 'ipaTrafficPeriod';
  ipa.Chart.insertCells_(table.insertRow())('download',
      ipa.Tools.bytesToMiB(this.stats.traffic.download) + ' MB');
  table.firstChild.lastChild.children[0].className = 'ipaTrafficDownload';
  table.firstChild.lastChild.children[1].className = 'ipaTrafficDownload';
  ipa.Chart.insertCells_(table.insertRow())('upload',
      ipa.Tools.bytesToMiB(this.stats.traffic.upload) + ' MB');
  table.firstChild.lastChild.children[0].className = 'ipaTrafficUpload';
  table.firstChild.lastChild.children[1].className = 'ipaTrafficUpload';
  ipa.Chart.insertCells_(table.insertRow())('total',
      ipa.Tools.bytesToMiB(this.stats.traffic.download + this.stats.traffic.upload) + ' MB');
  table.firstChild.lastChild.children[0].className = 'ipaTrafficTotal';
  table.firstChild.lastChild.children[1].className = 'ipaTrafficTotal';
  element.appendChild(table);
}

ipa.Chart.prototype.drawLag = function(element) {
  var lagTable = new google.visualization.DataTable();
  var options = {
      title: 'Upload lag [m]',
      hAxis: {format: 'HH:mm'}
  };
  var lag = this.stats.lag;
  for (var ts in lag) {  // just to get any property
    if (lag[ts] instanceof Array) {  // min and max in an array
      this.prepareLagChartMinMax(lagTable, options);
    } else {  // single value
      this.prepareLagChartSingleValue(lagTable, options);
    }
    break;
  }
  var lagChart = new google.visualization.LineChart(element);
  lagChart.draw(lagTable, options);
}

ipa.Chart.prototype.prepareLagChartMinMax = function(lagTable, options) {
  lagTable.addColumn('datetime');
  lagTable.addColumn('number', 'min');
  lagTable.addColumn('number', 'max');
  var lag = ipa.Tools.sorted(this.stats.lag);
  for (var i = 0; i < lag.length; i++) {
    var minLag = lag[i][1][0] / (1000 * 60);  // lag in minutes
    var maxLag = lag[i][1][1] / (1000 * 60);
    lagTable.addRow([new Date(parseInt(lag[i][0])), minLag, maxLag]);
  }
  options.legend = {position: 'top'};
}

ipa.Chart.prototype.prepareLagChartSingleValue = function(lagTable, options) {
  lagTable.addColumn('datetime');
  lagTable.addColumn('number');
  var lag = ipa.Tools.sorted(this.stats.lag);
  for (var i = 0; i < lag.length; i++) {
    lagTable.addRow([new Date(parseInt(lag[i][0])), lag[i][1] / (1000 * 60)]);  // lag in minutes
  }
  options.legend = 'none';
}

ipa.Chart.insertCells_ = function(tr) {
  return function(var_args) {
    for (var i = 0; i < arguments.length; ++i) {
      var td = tr.insertCell();
      td.appendChild(document.createTextNode(arguments[i]));
    }
  }
}

/** Returns epoch millis for 00:00 a.m. the specified number of daysAgo before timestamp. */
ipa.Chart.getStartOfDayDaysAgo_ = function(timestamp, daysAgo) {
  var d = new Date(timestamp);
  d.setDate(d.getDate() - daysAgo);
  d = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  return d.getTime();
}

ipa.Chart.endOfDay_ = new Date(0, 0, 0, 23, 59, 59);

/** Returns true if the specified Date-s cover one day, i.e. 00:00:00 to 23:59:59. */
ipa.Chart.isFullDay_ = function(a, b) {
  return a.getHours() == 0 && a.getMinutes() == 0 && a.getSeconds() == 0
      && b.getHours() == 23 && b.getMinutes() == 59 && b.getSeconds() == 59;
}

ipa.Chart.showNoData_ = function(data, element, text) {
  if (data) {
    return false;
  }
  var noDataLabel = document.createElement('div')
  noDataLabel.className = 'ipaNoData';
  noDataLabel.appendChild(document.createTextNode(text));
  element.appendChild(noDataLabel);
  return true;
}

ipa.Chart.prototype.indicateStaleData_ = function(timestamp, maxMinutes, element) {
  var latencyMinutes = ipa.Tools.millisToMinutes(this.options.endTimestamp - timestamp);
  if (latencyMinutes > maxMinutes) {
    var text = 'last update: ' + ipa.Tools.compactDateString(new Date(parseInt(timestamp)));
    var staleDataLabel = document.createElement('div');
    staleDataLabel.className = 'ipaStaleData';
    staleDataLabel.appendChild(document.createTextNode(text));
    element.appendChild(staleDataLabel);
  }
}

/**
 * Copy properties, overwriting them if they exist. Objects are handled recursively. Fails if object
 * structure does not match, i.e. from.a is an object but to.a is not.
 */
ipa.Chart.copyProperties_ = function(from, to) {
  for (var i in from) {
    if (typeof from[i] === 'object') {
      // Make sure to[i] is an object, too.
      if (i in to) {
        if (typeof to[i] !== 'object') {
          throw 'options structure incorrect - ' + i + ' is not an object';
        }
      } else {
        to[i] = {};
      }
      ipa.Chart.copyProperties_(from[i], to[i]);
    } else {  // Regular property.
      if (typeof to[i] === 'object') {
        throw 'options structure incorrect - ' + i + ' is an object';
      }
      to[i] = from[i];
    }
  }
}
