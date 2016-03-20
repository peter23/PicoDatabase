<?php

	$q = new PicoDatabaseQuery();
	$q = $q->select('1', '2')->select('3')->from('table')->where('1', '2', '3')->where('99')->orderBy('1');

	var_dump(trim($q));

	$d = new PicoDatabase('localhost', 'root');
	var_dump($d->processPlaceHolders('hello, ?_! how are you ?* the end', array('/\/\1\'1"1\/\/', '222')));
	var_dump($d->processPlaceHolders('??hello, ?_?? how ?? are you ?*??', array('/\/\1"1\'1\/\/', '222')));


	class PicoDatabase extends mysqli {

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

		public function escape($s) {
			return '\''.$this->real_escape_string($s).'\'';
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

		private $letters_replaces_w_spaces = array('A'=>' A','B'=>' B','C'=>' C','D'=>' D','E'=>' E','F'=>' F','G'=>' G','H'=>' H','I'=>' I','J'=>' J','K'=>' K','L'=>' L','M'=>' M','N'=>' N','O'=>' O','P'=>' P','Q'=>' Q','R'=>' R','S'=>' S','T'=>' T','U'=>' U','V'=>' V','W'=>' W','X'=>' X','Y'=>' Y','Z'=>' Z');

		public function __call($name, $arguments) {
			$name = strtr($name, $this->letters_replaces_w_spaces);
			$name = trim(strtoupper($name));

			$parts_count = count($this->parts);
			if( ($parts_count > 0) && ($this->parts[$parts_count - 1][0] == $name) ) {
				$this->parts[$parts_count - 1][1] = array_merge($this->parts[$parts_count - 1][1], $arguments);
			} else {
				$this->parts[] = array($name, $arguments);
			}
			return $this;
		}

		public function __toString() {
			$parts = $this->parts;
			foreach($parts as &$part) {
				$part = $part[0]."\n  ".implode("\n  ,", $part[1]);
			}
			return implode("\n", $parts);
		}

	}
