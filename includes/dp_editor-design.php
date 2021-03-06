<?php
/** * * * * * * * * * * * * * * * * * * * *
 *
 *	Department Editor Custom Creation
 *	
 *	1. Include Styles
 *	2. Admin Bar
 *	3. Advanced Edit Form
 *	4. Edit Table Lists
 *	5. Custom Course Message
 *	6. Tabs
 *	7. Helper Functions
 *
 * 	CSUN Department of Undergraduate Studies
 * 	2013-2014
 *
 * * * * * * * * * * * * * * * * * * * * * */


/* * * * * * * * * * * * * * * * * * * * * *
 *
 *  Including the styles and js
 *
 * * * * * * * * * * * * * * * * * * * * * */
/**
 * Include custom styles for editor
 * Hooks onto admin_enqueue_scripts action.
 */
function add_dp_editor_style() {
	$basedir = dirname(plugin_dir_url(__FILE__));
	wp_enqueue_style('dp-editor-style', $basedir . '/css/dp-editor-style.css');
}
add_action('admin_enqueue_scripts', 'add_dp_editor_style');
add_action('wp_enqueue_scripts', 'add_dp_editor_style');


/* * * * * * * * * * * * * * * * * * * * * *
 *
 *  Editing the adminbar
 *
 * * * * * * * * * * * * * * * * * * * * * */
/**
 * Remove admin bar links, add link to review page (editor home)
 * Hooks onto admin_bar_menu action.
 *
 * @param WP_Admin_Bar $wp_admin_bar Wordpress admin bar
 */
function add_csun_admin_bar_links( $wp_admin_bar ) {
	//add link to the department editor home page
	$args = array(
			'id' => 'csun_dashboard_link',
			'title' => '<span class="ab-icon"></span>
		<span id="ab-csun-dashboard" class="ab-label">Home</span>',
			'href' => admin_url('admin.php?page=review'),
			);
	$wp_admin_bar->add_node( $args );	//add dashboard link
	

	$cat = get_query_var( 'department_shortname', false );
	if($cat)
	{
		$edit_allowed = true;
		
		if(is_singular('departments') || is_singular('programs') || is_singular('courses') ) 
		{
			$id = get_the_ID();
			$type = rtrim(ucwords(get_post_type()), "s");
		}
		elseif(is_post_type_archive( 'departments' ))
		{
			$id = get_department($cat);
			$type = 'Department';
		}
	
		if(isset($id) && $edit_allowed)
		{
			$args = array(
				'id' => 'csun_edit_link',
				'title' => '<span class="ab-icon"></span>
			<span id="ab-csun-edit" class="ab-label">Edit '.$type.'</span>',
				'href' => admin_url('post.php?action=edit&post='.$id.'&department_shortname='.$cat),
				);
			$wp_admin_bar->add_node( $args );	//add edit link
		}
	}
	
	//remove all the other links
	$wp_admin_bar->remove_node( 'comments' );
	$wp_admin_bar->remove_node( 'new-content' );
	$wp_admin_bar->remove_node( 'wp-logo' );
	$wp_admin_bar->remove_node( 'site-name' );
	$wp_admin_bar->remove_node( 'edit-profile' );
	$wp_admin_bar->remove_node( 'user-info' );
}
add_action( 'admin_bar_menu', 'add_csun_admin_bar_links', 999 );

/**
 * Add secondary bar for navigation in the department
 * Hooks onto in_admin_header action
 */
function add_csun_admin_bar() {
	//if the category is in the url, use it (files&course list)
	if(isset($_REQUEST['department_shortname'])){
		$cat = $_REQUEST['department_shortname'];
	}
	elseif(get_query_var( 'department_shortname', false ))
	{
		$cat = get_query_var( 'department_shortname' );
	}
	//otherwise, figure out category from post (courses, programs, departments)
	elseif(isset($_REQUEST['post'])){
		//get all departments relating to the post
		$terms =  wp_get_post_terms( $_REQUEST['post'], 'department_shortname' );
		
		foreach($terms as $term){
			//ge and top level terms can't be the category
			if($term->slug !== 'ge' && $term->parent != 0 && $term->parent != 511) {
				//save the slug of the category that works
				$cat = $term->slug;
			}
		}
		
		if(!isset($cat)){	//if it only has a top level category
			foreach($terms as $term){
				if($term->slug !== 'ge') {
					//save the slug of the category that works
					$cat = $term->slug;
				}
			}
		}
	}

	//if we have a category, build the bar
	if(isset($cat)) : 
		$term_id = term_exists( $cat );	//get term id from slug

		//Cleaned up term description holding department name
		$dp_name = term_description( $term_id, 'department_shortname' );
		$dp_name = strip_tags($dp_name);		//remove p tags
		$dp_name = trim(preg_replace('/\s\s+/', ' ', $dp_name));	//remove newline character

		//make li active for current page
		$uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : NULL ;	//get uri
		$type = isset($_GET['post']) ? get_post_type( ($_GET['post'])) : NULL ;	//get post type

		if($uri AND $type === 'departments')
		{
			$page = 'overview';
		}
		else if ($uri AND $type === 'programs'){
			$page = 'program';
		}
		else if ($uri AND (strpos($uri,'courses')||$type === 'courses')) {
			$page = 'course';
		}
		else if ($uri AND strpos($uri,'proposals')) {
			$page = 'file';
		}
		else if ($uri AND strpos($uri,'faculty')) {
			$page = 'faculty';
		}
		else{
			$page = 'unknown';
		}

		//figure out which post is active first for programs/departments (will link to that tab)
		$department_id = get_department($cat);
		$program_id = get_first_program($cat);
?>
	<div id="csun-bar" role="naviagation">
	<div class="quicklinks" id="csun-toolbar" role="navigation" aria-label="Second navigation toolbar." tabindex="0">
		<ul id="csun-dept-bar" class="ab-second-menu">
			<li id="department-name"><?php echo $dp_name.' : '; ?></li>
			<li id="csun-overeview-link" <?php if($page === 'overview') echo 'class="active"'; ?>>
				<a class="ab-item" href="<?php echo admin_url().'post.php?action=edit&post='.$department_id.'&department_shortname='.$cat;?>">
					<span class="ab-icon dashicons dashicons-welcome-write-blog"></span>
					<span id="ab-csun-overview" class="ab-label">Overview</span>
				</a>		
			</li>
			<li id="csun-program-link" <?php if($page === 'program') echo 'class="active"'; ?>>
				<a class="ab-item" href="<?php echo admin_url().'post.php?action=edit&post='.$program_id.'&department_shortname='.$cat;?>">
					<span class="ab-icon dashicons dashicons-welcome-learn-more"></span>
					<span id="ab-csun-programs" class="ab-label">Programs</span>
				</a>		
			</li>
			<li id="csun-course-link" <?php if($page === 'course') echo 'class="active"'; ?>>
				<a class="ab-item" href="<?php echo admin_url().'edit.php?post_type=courses&amp;department_shortname='.$cat.'&amp;orderby=title&amp;order=asc';?>">
					<span class="ab-icon"></span>
					<span id="ab-csun-courses" class="ab-label">Courses</span>
				</a>		
			</li>
			<li id="csun-faculty-link" <?php if($page === 'faculty') echo 'class="active"'; ?>>
				<a class="ab-item" href="<?php echo site_url().'/academics/'.$cat.'/faculty/'; ?>">
					<span class="ab-icon dashicons dashicons-groups"></span>
					<span id="ab-csun-files" class="ab-label">Faculty</span>
				</a>		
			</li>
			<li id="csun-file-link" <?php if($page === 'file') echo 'class="active"'; ?>>
				<a class="ab-item" href="<?php echo admin_url().'admin.php?page=proposals&amp;department_shortname='.$cat; ?>">
					<span class="ab-icon"></span>
					<span id="ab-csun-files" class="ab-label">Files</span>
				</a>		
			</li>	
		</ul><!-- /csun-dept-bar-->		
	</div><!-- /quicklins-->
	</div><!-- /csun-bar-->
<?php
	endif; //end isset($cat)
}
add_action( 'in_admin_header', 'add_csun_admin_bar');
add_action( 'wp_after_admin_bar_render', 'add_csun_admin_bar');

/* * * * * * * * * * * * * * * * * * * * * *
 *
 *  Editing the advanced edit form
 *
 * * * * * * * * * * * * * * * * * * * * * */
 
/**
 * Remove post meta-boxes
 * Hooks onto admin_init action.
 */
function remove_meta_boxes() {
	remove_meta_box('formatdiv', 'post', 'normal');
	remove_meta_box('revisionsdiv', 'post', 'normal');
	remove_meta_box('tagsdiv-post_tag', 'post', 'normal');
	remove_meta_box('postimagediv', 'post', 'normal');
	remove_meta_box('department_shortnamediv', 'courses', 'side');
	remove_meta_box('general_educationdiv', 'courses', 'side');
	
}
add_action('admin_init', 'remove_meta_boxes');


/* * * * * * * * * * * * * * * * * * * * * *
 *
 *  Editing the edit list
 *
 * * * * * * * * * * * * * * * * * * * * * */

/**
 * Remove extra columns from course list table
 * Hooks onto manage_edit-courses_columns filter
 *
 * @param array $defaults Default course column list
 *
 * @return array	Simplified course column list
 */
function simplify_course_columns($defaults) {
  unset($defaults['cb']);
  unset($defaults['author']);
  unset($defaults['date']);
  unset($defaults['department']);
  unset($defaults['ge']);
  return $defaults;
}
add_filter('manage_edit-courses_columns', 'simplify_course_columns');

/**
 * Remove quick edit links
 * Hooks onto post_row_actions filter.
 *
 * @param array $actions	List of available actions
 *
 * @return array	Updated list of available actions
 */
function remove_quick_edit( $actions ) {
	unset($actions['inline hide-if-no-js']);
	return $actions;
}
add_filter('post_row_actions','remove_quick_edit',10,1);

/* * * * * * * * * * * * * * * * * * * * * *
 *
 *	Custom Messages
 *
 * * * * * * * * * * * * * * * * * * * * * */

/**
 * Adds custom messages where we need to use JavaScript
 * Courses page and above default WordPress boxes
 * Hooks onto admin_footer action
 */
function editor_admin_footer()
{
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : NULL ;

    $message = NULL;

	//Only edit page they can get to is courses, so assume edit.php = courses page
    if ($uri AND strpos($uri,'edit.php'))
    {
        $message = get_option( 'main_dp_settings');	//get message option
		$message = $message['course_message'];
		
	}

    if ($message)
    {
        ?><script>
            jQuery(function($)
            {
                $('<div id="course_message"></div><br />').text('<?php echo $message.$noedit; ?>').insertAfter('#wpbody-content .wrap h2:eq(0)');
            });
        </script><?php
		
    }
	
	//They can edit multiple posts though, so we have to figure out which one they're on
	if ($uri AND strpos($uri,'post.php'))
    {
		$post_id = $_GET['post'];
		$post_type = get_post_type( $post_id );
	
		if($post_type === 'programs')	//if its programs the default box is used for overview
			$description = '<label for="basic-box">Overview</label>Description of the program.';
		elseif($post_type === 'departments')	//if its departments the default box is used for misc
			$description = '<label for="basic-box">Misc</label>Department information that fits no where else (e.g. awards and scholarships).';
		
		if($description) {
		?><script>
			jQuery(function($) {
				//both placed are after the high acf fields
				$('<p class="label">' + '<?php echo $description; ?>' + '</p>').insertAfter('#acf_after_title-sortables')
			});
			</script><?php
		}
		
		if($post_type === 'courses') {
		
			//if user is not an adean, they cannot save changes on courses
			$user_ID = get_current_user_id();
			$user = get_userdata( $user_ID );
			if(in_array( 'dp_editor', (array) $user->roles) || in_array( 'dp_reviewer', (array) $user->roles )) {
				$noedit = '<h3><span class="dashicons dashicons-lock"></span>This content is read only. No changes will be saved. </h3>';
						
				?><script>
					jQuery(function($)
					{
						$('<?php echo $noedit; ?>').insertAfter('#lost-connection-notice');
					});
				</script>
				<style type="text/css">
					#postbox-container-1{display: none;}
				</style><?php
			}
		}
		
	}
	
} 
add_action('admin_footer', 'editor_admin_footer');

/* * * * * * * * * * * * * * * * * * * * * *
 *
 * Fake Tabs
 *
 * * * * * * * * * * * * * * * * * * * * * */
/**
 * Creates header on the programs page
 * Hooks onto all_admin_notices action.
 */
 function department_edit_tabs(){
	/* * * * * * * * * * * * * * * * * * * * * *
	 * Get posts for category
	 * * * * * * * * * * * * * * * * * * * * * */
	 $curr_post = $_GET['post'];
	 
	 $terms = wp_get_post_terms( $curr_post, 'department_shortname' );
	 
	 foreach($terms as $term) {
		if($term->parent != 0 && $term->parent != 511) {	//we only want the child terms and X Don't Use terms
			$post_cat = $term;
			break;
		}
	 }
	 
	 if(!isset($post_cat))		//but if we don't get anything from that
		foreach($terms as $term)
			$post_cat = $term;		//get whatever term we actually do have
	 
	 if( isset($post_cat)){
		$term_id = $post_cat->term_id;

		//get programs with that department code
		$args=array(
			'post_type' => 'programs',
			'department_shortname' => $post_cat->slug,
			'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ), 
			'numberposts' => 50,
		);
		$posts = get_posts( $args );
	}
		
	if( $posts ){
		
	/* * * * * * * * * * * * * * * * * * * * * *
	 * Build Tabs
	 * * * * * * * * * * * * * * * * * * * * * */

		$term = get_term($term_id, 'department_shortname');
		
		$message = get_option( 'main_dp_settings');	//get message option
		$message = $message['view_all_message'];
		//no edit message + remove the save meta box
		$noedit = '<h3><div class="dashicons dashicons-lock"></div>This content is read only. No changes will be saved. </h3>
					<style type="text/css">
						#postbox-container-1{display: none;}
					</style>';

		echo '<br />';
		echo '<h1>'.$term->description.'</h1>';	//department name
		echo '<p>'.$message.'</p>';
		
		//if user is not an adean, they cannot save changes
		$user_ID = get_current_user_id();
		$user = get_userdata( $user_ID );
		
		//editors and reviewers can't edit programs
		if(in_array( 'dp_editor', (array) $user->roles) || in_array( 'dp_reviewer', (array) $user->roles ))
		{
			echo $noedit;
		}
		

		//Create top tabs to switch between posts
		echo '<ul id="edit-tabs" class="nav nav-tabs">';
		foreach($posts as $post) {
			$post_ID = $post->ID;
			$post_type = $post->post_type;
			$post_name = $post->post_title;
			
			$post_option=get_field('option_title', $post_ID);

			echo '<li class="';
			
			if($post_ID == $curr_post)
				echo 'active ';
				
			if(isset($post_option)&&$post_option!=='')
				echo 'option" >';
			else
				echo 'nonoption" >';
			
			echo '<a href="'.get_edit_post_link( $post_ID ).'">'.$post_name;
			echo ', ';
			echo the_field('degree_type', $post_ID);
			if(isset($post_option)&&$post_option!==''){
				echo '<br />';
				echo '<span class="option">'.$post_option.'</span>';
			}
			echo '</a></li>';
		}	
		echo'</ul>';
	
	}
}
//only show on program edit pages
if( isset($_GET['post']) && ( get_post_type( $_GET['post'] ) === 'programs')){
	add_action( 'all_admin_notices' , 'department_edit_tabs');
}

/**
 * Creates the header on the department edit screen
 */
function overview_header()
{
	/* * * * * * * * * * * * * * * * * * * * * *
	 * Get category
	 * * * * * * * * * * * * * * * * * * * * * */
	 $curr_post = $_GET['post'];
	 
	 $terms = wp_get_post_terms( $curr_post, 'department_shortname' );
	 
	 foreach($terms as $term) {
		if($term->parent != 0 && $term->parent != 511) {	//we only want the child term
			$post_cat = $term;
			break;
		}
	 }
	 
	 if(!isset($post_cat))		//but if there is only a parent term
		foreach($terms as $term)
			$post_cat = $term;		//get the parent
		
	$message = get_option( 'main_dp_settings');	//get message option
	$message = $message['view_all_message'];
	//no edit message + remove the save meta box
	$noedit = '<h3><div class="dashicons dashicons-lock"></div>This content is read only. No changes will be saved. </h3>
				<style type="text/css">
					#postbox-container-1{display: none;}
				</style>';
				
	echo '<br />';
	echo '<h1>'.$post_cat->description.'</h1>';	//department name
	echo '<p>'.$message.'</p>';
	
	//if user is a reviewer, they cannot save changes
	$user_ID = get_current_user_id();
	$user = get_userdata( $user_ID );
	
	//reviewers can't edit departments
	if(in_array( 'dp_reviewer', (array) $user->roles ))
	{
		echo $noedit;
	}
}
//only show on program edit pages
if( isset($_GET['post']) && ( get_post_type( $_GET['post'] ) === 'departments')){
	add_action( 'all_admin_notices' , 'overview_header');
}
	
/* * * * * * * * * * * * * * * * * * * * * *
 *
 * Helper functions
 *
 * * * * * * * * * * * * * * * * * * * * * */
 
/**
 * Takes slug of term and returns id of the first department
 * 
 * @param string $term	The term which we need a post from
 * 
 * @return int 	ID of first post if successful, 0 if not
 */
function get_department($term) {
	$args=array(
		'post_type' => 'departments',
		'department_shortname' => $term,
		'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ), 
		'numberposts' => 1,
	);
	$departments = get_posts( $args );

	if($departments)
		return $departments[0]->ID;
		
	return 0;		
}

/**
 * Takes either slug of term and returns id of the first program
 * 
 * @param string $term	The term which we need a post from
 * 
 * @return int 	ID of first post if successful, 0 if not
 */
function get_first_program($term) {
	$args=array(
		'post_type' => 'programs',
		'department_shortname' => $term,
		'post_status' => array( 'publish', 'pending', 'draft', 'future', 'private' ), 
		'numberposts' => 1,
	);
	$programs = get_posts( $args );
	
	if($programs)
		return $programs[0]->ID;
		
	return 0;		
}

/**
 * Creates the edit link with the department shortname intact
 * Hooks onto get_edit_post_link filter
 * 
 * @param string $link		Original edit post link
 * @param int $post_ID		ID of post that this links to
 * @param string $context	Determines &amp vs &
 *
 * @return string	Modified edit link
 */
function department_edit_link($link, $post_ID, $context) {
	if(get_query_var( 'department_shortname', false ))
	{
		$_REQUEST['department_shortname'] = get_query_var( 'department_shortname' );
	}
	
	if(isset($_REQUEST['department_shortname'])){
		if ( 'display' == $context )
			$link = $link.'&amp;department_shortname='.$_REQUEST['department_shortname'];
		else
			$action = $link.'&department_shortname='.$_REQUEST['department_shortname'];
	}
		
	return $link;
}
add_filter('get_edit_post_link', 'department_edit_link', 10, 3);

?>