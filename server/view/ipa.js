google.load('visualization', '1.0', {'packages': ['corechart', 'timeline']});

var ipa = {};

// Options and their defaults.
ipa.Options = function() {
  this.url = 'ipa.php';  // Default in same directory, but can be an absolute URL.
  this.minutes = 60;  // Compute stats for the last x minutes...
  this.upToTimestampMillis = -1;  // ... up to here. -1 means now.
  this.fractionalDigits = 1;  // Textual output precision.
  this.timeSeriesPoints = 30;
  // Downsample time series to make charts readable. Increase for wider charts.
  this.doorTimeDays = 8;  // Show shed door status.
  this.pilotsTimeDays = 0;  // Show shed door status.
  // TODO: Figure out how to display mutliple days.
  this.systemStatusMinutes = 24 * 60;
  // Show system status (temperature, signal etc.) (0 to disable).
  this.showTimeOfMax = false;  // Show timestamp of maximum wind speed.
  this.dummy = false;  // Output inconsistent dummy data for testing.
  this.maxWindLatencyMinutes = 15;  // Show a warning when wind data is older.
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

/**
 * Returns time if date is today, otherwise returns date and time.
 */
ipa.Tools.compactDateString = function(date) {
  if (new Date(Date.now()).toLocaleDateString() == date.toLocaleDateString()) {
    return date.toLocaleTimeString();
  }
  return date.toLocaleString();
}

ipa.Chart = function(options) {
  this.options = new ipa.Options();
  for (var i in options) {
    this.options[i] = options[i];
  }
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
  request.open('GET',
      this.options.url
      + '?m=' + this.options.minutes
      + '&p=' + this.options.timeSeriesPoints
      + '&s=' + this.options.systemStatusMinutes
      + '&d=' + ipa.Chart.getStartOfDayDaysAgo_(this.options.doorTimeDays)
      + '&pc=' + ipa.Chart.getStartOfDayDaysAgo_(this.options.pilotsTimeDays)
      + (this.options.upToTimestampMillis >= 0 ? '&ts=' + this.options.upToTimestampMillis : '')
      + (this.options.dummy ? '&dummy=1' : ''),
      isAsync);
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
  table.firstChild.lastChild.children[0].className = 'avg';
  table.firstChild.lastChild.children[1].className = 'avgValue';
  ipa.Chart.insertCells_(table.insertRow())('max',
      this.stats.wind.max.toFixed(this.options.fractionalDigits) + ' km/h');
  table.firstChild.lastChild.children[0].className = 'max';
  table.firstChild.lastChild.children[1].className = 'maxValue';
  if (this.options.showTimeOfMax) {
    ipa.Chart.insertCells_(table.insertRow())('max@',
        ipa.Chart.formatTimestamp_(this.stats.wind.max_ts));
    table.firstChild.lastChild.children[0].className = 'maxts';
    table.firstChild.lastChild.children[1].className = 'maxtsValue';
  }
  ipa.Chart.insertCells_(table.insertRow())('from',
      ipa.Chart.formatTimestamp_(this.stats.wind.start_ts));
  table.firstChild.lastChild.children[0].className = 'from';
  table.firstChild.lastChild.children[1].className = 'fromValue';
  ipa.Chart.insertCells_(table.insertRow())('to',
      ipa.Chart.formatTimestamp_(this.stats.wind.end_ts));
  table.firstChild.lastChild.children[0].className = 'to';
  table.firstChild.lastChild.children[1].className = 'toValue';
  element.appendChild(table);

  ipa.Chart.indicateStaleData_(this.stats.wind.end_ts, this.options.maxWindLatencyMinutes, element);
}

ipa.Chart.prototype.drawTimeSeries = function(element) {
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
    row[0] = new Date(row[0]);  // convert timestamp to Date object
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

  ipa.Chart.indicateStaleData_(this.stats.wind.end_ts, this.options.maxWindLatencyMinutes, element);
}

ipa.Chart.prototype.drawHistogram = function(element) {
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

  ipa.Chart.indicateStaleData_(this.stats.wind.end_ts, this.options.maxWindLatencyMinutes, element);
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

  ipa.Chart.indicateStaleData_(this.stats.wind.end_ts, this.options.maxWindLatencyMinutes, element);
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

ipa.Chart.prototype.drawTemperature = function(element) {
  var temperatureTable = new google.visualization.DataTable();
  temperatureTable.addColumn('datetime');
  temperatureTable.addColumn('number');
  var temperature = ipa.Tools.sorted(this.stats.sys.temp_t);
  for (var i = 0; i < temperature.length; i++) {
    temperatureTable.addRow([new Date(parseInt(temperature[i][0])), temperature[i][1]]);
  }
  var options = {
      title: 'CPU temperature [\u00B0C]',  // \u00B0 is Unicode for the degree sign
    hAxis: {format: 'HH:mm'},
    legend: 'none'
  };
  var temperatureChart = new google.visualization.LineChart(element);
  temperatureChart.draw(temperatureTable, options);
}

ipa.Chart.prototype.drawTempHum = function(element) {
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

ipa.Chart.prototype.drawSignalStrength = function(element) {
  var strengthTable = new google.visualization.DataTable();
  strengthTable.addColumn('datetime');
  strengthTable.addColumn('number');
  var signalStrength = ipa.Tools.sorted(this.stats.sys.strength_t);
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
  var nwTypes = ipa.Tools.alphasorted(this.stats.sys.nwtype);
  for (var i = 0; i < nwTypes.length; i++) {
    nwTypeTable.addRow([nwTypes[i][0], nwTypes[i][1]]);
  }
  var options = {
    title: 'Network type'
  };
  var nwTypeChart = new google.visualization.PieChart(element);
  nwTypeChart.draw(nwTypeTable, options);
}

ipa.Chart.prototype.drawTransferVolume = function(element) {
  var volumeTable = new google.visualization.DataTable();
  volumeTable.addColumn('string', 'Volume');
  volumeTable.addColumn('number');
  var volumes = ipa.Tools.alphasorted(this.stats.sys.traffic);
  for (var i = 0; i < volumes.length; i++) {
    volumeTable.addRow([volumes[i][0], volumes[i][1] / (1024 * 1024)]);  // in MB
  }
  var options = {
    title: 'Transfer volume [MB]',
    legend: 'none'
  };
  var volumeChart = new google.visualization.ColumnChart(element);
  volumeChart.draw(volumeTable, options);
}

ipa.Chart.prototype.drawLag = function(element) {
  var lagTable = new google.visualization.DataTable();
  var options = {
      title: 'Upload lag [m]',
      hAxis: {format: 'HH:mm'}
  };
  var lag = this.stats.sys.lag;
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
  var lag = ipa.Tools.sorted(this.stats.sys.lag);
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
  var lag = ipa.Tools.sorted(this.stats.sys.lag);
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

/** Returns epoch millis for 00:00 a.m. the specified number of daysAgo. */
ipa.Chart.getStartOfDayDaysAgo_ = function(daysAgo) {
  var d = new Date();
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

ipa.Chart.formatTimestamp_ = function(timestamp) {
  var date = new Date(timestamp);
  var hh = ('0' + date.getHours()).slice(-2);
  var mm = ('0' + date.getMinutes()).slice(-2);
  var ss = ('0' + date.getSeconds()).slice(-2);
  return hh + ':' + mm + ':' + ss;
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

ipa.Chart.indicateStaleData_ = function(timestamp, maxMinutes, element) {
  var now = Date.now();
  var latencyMinutes = ipa.Tools.millisToMinutes(now - timestamp);
  if (latencyMinutes > maxMinutes) {
    var text = 'last update: ' + ipa.Tools.compactDateString(new Date(timestamp));
    var staleDataLabel = document.createElement('div');
    staleDataLabel.className = 'ipaStaleData';
    staleDataLabel.appendChild(document.createTextNode(text));
    element.appendChild(staleDataLabel);
  }
}
