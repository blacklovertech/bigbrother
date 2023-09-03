<h1>Pic Resize App</h1>
<form action="picCompressApp.php" method="post" enctype="multipart/form-data">
        <input type="file" name="Upload">
        <input type="submit">
    </form>

<?php

$dir = "./pics/";
$filename = $dir.basename($_FILES['Upload']['name']);
$filenameArray = pathinfo($filename);
$ext = array("jpeg","jpg","gif");
$isGood = 0;
$outputPic50 = $dir."50".basename($_FILES['Upload']['name']);
$outputPic100 = $dir."100".basename($_FILES['Upload']['name']);
$outputPic250 = $dir."250".basename($_FILES['Upload']['name']);
$outputPic500 = $dir."500".basename($_FILES['Upload']['name']);

if (file_exists($filename)) {
    echo "The file already exists<br>";
    $isGood = 1;
}

if ($_FILES['Upload']['size'] > 500000) {
    echo "File is over 500kB in size<br>";
    $isGood = 1;
}

if (!in_array($filenameArray['extension'],$ext)) {
    echo "File Type is not Allowed (Upload jpeg, jpg,gif)<br>";
    $isGood = 1;
}

if ($isGood != 1) {
    if (move_uploaded_file($_FILES['Upload']['tmp_name'], $filename)){
        echo "<p>File was uploaded --> ".$_FILES['Upload']['name'];
    } else {
     echo "Upload failed".$_FILES['Upload']['name'];
    }
}

shell_exec("ffmpeg -i '$filename' -vf scale=50:-1 '$outputPic50'");

shell_exec("ffmpeg -i '$filename' -vf scale=100:-1 '$outputPic100'");

shell_exec("ffmpeg -i '$filename' -vf scale=250:-1 '$outputPic250'");

shell_exec("ffmpeg -i '$filename' -vf scale=500:-1 '$outputPic500'");

echo "<br><img src=".$outputPic50."><br><br>";
echo "<img src=".$outputPic100."><br><br>";
echo "<img src=".$outputPic250."><br><br>";
echo "<img src=".$outputPic500."><br><br>";
echo "<img src=".$filename.">";
?>