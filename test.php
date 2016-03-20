<?php

	$q = new PicoDatabaseQuery();
	$q = $q->select('1', '2')->select('3')->from('table')->where('1', '2', '3')->where('99');

	var_dump(trim($q));

	$d = new PicoDatabase('localhost', 'root');
	var_dump($d->processPlaceHolders('hello, ?_! how are you ?* the end', array('/\/\1\'1"1\/\/', '222')));
	var_dump($d->processPlaceHolders('??hello, ?_?? how ?? are you ?*??', array('/\/\1"1\'1\/\/', '222')));


	class PicoDatabase {

		public $db;

		public function __construct($server = null, $username = null, $password = null, $database = null, $encoding = null) {
			return $this->connect($server, $username, $password, $database, $encoding);
		}

		public function connect($server = null, $username = null, $password = null, $database = null, $encoding = null) {
			if($this->db) {
				$this->db->close();
			}
			$_server = explode(':', $server);
			if(!isset($_server[1]) || !$_server[1] || $_server[0] == 'p') {
				$this->db = new mysqli($server, $username, $password, $database);
			} else {
				$this->db = new mysqli($_server[0], $username, $password, $database, $_server[1]);
			}
			if($encoding)  $this->db->set_charset($encoding);
		}

		public function escape($s) {
			return '\''.$this->escapeRaw($s).'\'';
		}

		public function escapeRaw($s) {
			return $this->db->real_escape_string($s);
		}

		public function escapeFieldName($s) {
			return '`'.str_replace('`', '``', $s).'`';
		}

		public function processPlaceHolders($s, $vals) {
			$s = explode('?', $s);
			$n = 0;
			$cn = count($s);
			for($i = 1; $i < $cn; $i++) {
				$c = substr($s[$i], 0, 1);
				switch($c) {
					case '_':
						$s[$i] = $this->escape($vals[$n]).substr($s[$i], 1);
						$n++;
						break;

					case '~':
						$s[$i] = $this->escapeRaw($vals[$n]).substr($s[$i], 1);
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

		public function __call($name, $arguments) {
			$name = strtoupper($name);
			$parts_count = count($this->parts);
			if( ($parts_count > 0) && ($this->parts[$parts_count - 1][0] == $name) ) {
				$this->parts[$parts_count - 1][1] = array_merge($this->parts[$parts_count - 1][1], $arguments);
			} else {
				$this->parts[] = array($name, $arguments);
			}
			return $this;
		}

		public function __toString() {
			return $this->_buildQuery();
		}

		public function _buildQuery() {
			$parts = $this->parts;
			foreach($parts as &$part) {
				$part = $part[0].' '.implode(', ', $part[1]);
			}
			return implode("\n", $parts);
		}

	}
