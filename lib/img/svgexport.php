<?php
	if (empty($_REQUEST['svg'])) die("<form action='./svgexport.php' method='POST'>
		<textarea name='svg' rows='40' cols='120'></textarea><br>
		<input type='submit'>
		</form>");
	file_put_contents('/tmp/tmp.svg', $_REQUEST['svg']);
	list($trash, $svg) = explode('<svg', $_REQUEST['svg'], 2);
	list($svg, $trash) = explode('>', $svg, 2);
	list($trash, $height) = explode('height="', $svg, 2);
	list($height, $trash) = explode('"', $height, 2);
	list($trash, $width) = explode('width="', $svg, 2);
	list($width, $trash) = explode('"', $width, 2);
	if (isset($_REQUEST['pdf'])) {
		exec("/usr/bin/inkscape --without-gui /tmp/tmp.svg -w $width -h $height -A /tmp/tmp.pdf 2>&1", $out, $retval);
		header("Content-type:application/pdf");
		readfile('/tmp/tmp.pdf');
		exit();
	}
	exec("/usr/bin/inkscape --without-gui /tmp/tmp.svg -w $width -h $height -e /tmp/tmp.png 2>&1", $out, $retval);
	header('Content-Type: image/png'); 
	readfile('/tmp/tmp.png');
?>
