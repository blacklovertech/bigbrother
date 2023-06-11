# WAF Bypassing Deface (Or maybe an inappropriate title to express!)
This repository contains scripts that can be used to deface such websites that supports php but no web shells!<br>
Well, pretty confusing right? Let's make it more clear with examples because example is better than concept!<br>
Assume, you are uploading shell through uploader in a site but some restricted functionalities that the particular shell has are triggering the firewall or not letting the shell run, then this will be useful to atleast deface in the public_html directory.<br>
the usage is below!
# Usage:
Firstly, upload your deface page(let it's name is hacked.html)<br>
Secondly, upload the deface.php<br>
Finally, visit/open the deface.php in your browser <br>
Enter the name of your previously uploaded deface page (eg: hacked.html)<br>
Done!<br>
If any problem arises, Issues tab is available!
# N.B:
This will work only when php code is executed in the server(both windows and linux) and also there is permission to upload files in the document root! The concept is simply using the copy function of php to copy deface page from
sub directory to the main directory!

