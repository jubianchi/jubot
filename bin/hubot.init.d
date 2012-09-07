#!/bin/sh

# This assumes you have:
# 1) A user called `hubot` in charge of the bot.
# 2) A file called /home/hubot/.hubotrc that contains the Hubot credentials.
#
# To set the adapter either edit bin/hubot to specify what you want or append
# `-- -a campfire` to the $DAEMON variable below.
#
### BEGIN INIT INFO
# Provides:          hubot
# Required-Start:    $all
# Required-Stop:     $all
# Default-Start:     2 3 4 5
# Default-Stop:      0 1 6
# Short-Description: starts the hubot service
# Description:       starts the Hubot bot for the Campfire rooms
### END INIT INFO

PATH=/home/hubot/node_modules/hubot/bin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
NAME="Hubot"
HUBOT_HOME="/opt/hubot"
LOGFILE="/var/log/hubot/hubot.log"
PIDFILE="/var/run/hubot.pid"
DAEMON="$HUBOT_HOME/bin/hubot"
ARGS="-a irc -n jubot"
ENV="$HUBOT_HOME/env"

set -e

. /lib/lsb/init-functions

case "$1" in
  start)
        log_daemon_msg "Starting $NAME: "
        . $ENV
        start-stop-daemon --start --quiet --pidfile $PIDFILE -c hubot:hubot --make-pidfile --background --chdir $HUBOT_HOME --exec $DAEMON -- $ARGS
        log_end_msg 0
        ;;
  stop)
        log_daemon_msg "Stopping $NAME: "
        start-stop-daemon --stop --quiet --pidfile $PIDFILE
        log_end_msg 0
        ;;

  restart)
        log_daemon_msg "Stopping $NAME: "	        
	start-stop-daemon --stop --quiet --pidfile $PIDFILE
	log_end_msg 0
	log_daemon_msg "Starting $NAME: "
	. $ENV
        start-stop-daemon --start --quiet --pidfile $PIDFILE -c hubot:hubot --make-pidfile --background --chdir $HUBOT_HOME --exec $DAEMON -- $ARGS
	log_end_msg 0
	;;

  status)
        PID=''
        if [ -f $PIDFILE ]
        then
                PID=$(cat $PIDFILE)
        fi

        if [ -n "$PID" ]
        then
	        echo "$NAME is running (pid $PID)."
                exit 0
        else
                echo "$NAME is not running."
                exit 1
        fi
        ;;

  *)
        N=/etc/init.d/$NAME
        echo "Usage: $N {start|stop}" >&2
        exit 1
        ;;  
esac

exit 0
