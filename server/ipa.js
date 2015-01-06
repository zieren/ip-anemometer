google.load('visualization', '1.0', {'packages': ['corechart']});

var ipa = {};

// NOTE: Keep keys in sync with wind_stats.py and common.php.
ipa.key = {};
// TODO: Clean this mess up.
ipa.key.TEMP_TIME_SERIES = 7;
ipa.key.LINK_STRENGTH_TIME_SERIES = 8;
ipa.key.LINK_NW_TYPE = 9;
ipa.key.LINK_UL_DL = 10;

// Options and their defaults.
ipa.Options = function() {
  this.url = 'ipa.php';  // Default in same directory, but can be an absolute URL.
  this.minutes = 60;  // Compute stats for the last x minutes...
  this.upToTimestampMillis = -1;  // ... up to here. -1 means now.
  this.fractionalDigits = 1;  // Textual output precision.
  this.timeSeriesPoints = 30;
      // Downsample time series to make charts readable. Increase for wider charts.
  this.systemStatusMinutes = 24 * 60;
      // Show system status (temperature, signal etc.) (0 to disable).
  this.showTimeOfMax = false;  // Show timestamp of maximum wind speed.
  this.dummy = false;  // Output inconsistent dummy data for testing.
}

// Keep these in sync with common.php.
ipa.constants = {};
ipa.constants.RESPONSE_NO_STATS = 'n/a';

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
  if (!isAsync) {
    // TODO: Handle request error.
    if (request.responseText === ipa.constants.RESPONSE_NO_STATS) {
      this.stats = null;  // TODO: Handle this below.
    } else {
      this.stats = JSON.parse(request.responseText);
    }
  }
}

ipa.Chart.prototype.drawSummary = function(element) {
  var table = document.createElement('table');
  table.className = 'summary';
  ipa.Chart.insertCells_(table.insertRow())('avg',
      this.stats['avg'].toFixed(this.options.fractionalDigits) + ' km/h');
  table.firstChild.lastChild.children[0].className = 'avg';
  table.firstChild.lastChild.children[1].className = 'avgValue';
  ipa.Chart.insertCells_(table.insertRow())('max',
      this.stats['max'].toFixed(this.options.fractionalDigits) + ' km/h');
  table.firstChild.lastChild.children[0].className = 'max';
  table.firstChild.lastChild.children[1].className = 'maxValue';
  if (this.options.showTimeOfMax) {
    ipa.Chart.insertCells_(table.insertRow())('max@',
        ipa.Chart.formatTimestamp_(this.stats['max_ts']));
    table.firstChild.lastChild.children[0].className = 'maxts';
    table.firstChild.lastChild.children[1].className = 'maxtsValue';
  }
  ipa.Chart.insertCells_(table.insertRow())('from',
      ipa.Chart.formatTimestamp_(this.stats['start_ts']));
  table.firstChild.lastChild.children[0].className = 'from';
  table.firstChild.lastChild.children[1].className = 'fromValue';
  ipa.Chart.insertCells_(table.insertRow())('to',
      ipa.Chart.formatTimestamp_(this.stats['end_ts']));
  table.firstChild.lastChild.children[0].className = 'to';
  table.firstChild.lastChild.children[1].className = 'toValue';
  element.appendChild(table);
}

ipa.Chart.prototype.drawTimeSeries = function(element) {
  var timeSeriesTable = new google.visualization.DataTable();
  timeSeriesTable.addColumn('datetime', 't');
  timeSeriesTable.addColumn('number', 'avg');
  timeSeriesTable.addColumn('number', 'max');
  var timeSeries = this.stats['time_series'];
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
}

ipa.Chart.prototype.drawHistogram = function(element) {
  var histogramDataTable = new google.visualization.DataTable();
  histogramDataTable.addColumn('number', 'km/h');
  histogramDataTable.addColumn('number', '%');
  histogramDataTable.addColumn('number', '%>=');
  var histogram = this.stats['hist'];
  var totalPercent = 100;
  for (var kmh in histogram) {
    var percent = histogram[kmh] * 100;
    histogramDataTable.addRow([parseInt(kmh), percent, totalPercent]);
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
}

ipa.Chart.prototype.drawTextHistogram = function(element) {
  var histogram = this.stats['hist'];
  table = document.createElement('table');
  var trSpeed = ipa.Chart.insertCells_(table.insertRow());
  var trPercent = ipa.Chart.insertCells_(table.insertRow());
  var trCumulative = ipa.Chart.insertCells_(table.insertRow());
  trSpeed('km/h');
  trPercent('%');
  trCumulative('%>=');
  var totalPercent = 100;
  for (var kmh in histogram) {
    var percent = histogram[kmh] * 100;
    trSpeed(kmh);
    trPercent(percent.toFixed(this.options.fractionalDigits));
    trCumulative(totalPercent.toFixed(this.options.fractionalDigits));
    totalPercent -= percent;
  }
  element.appendChild(table);
}

ipa.Chart.prototype.drawTemperature = function(element) {
  var temperatureTable = new google.visualization.DataTable();
  temperatureTable.addColumn('datetime');
  temperatureTable.addColumn('number');
  var timeSeries = this.stats[ipa.key.TEMP_TIME_SERIES];
  for (var i = 0; i < timeSeries.length; ++i) {
    var row = timeSeries[i];
    row[0] = new Date(row[0]);  // convert timestamp to Date object
    temperatureTable.addRow(row);
  }
  var options = {
    title: 'CPU temperature [°C]',
    hAxis: {format: 'HH:mm'},
    legend: 'none'
  };
  var temperatureChart = new google.visualization.LineChart(element);
  temperatureChart.draw(temperatureTable, options);
}

ipa.Chart.prototype.drawSignalStrength = function(element) {
  var strengthTable = new google.visualization.DataTable();
  strengthTable.addColumn('datetime');
  strengthTable.addColumn('number');
  var timeSeries = this.stats[ipa.key.LINK_STRENGTH_TIME_SERIES];
  for (var i = 0; i < timeSeries.length; ++i) {
    var row = timeSeries[i];
    row[0] = new Date(row[0]);  // convert timestamp to Date object
    strengthTable.addRow(row);
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
  var nwTypes = this.stats[ipa.key.LINK_NW_TYPE];
  for (var i in nwTypes) {
    nwTypeTable.addRow([i, nwTypes[i]]);
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
  volumeTable.addColumn('number', 'MB');
  var volumes = this.stats[ipa.key.LINK_UL_DL];
  for (var i in volumes) {
    volumeTable.addRow([i, volumes[i] / (1024 * 1024)]);  // in MB
  }
  var options = {
    title: 'Transfer volume [MB]'
  };
  var volumeChart = new google.visualization.ColumnChart(element);
  volumeChart.draw(volumeTable, options);
}

ipa.Chart.prototype.drawLag = function(element) {
  var lagTable = new google.visualization.DataTable();
  lagTable.addColumn('datetime');
  lagTable.addColumn('number');
  var lags = this.stats['lag'];
  for (var ts in lags) {
    lagTable.addRow([new Date(parseInt(ts)), lags[ts] / (1000 * 60)]);  // lag in minutes
  }
  var options = {
    title: 'Lag [m]',
    hAxis: {format: 'HH:mm'},
    legend: 'none'
  };
  var lagChart = new google.visualization.LineChart(element);
  lagChart.draw(lagTable, options);
}

ipa.Chart.insertCells_ = function(tr) {
  return function(var_args) {
    for (var i = 0; i < arguments.length; ++i) {
      var td = tr.insertCell();
      td.appendChild(document.createTextNode(arguments[i]));
    }
  }
}

ipa.Chart.formatTimestamp_ = function(timestamp) {
  var date = new Date(timestamp);
  var hh = ('0' + date.getHours()).slice(-2);
  var mm = ('0' + date.getMinutes()).slice(-2);
  var ss = ('0' + date.getSeconds()).slice(-2);
  return hh + ':' + mm + ':' + ss;
}
