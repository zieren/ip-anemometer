try:
  import spidev  #@UnresolvedImport
except ImportError:
  pass

import common
import config
from config import C
import log


class SpiAdc:
  _CHANNELS = C.ADC_CHANNELS()
  _VREFS = C.ADC_VREFS()

  def __init__(self):
    self._log = log.get_logger('ipa.adc')
    self._spi = spidev.SpiDev()
    self._spi.open(0, 0)
    if len(SpiAdc._CHANNELS) != len(SpiAdc._VREFS):
      raise config.InvalidConfigException(
          'config has %d channels, but %d vrefs' % (len(SpiAdc._CHANNELS), len(SpiAdc._VREFS)))
    self._log.info('initialized (channels: %s; vrefs: %s)' % (SpiAdc._CHANNELS, SpiAdc._VREFS))

  def _read(self, channel):
    """Reads ADC channel 0..7"""
    response = self._spi.xfer2([1, (8 + channel) << 4, 0])
    value = ((response[1] & 3) << 8) + response[2]
    return value / 1023.0  # 10 bit resolution

  def get_sample(self):
    sample = {}
    for i, channel in enumerate(SpiAdc._CHANNELS):
      vref = SpiAdc._VREFS[i]
      sample[channel] = (common.timestamp(), self._read(channel) * vref)
    return 'adc', sample
