<?php

require_once('../tm_model.php');

class Post extends TM_Model {
	var $belongs_to = array('User');

	public function __construct() {
		parent::__construct();
	}
}
