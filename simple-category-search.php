<?php
$simple_category_search = SimpleCategorySearch::get_instance();
$simple_category_search->init();

class SimpleCategorySearch {
	static $instance;

	const SHORT_CODE = 'category-search';
	const SELECT_CLASS = 'categories_search';
	const RESULT_CLASS = 'search_result';

	private function __construct() {}

	public static function get_instance() {
		if( !isset( self::$instance ) ) {
			$c = __CLASS__;
			self::$instance = new $c();
		}
		return self::$instance;
	}

	public function init() {
		add_shortcode(self::SHORT_CODE, array($this, 'shortcode_handler'));

		add_action('wp_print_scripts', array($this, 'add_scripts'));
		add_action('wp_footer', array($this, 'add_footer'));
		add_action('init', array($this, 'wp_init'));
	}

	//**************************************************************************************
	// shortcode handler
	//**************************************************************************************
	public function shortcode_handler($atts, $content = null){
		extract( shortcode_atts( array(
			'parent' => 0 ,
			), $atts) );
		$parent = (int)$parent;
		$categories = $this->get_child_categories($parent);

		$output  = '<div class="'.self::SELECT_CLASS.'">'."\n";
		if (count($categories) > 0) {
			$classname = 'parentcategory-' . ($parent === 0 ? 'none' : $parent);
			$output .= "<select id=\"category-{$parent}\" class=\"{$classname}\">";
			$output .= '<option value="" selected></option>';
			foreach ($categories as $category) {
				$output .= "<option value=\"{$category['term_id']}\">{$category['name']}</option>";
			}
			$output .= '</select>'."\n";
		}
		$output .= '</div>'."\n";
		$output .= '<div class="'.self::RESULT_CLASS.'">'."\n";
		$output .= '<button>'.__('Search')."</button>\n";
		$output .= '</div>'."\n";

		return $output;
	}

	//**************************************************************************************
	// Add Scripts
	//**************************************************************************************
	public function add_scripts() {
		if (!$this->has_shortcode(self::SHORT_CODE))
			return;
		wp_enqueue_script('jquery');
	}

	//**************************************************************************************
	// Add footer
	//**************************************************************************************
	public function add_footer() {
		if (!$this->has_shortcode(self::SHORT_CODE))
			return;

		$site_url = site_url();
		$img = '<img src="%1$s" class="%2$s" style="float:left;width:16p;height:16px;" />';
		$loading_img = sprintf($img, admin_url('images/wpspin_light.gif'), 'loading');

		$output  = '<script type="text/javascript">//<![CDATA[' . "\n";
		$output .= '(function($){$(document).ready(function() {';
		$output .= 'var s=$("div.'.self::SELECT_CLASS.'");';
		$output .= 'var r=$("div.'.self::RESULT_CLASS.'");';

		$output .= 'var sc=function(){var a="parent"+this.id;var b=$(this).val();$("select."+a,s).fadeOut("fast",function(){$(this).remove()});$("ul",r).fadeOut("fast",function(){$(this).remove()});a+=" "+this.className;if(b!==""){var c={type:"json",parentcategory:b};$("button",r).css("visibility","hidden");s.append(\''.$loading_img.'\');$.ajax({url:"'.$site_url.'",cache:false,type:"GET",dataType:"json",data:c,success:function(c){$("img.loading",s).remove();$("button",r).css("visibility","visible");if(c.count>0){var d=$(\'<select id="category-\'+b+\'" class="\'+a+\'"></select>\').hide();d.append($(\'<option value="" selected></option>\'));$.each(c.result,function(){d.append($(\'<option value="\'+this["term_id"]+\'">\'+this["name"]+"</option>"))});d.change(sc);s.append(d.fadeIn())}},error:function(){alert("error")}})}};';
		$output .= '$("select",s).change(sc);';
		$output .= '$("button",r).click(function(){var a="";jQuery("select",s).each(function(){if(jQuery(this).val()!=="")a=jQuery(this).val()});$("ul",r).fadeOut("fast",function(){$(this).remove()});if(a!==""){var b={type:"json",category:a};$("button",r).css("visibility","hidden");r.append(\''.$loading_img.'\');$.ajax({url:"'.$site_url.'",cache:false,type:"GET",dataType:"json",data:b,success:function(b){$("img.loading",r).remove();$("button",r).css("visibility","visible");if(b.count>0){var c=$(\'<ul id="category-\'+a+\'" style="clear:both;"></ul>\').hide();$.each(b.result,function(){c.append($(\'<li><a href="\'+this["permalink"]+\'" title="\'+this["title"]+\'">\'+this["title"]+"</a></li>"))});r.append(c.fadeIn())}},error:function(){alert("error")}})}});';

		$output .= '});})(jQuery);' . "\n";
		$output .= '//]]></script>' . "\n";

		echo $output;
	}

	//**************************************************************************************
	// wp init
	//**************************************************************************************
	public function wp_init() {
		if (! (isset($_GET['type']) && $_GET['type'] === 'json') )
			return;

		$result = FALSE;

		if (isset($_GET['parentcategory'])) {
			$result = array();
			$parent = (int)$_GET['parentcategory'];
			$categories = $this->get_child_categories($parent);
			$result = array(
				'count' => count($categories) ,
				'result' => $categories ,
				);

		} else if (isset($_GET['category'])) {
			$result = array();
			$category_id = (int)$_GET['category'];
			$posts = array();
			query_posts('&posts_per_page=-1&cat='.$category_id);
			while (have_posts()) {
				the_post();
				$posts[] = array(
					'permalink' => get_permalink() ,
					'title' => get_the_title() ,
					);
			}
			wp_reset_query();
			$result = array(
				'count' => count($posts) ,
				'result' => $posts ,
				);
		}

		if ($result !== FALSE) {
			header('Content-Type: application/json; charset='. get_option('charset'));
			echo json_encode($result);
			exit;
		}
	}

	//**************************************************************************************
	// Utility
	//**************************************************************************************

	// has shortcode ?
	private function has_shortcode($shortcode) {
		global $wp_query;
		static $has_shortcode = array();

		if ( isset($has_shortcode[$shortcode]) )
			return (bool)$has_shortcode[$shortcode];

		$posts   = (array) $wp_query->posts;
		$pattern = '/\[' . preg_quote($shortcode, '/') . '[^\]]*\]/im';
		$found   = FALSE;
		$has_teaser = !( is_single() || is_page() );

		foreach($posts as $post) {
			if (isset($post->post_content)) {
				$post_content = $post->post_content;
				if ( $has_teaser && preg_match('/<!--more(.*?)?-->/', $post_content, $matches) ) {
					$content = explode($matches[0], $post_content, 2);
					$post_content = $content[0];
				}

				if ( !empty($post_content) && preg_match($pattern, $post_content) ) {
					$found = TRUE;
				}
			}
			if ( $found )
				break;
		}
		unset($posts);

		$has_shortcode[$shortcode] = $found;

		return $found;
	}

	// get child categories
	private function get_child_categories($parent = 0) {
		$categories = get_categories();
		$parent = (int)$parent;
		$child_categories = array();
		foreach ($categories as $category) {
			if ((int)$category->parent === $parent) {
				$child_categories[] = array(
					'term_id' => $category->term_id ,
					'name' => $category->name ,
					'link' => get_category_link($category->term_id) ,
					'parent' => $category->parent ,
					);
			}
		}
		return $child_categories;
	}
}
