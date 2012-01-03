<?php 

class TM_Model extends CI_Model {
	// DB info
	protected $name;
	protected $table;

	// associations
	protected $has_one = array();
	protected $has_many = array();

	public function __construct() {
		parent::__construct();

		// table name is by default the plural of class name
		$this->name = strtolower(get_class($this));
		$this->table = $this->name . 's';
	}

	public function __call($method, $args) {
		// get_by_column function
		if (preg_match('/^get_by_([\w_]+)/', $method, $matches)) {
			// break up function name and remove get_by
			$fields = explode('_', $matches[0]);
			array_shift($fields);
			array_shift($fields);

			// set up query
			$is_field = true;
			$query = array();
			$counter = 0;

			// build query array from 
			foreach ($fields as $field) {
				// every other field will be a conjunction
				if ($is_field) {
					$query[$field] = $args[$counter];
					$counter++;
				}

				// alternate fields and conjunctions
				$is_field = !$is_field;
			}

			return $this->get_by($query);
		}
	}

	public function __get($v) {
		// check if property already exists
		if (isset($this->{$v}))
			return parent::__get($this->{$v});

		// check if property is an associated object
		$has_one_model = ucfirst($v);
		$has_many_model = ucfirst(substr($v, 0, strlen($v) - 1));
		$has_one = in_array($has_one_model, $this->has_one);
		$has_many = in_array($has_many_model, $this->has_many);

		if ($has_one) {
			// instantiate associated model
			$model = new $has_on_model;

			// get value and save for future use
			$value = $model->get($this->{"{$v}_id"});
			$this->{$v} = $value;
			return $value;
		}

		if ($has_many) {
			// instantiate associated model
			$model = new $has_many_model;

			// get value and save for future use
			$key = strtolower(get_class($this)) . '_id';
			$value = $model->get_by(array($key => $this->id));
			$this->{$v} = $value;
			return $value;
		}

		return parent::__get($v);
	}

	/**
	 * Add a new object
	 * @param $data Either an object or associative array representing a single DB row
	 * @return ID of inserted row
	 *
	 */
	public function add($data) {
		if ($this->db->insert($this->table, $data))
			return $this->db->insert_id();
		else
			return false;
	}

	/**
	 * Add a new set of objects
	 * @param $data Array of objects or associative arrays representing DB rows
	 * @return False if unsuccessful
	 *
	 */
	public function add_batch($data) {
		return $this->db->insert_batch($this->table, $data);
	}

	/**
	 * Delete an object
	 * Can be called via $o->delete() or $this->Model->delete(1)
	 * @param $id ID of object to delete
	 * 
	 */
	public function delete() {
		$args = func_get_args();

		// no argument, so delete current object
		if (empty($args))
			return $this->db->where('id', $this->id)->delete($this->table);

		// argument given, so delete that object
		return $this->db->where('id', $args[0])->delete($this->table);
	}

	/**
	 * Get a single object
	 * @param $id ID of object to get
	 * @return Object representing DB row
	 *
	 */
	public function get($id, $fetch_related = true) {
		// row doesn't take class name as an argument :(
		$objects = $this->db->limit(1)->get_where($this->table, array('id' => $id))->result(get_class($this));
		return (isset($objects[0])) ? $objects[0] : false;
	}

	/**
	 * Get a set of objects by a set of criteria
	 * @param $query Associative array mapping column names to values
	 * @param $key Field to use as the key in the returned associative array
	 * @return Associative array keyed on $key
	 *
	 */
	// TODO: BULK FETCH ASSOCIATIONS
	public function get_by($query, $key = 'id') {
		// get rows from database
		$type = get_class($this);
		$objects = $this->db->get_where($this->table, $query)->result($type);

		// convert unordered array to array keyed on given argument
		$result = array();
		foreach ($objects as $object) {
			// determine key for result array
			$k = $object->{$key};

			// if value is already set, then convert to an array
			if (isset($result[$k]))
				$result[$k] = array($result[$k]);

			// value is an array, so push to it
			if (isset($result[$k]) && is_array($result[$k]))
				$result[$k][] = $object;
			// value is not an array, so set it
			else
				$result[$object->{$key}] = $object;
		}

		return $result;
	}

	/**
	 * Save the current object to the database
	 * Object will be added if no ID is given, else updated by ID
	 * @return False if unsuccessful
	 *
	 */
	public function save() {
		$args = func_get_args();
		
		// no argument, so save current object
		if (empty($args))
			$o = $this;
		// argument given, so save that object
		else
			$o = $args[0];

		// ID is set, so update existing object
		if (isset($o->id))
			return $this->update($o->id, $o);

		// ID is not set, so create new object
		return $this->add($o);
	}

	/**
	 * Update an existing DB row
	 * @param $id ID of row to update
	 * @param $data New values for row
	 * @return False if unsuccessful
	 *
	 */
	public function update($id, $data) {
		return $this->db->where('id', $id)->update($this->table, $data);
	}
}
