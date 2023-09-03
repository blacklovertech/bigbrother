<html>
    <head>
        <meta http-equiv="refresh" content="5">
    </head> 
<body> 

<?php

$websites = array("103.238.230.130","kalvi.kalasalingam.ac.in","kalasalingam.ac.in");

echo "<h1>Site Status  ".date("h:i:s")."</h1>";

foreach ($websites as $url){
    $command = "ping -c 1 ".$url;
    echo "<strong>Address: ".$url."</strong>";
    echo "<pre>";
    echo shell_exec($command);
    echo "</pre>";
}

?>

</body>
</html>