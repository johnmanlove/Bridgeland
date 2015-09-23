<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Footer Template
 *
 *
 * @file           footer.php
 * @package        Responsive
 * @author         Emil Uzelac
 * @copyright      2003 - 2014 CyberChimps
 * @license        license.txt
 * @version        Release: 1.2
 * @filesource     wp-content/themes/responsive/footer.php
 * @link           http://codex.wordpress.org/Theme_Development#Footer_.28footer.php.29
 * @since          available since Release 1.0
 */

/*
 * Globalize Theme options
 */
global $responsive_options;
$responsive_options = responsive_get_options();
?>
<?php responsive_wrapper_bottom(); // after wrapper content hook ?>
</div><!-- end of #wrapper -->
<?php responsive_wrapper_end(); // after wrapper hook ?>
</div><!-- end of #container -->
<?php responsive_container_end(); // after container hook ?>

<div id="footer" class="clearfix">
	<?php responsive_footer_top(); ?>

	<div id="footer-social" class="clearfix">
		<div class="logo">
                <a href="/"><img src="/Bridgeland/wp-content/uploads/2015/09/logo.png" alt="Bridgeland"></a>
        </div>
        <div class="social-links">
            <ul>
                <li><a id="facebook" href="https://www.facebook.com/Bridgelandcommunity" target="_blank">Facebook</a></li>
                <li><a id="twitter" href="https://twitter.com/BridgelandTweet" target="_blank">Twitter</a></li>
                <li><a id="linkedin" href="https://www.linkedin.com/company/bridgeland-development-lp?trk=tabs_biz_home" target="_blank">LinkedIn</a></li>
                <li><a id="youtube" href="https://www.youtube.com/user/BridgelandTV" target="_blank">YouTube</a></li>
                <li><a id="instagram" href="http://instagram.com/bridgelandbalance" target="_blank">Instagram</a></li>
                <li><a id="googleplus" href="https://plus.google.com/+BridgelandCypress/posts" target="_blank">Google+</a></li>
                <li><a id="pinterest" href="https://www.pinterest.com/bridgelandpins/" target="_blank">Pinterest</a></li>
                <li><a id="maps" href="https://www.google.com/maps/place/Bridgeland,+TX+77433/@29.9496537,-95.7352924,16z/data=!3m1!4b1!4m2!3m1!1s0x86412a06400268f9:0x02cdbdea23e7194c" target="_blank">Google Maps</a></li>
            </ul>
        </div>
	</div>

	<div id="footer-wrapper" class="clearfix">

		<?php get_sidebar( 'footer' ); ?>

	</div><!-- end #footer-wrapper -->

	<?php responsive_footer_bottom(); ?>
</div><!-- end #footer -->
<?php responsive_footer_after(); ?>

<?php wp_footer(); ?>
</body>
</html>