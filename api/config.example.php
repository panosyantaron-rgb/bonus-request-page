<?php
// ============================================
// DATABASE CONFIG — fill these in from cPanel
// cPanel > MySQL Databases (create DB + user, add user to DB with ALL PRIVILEGES)
// ============================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'cpaneluser_bonusdb');   // e.g. taron_bonusdb
define('DB_USER', 'cpaneluser_bonususer'); // e.g. taron_bonususer
define('DB_PASS', 'CHANGE_THIS_PASSWORD');

// Admin panel login
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin123');          // change this before going live
