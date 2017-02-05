<?php
/*
  Plugin Name: WC Vendors - Gravity Forms Binding
  Plugin URI: 
  Description: Allows you to use Gravity Forms on WC Vendors. Requires the Gravity Forms plugin to work. Requires WooCommerce 2.3 or higher
  Version: 2.10.6
  Author: MarkKing
  Author URI: 
  Developer: Mark King
  Developer URI: 
  Requires at least: 3.1
  Tested up to: 4.4.1

  License: GNU General Public License v3.0
  License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Required functions
 */
if ( ! class_exists( 'WC_Dependencies' ) )
  require_once( 'includes/class-wc-dependencies.php' );
  //require_once( 'includes/class-wcv-exception.php' );

/**
 * WC Detection
 */
if ( ! function_exists( 'is_woocommerce_active' ) ) {
  function is_woocommerce_active() {
    return WC_Dependencies::woocommerce_active_check();
  }
}

if ( is_woocommerce_active() ) {
  load_plugin_textdomain( 'wcv_gf_binding', null, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
  include 'compatibility.php';

  class WCV_Gravityforms {
    var $gravity_products = array();
    var $rendered_form = false;

    public function __construct() {
      add_action( 'wc_prd_vendor_options', array($this, 'load_settings') );
      add_action( 'wcv_save_product_meta', array($this, 'save_gravity_form') );
      //add_action( 'woocommerce_product_meta_start', array($this, 'get_product_meta'), 10, 2 );
    }

    public static function product_category( $post_id ) {

      include_once( 'wcvendors-gravityform-form.php' );

      $display_options = (array) WC_Vendors::$pv_options->get_option( 'wcvgf_display_options' );
      $gravityform_id = WC_Vendors::$pv_options->get_option( 'wcvgf_product_category' );

      if(isset( $gravityform_id ) && is_numeric( $gravityform_id ) ) {  
        $gravity_form_data = RGFormsModel::get_form_meta( $gravityform_id );

        $product_form = new WCV_Gravityform_Form( $gravityform_id, $post_id, 'category' );
        $product_form->get_form( $gravity_form_data, $display_options );
        $product_form->set_category_values();
      }
    }

    public static function product_meta( $post_id ) {

      include_once( 'wcvendors-gravityform-form.php' );

      $display_options = (array) WC_Vendors::$pv_options->get_option( 'wcvgf_display_options' );
      $gravityform_id = WC_Vendors::$pv_options->get_option( 'wcvgf_product_meta' );

      if(isset( $gravityform_id ) && is_numeric( $gravityform_id ) ) {  
        $gravity_form_data = RGFormsModel::get_form_meta( $gravityform_id );

        $product_form = new WCV_Gravityform_Form( $gravityform_id, $post_id, 'meta' );
        $product_form->get_form( $gravity_form_data, $display_options );
        $product_form->set_input_values();
      }
    }

    public function get_product_meta( $post_id ) {
      $post_metas = get_post_meta( $post_id, 'wcv_custom_meta');

      if(!empty( $post_metas ) ) {
        foreach ( $post_metas[0] as $key => $value ) {
          echo '<p><strong>'.$value[0].':</strong> '.$value[1].'</p>';
        }
      }
    }

    public function save_gravity_form( $post_id ) {
      // Save Categories
      $post_cats = array_intersect_key( $_POST, array_flip(preg_grep('/^category_/', array_keys( $_POST ) ) ) );
      
      if ( !empty( $post_cats ) ) {
        $taxonomy_name = 'product_cat';
        $parent = $this->get_parent_cat();
        $product_cat = array();
        $cat_meta = array();

        foreach ( $post_cats as $key => $value ) {
          if( !empty( $value ) ) {
            //Check if Category exists
            $cat_term = term_exists( $value, $taxonomy_name, $parent);
            if ($cat_term === 0 || $cat_term === null) {
              // ADD Category
              $data = array(
                'name' => $value,
                'parent' => $parent
              );
              $cat_term = $this->create_product_category($data);
            } else {
              $cat_term = $this->get_product_category( $cat_term['term_id'] );
            }
            $product_cat[ ] = $cat_term['id'];
            $parent = $cat_term['id'];
            $cat_meta[$key] = $value;
          }
        }

        $categories = array_map( 'intval', $product_cat ); 
        $categories = array_unique( $categories ); 
        wp_set_post_terms( $post_id, $categories, $taxonomy_name );
        update_post_meta( $post_id, 'categories', $cat_meta ); 
      }

      // Save Meta Data
      $custom_metas = array_intersect_key( $_POST, array_flip(preg_grep('/^meta_/', array_keys( $_POST ) ) ) );
      $gravityform_id = WC_Vendors::$pv_options->get_option( 'wcvgf_product_meta' );

      if ( !empty( $custom_metas ) && isset( $gravityform_id ) && is_numeric( $gravityform_id ) ) {
        $gravity_form_data = GFAPI::get_form( $gravityform_id );
        $sanitized_data = array();
        $tags = array();

        foreach ( $custom_metas as $key => $value ) {
          if( !empty( $value ) ) {
            $field_title;
            $fid = filter_var($key, FILTER_SANITIZE_NUMBER_INT);
            foreach ($gravity_form_data['fields'] as $field) {
              if( $field->id == $fid) {
                $field_title = $field->label;
              }
            }
            $sanitized_data[$key] = array( $field_title, $value);

            // Add meta to tags
            $existing_tag = get_term( $value, 'product_tag' ); 
            if ( $existing_tag != null ) { 
              $tags[] = $existing_tag->slug; 
            } else if( strlen($value) > 2) { 
              $tags[] = $value; 
            }
          }
        }

        $tags = array_unique( $tags ); 
        $tags = implode( ',', $tags ); 

        wp_set_post_terms( $post_id, $tags, 'product_tag' );
        update_post_meta( $post_id, 'wcv_custom_meta', $sanitized_data ); 
      }
    }

    public function get_product_categories() {
      $product_categories = array();

      $terms = get_terms( 'product_cat', array( 'hide_empty' => false, 'fields' => 'ids' ) );
      foreach ( $terms as $term_id ) {
        $product_categories[] = WCV_Gravityforms::get_product_category( $term_id );
      }

      return $product_categories;
    }

    public function get_product_category( $id ) {

      $id = absint( $id );
      $term = get_term( $id, 'product_cat' );
      $term_id = intval( $term->term_id );

      $product_category = array(
        'id'          => $term_id,
        'name'        => $term->name,
        'slug'        => $term->slug,
        'parent'      => $term->parent,
        'count'       => intval( $term->count )
      );
      return $product_category;
    }

    public function create_product_category( $data ) {
      global $wpdb;

      $defaults = array(
        'name'        => '',
        'slug'        => '',
        'description' => '',
        'parent'      => 0,
        'display'     => 'default',
        'image'       => '',
      );

      $data = wp_parse_args( $data, $defaults );
      $data = apply_filters( 'woocommerce_api_create_product_category_data', $data, $this );

      // Check parent.
      $data['parent'] = absint( $data['parent'] );
      if ( $data['parent'] ) {
        $parent = get_term_by( 'id', $data['parent'], 'product_cat' );
        if ( ! $parent ) {
          return __( 'Product category parent is invalid', 'woocommerce' );
        }
      }

      $insert = wp_insert_term( $data['name'], 'product_cat', $data );
      if ( is_wp_error( $insert ) ) {
        return $insert->get_error_message();
      }

      $id = $insert['term_id'];

      do_action( 'woocommerce_api_create_product_category', $id, $data );

      return $this->get_product_category( $id );
    }

    public function get_parent_cat() {
      $product_cats = array();
      $terms = get_terms( 'product_cat', array( 'hide_empty' => false, 'parent' => 0, 'number' => 1 ) );
      return $terms[0]->term_id;
    }

    public function load_settings( $options ) {
      $select_options = array('NULL' => 'None');

      foreach ( RGFormsModel::get_forms() as $form ) {
        $select_options[esc_attr( $form->id )] = __( wptexturize( $form->title ), 'wcvendors-pro' );
      }

      $options[ ] = array( 'name' => __( 'Gravity Forms', 'wcvendors-pro' ), 'type' => 'heading' );
      $options[ ] = array( 
        'name' => __( 'Gravity Forms Binding', 'wcvendors-pro' ), 
        'type' => 'title', 
        'desc' => __( 'These options allow you to add a Gravity Forms to your Product Page', 'wcvendors-pro' ) );
      $options[ ] = array(
        'name'     => __( 'Display Options', 'wcvendors-pro' ),
        'id'       => 'wcvgf_display_options',
        'options'  => array(
          'wcvgf_display_title'       => __( 'Display Title', 'wcvendors-pro' ),
          'wcvgf_display_description' => __( 'Display Description', 'wcvendors-pro' ),
        ),
        'type'     => 'checkbox',
        'multiple' => true,
      );
      $options[ ] = array(
        'name'     => __( 'Product Categories Form', 'wcvendors-pro' ),
        'desc'     => __( 'Choose your gravity form.', 'wcvendors-pro' ),
        'id'       => 'wcvgf_product_category',
        'type'     => 'select',
        'options'  => $select_options, 
        'std'      => 'select'
      );
      $options[ ] = array(
        'name'     => __( 'Product Meta Form', 'wcvendors-pro' ),
        'desc'     => __( 'Choose your gravity form.', 'wcvendors-pro' ),
        'id'       => 'wcvgf_product_meta',
        'type'     => 'select',
        'options'  => $select_options, 
        'std'      => 'select'
      );
      return $options;
    }
  }

  $wcv_gravityforms = new WCV_Gravityforms();
}