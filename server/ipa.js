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
  this.doorTimeDays = 7;  // Show shed door status.
  this.systemStatusMinutes = 24 * 60;
      // Show system status (temperature, signal etc.) (0 to disable).
  this.showTimeOfMax = false;  // Show timestamp of maximum wind speed.
  this.dummy = false;  // Output inconsistent dummy data for testing.
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
      + '&d=' + this.options.doorTimeDays
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
    if (!this.stats.wind) {
      alert('No wind data available.');
      // TODO: Signal this to the client instead. Also allow the client to get the lag so it can
      // show a message for stale data.
    }
  }
}

ipa.Chart.prototype.drawSummary = function(element) {
  var table = document.createElement('table');
  table.className = 'summary';
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
}

ipa.Chart.prototype.drawTimeSeries = function(element) {
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
}

ipa.Chart.prototype.drawHistogram = function(element) {
  var histogramDataTable = new google.visualization.DataTable();
  histogramDataTable.addColumn('number', 'km/h');
  histogramDataTable.addColumn('number', '%');
  histogramDataTable.addColumn('number', '%>=');
  var histogram = this.stats.wind.hist;
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
  var histogram = this.stats.wind.hist;
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

ipa.Chart.prototype.drawDoor = function(element) {
  var doorTable = new google.visualization.DataTable();
  doorTable.addColumn({type: 'string'});  // day, e.g. 'Fr 13'
  doorTable.addColumn({type: 'string'});  // 'open' or 'closed'
  doorTable.addColumn({type: 'date'});  // from
  doorTable.addColumn({type: 'date'});  // to
  var door = this.stats.door;
  var previousTs = 0;
  var status = ['closed', 'open'];
  var rows = new Array();
  var addRow = function(status, a, b) {
    var dayNames = new Array('Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa');
    var label = dayNames[a.getDay()] + ' ' + a.getDate();
    a = new Date(0, 0, 0, a.getHours(), a.getMinutes(), a.getSeconds());
    b = (typeof b === 'undefined') ? new Date(0, 0, 0, 23, 59, 59)
        : new Date(0, 0, 0, b.getHours(), b.getMinutes(), b.getSeconds());
    rows.push([label, status, a, b]);
  }
  var sameDate = function(a, b) {
    return a.getMonth() == b.getMonth() && a.getDate() == b.getDate();
  }
  for (var ts in door) {
    if (previousTs) {
      var cursor = new Date(parseInt(previousTs));
      var end = new Date(parseInt(ts));
      while (!sameDate(cursor, end)) {
        addRow(status[door[previousTs]], cursor);
        cursor = new Date(cursor.getFullYear(), cursor.getMonth(), cursor.getDate() + 1, 0, 0, 0);
      }
      addRow(status[door[previousTs]], cursor, end);
    }
    previousTs = ts;
  }
  if (!rows.length) {
    return;  // TODO: We should generally indicate "no data" better.
  }
  rows.reverse();
  doorTable.addRows(rows);
  var options = {
    title: 'Door open',
    hAxis: {format: 'HH:mm'},
    legend: 'none'
  };
  var doorChart = new google.visualization.Timeline(element);
  doorChart.draw(doorTable, options);
}

ipa.Chart.prototype.drawTemperature = function(element) {
  var temperatureTable = new google.visualization.DataTable();
  temperatureTable.addColumn('datetime');
  temperatureTable.addColumn('number');
  var temperature = this.stats.sys.temp_t;
  for (var ts in temperature) {
    temperatureTable.addRow([new Date(parseInt(ts)), temperature[ts]]);
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
  var signalStrength = this.stats.sys.strength_t;
  for (var ts in signalStrength) {
    strengthTable.addRow([new Date(parseInt(ts)), signalStrength[ts]]);
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
  var nwTypes = this.stats.sys.nwtype;
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
  volumeTable.addColumn('number');
  var volumes = this.stats.sys.traffic;
  for (var i in volumes) {
    volumeTable.addRow([i, volumes[i] / (1024 * 1024)]);  // in MB
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
  var lag = this.stats.sys.lag;
  var options = {
      title: 'Upload lag [m]',
      hAxis: {format: 'HH:mm'}
  };
  for (var ts in lag) {  // just to get the first property
    if (lag[ts] instanceof Array) {  // min and max in an array
      this.prepareLagChartMinMax(lag, lagTable, options);
    } else {  // single value
      this.prepareLagChartSingleValue(lag, lagTable, options);
    }
    break;
  }
  var lagChart = new google.visualization.LineChart(element);
  lagChart.draw(lagTable, options);
}

ipa.Chart.prototype.prepareLagChartMinMax = function(lag, lagTable, options) {
  lagTable.addColumn('datetime');
  lagTable.addColumn('number', 'min');
  lagTable.addColumn('number', 'max');
  for (var ts in lag) {
    var minLag = lag[ts][0] / (1000 * 60);  // lag in minutes
    var maxLag = lag[ts][1] / (1000 * 60);
    lagTable.addRow([new Date(parseInt(ts)), minLag, maxLag]);
  }
  options.legend = {position: 'top'};
}

ipa.Chart.prototype.prepareLagChartSingleValue = function(lag, lagTable, options) {
  lagTable.addColumn('datetime');
  lagTable.addColumn('number');
  for (var ts in lag) {
    lagTable.addRow([new Date(parseInt(ts)), lag[ts] / (1000 * 60)]);  // lag in minutes
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

ipa.Chart.formatTimestamp_ = function(timestamp) {
  var date = new Date(timestamp);
  var hh = ('0' + date.getHours()).slice(-2);
  var mm = ('0' + date.getMinutes()).slice(-2);
  var ss = ('0' + date.getSeconds()).slice(-2);
  return hh + ':' + mm + ':' + ss;
}
