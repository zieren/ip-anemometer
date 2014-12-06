window.onload = function() {
	requestStats(5);
}

function requestStats(minutes) {
	var request = new XMLHttpRequest();
  request.onreadystatechange = function() {
    if (request.readyState == 4 && request.status == 200) {
    	outputStats(request.responseText);
    }
  }
  request.open("GET", "ipa.php?m=" + minutes, true);  // XXX
  request.send();
}

function outputStats(statsJson) {
	var stats = JSON.parse(statsJson);
	var body = document.body;
	var table = document.createElement('table');
  var tr = table.insertRow();
  var td = tr.insertCell();
  td.appendChild(document.createTextNode('avg'));
  td = tr.insertCell();
  td.appendChild(document.createTextNode('max'));
  td = tr.insertCell();
  td.appendChild(document.createTextNode('max@'));
  tr = table.insertRow();
  td = tr.insertCell();
  td.appendChild(document.createTextNode(stats[0].toFixed(1)));
  td = tr.insertCell();
  td.appendChild(document.createTextNode(parseInt(stats[1]).toFixed(1)));
  td = tr.insertCell();
  td.appendChild(document.createTextNode(formatTimestamp(stats[2])));

  var div = document.getElementById("ipaWind");
  div.innerHTML = '';
  div.appendChild(table);

  var histogram = stats[3];
  table = document.createElement('table');
	var trV = table.insertRow();
	var trP = table.insertRow();
  for (var v in histogram) {
  	td = trV.insertCell();
  	td.appendChild(document.createTextNode(v));
  	td = trP.insertCell();
  	td.appendChild(document.createTextNode((histogram[v] * 100).toFixed(1)));
  }
  div.appendChild(table);

	div.appendChild(document.createTextNode(
			formatTimestamp(stats[4]) + ' to ' + formatTimestamp(stats[5])));
}

function formatTimestamp(timestamp) {
	timestamp = parseInt(timestamp);
  var date = new Date(timestamp);
	var hh = ('0' + date.getHours()).slice(-2);
	var mm = ('0' + date.getMinutes()).slice(-2);
	var ss = ('0' + date.getSeconds()).slice(-2);
	return hh + ':' + mm + ':' + ss;
}
