#!/usr/local/bin/php
<?

// This updates all nodes mentioned in the following table, used during import/turnover


$table  = "shine_import.updatables";

require_once('lib.cf');
require_once("CustomerTree.class.php");


$tree = new CustomerTree();

$nodes = $tree->db->all_results("select CustNo from $table ");
foreach ($nodes as $node) {
 $who = $node['CustNo'];
 $node =  $tree->get($who);
 if (!$node) {
   die("No such node $who");
 }
// print "doing $who\n";
 $tree->updateBonusDataForRank("", $who);
}





?>
