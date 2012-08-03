<?php

/**
 * Script to monitor health of a Drupal 7 site, useful for monitoring, load balancers, etc.
 * Takes an optional comma-separated string of options in the querystring:
 *   health.php?options=db,slavedb,memcache,files
 */

/**
 * Register our shutdown function so that no other shutdown functions run before this one.
 * This shutdown function calls exit(), immediately short-circuiting any other shutdown functions,
 * such as those registered by the devel.module for statistics.
 */
register_shutdown_function('status_shutdown');
function status_shutdown() {
  exit();
}

/**
 * Bootstrap Drupal.
 */
define('DRUPAL_ROOT', getcwd());
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

// Get options from querystring, default to all options
$options_all = TRUE;
if (isset($_GET['options'])) {
  $options = explode(',', strtolower($_GET['options']));
  if (!in_array('all', $options)) {
    $options_all = FALSE;
  }
}

// Don't cache the response.
header("Cache-Control: no-cache");

// Build list of errors.
$errors = array();


// DATABASE: Check that the primary database is active.
if ($options_all || in_array('db', $options)) {
  $query = db_select('users', 'u')
    ->fields('u')
    ->condition('u.uid', 1, '=');
  $result = $query->execute();
  if (!$account = $result->fetchAssoc()) {
    $errors[] = 'Master database not responding.';
  }
}


// SLAVE DATABASE
// Check that slave databases are active.
if ($options_all || in_array('slavedb', $options)) {
  // TODO: Implement check for slave db.
}


// MEMCACHE
// Check that all memcache instances are running on this server.
if ($options_all || in_array('memcache', $options)) {
  if (isset($conf['cache_backends']) && isset($conf['memcache_servers'])) {
    // Select PECL memcache/memcached library to use
    $preferred = variable_get('memcache_extension', NULL);
    if (isset($preferred) && class_exists($preferred)) {
      $extension = $preferred;
    }
    // If no extension is set, default to Memcache.
    elseif (class_exists('Memcache')) {
      $extension = 'Memcache';
    }
    elseif (class_exists('Memcached')) {
      $extension = 'Memcached';
    }
    else {
      $errors[] = 'Memcache and Memcached PECL extensions are not available.';
    }
    // Test server connections
    if ($extension) {
      foreach ($conf['memcache_servers'] as $address => $bin) {
        list($ip, $port) = explode(':', $address);
        if ($extension == 'Memcache') {
          if (!memcache_connect($ip, $port)) {
            $errors[] = 'Memcache bin <em>' . $bin . '</em> at address ' . $address . ' is not available.';
          }
        }
        elseif ($extension == 'Memcached') {
          $m = new Memcached();
          $m->addServer($ip, $port);
          if ($m->getVersion() == FALSE) {
            $errors[] = 'Memcached bin <em>' . $bin . '</em> at address ' . $address . ' is not available.';
          }
        }
      }
    }
  }
}


// FILESYSTEM
// Check that the files directory is operating properly.
if ($options_all || in_array('files', $options)) {
  if ($test = tempnam(variable_get('file_directory_path', conf_path() . '/files'), 'status_check_')) {
    // Uncomment to check if files are saved in the correct server directory.
    //if (!strpos($test, '/mnt/nfs') === 0) {
    //  $errors[] = 'Files are not being saved in the NFS mount under /mnt/nfs.';
    //}
    if (!unlink($test)) {
      $errors[] = 'Could not delete newly create files in the files directory.';
    }
  }
  else {
    $errors[] = 'Could not create temporary file in the files directory.';
  }
}


// Print all errors.
if ($errors) {
  $errors[] = 'Errors on this server will cause it to be removed from the load balancer.';
  header('HTTP/1.1 500 Internal Server Error');
  print implode("<br />\n", $errors);
}
else {
  // Split up this message, to prevent the remote chance of monitoring software
  // reading the source code if PHP fails and then matching the string.
  print 'OK' . ' 200';
}

// Exit immediately, note the shutdown function registered at the top of the file.
exit();
