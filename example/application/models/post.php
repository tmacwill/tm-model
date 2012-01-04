<?php

require_once('../tm_model.php');

class Post extends TM_Model {
	var $belongs_to = array('User');
	var $has_many = array('Comment');

	public function __construct() {
		parent::__construct();
	}
}
