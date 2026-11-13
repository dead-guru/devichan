/*
 * storage-migration.js - resets localStorage left by older versions of the site
 *
 * Usage, before any script that uses localStorage:
 *   $config['additional_javascript'][] = 'js/storage-migration.js';
 */

// Not on document ready: by then other scripts have stored their defaults and
// the reset would wipe them on the first visit.
if (localStorage.version !== '3') {
    console.log('Clearing localStorage');
    localStorage.clear();
    localStorage.version = '3';
}
