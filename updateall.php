<?PHP

$programes = array (
	//programa => array("títol", "url dels capítols", "patró que identifica com enllaç de capçitol, "fitxer xml del podcast");
    "polonia"  => array("Polònia", "http://www.ccma.cat/tv3/alacarta/polonia/", "/Polonia-", "polonia.xml"),
    "puntcat" => array(".CAT", "http://www.ccma.cat/tv3/alacarta/puntcat/", "-CAT", "puntcat.xml"),
    "elcarc"   => array("El crac", "http://www.ccma.cat/tv3/alacarta/el-crac/", "/Capitol", "elcracHD.xml"),
    "foraster"   => array("El Foraster", "http://www.ccma.cat/tv3/alacarta/el-foraster/", "-Foraster/", "elforaster.xml")
);

//funció per obtenir tamany del v�deo
function mida($url)
{
	$data = get_headers($url, true);
	if (isset($data['Content-Length']))
		return (int) $data['Content-Length'];
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Podcats</title>
</head>

<body>

<?PHP

include_once("analyticstracking.php");

foreach ($programes as $programa)
{
	$url = $programa[1];
	$patro = $programa[2];
	$xmlFile = $programa[3];
	
	echo "<br/ ><h2>".$programa[0]."</h2>";
	
	//parsejo la url del programa buscant links (original parsing code by Chirp Internet: www.chirp.com.au)
	$input = @file_get_contents($url) or die("no es pot carregar $url");
	$regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
	$capitols = array();
	$links = array();
	
	//fico tots els links en un array
	if(preg_match_all("/$regexp/siU", $input, $matches)) {
		foreach ($matches[2] as $value) {		
			//miro si el link �s un cap�tol complert a trav�s d'un patr� de l'enlla�
			if (strpos($value,$patro) !== false) {
				//agafo nom�s el codi del v�deo i miro que no aparegui m�s d'una vegada
				$codi = substr($value, strrpos(substr($value, 0, -1), "/")+1, -1);
				if (!in_array($codi, $capitols)) { array_push($capitols, $codi); array_push($links, $value); }
			}
		}
	}

	
	//agafo la informaci� dels cap�tols:

	$dadacapitol = array();
	//$url = "http://dinamics.ccma.cat/pvideo/media.jsp?media=video&version=0s&idint=".$capitols[$i];
	$url = "http://videostv3.herokuapp.com/videos/".$capitols[0];
	$json = @file_get_contents($url) or die("no es pot carregar $url");
	
	$jsonIterator = new RecursiveIteratorIterator(
	new RecursiveArrayIterator(json_decode($json, TRUE)),
	RecursiveIteratorIterator::SELF_FIRST);
	
	foreach ($jsonIterator as $key => $val) {
		if (!is_array($val)) {
			if ($key == "title") array_push($dadacapitol, $val);
			if ($key == "description") array_push($dadacapitol, $val);
			if ($key == "url") array_push($dadacapitol, $val);
		}
	}
	
	$dades = simplexml_load_file($xmlFile);
	
	$ultimcapitol = count($dades->channel->item)-1;
	$duracio = explode(" ", substr($input, strpos($input, "PT 0")+3, 7));
	$minuts = preg_replace("/[^0-9,.]/", "", $duracio[0])*60 + preg_replace("/[^0-9,.]/", "", $duracio[1]);
	
	//miro si el podcast est� actualitzat
	$titol = $dades->channel->item[$ultimcapitol]->title;
	if ($titol == $dadacapitol[0]) echo /*$titol." = ".$dadacapitol[0]."?<p>*/"ja existeix, i dura $minuts minuts!<p><p><p><p>";
	
	//si no ho està, actualitzo!
	else {
		if ($minuts > 18) {
			echo $titol." = ".$dadacapitol[0]."?<p><p>";
			$capitol = $dades->channel->addChild('item');
			$capitol->addChild('title', $dadacapitol[0]);
			$capitol->addChild('link', 'http://www.ccma.cat'.$links[0]);
			$capitol->addChild('description', $dadacapitol[1]);
			
			//insereixo HD
			$enclosure = $capitol->addChild('enclosure'); 
			$enclosure->addAttribute('url', $dadacapitol[3]); 
			$enclosure->addAttribute('length', mida($dadacapitol[3])); 
			$enclosure->addAttribute('type', 'video/mp4');  
			
			$capitol->addChild('pubDate', gmdate('D, d M Y H:i:s \G\M\T', time()));
			
			$dades->asXML($xmlFile);
			echo "inserit l'episodi '$dadacapitol[0]'<p>";	
			
			//fico maco el xml
			$dom = new DOMDocument('1.0');
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput = true;
			$dl = @$dom->load($xmlFile); // remove error control operator (@) to print any error message generated while loading.
			if ( !$dl ) die('Error while parsing the document: ' . $xmlFile);
			$dom->save($xmlFile);  
		}
		else {
			echo "Podcast no actualitzat, però vídeo corrupte. A esperar que pugin el correcte...";
		}
	}
}

?>

</body>
</html>