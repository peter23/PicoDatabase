<?php

	$d = new PicoDatabase('localhost', 'root');

	$q = $d
		->select('f1', 'f2')->select('f3')->select(array('f4', 'f5'))->select('?@', 'f6')->select('?@', array('f7', 'f8'))
		->from('table1')->from('table2')
		->helloWorld()
		->leftJoin('table3')
		->on('a = ?_', 1)
		->on('b = ?*', 2)
		->where('x=?_ OR y=?* OR z LIKE \'??\' OR j LIKE ?_ OR k LIKE ?_', 1, 2, '%??%/\"/\%??%', '/\/\1\'1"1\/\/')
		->where('a IN (?_)', array(1, 2, 3))
		->where(array('x = ?_', 'y = ?_'), 10)
		->where(array(
			'a = ?_' => 11,
			'b = ?_' => 22
		))
		->where('?@ = ?@', 'n', 'm')
		->set('?_', array('first'=>1, 'second'=>2, 'third'=>3))
		->set('?_', array('first'=>1, 'second'=>2, 'third'=>3))
		->orderBy('f9')
		->limit(1, 2)
	;

	var_dump(strval($q));


	var_dump(strval($d->implodeSql('OR')->add('1')->add('2')));


	var_dump(strval($d->select('* FROM blabla where bla "')));



	class PicoDatabase extends mysqli {

		private $letters_replaces_w_spaces = array('A'=>' A','B'=>' B','C'=>' C','D'=>' D','E'=>' E','F'=>' F','G'=>' G','H'=>' H','I'=>' I','J'=>' J','K'=>' K','L'=>' L','M'=>' M','N'=>' N','O'=>' O','P'=>' P','Q'=>' Q','R'=>' R','S'=>' S','T'=>' T','U'=>' U','V'=>' V','W'=>' W','X'=>' X','Y'=>' Y','Z'=>' Z');


		public function __call($name, $arguments) {
			$_name = strtoupper(substr($name, 0, 6));
			if($_name === 'SELECT' || $_name === 'INSERT' || $_name === 'UPDATE' || $_name === 'DELETE' || strtoupper($name) === 'IMPLODESQL') {
				return new PicoDatabaseQuery($this, $name, $arguments);
			} else {
				throw new Exception('Call to undefined method PicoDatabase::'.$name.'()');
			}
		}


		public function __construct($server = null, $username = null, $password = null, $database = null, $encoding = null) {
			$_server = explode(':', $server);
			if(!isset($_server[1]) || !$_server[1] || $_server[0] === 'p') {
				parent::__construct($server, $username, $password, $database);
			} else {
				parent::__construct($_server[0], $username, $password, $database, $_server[1]);
			}

			if($this->connect_error) {
				throw new Exception('PicoDatabase.__construct: ('.$this->connect_errno.') '.$this->connect_error, $this->connect_errno);
			}

			if($encoding)  $this->set_charset($encoding);
		}


		public function __destruct() {
			$this->close();
		}


		public function buildQuery() {
			return new PicoDatabaseQuery($this);
		}


		public function escape($s) {
			if(!is_array($s)) {
				return '\''.$this->real_escape_string($s).'\'';

			} else {
				foreach($s as $s_key => &$s1) {
					if(is_int($s_key)) {
						$s1 = $this->escape($s1);
					} else {
						$s1 = $this->escapeFieldName($s_key).'='.$this->escape($s1);
					}
				}
				return implode(', ', $s);
			}
		}


		public function escapeFieldName($s) {
			if(!is_array($s)) {
				return '`'.str_replace('`', '``', $s).'`';

			} else {
				foreach($s as &$s1) {
					$s1 = $this->escapeFieldName($s1);
				}
				return implode(', ', $s);
			}
		}


		public function sqlOpsToUpper($s) {
			return trim(strtoupper(strtr($s, $this->letters_replaces_w_spaces)));
		}


		public function processPlaceHolders($s, $vals) {
			$s0 = $s;
			$s = explode('?', $s);
			$n = 0;
			$s_count = count($s);
			for($i = 1; $i < $s_count; $i++) {
				$c = substr($s[$i], 0, 1);
				switch($c) {
					case '_':
					case '*':
					case '@':
						if(!isset($vals[$n])) {
							throw new Exception('PicoDatabase.processPlaceHolders: incorrect number of arguments ('.serialize($s0).','.serialize($vals).')');
						}
						if($c === '_') {
							$vals[$n] = $this->escape($vals[$n]);
						} elseif($c === '@') {
							$vals[$n] = $this->escapeFieldName($vals[$n]);
						}
						$s[$i] = $vals[$n].substr($s[$i], 1);
						$n++;
						break;

					case '':
						$s[$i] = '?';
						$i++;
						break;

					default:
						throw new Exception('PicoDatabase.processPlaceHolders: incorrect placeholder ('.serialize($c).')');
						break;
				}
			}
			return implode('', $s);
		}

	}



	class PicoDatabaseQuery {

		public $parts = array();

		private $db;


		public function __call($name, $arguments) {
			//if first argument is not an array and does not contain placeholders
			if(count($arguments) > 0 && !is_array($arguments[0]) && strpos($arguments[0], '?') === false) {
				$arguments = array($arguments);
			}
			return $this->__call_($name, $arguments);
		}


		private function __call_($name, $arguments) {
			$arguments_count = count($arguments);

			//if first argument is an array
			if($arguments_count > 0 && is_array($arguments[0])) {
				//take that array
				$subcalls = array_shift($arguments);
				//and process each its element
				foreach($subcalls as $subcall_key => &$subcall) {
					if(!is_array($subcall)) $subcall = array($subcall);
					//if an element has string key
					if(is_string($subcall_key)) {
						//use that key as string with placeholders and the element as values
						$this->__call_($name, array_merge(array($subcall_key), $subcall));
					} else {
						//use element as string with placeholders and other argumants as values
						$this->__call_($name, array_merge($subcall, $arguments));
					}
				}

			} else {
				$name = $this->db->SqlOpsToUpper($name);

				//if there are more than one arguments
				if($arguments_count > 1) {
					//use first argument as string with placeholders
					$tmp_statement = array_shift($arguments);
					//and other arguments as values
					$arguments = array($this->db->processPlaceHolders($tmp_statement, $arguments));
				}

				$parts_count = count($this->parts) - 1;
				if($parts_count >= 0 && $this->parts[$parts_count][0] === $name) {
					$this->parts[$parts_count][1] = array_merge($this->parts[$parts_count][1], $arguments);
				} else {
					$this->parts[] = array($name, $arguments);
				}
			}

			return $this;
		}


		public function __construct(&$db, $call = null, $arguments = array()) {
			$this->db = $db;
			if(!is_null($call)) {
				$this->__call($call, $arguments);
			}
		}


		public function __toString() {
			$parts = $this->parts;
			foreach($parts as &$part) {
				$count_part1 = count($part[1]);
				if($count_part1 > 0 && ($part[0] === 'WHERE' || $part[0] === 'ON' || $part[0] === 'HAVING')) {
					$part = $part[0]."\n  (".implode(")\n  AND (", $part[1]).')';
				} else {
					$part = $part[0].($count_part1 > 0 ? "\n  ".implode("\n  ,", $part[1]) : '');
				}
			}
			return implode("\n", $parts);
		}


		public function execute() {

		}


		public function fetch() {

		}


		public function fetchAll($index = null) {

		}


		public function fetchCol($col = null, $index = null) {

		}


		public function fetchVal($col = null) {

		}

	}
