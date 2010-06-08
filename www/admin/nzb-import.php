<?php

require_once("config.php");
require_once(WWW_DIR."/lib/adminpage.php");
require_once(WWW_DIR."/lib/framework/db.php");
$db = new DB();

if (empty($argc))
	$page = new AdminPage();

if (!empty($argc) || $page->isPostBack() )
{
	$retval = "";	
	$strTerminator = "<br />";
	
	if (!empty($argc))
	{
		$strTerminator = "\n";
		$path = $argv[1];
	}
	else		
	{
		$strTerminator = "<br />";
		$path = $_POST["folder"];
	}

	if (substr($path, strlen($path) - 1) != '/')
		$path = $path."/";

	$groups = $db->query("SELECT ID, name FROM groups");
	foreach ($groups as $group)
		$siteGroups[$group["name"]] = $group["ID"];

	$nzbCount = 0;

	foreach(glob($path."*.nzb") as $nzbFile) 
	{

		$nzb = file_get_contents($nzbFile);
		
		$xml = @simplexml_load_string($nzb);
		if (!$xml || strtolower($xml->getName()) != 'nzb') 
			continue;
		
		$i=0;
		foreach($xml->file as $file) 
		{
			//file info
			$name = (string)$file->attributes()->subject;
			$fromname = (string)$file->attributes()->poster;
			$unixdate = (string)$file->attributes()->date;
			$date = date("Y-m-d H:i:s", (string)$file->attributes()->date);
			
			//groups
			$groupArr = array();
			foreach($file->groups->group as $group) 
			{
				$group = (string)$group;
				if (array_key_exists($group, $siteGroups)) 
				{
					$groupID = $siteGroups[$group];
				}
				$groupArr[] = $group;
			}
			$xref = 'Xref: '.implode(' ', $groupArr);
					
			$totalParts = sizeof($file->segments->segment);
			
			//insert binary
			$binarySql = "INSERT INTO binaries (name, fromname, date, xref, totalParts, groupID) 
					VALUES ('".$name."', '".$fromname."', '".$date."', '".$xref."', '".$totalParts."', '".$groupID."')";
			$binaryId = $db->queryInsert($binarySql);
			
			//segments (i.e. parts)
			foreach($file->segments->segment as $segment) 
			{
				$messageId = (string)$segment;
				$partnumber = $segment->attributes()->number;
				$size = $segment->attributes()->bytes;
				$partsSql = "INSERT INTO parts (binaryID, messageID, number, partnumber, size, dateadded)
						VALUES ('".$binaryId."', '".$messageId."', '0', '".$partnumber."', '".$size."', '".$date."')";
				$partsQuery = $db->queryInsert($partsSql);
			}
		}
		$nzbCount++;
		unlink($nzbFile);

		if (!empty($argc))
		{
			echo ("imported ".$nzbFile."\n");
			flush();
		}
		else
		{
			$retval.= "imported ".$nzbFile."<br />";
		}
	}
	
	$retval.= 'Processed '.$nzbCount.' nzbs';

	if (!empty($argc))
	{
		echo 'Processed '.$nzbCount.' nzbs';
		die();
	}
	
	$page->smarty->assign('output', $retval);	
	
}

$page->title = "Import Nzbs";
$page->content = $page->smarty->fetch('admin/nzb-import.tpl');
$page->render();

?>