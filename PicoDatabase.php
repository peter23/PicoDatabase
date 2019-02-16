<?php

/**
 * PicoDatabase is simple SQL query builder
 *
 * @link https://github.com/peter23/PicoDatabase
 * @author i@peter23.com
 * @license http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License, version 3
 * @require PHP >= 5.4, mysqlnd
 */


	class PicoDatabase extends mysqli {

		public function __construct($server = null, $username = null, $password = null, $database = null, $encoding = null) {
			$_server = explode(':', $server);
			if(!isset($_server[1]) || !$_server[1] || $_server[0] === 'p') {
				parent::__construct($server, $username, $password, $database);
			} else {
				parent::__construct($_server[0], $username, $password, $database, $_server[1]);
			}

			if($this->connect_error) {
				throw new PicoDatabaseException('PicoDatabase.__construct: ('.$this->connect_errno.') '.$this->connect_error, $this->connect_errno);
			}

			if($encoding)  $this->set_charset($encoding);
		}


		public function __destruct() {
			$this->close();
		}


		public function __call($name, $arguments) {
			return new PicoDatabaseQueryBuilder($this, $name, $arguments);
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


		public function processPlaceHolders($s, $vals) {
			$s0 = $s;
			$s = explode('?', $s);
			$n = 0;
			$s_count = count($s);
			for($i = 1; $i < $s_count; $i++) {
				$c = substr($s[$i], 0, 1);
				$r = substr($s[$i], 1);
				switch($c) {
					case '_':
					case '*':
					case '@':
						if(!array_key_exists($n, $vals)) {
							throw new PicoDatabaseException('PicoDatabase.processPlaceHolders: incorrect number of arguments ('.serialize($s0).','.serialize($vals).')');
						}
						if($c === '_') {
							$vals[$n] = $this->escape($vals[$n]);
						} elseif($c === '@') {
							$vals[$n] = $this->escapeFieldName($vals[$n]);
						}
						$s[$i] = is_array($vals[$n]) ? implode(', ', $vals[$n]) : $vals[$n];
						$n++;
						break;

					case '': //?? = ?
						$s[$i] = '?';
						$i++;
						break;

					default:
						throw new PicoDatabaseException('PicoDatabase.processPlaceHolders: incorrect placeholder ('.serialize($c).')');
						break;
				}
				$s[$i] .= $r;
			}
			if(array_key_exists($n, $vals)) {
				throw new PicoDatabaseException('PicoDatabase.processPlaceHolders: incorrect number of arguments ('.serialize($s0).','.serialize($vals).')');
			}
			return implode('', $s);
		}


		public function needProcessPlaceHolders(&$s, $mod_string = false) {
			$q_marks = substr_count($s, '?');
			$q_marks_placeholders = substr_count($s, '??');
			if($q_marks && $q_marks_placeholders && ($q_marks === $q_marks_placeholders*2)) {
				if($mod_string) {
					$s = str_replace('??', '?', $s);
				}
				return false;
			} else {
				return $q_marks;
			}
		}


		public function query($strQuery, $resultmode = null) {
			if(func_num_args() > 1) {
				$args = func_get_args();
				array_shift($args);
				$strQuery = $this->processPlaceHolders($strQuery, $args);
			}
			if($this->real_query($strQuery)) {
				if($this->field_count) {
					return new PicoDatabaseQueryResult($this);
				} else {
					return true;
				}
			} else {
				throw new PicoDatabaseException('PicoDatabase.query: ('.$this->errno.') '.$this->error, $this->errno);
			}
		}

	}



	class PicoDatabaseQueryResult extends mysqli_result {

		public function __destruct() {
			$this->free();
		}


		public function fetch() {
			return $this->fetch_assoc();
		}


		public function fetchAll($indexCol = null) {
			if($indexCol) {
				$ret = array();
				foreach($this as $row) {
					if(!isset($row[$indexCol])) {
						throw new PicoDatabaseException('PicoDatabaseQueryResult.fetchAll: nonexistent indexCol ('.serialize($indexCol).','.serialize(array_keys($row)).')');
					}
					$ret[$row[$indexCol]] = $row;
				}
				return $ret;
			} else {
				return $this->fetch_all(MYSQLI_ASSOC);
			}
		}


		public function fetchCol($col = null, $indexCol = null) {
			$ret = array();
			if($col || $indexCol) {
				foreach($this as $row) {
					if(!$col) {
						$col = array_keys($row);
						$col = $col[0];
					}
					if(!isset($row[$col])) {
						throw new PicoDatabaseException('PicoDatabaseQueryResult.fetchCol: nonexistent col ('.serialize($col).','.serialize(array_keys($row)).')');
					}
					if($indexCol) {
						if(!isset($row[$indexCol])) {
							throw new PicoDatabaseException('PicoDatabaseQueryResult.fetchCol: nonexistent indexCol ('.serialize($indexCol).','.serialize(array_keys($row)).')');
						}
						$ret[$row[$indexCol]] = $row[$col];
					} else {
						$ret[] = $row[$col];
					}
				}
			} else {
				while($row = $this->fetch_row()) {
					$ret[] = $row[0];
				}
			}
			return $ret;
		}


		public function fetchVal($col = null) {
			if($col) {
				$row = $this->fetch_assoc();
				if(!isset($row[$col])) {
					throw new PicoDatabaseException('PicoDatabaseQueryResult.fetchVal: nonexistent col ('.serialize($col).','.serialize(array_keys($row)).')');
				}
				return $row[$col];
			} else {
				$row = $this->fetch_row();
				return $row[0];
			}
		}

	}



	class PicoDatabaseQueryBuilder {

		private $db;
		public $parts = array();


/*
	1) check first argument

	1.1) normal value - process all arguments as normal values

	1.2) placeholders string - process other arguments as values for placeholders

	1.3) array - throw exception if there are more arguments
		process array by keys and values

	1.3.1) numeric key
		process as call with normal value

	1.3.2) string key
		if value is not array - add "= ?_" to key and process as 1.3.3
		if value is array - add " IN (?_)" to key and process as 1.3.3

	1.3.3) placeholders string key - process value as array of values for placeholders
		convert non-array value to array

	2) exception: if calls are `where`, `set`, `on` and `having`

	2.1.1) normal value and one more argument - process as array(<first> => <second>)

	2.1.2) normal value and more than one more argument - exception

	3) exception: if calls are `insert`, `update`, `replace`

	3.1.2) normal value and array - process first array as `set` and second (if it is) as `where`

	3.1.2) normal value and more than two more arguments - exception
*/


		public function __call($name, $arguments) {
			if(substr($name, 0, 5) === 'fetch') {
				return $this->doQuery($name, $arguments);
			}

			else {
				$name = ltrim(strtoupper(strtr($name, array('A'=>' A','B'=>' B','C'=>' C','D'=>' D','E'=>' E','F'=>' F','G'=>' G','H'=>' H','I'=>' I','J'=>' J','K'=>' K','L'=>' L','M'=>' M','N'=>' N','O'=>' O','P'=>' P','Q'=>' Q','R'=>' R','S'=>' S','T'=>' T','U'=>' U','V'=>' V','W'=>' W','X'=>' X','Y'=>' Y','Z'=>' Z'))));
				//if we have arguments
				if(count($arguments)) {
					$first_argument = $arguments[0];
					//remove first arguments from arguments array
					array_shift($arguments);
					//if first argument is array
					if(is_array($first_argument)) {
						if(count($arguments)) {
							throw new PicoDatabaseException('PicoDatabaseQueryBuilder: array argument should be alone ('.serialize($name).')');
						}
						$this->__call1($name, $first_argument);
					}
					//if first argument is placeholders string
					elseif($this->db->needProcessPlaceHolders($first_argument)) {
						$this->__call1($name, array($first_argument => $arguments));
					}
					//if first argument is primitive value and we have more arguments
					elseif(count($arguments)) {
						//process some exceptions for syntax sugar
						$_name = substr($name, 0, 6);
						if(($_name === 'INSERT' || $_name === 'UPDATE' || $_name === 'REPLAC') && is_array($arguments[0])) {
							if(count($arguments) > 2) {
								throw new PicoDatabaseException('PicoDatabaseQueryBuilder: this method does not allow more than one primitive argument and two arrays ('.serialize($name).')');
							}
							//allow insert('table', <array_for_set>, [<array_for_where>])
							$this->db->needProcessPlaceHolders($first_argument, true);
							$this->__call1($name, array($first_argument));
							$this->__call1('SET', $arguments[0]);
							if(isset($arguments[1])) {
								$this->__call1('WHERE', is_array($arguments[1]) ? $arguments[1] : array($arguments[1]));
							}
						}
						elseif($_name === 'WHERE' || $_name === 'SET' || $_name === 'ON' || $_name === 'HAVING') {
							if(count($arguments) > 1) {
								throw new PicoDatabaseException('PicoDatabaseQueryBuilder: this method does not allow more than two primitive arguments ('.serialize($name).')');
							}
							//allow where('field', <value>)
							$this->__call1($name, array($first_argument => $arguments[0]));
						}
						else {
							//just a list of primitive arguments
							$this->db->needProcessPlaceHolders($first_argument, true);
							$this->__call1($name, array_merge(array($first_argument), $arguments));
						}
					}
					//we have only one primitive argument
					else {
						$this->db->needProcessPlaceHolders($first_argument, true);
						$this->__call1($name, array($first_argument));
					}
				}
				//if we do not have arguments
				else {
					$this->__call1($name, array());
				}
			}

			return $this;
		}


		private function __call1($name, $arguments) {
			foreach($arguments as $arg_key => &$arg_val) {
				//if key is string
				if(!is_int($arg_key)) {
					//if key is not placeholders string
					if(!$this->db->needProcessPlaceHolders($arg_key, true)) {
						//if value is an array
						if(is_array($arg_val)) {
							//add IN ?
							if(strtoupper(substr(rtrim($arg_key), -2)) !== 'IN') {
								$arg_key .= ' IN';
							}
							$arg_key .= ' (?_)';
						}
						//if value is not an array
						else {
							//add = ?
							$arg_key_last_char = substr(rtrim($arg_key), -1);
							if(($arg_key_last_char !== '=') && ($arg_key_last_char !== '<') && ($arg_key_last_char !== '>')) {
								$arg_key .= ' =';
							}
							$arg_key .= ' ?_';
						}
						$arg_val = array($arg_val);
					}
					$arg_val = $this->db->processPlaceHolders($arg_key, is_array($arg_val) ? $arg_val : array($arg_val));
				}
			}
			//add processed task to parts
			$parts_count = count($this->parts) - 1;
			//if previous part has the same name
			if($parts_count >= 0 && $this->parts[$parts_count][0] === $name) {
				$this->parts[$parts_count][1] = array_merge($this->parts[$parts_count][1], $arguments);
			} else {
				if($name === 'NOP')  $name = '';
				$this->parts[] = array($name, $arguments);
			}
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
				if($count_part1 && ($part[0] === 'WHERE' || $part[0] === 'ON' || $part[0] === 'HAVING')) {
					$part = $part[0]."\n  (".implode(")\n  AND (", $part[1]).')';
				} else {
					$part = $part[0].($count_part1 ? "\n  ".implode("\n  ,", $part[1]) : '');
				}
			}
			return implode("\n", $parts);
		}


		public function execute() {
			return $this->db->query(strval($this));
		}


		private function doQuery($name, $arguments) {
			$ret = $this->execute();
			if(is_bool($ret)) {
				return $ret;
			} else {
				//much faster than call_user_func_array
				return $ret->$name(
					isset($arguments[0]) ? $arguments[0] : null,
					isset($arguments[1]) ? $arguments[1] : null,
					isset($arguments[2]) ? $arguments[2] : null
				);
			}
		}

	}



	class PicoDatabaseException extends Exception { }
