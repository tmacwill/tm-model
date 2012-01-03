<?php

class Posts extends CI_Controller {
	public function __construct() {
		parent::__construct();

		$this->load->model('Post');
		$this->load->model('Thing');
		$this->load->model('User');
	}

	public function index() {
	}
}
