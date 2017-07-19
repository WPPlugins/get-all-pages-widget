<?php
/*
Plugin Name: Get All Sites Widget
Plugin URI: http://kanedo.net/projekte/recent-comments-across-sites
Description: A widget to display all pages across all sites of a multi site wordpress installation
Author: Gabriel Bretschner
Version: 1.2.1
Author URI: http://kanedo.net

SVN-Version: $id$
*/


class SiteWidget extends WP_Widget {
	public $tree = array();
	public $htmltree = "";

  static function isValidEnviroment(){
    if(!is_multisite()){
      return false;
    }
    return true;
  }
	
	function SiteWidget(){
		$widget_ops = array('classname' => 'widgets_pages_across_all_sites', 'description' => 'Display pages from all sites' );
		$this->WP_Widget('page_site', 'Site Widget', $widget_ops);
	}
	
	function widget($args, $instance) {
			extract($args, EXTR_SKIP);
			$pages = $this->getPages();

			foreach($pages as $page){
				if(!array_key_exists($page->blog_id, $this->tree)){
					$this->tree[$page->blog_id] = array();
				}
				if($page->post_parent == "0"){
					$this->tree[$page->blog_id][$page->ID] = array();
					$this->tree[$page->blog_id][$page->ID]['post_title'] = $page->post_title;
					$this->tree[$page->blog_id][$page->ID]['blog_id'] = $page->blog_id; 
				}else{
					if(array_key_exists($page->post_parent, $this->tree[$page->blog_id])){
						if(!array_key_exists("children", $this->tree[$page->blog_id][$page->post_parent])){
							$this->tree[$page->blog_id][$page->post_parent]['children'] = array();
						}
						if(!array_key_exists($page->blog_id, $this->tree[$page->blog_id][$page->post_parent]['children'])){
								$this->tree[$page->blog_id][$page->post_parent]['children'][$page->blog_id] = array();
						}
						$this->tree[$page->blog_id][$page->post_parent]['children'][$page->blog_id][$page->ID] = array();
						$this->tree[$page->blog_id][$page->post_parent]['children'][$page->blog_id][$page->ID]['post_title'] = $page->post_title;
						$this->tree[$page->blog_id][$page->post_parent]['children'][$page->blog_id][$page->ID]['blog_id'] = $page->blog_id;
					}
				}
			}
			echo($before_widget);
			echo $before_title.$instance['title'].$after_title;
			$this->echoHTMLTree($instance['showblog']);	
			echo $after_widget;
	}

  function getPages(){
    global $wpdb;
    $selects = array();
    $sites = array();
    if(function_exists('wp_get_sites')){
      $sites = wp_get_sites();
    }
    foreach($sites as $blog){
      $selects[] = $this->getPagesForBlogId($blog['blog_id']);
    }
    return $wpdb->get_results(implode(" UNION ALL ", $selects)."ORDER BY blog_id,post_parent");
  }

  /**
   * build a query to get all pages for blogid
   * @return string prepared SQL Statement
   */
  function getPagesForBlogId( $id ){
    global $wpdb;
    if($id == 1){
      $pre = "";
    }else{
      $pre = $id."_";
    }
    return $wpdb->prepare("(SELECT ID, post_title,post_parent, %d as blog_id FROM {$wpdb->base_prefix}{$pre}posts WHERE post_status = 'publish' AND post_type='page')", $id);
  }
	
	function echoHTMLTree($sblog){
		echo $this->generateHTMLTree($this->tree, $sblog);
	}
	
	function generateHTMLTree($branches, $sblog){
		$tree = "";
		$tree .= "<ul>";
		foreach($branches as $bid => $bcontent ){
			foreach($bcontent as $id => $content){
				$tree .= "<li>";
				$blogdetails = get_blog_details( $bid, true);
				$_title = "";
				if($sblog){
					$_title .= $blogdetails->blogname.": ";
				}
				$_title .= $content['post_title'];
				$tree .= "<a href=\"".get_blog_permalink($bid, $id)."\" title=\"".$_title."\">".$content['post_title']."</a>";
				if(is_array($content)){
					if(array_key_exists("children", $content)){
						$tree .= $this->generateHTMLTree($content['children'], $sblog);
					}
				}
				$tree .= "</li>";
			}
	
		}
		$tree .= "</ul>";
		return $tree;
	}
	
	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['showblog'] = ($new_instance['showblog'] == "on")? true : false;
		return $instance;
	}
	function form($instance) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'showblog' => ''));
		$title = strip_tags($instance['title']);
		$showblog = strip_tags($instance['showblog']);
		?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>">
				<?php echo _e('Title', 'get-all-comments-widget');?>: 
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
			</label>
		</p>
<p>
	<input class="checkbox" type="checkbox" <?php checked( (bool) $instance['showblog'], true ); ?> id="<?php echo $this->get_field_id( 'showblog' ); ?>" name="<?php echo $this->get_field_name( 'showblog' ); ?>" />
	<label for="<?php echo $this->get_field_id( 'showblog' ); ?>"><?php _e('Show blogname in linktitle'); ?></label>
</p>

		<p><small><?php echo _e('Visit', 'get-all-comments-widget'); ?>:&nbsp;<a href="http://kanedo.net?pk_campaign=Plugin&pk_keyword=get-all-pages-widget">kanedo.net</a></small></p>
		<?php
	}
}
function register_SiteWidget(){
  if(!SiteWidget::isValidEnviroment()){
    echo "<div class='error'><p><strong>Get All Sites Widget</strong>: your site need to be a multisite installation in order for the plugin to work</p></div>";
    return;
  }
  register_widget('SiteWidget');
}
add_action('init', 'register_SiteWidget', 1);