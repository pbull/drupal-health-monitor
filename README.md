drupal-health-monitor
=====================

Script to monitor health of a Drupal 7 site, useful for monitoring, load balancers, etc.
Takes an optional comma-separated string of options in the querystring to only run the
requested checks. Default is to run all checks: monitor.php?options=db,slavedb,memcache,files
