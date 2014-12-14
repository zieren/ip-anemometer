var ipa = new function() {
  // NOTE: Keep WIND_KEY_* in sync with wind_stats.py and common.php.
  this.WIND_KEY_AVG = 0;
  this.WIND_KEY_MAX = 1;
  this.WIND_KEY_MAX_TS = 2;
  this.WIND_KEY_HIST = 3;
  this.WIND_KEY_START_TS = 4;
  this.WIND_KEY_END_TS = 5;

  // Additional stats only computed on the server. Keep these in sync with ipa.js.
  this.WIND_KEY_TIME_SERIES = 6;

  var WIND_MINUTES_DEFAULT = 5;

//  window.addEventListener('load', function() {
//    var inputMinutes = document.getElementById('ipaMinutes');
//    inputMinutes.value = WIND_MINUTES_DEFAULT;
//    inputMinutes.focus();
//    requestStats(WIND_MINUTES_DEFAULT);
//  });

  this.requestStats = function(minutes) {
    var request = new XMLHttpRequest();
//    request.onreadystatechange = function() {
//      if (request.readyState == 4 && request.status == 200) {
//        outputStats(request.responseText);
//      }
//    }
    request.open("GET", "ipa.php?dummy=1&m=" + minutes, false);
    request.send();
    if (request.responseText == "n/a") {
      return null;
    }
    var stats = JSON.parse(request.responseText);
    outputStats(stats);
    return stats;
  }

  var insertCellsFunction = function(tr) {
    return function(var_args) {
      for (var i = 0; i < arguments.length; ++i) {
        var td = tr.insertCell();
        td.appendChild(document.createTextNode(arguments[i]));
      }
    }
  }

  var outputStats = function(stats) {
    var body = document.body;
    var div = document.getElementById("ipaWind");
    div.innerHTML = '';

    var table = document.createElement('table');
    insertCellsFunction(table.insertRow())('avg', 'max', 'max@');
    insertCellsFunction(table.insertRow())(
        stats[ipa.WIND_KEY_AVG].toFixed(1),
        stats[ipa.WIND_KEY_MAX].toFixed(1),  // XXX int?!
        formatTimestamp(stats[ipa.WIND_KEY_MAX_TS]));
    div.appendChild(table);

    var histogram = stats[ipa.WIND_KEY_HIST];
    table = document.createElement('table');
    var trV = insertCellsFunction(table.insertRow());
    var trP = insertCellsFunction(table.insertRow());
    var trC = insertCellsFunction(table.insertRow());
    trV('km/h');
    trP('%');
    trC('%>=');
    var c = 100;
    for (var v in histogram) {
      var p = histogram[v] * 100;
      trV(v);
      trP(p.toFixed(1));
      trC(c.toFixed(1));
      c -= p;
    }
    div.appendChild(table);

    div.appendChild(document.createTextNode(formatTimestamp(stats[ipa.WIND_KEY_START_TS]) + ' to '
        + formatTimestamp(stats[ipa.WIND_KEY_END_TS])));
  }

  var formatTimestamp = function(timestamp) {
    var date = new Date(timestamp);
    var hh = ('0' + date.getHours()).slice(-2);
    var mm = ('0' + date.getMinutes()).slice(-2);
    var ss = ('0' + date.getSeconds()).slice(-2);
    return hh + ':' + mm + ':' + ss;
  }
}
