try:
  import Adafruit_DHT  #@UnresolvedImport
except ImportError:
  import dht_dummy as Adafruit_DHT  # TODO: Just pass here and fail if trying to use it?
import common
from config import C
import dht_dummy
from logger import LOGGER_FACTORY

class Dht:
  """Reads DHT11/DHT22/AM2302 sensor."""

  _SENSOR = C.DHT_SENSOR()
  _PIN = C.DHT_PIN()
  _RETRIES = C.DHT_RETRIES()

  def __init__(self):
    self._log = LOGGER_FACTORY.get_logger('ipa.dht')
    self._log.info('sensor=%d pin=%d retries=%d' % (Dht._SENSOR, Dht._PIN, Dht._RETRIES))
    if Adafruit_DHT.read_retry == dht_dummy.read_retry:
      self._log.critical('Adafruit_DHT module is not installed')

  def get_sample(self):
    humidity, temperature = Adafruit_DHT.read_retry(Dht._SENSOR, Dht._PIN, retries=Dht._RETRIES)
    if humidity == None or temperature == None:
      self._log.warning('failed to read temperature/humidity')
    return 'temp_hum', (common.timestamp(), temperature, humidity)
