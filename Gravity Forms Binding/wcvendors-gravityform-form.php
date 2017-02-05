<?php
class WCV_Gravityform_Form {
	private $current_page;
	private $next_page;
	private $form_id = 0;
	private $post_id = 0;
	private $form_name;

	public function __construct($form_id, $post_id, $form_name) {
		$this->form_id = $form_id;
		$this->post_id = $post_id;
		$this->form_name = $form_name;

		add_filter('gform_form_tag', array(&$this, 'on_form_tag'), 10, 2);
		add_filter('gform_submit_button', array(&$this, 'on_submit_button'), 10, 2);
	}

	function get_form($options, $display_options) {
		extract(shortcode_atts(array(
		    'display_title' => true,
		    'display_description' => true,
		    'display_inactive' => false,
		    'field_values' => false,
		    'ajax' => false,
		    'tabindex' => 1,
		), $options));

		//Get the form meta so we can make sure the form exists.
		$form_meta = RGFormsModel::get_form_meta($this->form_id);
		if (!empty($form_meta)) {

			if (!empty($_POST)) {
				$_POST['gform_submit'] = isset($_POST['gform_old_submit']) ? $_POST['gform_old_submit'] : '';
				$_POST['gform_old_submit'] = $_POST['gform_submit'];
			}

			$form = RGForms::get_form($this->form_id, $display_options['wcvgf_display_title'], $display_options['wcvgf_display_description'], $display_inactive, $field_values, $ajax, $tabindex);
			
			//gfield_label
			unset($_POST['gform_submit']);
			$form = str_replace('</form>', '', $form);
			$form = str_replace('gfield_label', '', $form);
			$form = str_replace("name='input", "name='".$this->form_name, $form);
			$form = $this->delete_all_between("<div class='gform_footer top_label'>", '</div>', $form);
			$this->current_page = GFFormDisplay::get_current_page($this->form_id);
			$this->next_page = $this->current_page + 1;
			$this->previous_page = $this->current_page - 1;
			$this->next_page = $this->next_page > $this->get_max_page_number($form_meta) ? 0 : $this->next_page;

			echo $form;
			
			echo '<input type="hidden" name="gform_form_id" id="gform_form_id" value="' . $this->form_id . '" />';

			$description_class = rgar($form_meta, "descriptionPlacement") == "above" ? "description_above" : "description_below";
		}
	}

	function set_input_values() {
		$post_metas = get_post_meta( $this->post_id, 'wcv_custom_meta');
		if ( !empty( $post_metas ) ) {
			echo '<script>';
			foreach ( $post_metas[0] as $key => $value ) {
				echo 'document.getElementsByName("'.$key.'")[0].value="'.$value[1].'";';
			}
			echo '</script>';
		}
	}

	function set_category_values() {
		$post_metas = get_post_meta( $this->post_id, 'categories' );
		if ( !empty( $post_metas ) ) {
			echo '<script>';
			foreach ( $post_metas[0] as $key => $value ) {
				echo 'document.getElementsByName("'.$key.'")[0].value="'.$value.'";';
			}
			echo '</script>';
		}
	}

	function delete_all_between($beginning, $end, $string) {
	 	$beginningPos = strpos($string, $beginning);
	 	$endPos = strpos($string, $end, $beginningPos);
		if ($beginningPos === false || $endPos === false) {

	    	return $string;
	  	}

	  	$textToDelete = substr($string, $beginningPos, ($endPos + strlen($end)) - $beginningPos);

	  	return str_replace($textToDelete, '', $string);
	}

	// filter out the Gravity Form form tag so all we have are the fields
	function on_form_tag($form_tag, $form) {
		if ($form['id'] != $this->form_id) {
			return $form_tag;
		}
		return '';
	}

	// filter the Gravity Forms button type
	function on_submit_button($button, $form) {
		if ($form['id'] != $this->form_id) {
			return $button;
		}
		return '';
	}

	function on_print_scripts() {
		$garvityforms_params = array(
		    'form_id' => $this->form_id,
		    'next_page' => $this->next_page,
		    'previous_page' => $this->previous_page,
		);

		wp_localize_script('wc-gravityforms-product-addons', 'gravityforms_params', $garvityforms_params);
		?>
		<script>
			gravityforms_params = <?php echo json_encode($garvityforms_params); ?>;
		</script>
		<?php
	}

	private function get_max_page_number($form) {
		$page_number = 0;
		foreach ($form["fields"] as $field) {
			if ($field["type"] == "page") {
				$page_number++;
			}
		}
		return $page_number == 0 ? 0 : $page_number + 1;
	}

}
?>