<?php
/**
 * Основне поставке Вордпреса.
 *
 * Ова датотека се користи од стране скрипте за прављење wp-config.php током
 * инсталирања. Не морате да користите веб место, само умножите ову датотеку
 * у "wp-config.php" и попуните вредности.
 *
 * Ова датотека садржи следеће поставке:
 * * MySQL подешавања
 * * тајне кључеве
 * * префикс табеле
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL подешавања - Можете добити ове податке од свог домаћина ** //
/** Име базе података за Вордпрес */
define( 'DB_NAME', 'magic_moments' );

/** Корисничко име MySQL базе */
define( 'DB_USER', 'root' );

/** Лозинка MySQL базе */
define( 'DB_PASSWORD', 'root' );

/** MySQL домаћин */
define( 'DB_HOST', 'localhost' );

/** Скуп знакова за коришћење у прављењу табела базе. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Не мењајте ово ако сте у сумњи. */
define( 'DB_COLLATE', '' );

/**#@+
 * Јединствени кључеви за аутентификацију.
 *
 * Промените ово у различите јединствене изразе!
 * Можете направити ово користећи {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org услугу тајних кључева}
 * Ово можете променити у сваком тренутку да бисте поништили све постојеће колачиће.
 * Ово ће натерати кориснике да се поново пријаве.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '][Yt*d9w)JpAV3!g-!{[[c?x[,zkBW2KePS.;Im(Hy:Wq]#b1%qa:m0$/ ,S7Jw+');
	define('SECURE_AUTH_KEY',  ':x%+HC;Q09$qJNs#i-#!_s^hQyac]KbB$!PPPEXpM5c%0VU#|w93e.=s8<Usu#o}');
	define('LOGGED_IN_KEY',    's~vBx,wxppKY,-+(8&.,|rR2G!Xzqj{xM92[ +UuY3Q0Mw/b2Tvvy9dW`7M3,|>L');
	define('NONCE_KEY',        'Wg&M+a)g|u,ea5WrKi1lS/d}uU<_U8 a^O$`3aU]%a{~#_AxU6S{4x+ {H#f,W7=');
	define('AUTH_SALT',        'MUVH?z+5{stVl*5s_~//|LpW/LB?In(6BG]=F6m+](a*UHG5(xxwONyiwI_x+C}g');
	define('SECURE_AUTH_SALT', 'wlpjU~+n)+wq`z||_=H+Kgtk!cj)0z{TRGU$C#mA&H&pE`pu<r}#rqAD?1Gb{4I|');
	define('LOGGED_IN_SALT',   'Q^!tYNHj(c-*R5X9e~p2pm|Xo5IFT]2cUHRp*54n3xg1f+5d^>C#$ 3-|?PX.=TN');
	define('NONCE_SALT',       'oV|ea1eXXM5[|@:cgN|^]q=Q#M?%1w`&p_U5U|y~3d:2zG?}d8R? ]q~oap4:+XH');

/**#@-*/

/**
 * Префикс табеле Вордпресове базе података.
 *
 * Можете имати више инсталација Вордпреса у једној бази уколико
 * свакој дате јединствени префикс. Само бројеви, слова и доње цртице!
 */
$table_prefix = 'mm_';

/**
 * За градитеље: исправљање грешака у Вордпресу ("WordPress debugging mode").
 *
 * Промените ово у true да бисте омогућили приказ напомена током градње.
 * Веома се препоручује да градитељи тема и додатака користе WP_DEBUG
 * у својим градитељским окружењима.
 *
 * За више података о осталим константама које могу да се
 * користе током отклањања грешака, посетите Документацију.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define( 'WP_DEBUG', false );

/* То је све, престаните са уређивањем! Срећно објављивање. */

/** Апсолутна путања ка Вордпресовом директоријуму. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Поставља Вордпресове променљиве и укључене датотеке. */
require_once( ABSPATH . 'wp-settings.php' );
