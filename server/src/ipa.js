google.load('visualization', '1.0', {'packages': ['corechart']});

var ipa = {};

// NOTE: Keep keys in sync with wind_stats.py and common.php.
ipa.key = {};
ipa.key.WIND_AVG = 0;
ipa.key.WIND_MAX = 1;
ipa.key.WIND_MAX_TS = 2;
ipa.key.WIND_HIST = 3;
ipa.key.WIND_START_TS = 4;
ipa.key.WIND_END_TS = 5;
// Additional stats only computed on the server. Keep these in sync with common.php.
ipa.key.WIND_TIME_SERIES = 6;

// Options and their defaults.
ipa.Options = function() {
  this.dummy = false;  // Use dummy data for testing?
  this.minutes = 60;  // Window size.
  this.fractionalDigits = 1;  // For textual histogram.
  this.url = 'ipa.php';  // Default in same directory.
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
  var minutes = parseInt('0' + this.options.minutes);
  request.open('GET', this.options.url + '?dummy=' + (this.options.dummy ? '1' : '0')
      + '&m=' + minutes, isAsync);
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
      this.stats = null;
    } else {
      this.stats = JSON.parse(request.responseText);
    }
  }
}

ipa.Chart.prototype.drawSummary = function(element) {
  var table = document.createElement('table');
  table.className = 'summary';
  ipa.Chart.insertCells_(table.insertRow())('avg',
      this.stats[ipa.key.WIND_AVG].toFixed(this.options.fractionalDigits) + ' km/h');
  table.firstChild.lastChild.children[0].className = 'avg';
  table.firstChild.lastChild.children[1].className = 'avgValue';
  ipa.Chart.insertCells_(table.insertRow())('max',
      this.stats[ipa.key.WIND_MAX].toFixed(this.options.fractionalDigits) + ' km/h');
  table.firstChild.lastChild.children[0].className = 'max';
  table.firstChild.lastChild.children[1].className = 'maxValue';
  ipa.Chart.insertCells_(table.insertRow())('max@',
      ipa.Chart.formatTimestamp_(this.stats[ipa.key.WIND_MAX_TS]));
  table.firstChild.lastChild.children[0].className = 'maxts';
  table.firstChild.lastChild.children[1].className = 'maxtsValue';
  ipa.Chart.insertCells_(table.insertRow())('from',
      ipa.Chart.formatTimestamp_(this.stats[ipa.key.WIND_START_TS]));
  table.firstChild.lastChild.children[0].className = 'from';
  table.firstChild.lastChild.children[1].className = 'fromValue';
  ipa.Chart.insertCells_(table.insertRow())('to',
      ipa.Chart.formatTimestamp_(this.stats[ipa.key.WIND_END_TS]));
  table.firstChild.lastChild.children[0].className = 'to';
  table.firstChild.lastChild.children[1].className = 'toValue';
  element.appendChild(table);
}

ipa.Chart.prototype.drawTimeSeries = function(element) {
  var timeSeriesTable = new google.visualization.DataTable();
  timeSeriesTable.addColumn('datetime', 't');
  timeSeriesTable.addColumn('number', 'avg');
  timeSeriesTable.addColumn('number', 'max');
  var timeSeries = this.stats[ipa.key.WIND_TIME_SERIES];
  for (var i = 0; i < timeSeries.length; ++i) {
    var row = timeSeries[i];
    row[0] = new Date(row[0]);  // convert timestamp to Date object
    timeSeriesTable.addRow(row);
  }
  var options = {
    title: 'Wind [km/h]',
    hAxis: {format: 'HH:mm'},
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
  var histogram = this.stats[ipa.key.WIND_HIST];
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
  var histogram = this.stats[ipa.key.WIND_HIST];
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
