<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'bridgeland');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', 'root');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'L~9$P{|>bNITyv6vurSfdHL?3~4.L+skT|,f1<;eO8KF[18P|e5GSyN^C![0yQ[~');
define('SECURE_AUTH_KEY',  '8E)X<n2el${H$oJYFME<<IBNR>$OYOasxS8+0W[><>hYaZZ[U+q!|+n~-m:S~!Jn');
define('LOGGED_IN_KEY',    'bvD|,#zK?D*-rctKR,AcA`-dMp`Ih#1iJsg1I])0g<>Y|T}HU`5{ORZk+zkgvq}U');
define('NONCE_KEY',        'pf2cW0xD0p0F[V/=o!tO#ccc^8+$gSnPA>wrNyi37M!G{bROzMGM52I~txik5$+G');
define('AUTH_SALT',        'dc|GYn?bf;Y6/Ma2LFR,OEQo7=4H@#Q2V6k.irgH3h3Et{My6=a_(=c>^$4$`X>S');
define('SECURE_AUTH_SALT', 'g%|jG_Dd|bOzJE3XG<!/{]s.c#?B[2$Oe;Vzf%|Ea+L|Y}XSb+4h,=|{ >)+uz5t');
define('LOGGED_IN_SALT',   '(z $Ax;yi1|<_:4mi%c_5n<JApQg.hUzXi#7=+2EDP Dt^}.z@@(2t2wcP$@CC|J');
define('NONCE_SALT',       '-[L_WcL}TjgR=ucuTv%r9)Do*l:IBIGYx@.)]{qT9FZ4*c1T!z>ZE+=veP5n_@?*');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
