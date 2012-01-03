<?php

require_once('../tm_model.php');

class User extends TM_Model {
	var $has_many = array('Post');

	public function __construct() {
		parent::__construct();
	}
}
