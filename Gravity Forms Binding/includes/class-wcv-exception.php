<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCV_Exception extends Exception {

	/** @var string sanitized error code */
	protected $error_code;

	public function __construct( $error_code, $error_message, $http_status_code ) {
		$this->error_code = $error_code;
		parent::__construct( $error_message, $http_status_code );
	}

	public function getErrorCode() {
		return $this->error_code;
	}
}
