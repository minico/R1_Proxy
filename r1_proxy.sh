#!/bin/bash
### BEGIN INIT INFO
# Provides:     test
# Required-Start:  $remote_fs $syslog
# Required-Stop:   $remote_fs $syslog
# Default-Start:   2 3 4 5
# Default-Stop:   0 1 6
# Short-Description: start test
# Description:    start test
### END INIT INFO

#此处编写脚本内容
sudo http_server -p 82 /home/admin/www &
cd /home/admin/R1_Proxy
sudo php ./asr_proxy.php &
exit 0
