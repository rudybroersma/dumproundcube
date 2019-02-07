# dumproundcube
Dumps roundcube user preferences into SQL statements. Handy for migrating a user's roundcube data to another server.
# Usage
On source server, do:
php dumproundcube.php -a info@example.com | nc destination.server 1 -q0
On destination server, do:
nc -l -p 1 | mysql
# Result
All settings from the source server for info@example.com will be moved to destination server. Any settings the destination server had will be removed. Other users data are not affected.
