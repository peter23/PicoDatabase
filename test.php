<?php

	require('PicoDatabase.php');

	$d = new PicoDatabase('localhost', 'test', 'test', 'test', 'utf8');

	echo '==========', "\n";

	var_dump(strval(  $d->hello()->world()  ));

	echo '==========', "\n";

	var_dump(strval(  $d->select('x', 'y')  ));

	echo '==========', "\n";

	var_dump(strval(  $d->select('x')->select('y')  ));

	echo '==========', "\n";

	var_dump(strval(  $d->select(array('x', 'y'))  ));

	echo '==========', "\n";

	var_dump(strval(  $d->select('?@', 'x')  ));

	echo '==========', "\n";

	try {
		var_dump(strval(  $d->select('?@', 'x', 'y')  ));
	} catch(Exception $e) {
		var_dump($e->getMessage());
	}

	echo '==========', "\n";

	var_dump(strval(  $d->select('?@', array('x', 'y'))  ));

	echo '==========', "\n";

	var_dump(strval(  $d->select(array('?@', 'x', 'y'))  ));

	echo '==========', "\n";

	try {
		var_dump(strval(  $d->select(array('x', 'y'), array('n', 'm'))  ));
	} catch(Exception $e) {
		var_dump($e->getMessage());
	}

	echo '==========', "\n";

	var_dump(strval(  $d->select('??', 'f1', 'f2') ));

	echo '==========', "\n";

	var_dump(strval(  $d->insertInto('t1', array('x'), 'y')  ));

	echo '==========', "\n";

	var_dump(strval(  $d->insertInto('t1')->set(array('x'))->where('y')  ));

	echo '==========', "\n";

	try {
		var_dump(strval(  $d->insertInto('t1', array('x'), 'y', 'z')  ));
	} catch(Exception $e) {
		var_dump($e->getMessage());
	}

	echo '==========', "\n";

	var_dump(strval(  $d->insertInto('t1', array('x', 'y' => 1, 'z' => array(1, 2, 3)), array('n', 'm' => 1, 'p' => array(1, 2, 3)))  ));

	echo '==========', "\n";

	var_dump(strval(  $d->where('x', 1)  ));

	echo '==========', "\n";

	try {
		var_dump(strval(  $d->where('x', 1, 2)  ));
	} catch(Exception $e) {
		var_dump($e->getMessage());
	}

	echo '==========', "\n";

	var_dump(strval(  $d->where('x LIKE ?_ OR y = ?* OR ?_', '%1/2\3\'?_??%', 'NO\'W()', array('z' => '9', 'zz' => 0))  ));

	echo '==========', "\n";

	var_dump(strval(  $d->where(array('x IN (?_) OR y IN (?_)' => array(array(1,2,3), array(4,5,6))))  ));

	echo '==========', "\n";
