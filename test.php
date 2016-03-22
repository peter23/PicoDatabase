<?php

	$d = new PicoDatabase('localhost', 'root');

	var_dump($d->processPlaceHolders('hello, ?_! how are you ?* the end', array('/\/\1\'1"1\/\/', '222')));
	var_dump($d->processPlaceHolders('??hello, ?_?? how ?? are you ?*??', array('/\/\1"1\'1\/\/', '222')));

	$q = $d
		->select(array('f1', 'f2'))->select('f3')
		->from('table1')->from('table2')
		->leftJoin('ggg')
		->on('a = ?_', 1)
		->where('1')
		->where('a IN (?_)', array(1,2,3))
		->where('x=?_', 1)
		->where('x=?_ OR y=?* OR z=?_', 1, 2, 3)
		->where(array('x = ?_', 'y = ?_'), 10)
		->where(array(
			'a = ?_' => 11,
			'b = ?_' => 22
		))
		->set('?_', array('first'=>1, 'second'=>2, 'third'=>3))
		->set('?_', array('first'=>1, 'second'=>2, 'third'=>3))
		->orderBy('ggg')
		->limit(array(1, 2))
	;

	var_dump(strval($q));



	class PicoDatabase extends mysqli {

		public function __call($name, $arguments) {
			$_name = strtoupper(substr($name, 0, 6));
			if(($_name == 'SELECT') || ($_name == 'INSERT') || ($_name == 'UPDATE') || ($_name == 'DELETE')) {
				return new PicoDatabaseQuery($this, $name, $arguments);
			} else {
				throw new Exception('Call to undefined method PicoDatabase::'.$name.'()');
			}
		}


		public function __construct($server = null, $username = null, $password = null, $database = null, $encoding = null) {
			$_server = explode(':', $server);
			if(!isset($_server[1]) || !$_server[1] || $_server[0] == 'p') {
				parent::__construct($server, $username, $password, $database);
			} else {
				parent::__construct($_server[0], $username, $password, $database, $_server[1]);
			}

			if($this->connect_error) {
				throw new Exception('('.$this->connect_errno.') '.$this->connect_error, $this->connect_errno);
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

				$s_keys = array_keys($s);
				if(is_int( array_shift($s_keys) )) {
					foreach($s as &$s1) {
						$s1 = $this->escape($s1);
					}
					return implode(',', $s);

				} else {
					foreach($s as $s_key=>&$s1) {
						$s1 = $this->escapeFieldName($s_key).'='.$this->escape($s1);
					}
					return implode(' , ', $s);
				}

			}

		}


		public function escapeFieldName($s) {
			return '`'.str_replace('`', '``', $s).'`';
		}


		public function processPlaceHolders($s, $vals) {
			$s = explode('?', $s);
			$n = 0;
			$s_count = count($s);
			for($i = 1; $i < $s_count; $i++) {
				$c = substr($s[$i], 0, 1);
				switch($c) {
					case '_':
						$s[$i] = $this->escape($vals[$n]).substr($s[$i], 1);
						$n++;
						break;

					case '*':
						$s[$i] = $vals[$n].substr($s[$i], 1);
						$n++;
						break;

					case '':
						$s[$i] = '?';
						$i++;
						break;
				}
			}
			return implode('', $s);
		}

	}



	class PicoDatabaseQuery {

		public $parts = array();

		private $db;

		private $letters_replaces_w_spaces = array('A'=>' A','B'=>' B','C'=>' C','D'=>' D','E'=>' E','F'=>' F','G'=>' G','H'=>' H','I'=>' I','J'=>' J','K'=>' K','L'=>' L','M'=>' M','N'=>' N','O'=>' O','P'=>' P','Q'=>' Q','R'=>' R','S'=>' S','T'=>' T','U'=>' U','V'=>' V','W'=>' W','X'=>' X','Y'=>' Y','Z'=>' Z');


		public function __call($name, $arguments) {
			$arguments_count = count($arguments);
			if(($arguments_count >= 1) && (is_array($arguments[0]))) {
				$subcalls = array_shift($arguments);
				foreach($subcalls as &$subcall) {
					$this->__call($name, array_merge(array($subcall), $arguments));
				}

			} else {
				$name = strtr($name, $this->letters_replaces_w_spaces);
				$name = trim(strtoupper($name));

				if($arguments_count > 1) {
					$tmp_statement = array_shift($arguments);
					$arguments = array($this->db->processPlaceHolders($tmp_statement, $arguments));
				}

				$parts_count = count($this->parts) - 1;
				if( ($parts_count >= 0) && ($this->parts[$parts_count][0] == $name) ) {
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
				if($count_part1 && (($part[0] == 'WHERE') || ($part[0] == 'ON') || ($part[0] == 'HAVING'))) {
					$part = $part[0]."\n  (".implode(")\n  AND (", $part[1]).')';
				} else {
					$part = $part[0].($count_part1 ? "\n  ".implode("\n  ,", $part[1]) : '');
				}
			}
			return implode("\n", $parts);
		}


		public function execute() {

		}


		public function fetch() {

		}


		public function fetchAll() {

		}


		public function fetchCol() {

		}


		public function fetchIndexed() {

		}


		public function fetchVal() {

		}

	}
