<?php
/*
Plugin Name: TextPattern Importer
Plugin URI: http://wordpress.org/extend/plugins/textpattern-importer/
Description: Import categories, users, posts, comments, and links from a TextPattern blog.
Author: wordpressdotorg
Author URI: http://wordpress.org/
Version: 0.3.1
Stable tag: 0.3.1
License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}

if(!function_exists('get_comment_count'))
{
	/**
	 * Get the comment count for posts.
	 *
	 * @package WordPress
	 * @subpackage Textpattern_Import
	 *
	 * @param int $post_ID Post ID
	 * @return int
	 */
	function get_comment_count($post_ID)
	{
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare("SELECT count(*) FROM $wpdb->comments WHERE comment_post_ID = %d", $post_ID) );
	}
}

if(!function_exists('link_exists'))
{
	/**
	 * Check whether link already exists.
	 *
	 * @package WordPress
	 * @subpackage Textpattern_Import
	 *
	 * @param string $linkname
	 * @return int
	 */
	function link_exists($linkname)
	{
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare("SELECT link_id FROM $wpdb->links WHERE link_name = %s", $linkname) );
	}
}

/**
 * TextPattern Importer
 *
 * @package WordPress
 * @subpackage Importer
 */
if ( class_exists( 'WP_Importer' ) ) {
class Textpattern_Import extends WP_Importer {

	function header()
	{
		echo '<div class="wrap">';

		if ( version_compare( get_bloginfo( 'version' ), '3.8.0', '<' ) ) {
			screen_icon();
		}

		echo '<h2>'.__('Import Textpattern', 'textpattern-importer').'</h2>';
		echo '<p>'.__('Steps may take a few minutes depending on the size of your database. Please be patient.', 'textpattern-importer').'</p>';
	}

	function footer()
	{
		echo '</div>';
	}

	function greet() {
		echo '<div class="narrow">';
		echo '<p>'.__('Howdy! This imports categories, users, posts, comments, and links from any Textpattern >= 4.0.2 and <= 4.8.8 into this site.', 'textpattern-importer').'</p>';
		echo '<p>'.__('This has not been tested on previous versions of Textpattern.  Mileage may vary.', 'textpattern-importer').'</p>';
		echo '<p>'.__('Your Textpattern Configuration settings are as follows:', 'textpattern-importer').'</p>';
		echo '<form action="admin.php?import=textpattern&amp;step=1" method="post">';
		wp_nonce_field('import-textpattern');
		$this->db_form();
		echo '<p class="submit"><input type="submit" name="submit" class="button" value="'.esc_attr__('Import', 'textpattern-importer').'" /></p>';
		echo '</form>';
		echo '</div>';
	}

	function get_txp_cats()
	{
		global $wpdb;
		// General Housekeeping
		$txpdb = new wpdb(get_option('txpuser'), get_option('txppass'), get_option('txpname'), get_option('txphost'));
		$prefix = get_option('tpre');

		// Get Categories
		return $txpdb->get_results('SELECT
			id,
			name,
			title
			FROM '.$prefix.'txp_category
			WHERE type = "article"',
			ARRAY_A);
	}

	function get_txp_users()
	{
		global $wpdb;
		// General Housekeeping
		$txpdb = new wpdb(get_option('txpuser'), get_option('txppass'), get_option('txpname'), get_option('txphost'));
		$prefix = get_option('tpre');

		// Get Users

		return $txpdb->get_results('SELECT
			user_id,
			name,
			RealName,
			email,
			privs
			FROM '.$prefix.'txp_users', ARRAY_A);
	}

	function get_txp_posts()
	{
		// General Housekeeping
		$txpdb = new wpdb(get_option('txpuser'), get_option('txppass'), get_option('txpname'), get_option('txphost'));
		$prefix = get_option('tpre');

		// Get Posts
		return $txpdb->get_results('SELECT
			ID,
			Posted,
			AuthorID,
			LastMod,
			Title,
			Body,
			Excerpt,
			Category1,
			Category2,
			Status,
			Keywords,
			url_title,
			comments_count
			FROM '.$prefix.'textpattern
			', ARRAY_A);
	}

	function get_txp_comments()
	{
		global $wpdb;
		// General Housekeeping
		$txpdb = new wpdb(get_option('txpuser'), get_option('txppass'), get_option('txpname'), get_option('txphost'));
		$prefix = get_option('tpre');

		// Get Comments
		return $txpdb->get_results('SELECT * FROM '.$prefix.'txp_discuss', ARRAY_A);
	}

	function get_txp_links()
	{
		//General Housekeeping
		$txpdb = new wpdb(get_option('txpuser'), get_option('txppass'), get_option('txpname'), get_option('txphost'));
		$prefix = get_option('tpre');

		return $txpdb->get_results('SELECT
			id,
			date,
			category,
			url,
			linkname,
			description
			FROM '.$prefix.'txp_link',
			ARRAY_A);
	}

	function cat2wp($categories='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$txpcat2wpcat = array();
		// Do the Magic
		if(is_array($categories))
		{
			echo '<p>'.__('Importing Categories...', 'textpattern-importer').'<br /><br /></p>';
			foreach ($categories as $category)
			{
				$count++;
				extract($category);


				// Make Nice Variables
				$name = esc_sql($name);
				$title = esc_sql($title);

				if($cinfo = category_exists($name))
				{
					$ret_id = wp_insert_category(array('cat_ID' => $cinfo, 'category_nicename' => $name, 'cat_name' => $title));
				}
				else
				{
					$ret_id = wp_insert_category(array('category_nicename' => $name, 'cat_name' => $title));
				}
				$txpcat2wpcat[$id] = $ret_id;
			}

			// Store category translation for future use
			add_option('txpcat2wpcat',$txpcat2wpcat);
			echo '<p>'.sprintf(_n('Done! <strong>%1$s</strong> category imported.', 'Done! <strong>%1$s</strong> categories imported.', $count, 'textpattern-importer'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Categories to Import!', 'textpattern-importer');
		return false;
	}

	function users2wp($users='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$txpid2wpid = array();

		// Midnight Mojo
		if(is_array($users))
		{
			echo '<p>'.__('Importing Users...', 'textpattern-importer').'<br /><br /></p>';
			foreach($users as $user)
			{
				$count++;
				extract($user);

				// Make Nice Variables
				$name = esc_sql($name);
				$RealName = esc_sql($RealName);

				if ( $uinfo = get_user_by( 'login', $name ) ) {

					$ret_id = wp_insert_user(array(
								'ID'			=> $uinfo->ID,
								'user_login'	=> $name,
								'user_nicename'	=> $RealName,
								'user_email'	=> $email,
								'user_url'		=> 'http://',
								'display_name'	=> $name)
								);
				} else {
					$ret_id = wp_insert_user(array(
								'user_login'	=> $name,
								'user_pass'     => 'password123',
								'user_nicename'	=> $RealName,
								'user_email'	=> $email,
								'user_url'		=> 'http://',
								'display_name'	=> $name)
								);
				}

				$txpid2wpid[$user_id] = $ret_id;

				// Set Textpattern-to-WordPress permissions translation
				$transperms = array(1 => '10', 2 => '9', 3 => '5', 4 => '4', 5 => '3', 6 => '2', 7 => '0');

				// Update Usermeta Data
				$user = new WP_User($ret_id);
				if('10' == $transperms[$privs]) { $user->set_role('administrator'); }
				if('9'  == $transperms[$privs]) { $user->set_role('editor'); }
				if('5'  == $transperms[$privs]) { $user->set_role('editor'); }
				if('4'  == $transperms[$privs]) { $user->set_role('author'); }
				if('3'  == $transperms[$privs]) { $user->set_role('contributor'); }
				if('2'  == $transperms[$privs]) { $user->set_role('contributor'); }
				if('0'  == $transperms[$privs]) { $user->set_role('subscriber'); }

				update_user_meta( $ret_id, 'wp_user_level', $transperms[$privs] );
				update_user_meta( $ret_id, 'rich_editing', 'false');
			}// End foreach($users as $user)

			// Store id translation array for future use
			add_option('txpid2wpid',$txpid2wpid);


			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> users imported.', 'textpattern-importer'), $count).'<br /><br /></p>';
			return true;
		}// End if(is_array($users)

		echo __('No Users to Import!', 'textpattern-importer');
		return false;

	}// End function user2wp()

	function posts2wp($posts='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$txpposts2wpposts = array();
		$cats = array();

		// Do the Magic
		if(is_array($posts))
		{
			echo '<p>'.__('Importing Posts...', 'textpattern-importer').'<br /><br /></p>';
			foreach($posts as $post)
			{
				$count++;
				extract($post);

				// Set Textpattern-to-WordPress status translation
				$stattrans = array(1 => 'draft', 2 => 'private', 3 => 'draft', 4 => 'publish', 5 => 'publish');

				$uinfo = get_user_by( 'login', $AuthorID );

				if ( ! $uinfo ) {
					$uinfo = 1;
				}

				$authorid = ( is_object( $uinfo ) ) ? $uinfo->ID : $uinfo;

				$Title = esc_sql( $Title );
				$Body = esc_sql( $Body );
				$Excerpt = esc_sql( $Excerpt );
				$post_status = $stattrans[$Status];

				// Import Post data into WordPress

				if($pinfo = post_exists($Title,$Body))
				{
					$ret_id = wp_insert_post(array(
						'ID'				=> $pinfo,
						'post_date'			=> $Posted,
						'post_author'		=> $authorid,
						'post_modified'		=> $LastMod,
						'post_title'		=> $Title,
						'post_content'		=> $Body,
						'post_excerpt'		=> $Excerpt,
						'post_status'		=> $post_status,
						'post_name'			=> $url_title,
						'comment_count'		=> $comments_count)
						);
					if ( is_wp_error( $ret_id ) )
						return $ret_id;
				}
				else
				{
					$ret_id = wp_insert_post(array(
						'post_date'			=> $Posted,
						'post_author'		=> $authorid,
						'post_modified'		=> $LastMod,
						'post_title'		=> $Title,
						'post_content'		=> $Body,
						'post_excerpt'		=> $Excerpt,
						'post_status'		=> $post_status,
						'post_name'			=> $url_title,
						'comment_count'		=> $comments_count)
						);
					if ( is_wp_error( $ret_id ) )
						return $ret_id;
				}
				$txpposts2wpposts[$ID] = $ret_id;
				// Make Post-to-Category associations
				$cats = array();

				if ( ! empty( $Category1 ) ) {
					$category2 = get_category_by_slug( $Category1 );

					if ( ! empty( $category1 ) ) {
						$cats[] = $category1->term_id;
					}
				}

				if ( ! empty( $Category2 ) ) {
					$category2 = get_category_by_slug( $Category2 );

					if ( ! empty( $category2 ) ) {
						$cats[] = $category2->term_id;
					}
				}

				if ( ! empty( $cats ) ) {
					wp_set_post_categories ( $ret_id, $cats );
				}
			}
		}
		// Store ID translation for later use
		add_option('txpposts2wpposts',$txpposts2wpposts);

		echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> posts imported.', 'textpattern-importer'), $count).'<br /><br /></p>';
		return true;
	}

	function comments2wp($comments='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$txpcm2wpcm = array();
		$postarr = get_option('txpposts2wpposts');

		// Magic Mojo
		if(is_array($comments))
		{
			echo '<p>'.__('Importing Comments...', 'textpattern-importer').'<br /><br /></p>';
			foreach($comments as $comment)
			{
				$count++;
				extract($comment);

				// WordPressify Data
				$comment_ID = ltrim($discussid, '0');
				$comment_post_ID = $postarr[$parentid];
				$comment_approved = (1 == $visible) ? 1 : 0;
				$name = esc_sql($name);
				$email = esc_sql($email);
				$web = esc_sql($web);
				$message = esc_sql($message);

				$comment = array(
							'comment_post_ID'	=> $comment_post_ID,
							'comment_author'	=> $name,
							'comment_author_IP'		=> '',
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_date'		=> $posted,
							'comment_content'	=> $message,
							'comment_approved'	=> $comment_approved);
				$comment = wp_filter_comment($comment);

				if ( $cinfo = comment_exists($name, $posted) ) {
					// Update comments
					$comment['comment_ID'] = $cinfo;
					$ret_id = wp_update_comment($comment);
				} else {
					// Insert comments
					$ret_id = wp_insert_comment($comment);
				}
				$txpcm2wpcm[$comment_ID] = $ret_id;
			}
			// Store Comment ID translation for future use
			add_option('txpcm2wpcm', $txpcm2wpcm);

			// Associate newly formed categories with posts
			get_comment_count($ret_id);


			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> comments imported.', 'textpattern-importer'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Comments to Import!', 'textpattern-importer');
		return false;
	}

	function links2wp($links='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;

		// Deal with the links
		if(is_array($links))
		{
			echo '<p>'.__('Importing Links...', 'textpattern-importer').'<br /><br /></p>';
			foreach($links as $link)
			{
				$count++;
				extract($link);

				// Make nice vars
				$category = esc_sql($category);
				$linkname = esc_sql($linkname);
				$description = esc_sql($description);
				$linfo = link_exists($linkname);

				if ( isset( $linfo ) ) {
					$ret_id = wp_insert_link(array(
								'link_id'			=> $linfo,
								'link_url'			=> $url,
								'link_name'			=> $linkname,
								'link_category'		=> $category,
								'link_description'	=> $description,
								'link_updated'		=> $date)
								);
				} else {
					$ret_id = wp_insert_link(array(
								'link_url'			=> $url,
								'link_name'			=> $linkname,
								'link_category'		=> $category,
								'link_description'	=> $description,
								'link_updated'		=> $date)
								);
				}
				$txplinks2wplinks[$ret_id] = $ret_id;
			}
			add_option('txplinks2wplinks',$txplinks2wplinks);
			echo '<p>';
			printf(_n('Done! <strong>%s</strong> link imported', 'Done! <strong>%s</strong> links imported', $count, 'textpattern-importer'), $count);
			echo '<br /><br /></p>';
			return true;
		}
		echo __('No Links to Import!', 'textpattern-importer');
		return false;
	}

	function import_categories()
	{
		// Category Import
		$cats = $this->get_txp_cats();
		$this->cat2wp($cats);
		add_option('txp_cats', $cats);



		echo '<form action="admin.php?import=textpattern&amp;step=2" method="post">';
		wp_nonce_field('import-textpattern');
		printf('<p class="submit"><input type="submit" name="submit" class="button" value="%s" /></p>', esc_attr__('Import Users', 'textpattern-importer'));
		echo '</form>';

	}

	function import_users()
	{
		// User Import
		$users = $this->get_txp_users();
		$this->users2wp($users);

		echo '<form action="admin.php?import=textpattern&amp;step=3" method="post">';
		wp_nonce_field('import-textpattern');
		printf('<p class="submit"><input type="submit" name="submit" class="button" value="%s" /></p>', esc_attr__('Import Posts', 'textpattern-importer'));
		echo '</form>';
	}

	function import_posts()
	{
		// Post Import
		$posts = $this->get_txp_posts();
		$result = $this->posts2wp($posts);
		if ( is_wp_error( $result ) )
			return $result;

		echo '<form action="admin.php?import=textpattern&amp;step=4" method="post">';
		wp_nonce_field('import-textpattern');
		printf('<p class="submit"><input type="submit" name="submit" class="button" value="%s" /></p>', esc_attr__('Import Comments', 'textpattern-importer'));
		echo '</form>';
	}

	function import_comments()
	{
		// Comment Import
		$comments = $this->get_txp_comments();
		$this->comments2wp($comments);

		echo '<form action="admin.php?import=textpattern&amp;step=5" method="post">';
		wp_nonce_field('import-textpattern');
		printf('<p class="submit"><input type="submit" name="submit" class="button" value="%s" /></p>', esc_attr__('Import Links', 'textpattern-importer'));
		echo '</form>';
	}

	function import_links()
	{
		//Link Import
		$links = $this->get_txp_links();
		$this->links2wp($links);
		add_option('txp_links', $links);

		echo '<form action="admin.php?import=textpattern&amp;step=6" method="post">';
		wp_nonce_field('import-textpattern');
		printf('<p class="submit"><input type="submit" name="submit" class="button" value="%s" /></p>', esc_attr__('Finish', 'textpattern-importer'));
		echo '</form>';
	}

	function cleanup_txpimport()
	{
		delete_option('tpre');
		delete_option('txp_cats');
		delete_option('txpid2wpid');
		delete_option('txpcat2wpcat');
		delete_option('txpposts2wpposts');
		delete_option('txpcm2wpcm');
		delete_option('txplinks2wplinks');
		delete_option('txpuser');
		delete_option('txppass');
		delete_option('txpname');
		delete_option('txphost');
		do_action('import_done', 'textpattern');
		$this->tips();
	}

	function tips()
	{
		echo '<p>'.__('Welcome to WordPress.  We hope (and expect!) that you will find this platform incredibly rewarding!  As a new WordPress user coming from Textpattern, there are some things that we would like to point out.  Hopefully, they will help your transition go as smoothly as possible.', 'textpattern-importer').'</p>';
		echo '<h3>'.__('Users', 'textpattern-importer').'</h3>';
		echo '<p>'.sprintf(__('You have already setup WordPress and have been assigned an administrative login and password.  Forget it.  You didn&#8217;t have that login in Textpattern, why should you have it here?  Instead we have taken care to import all of your users into our system.  Unfortunately there is one downside.  Because both WordPress and Textpattern uses a strong encryption hash with passwords, it is impossible to decrypt it and we are forced to assign temporary passwords to all your users.  <strong>Every user has the same username, but their passwords are reset to password123.</strong>  So <a href="%1$s">log in</a> and change it.', 'textpattern-importer'), get_bloginfo( 'wpurl' ) . '/wp-login.php').'</p>';
		echo '<h3>'.__('Preserving Authors', 'textpattern-importer').'</h3>';
		echo '<p>'.__('Secondly, we have attempted to preserve post authors.  If you are the only author or contributor to your blog, then you are safe.  In most cases, we are successful in this preservation endeavor.  However, if we cannot ascertain the name of the writer due to discrepancies between database tables, we assign it to you, the administrative user.', 'textpattern-importer').'</p>';
		echo '<h3>'.__('Textile', 'textpattern-importer').'</h3>';
		echo '<p>'.__('Also, since you&#8217;re coming from Textpattern, you probably have been using Textile to format your comments and posts.  If this is the case, we recommend downloading and installing <a href="http://www.huddledmasses.org/category/development/wordpress/textile/">Textile for WordPress</a>.  Trust me... You&#8217;ll want it.', 'textpattern-importer').'</p>';
		echo '<h3>'.__('WordPress Resources', 'textpattern-importer').'</h3>';
		echo '<p>'.__('Finally, there are numerous WordPress resources around the internet.  Some of them are:', 'textpattern-importer').'</p>';
		echo '<ul>';
		echo '<li>'.__('<a href="http://wordpress.org/">The official WordPress site</a>', 'textpattern-importer').'</li>';
		echo '<li>'.__('<a href="http://wordpress.org/support/">The WordPress support forums</a>', 'textpattern-importer').'</li>';
		echo '<li>'.__('<a href="http://codex.wordpress.org/">The Codex (In other words, the WordPress Bible)</a>', 'textpattern-importer').'</li>';
		echo '</ul>';
		echo '<p>'.sprintf(__('That&#8217;s it! What are you waiting for? Go <a href="%1$s">log in</a>!', 'textpattern-importer'), get_bloginfo( 'wpurl' ) . '/wp-login.php').'</p>';
	}

	function db_form()
	{
		echo '<table class="form-table">';
		printf('<tr><th scope="row"><label for="dbuser">%s</label></th><td><input type="text" name="dbuser" id="dbuser" /></td></tr>', __('Textpattern Database User:', 'textpattern-importer'));
		printf('<tr><th scope="row"><label for="dbpass">%s</label></th><td><input type="password" name="dbpass" id="dbpass" /></td></tr>', __('Textpattern Database Password:', 'textpattern-importer'));
		printf('<tr><th scope="row"><label for="dbname">%s</label></th><td><input type="text" id="dbname" name="dbname" /></td></tr>', __('Textpattern Database Name:', 'textpattern-importer'));
		printf('<tr><th scope="row"><label for="dbhost">%s</label></th><td><input type="text" id="dbhost" name="dbhost" value="localhost" /></td></tr>', __('Textpattern Database Host:', 'textpattern-importer'));
		printf('<tr><th scope="row"><label for="dbprefix">%s</label></th><td><input type="text" name="dbprefix" id="dbprefix"  /></td></tr>', __('Textpattern Table prefix (if any):', 'textpattern-importer'));
		echo '</table>';
	}

	function dispatch()
	{

		if (empty ($_GET['step']))
			$step = 0;
		else
			$step = (int) $_GET['step'];
		$this->header();

		if ( $step > 0 )
		{
			check_admin_referer('import-textpattern');

			if ( ! empty( $_POST['dbuser'] ) ) {
				if(get_option('txpuser'))
					delete_option('txpuser');
				add_option('txpuser', sanitize_user( $_POST['dbuser'], true));
			}

			if ( ! empty( $_POST['dbpass'] ) ) {
				if(get_option('txppass'))
					delete_option('txppass');
				add_option('txppass',  sanitize_user($_POST['dbpass'], true));
			}

			if ( ! empty( $_POST['dbname'] ) ) {
				if(get_option('txpname'))
					delete_option('txpname');
				add_option('txpname',  sanitize_user($_POST['dbname'], true));
			}

			if ( ! empty( $_POST['dbhost'] ) ) {
				if(get_option('txphost'))
					delete_option('txphost');
				add_option('txphost',  sanitize_user($_POST['dbhost'], true));
			}

			if ( ! empty( $_POST['dbprefix'] ) ) {
				if(get_option('tpre'))
					delete_option('tpre');
				add_option('tpre',  sanitize_user($_POST['dbprefix']));
			}
		}

		switch ($step)
		{
			default:
			case 0 :
				$this->greet();
				break;
			case 1 :
				$this->import_categories();
				break;
			case 2 :
				$this->import_users();
				break;
			case 3 :
				$result = $this->import_posts();
				if ( is_wp_error( $result ) )
					echo $result->get_error_message();
				break;
			case 4 :
				$this->import_comments();
				break;
			case 5 :
				$this->import_links();
				break;
			case 6 :
				$this->cleanup_txpimport();
				break;
		}

		$this->footer();
	}
}

$txp_import = new Textpattern_Import();

register_importer('textpattern', __('TextPattern', 'textpattern-importer'), __('Import categories, users, posts, comments, and links from a TextPattern blog.', 'textpattern-importer'), array ($txp_import, 'dispatch'));

} // class_exists( 'WP_Importer' )

function textpattern_importer_init() {
    load_plugin_textdomain( 'textpattern-importer', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'textpattern_importer_init' );
