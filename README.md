IP Anemometer
=============

This is a hardware+software project. A wind sensor is connected to a Raspberry
Pi via the GPIO interface. The Pi collects measurements and uploads them to a
web server, which stores them in a MySQL database. The server shows various
statistics, such as wind speed over time, average, maximum and a histogram.

Also supported:
- DHT22 type sensor for temperature and humidity.
- MCP3008 analog/digital converter for custom inputs, e.g. voltage.
- Digital switches, e.g. "door open" detection and pilot counter.

See http://zieren.de for more information.
