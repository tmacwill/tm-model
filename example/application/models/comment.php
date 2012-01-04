<?php

require_once('../tm_model.php');

class Comment extends TM_Model {
	var $belongs_to = array('Post');

	public function __construct() {
		parent::__construct();
	}
}
