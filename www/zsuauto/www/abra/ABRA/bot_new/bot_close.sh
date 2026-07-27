#!/bin/bash



##pid=$$
##iddt=`ps fax`
##idd=`echo "$iddt" | grep -v $pid|grep bot.sh|awk '{ print $1 }'`

##if [[ -n $idd ]]; then
##echo "exit , work now ID $idd "


#id=`ps ax|grep bot.sh|grep botnews|awk -F " " '{print $1}'`
#echo $id

#if [[ $id ]] ;then
#echo "exit , work now ID $id "

#kill $id
##else

echo "to work"


/usr/bin/php -f "/home/zsuauto/web/www/abra/ABRA/bot_new/bot_kleizeigin_close.php"
#/usr/bin/php -f "/home/infonc4/www/www/botnews/botnews_business.php"
#/usr/bin/php -f "/home/infonc4/www/www/botnews/botnews_auto.php"
#/usr/bin/php -f "/home/infonc4/www/www/botnews/botnews_culture.php"
#/usr/bin/php -f "/home/infonc4/www/www/botnews/botnews_health.php"
#/usr/bin/php -f "/home/infonc4/www/www/botnews/botnews_sience.php"
#/usr/bin/php -f "/home/infonc4/www/www/botnews/botnews_sport.php"
#/usr/bin/php -f "/home/infonc4/www/www/botnews/botnews_travel.php"
##fi