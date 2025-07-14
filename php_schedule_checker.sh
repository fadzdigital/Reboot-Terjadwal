#!/system/bin/sh

#======== fadzdigital ==========
# data/adb/service.d/php_schedule_checker.sh

# Sesuaikan Path ke binary PHP CLI kamu
PHP="/data/adb/php7/files/bin/php"
# Path ke script PHP schedule checker
CHECKER="/data/adb/php7/files/www/tools/reboot_schedule_checker.php"
# Path log
LOG="/data/adb/schedule_reboot.log"

while true; do
    echo "$(date '+%Y-%m-%d %H:%M:%S') - [SHELL] PHP checker triggered, running as $(whoami)" >> "$LOG"
    $PHP $CHECKER >> "$LOG" 2>&1
    sleep 60
done
