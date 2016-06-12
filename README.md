Router WAT - Whitelist Adding Thingy
=======================

Something to add IPs to RouterOS-based router's whitelist. Not generalised, just for a specific purpose.

Should http auth as minimal security layer.

Should run `sudo crontab -u www-data -e` and add following to www-data's crontab:

```
*/1   *    *    *    *     /home/anthony/whitelister/cli_scripts/save_to_router.php --check_flag >/dev/null 2>&1
```