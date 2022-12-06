<?php // content="text/plain; charset=utf-8"

require_once ('./src/jpgraph.php');
require_once ('./src/jpgraph_line.php');
require_once ('./src/jpgraph_log.php');
function TimeCallback($aVal) {
	if (isset($_REQUEST['radfet'])) return '';
    return Date('H:i', $aVal);
}
$request = "http://fcs-proxy-01.elettra.eu/egiga2m/lib/service/hdbpp_plot_service.php?conf={$_REQUEST['conf']}&start=".urlencode($_REQUEST['start'])."&stop=".urlencode($_REQUEST['stop'])."&ts=".urlencode($_REQUEST['ts'])."&no_pretimer";
if (isset($_REQUEST['debug'])) echo("<a href='$request'>$request</a><br>\n");
$jdata = file_get_contents($request);
if (isset($_REQUEST['debug'])) die('jdata: '.$jdata);
$ddata = json_decode($jdata, true, 512, JSON_INVALID_UTF8_IGNORE);

$graph = new Graph($_REQUEST['width'],$_REQUEST['height']);
$graph->SetFrame(false);

$m = array(40,20,30,50);
$title = isset($_REQUEST['no_title'])? '': $ddata['ts'][0]['label'];

$scale = 'lin';
if (!empty($_REQUEST['logY'])) {$l = explode(';',$_REQUEST['logY']); $scale = $l[0]==1? 'log': 'lin';}

if (isset($_REQUEST['radfet'])) {
	$t = time();
	$dd = $t - ($t % 3600);
	$graph->SetScale("int$scale", 0, 40, $dd-3600*8, $dd+3600);
	$m = explode(',','5,5,5,0');
	$title = '';
	$graph->xaxis->SetMajTickPositions([$dd-(3600*8), $dd-(3600*6), $dd-(3600*4), $dd-(3600*2), $dd]);
}
else {
	$graph->SetScale("int$scale");
}

if (isset($_REQUEST['margin'])) $m = explode(',', $_REQUEST['margin']);
$graph->SetMargin($m[0],$m[2],$m[2],$m[3]);

if (!empty($title)) $graph->title->Set($title);

// Setup the callback and adjust the angle of the labels
$graph->xaxis->SetLabelFormatCallback('TimeCallback');
$graph->xaxis->SetLabelAngle(90);
// $graph->yaxis->SetTextTickInterval(0,20);
$graph->xgrid->Show(true,true);
$graph->ygrid->Show(true,true);
$graph->ygrid->SetFill(true,'white', 'white'); 


$ts = $ddata['ts'];
$colors = array('blue','red','green','orange','purple','cyan','brown','black','gray','magenta');
foreach($ts as $ti=>$tdata) {
	$ydata = $xdata = array();
	$y0 = $tdata['data'][0][1];
	foreach($tdata['data'] as $d) {
		$xdata[] = $d[0]/1000;
		$ydata[] = $d[1] + (isset($_REQUEST['radfet'])? (((1+$ti)*0.3) - $y0): 0);
	}
	$line = new LinePlot($ydata,$xdata);
	$line->SetColor($colors[$ti%count($colors)]);
	$line->SetWeight(1);
	$graph->Add($line);
}
$graph->Stroke();
?>
