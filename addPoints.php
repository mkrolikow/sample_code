#!/usr/local/bin/php
<?

require_once('lib.cf');
require_once("CustomerTree.class.php");


$custno = $argv[1];
$points = $argv[2];

if (!$custno) {
 print "Usage: addPoints <custno> <points>>\n";
 exit();
}



$tree = new CustomerTree();
$node =  $tree->get($custno);
$nodename = $node['lastname'];

if (!$node) {
 print "No such node $custno.\n";
 exit();
}

print "Adding $points to $nodename $custno\n";
$time = microtime(true);

$tree->addPoints($node, $points);

printf("Operation succesful, time taken: %0.4g seconds." . PHP_EOL, microtime(true) - $time);
