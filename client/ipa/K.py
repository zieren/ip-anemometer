# -*- coding: utf-8 -*-


# ----- Client/server interaction. NOTE: These must match common.php -----

TIMESTAMP_KEY = 'ts'
META_KEY = 'meta'
TEMP_KEY = 'temp'
WIND_KEY = 'wind'
WIND_STARTUP_TIME_KEY = 'wind_startup'
WIND_UP_TO_TIME_KEY = 'wind_up_to'
WIND_AGGREGATE_STATS_KEY = 'wind_stats'
UPLOAD_KEY = 'upload'
CLIENT_MD5_KEY = 'md5'
STRATUM_KEY = 'stratum'
STARTUP_TIME_KEY = 'startup'
CLIENT_TIMESTAMP_KEY = 'cts'
FAILED_UPLOADS_KEY = 'ulfs'
UPLOAD_POST_KEY = 'bz2'
COMMAND_RESTART = 'restart'
COMMAND_SHUTDOWN = 'shutdown'
COMMAND_REBOOT = 'reboot'
RESPONSE_STATUS = 'status'
RESPONSE_STATUS_OK = 'ok'
LINK_KEY = 'link'
LINK_NW_TYPE_KEY = 'nwtype'
LINK_STRENGTH_KEY = 'strength'
LINK_UPLOAD_KEY = 'upload'
LINK_DOWNLOAD_KEY = 'download'


# ----- Client constants -----

CLIENT_GREETING = 'IP anemometer client 0.0.4 - (c) Jörg Zieren - http://zieren.de - GNU GPL v3'
CONFIG_FILENAME = 'ipa.cfg'
RESPONSE_STATUS_UNKNOWN = 'N/A'
RETURN_VALUE_SHUTDOWN = 100
RETURN_VALUE_REBOOT = 101
