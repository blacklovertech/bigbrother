<form action="shellExecPing.php" method="post">
Website? <input type="text" name="website">
<input type="submit">
</form>

<?php

$website = $_POST['website'];
$command = "ping -c 1 ".$website;

echo "Website: ".$command;
echo "<pre>";
echo shell_exec($command);
echo "</pre>";

echo "<h4>If/Else Statement</h4>";

if (shell_exec($command)== TRUE) {
    echo "Website UP";
} else {
    echo "website DOWN";
}

?>