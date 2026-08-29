rm /home/happyman/map/out/test/243000x2660*
debug=
# 1. normal
#php cmd_make2.php -r 243:2658:3:3:TWD97 -O ~/map/out/test -v 1921 -t test -D 3x4 -m /dev/shm $debug
# 2. with GPX
php cmd_make2.php -r 243:2660:3:3:TWD97 -O ~/map/out/test -v 1921 -G -t test -D 3x4 -m /dev/shm $debug

# -G merge
