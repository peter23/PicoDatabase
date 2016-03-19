<?php

	$q = new PicoDatabaseQuery();
	$q = $q->select('1', '2')->select('3')->from('table')->where('1', '2', '3')->where('99');

	var_dump(trim($q));


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
			return implode(' ', $parts);
		}

	}
