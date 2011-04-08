<?php

require 'lib/libwolk/libwolk.php';

include 'conf/api.php';

wolk_api_main($_SERVER['REQUEST_METHOD']);