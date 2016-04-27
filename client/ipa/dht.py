import Adafruit_DHT
import common
from config import C
from logger import LOGGER_FACTORY

class Dht:
  """Reads DHT11/DHT22/AM2302 sensor."""

  _SENSOR = C.DHT_SENSOR()
  _PIN = C.DHT_PIN()
  _RETRIES = C.DHT_RETRIES()

  def __init__(self):
    self._log = LOGGER_FACTORY.get_logger('ipa.dht')
    self._log.info('sensor=%d pin=%d retries=%d' % (Dht._SENSOR, Dht._PIN, Dht._RETRIES))

  def get_sample(self):
    humidity, temperature = Adafruit_DHT.read_retry(Dht._SENSOR, Dht._PIN, retries=Dht._RETRIES)
    if humidity == None or temperature == None:
      self._log.warning('failed to read temperature/humidity')
    return 'temp_hum', (common.timestamp(), temperature, humidity)
