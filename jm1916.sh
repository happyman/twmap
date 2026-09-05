rm /home/happyman/map/out/test/239000x2517000*
debug=
# 1. normal
#php cmd_make2.php -r 243:2658:3:3:TWD97 -O ~/map/out/test -v 1921 -t test -D 3x4 -m /dev/shm $debug
# 2. with GPX
php cmd_make2.php -r 239:2517:4:4:TWD97 -O ~/map/out/test -v 1916 -G -t test -D 3x4 -D 5x7 -m /dev/shm $debug

# -G merge
