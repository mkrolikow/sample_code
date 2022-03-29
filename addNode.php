#!php
<?
require_once('lib.cf');
require_once("CustomerTree.class.php");

foreach (range(1,2) as $i) if (!isset($argv[$i])) {
    print "Usage: php " . __FILE__ . " <cust no> <parent no> [<node name>]\n";
    exit(1);
}

$custno = $argv[1];
$parentno = $argv[2];

$fullname = trim($argv[3] ?? "");
$breakpoint = strpos($fullname, " ") ?: 0;
list($firstname, $lastname) = [substr($fullname, 0, $breakpoint), substr($fullname, $breakpoint + 1)];

$tree = new CustomerTree();

if ($tree->get($custno)) {
    print "Node $custno already exists.\n";
    exit(1);
}

$parent = $tree->get($parentno);
if (!$parent) {
    print "No such parent $parentno.\n";
    exit(1);
}

print "Adding new node '$fullname' with CustNo $custno to parent $parentno '{$parent['lastname']}'\n";

$newnode = $tree->addLeaf([
    'CustNo' => $custno,
    'lastname' => $lastname,
    'firstname' => $firstname,
    'parent' => $parentno
]);

print "Result: \n";
print_r($newnode);
print "\nOk!\n";
