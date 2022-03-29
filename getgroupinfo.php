#!/usr/local/bin/php
<?

require_once('lib.cf');
require_once("CustomerTree.class.php");

$custNo = $argv[1];


$tree = new CustomerTree();
$node = $tree->get($custNo);

if(!$node) {
 print "No such node $custNo\n";
}

$then = microtime(true);
$guys = $tree->getGroupInfo($node);
$now = microtime(true);

$spent =  ($now - $then);


if  ($node)
print("Node " . $node['CustNo'] . " " . $node['lastname'] . " rank " . $node['rank'] . "\n");


print "Lev\tgroups\tmembers\tpoints\n";
print "-------------------------------------------\n";
foreach($guys as $ancestor) {
 print "{$ancestor['level']}\t{$ancestor['leaders']}\t{$ancestor['members']}\t{$ancestor['points']}\n";
}

print "Used $spent microseconds\n";




?>
