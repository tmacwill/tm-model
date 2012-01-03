<?php 

class TM_Model extends CI_Model {
	// DB info
	protected $name;
	protected $table;

	// associations
	protected $belongs_to = array();
	protected $has_one = array();
	protected $has_many = array();

	public function __construct() {
		parent::__construct();

		// table name is by default the plural of class name
		$this->name = strtolower(get_class($this));
		$this->table = $this->name . 's';
	}

	/**
	 * Lazy-load associations
	 *
	 */
	public function __get($v) {
		// check if property already exists
		if (isset($this->{$v}))
			return parent::__get($this->{$v});

		// construct model names from property
		$belongs_to_model = ucfirst($v);
		$has_one_model = ucfirst($v);
		$has_many_model = ucfirst(substr($v, 0, strlen($v) - 1));

		// check if property is an association
		$belongs_to = in_array($belongs_to_model, $this->belongs_to);
		$has_one = in_array($has_one_model, $this->has_one);
		$has_many = in_array($has_many_model, $this->has_many);

		if ($belongs_to) {
			// instantiate associated model
			$model = new $belongs_to_model;

			// get value and save for future use
			$value = $model->get($this->{"{$v}_id"});
			$this->{$v} = $value;
			return $value;
		}

		else if ($has_many || $has_one) {
			// instantiate associated model
			$model = ($has_many) ? new $has_many_model : new $has_one_model;

			// get value and save for future use
			$key = strtolower(get_class($this)) . '_id';
			$value = $model->get_by(array($key => $this->id));
	
			// convert array to scalar for has_one
			if ($has_one && is_array($value) && !empty($value))
				$value = array_shift($value);

			$this->{$v} = $value;
			return $value;
		}

		return parent::__get($v);
	}

	/**
	 * Add a new object
	 *
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
	 *
	 * @param $data Array of objects or associative arrays representing DB rows
	 * @return False if unsuccessful
	 *
	 */
	public function add_batch($data) {
		return $this->db->insert_batch($this->table, $data);
	}

	/**
	 * Delete an object
	 *
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
	 * Delete a set of objects by a set of criteria
	 *
	 * @param $query Associative array mapping column names to values
	 *
	 */
	public function delete_by($query) {
		// determine which query parameters are arrays
		$where_ins = array();
		foreach ($query as $field => $value) {
			if (is_array($value)) {
				// move field from = to IN
				$where_ins[$field] = $value;
				unset($query[$field]);
			}
		}

		// delete rows from database
		$q = $this->db->where($query);
		foreach ($where_ins as $k => $v)
			$q->where_in($k, $v);
		$q->delete($this->table);
	}

	/**
	 * Get a single object
	 *
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
	 * Get the objects associated with this object
	 *
	 * @param $associations Optional list of associations to fetch. If not given, all are fetched
	 *
	 */
	public function get_associated() {
		// convert relationships to associative arrays for faster access
		$belongs_to_assoc = array();
		$has_one_assoc = array();
		$has_many_assoc = array();

		foreach ($this->belongs_to as $v)
			$belongs_to_assoc[$v] = true;
		foreach ($this->has_one as $v)
			$has_one_assoc[$v] = true;
		foreach ($this->has_many as $v)
			$has_many_assoc[$v] = true;

		$args = func_get_args();

		// no argument, so get all associations
		if (empty($args))
			$fetch = array_merge($this->belongs_to, $this->has_one, $this->has_many);

		// array argument, so use that list
		else if (is_array($args[0]))
			$fetch = $args[0];

		// string argument, so construct array
		else
			$fetch = array($args[0]);

		// load each association
		foreach ($fetch as $association) {
			if (isset($belongs_to_assoc[$association]) || isset($has_one_assoc[$association])) {
				$key = strtolower($association);
				$value = $this->{$key};
			}

			else if (isset($has_many_assoc[$association])) {
				$key = strtolower($association) . 's';
				$value = $this->{$key};
			}
		}

		// allow method chaining
		return $this;
	}

	/**
	 * Get a set of objects by a set of criteria
	 *
	 * @param $query Associative array mapping column names to values
	 * @param $eager Associations to fetch eagerly
	 * @param $key Field to use as the key in the returned associative array
	 * @return Associative array keyed on $key
	 *
	 */
	public function get_by($query, $eager = array(), $group_key = 'id') {
		// convert relationships to associative arrays for faster access
		$belongs_to_assoc = array();
		$has_one_assoc = array();
		$has_many_assoc = array();

		foreach ($this->belongs_to as $v)
			$belongs_to_assoc[$v] = true;
		foreach ($this->has_one as $v)
			$has_one_assoc[$v] = true;
		foreach ($this->has_many as $v)
			$has_many_assoc[$v] = true;

		// determine which query parameters are arrays
		$where_ins = array();
		foreach ($query as $field => $value) {
			if (is_array($value)) {
				// move field from = to IN
				$where_ins[$field] = $value;
				unset($query[$field]);
			}
		}

		// get rows from database
		$type = get_class($this);
		$q = $this->db->where($query);
		foreach ($where_ins as $k => $v)
			$q->where_in($k, $v);
		$objects = $q->get($this->table)->result($type);

		// load all objects associated with all returned objects
		$associations = array();
		foreach ($eager as $association) {
			// fetch objects associated with this object
			$model = new $association;

			// belongs_to relation
			if (isset($belongs_to_assoc[$association])) {
				$key = strtolower($association) . '_id';
				$key_ids = array_map(function ($e) use ($key) { return $e->{$key}; }, $objects);
				$associations[$association] = $model->get_by(array('id' => $key_ids));
			}

			// has_many relation
			else if (isset($has_many_assoc[$association]) || isset($has_one_assoc[$association])) {
				$key = strtolower(get_class($this)) . '_id';
				$object_ids = array_map(function ($e) { return $e->id; }, $objects);
				$associations[$association] = $model->get_by(array($key => $object_ids), array(), $key);
			}
		}

		// associate objects with fetched objects
		foreach ($associations as $association => $associated_objects) {
			// make association with each object
			foreach ($objects as &$object) {
				// determine key for object 
				$object_key = strtolower($association) . ((isset($has_many_assoc[$association])) ? 's' : '');

				// determine key for associated object
				if (isset($belongs_to_assoc[$association]))
					$association_key = strtolower($association) . '_id';
				else if (isset($has_many_assoc[$association]) || isset($has_one_assoc[$association]))
					$association_key = 'id';

				// make association
				$object->{$object_key} = isset($associated_objects[$object->{$association_key}]) ? 
					$associated_objects[$object->{$association_key}] : null;

				// make sure value is an array for has_many
				if (isset($has_many_assoc[$association]) && !is_array($object->{$object_key}))
					$object->{$object_key} = array($object->{$object_key});

				// make sure value is an object for has_one
				if (isset($has_one_asssoc[$association]) && isset($object->{$object_key}) && 
						is_array($object->{$object_key}) && !empty($object->{$object_key}))
					$object->{$object_key} = array_shift($object->{$object_key});
			}
		}

		// convert unordered array to array keyed on given argument
		$result = array();
		foreach ($objects as &$object)
			$result[$object->{$group_key}][] = $object;

		// flatten array values that only have one child
		foreach ($result as $k => $v)
			if (count($v) == 1)
				$result[$k] = $result[$k][0];

		return $result;
	}

	/**
	 * Save the current object to the database
	 * Object will be added if no ID is given, else updated by ID
	 * Can be called via $o->save() or $this->Model->save($o)
	 *
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
	 *
	 * @param $id ID of row to update
	 * @param $data New values for row
	 * @return False if unsuccessful
	 *
	 */
	public function update($id, $data) {
		return $this->db->where('id', $id)->update($this->table, $data);
	}
}
