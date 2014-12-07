<?PHP

$programes = array (
	//programa => array("títol", "url dels capítols", "patró que identifica com enllaç de capítol, "fitxer xml del podcast");
    "polonia"  => array("Polònia", "http://www.ccma.cat/tv3/alacarta/polonia/", "/Polonia-", "polonia.xml"),
    "puntcat" => array(".CAT", "http://www.ccma.cat/tv3/alacarta/puntcat/ultims-programes/", "-CAT", "puntcat.xml"),
    "elcarc"   => array("El crac", "http://www.ccma.cat/tv3/alacarta/el-crac/", "/Capitol", "elcrac.xml")
);

//funció per obtenir tamany del vídeo
function mida($url)
{
	$data = get_headers($url, true);
	if (isset($data['Content-Length']))
		return (int) $data['Content-Length'];
}

foreach ($programes as $programa)
{
	$url = $programa[1];
	$patro = $programa[2];
	$xmlFile = $programa[3];
	
	echo "<br/ ><h1>".$programa[0]."</h1>";
	
	//parsejo la url del programa buscant links (original parsing code by Chirp Internet: www.chirp.com.au)
	$input = @file_get_contents($url) or die("no es pot carregar $url");
	$regexp = "<a\s[^>]*href=(\"??)([^\" >]*?)\\1[^>]*>(.*)<\/a>";
	$capitols = array();
	$links = array();
	
	//fico tots els links en un array
	if(preg_match_all("/$regexp/siU", $input, $matches)) {
		foreach ($matches[2] as $value) {		
			//miro si el link és un capítol complert a través d'un patró de l'enllaç
			if (strpos($value,$patro) !== false) {
				//agafo només el codi del vídeo i miro que no aparegui més d'una vegada
				$codi = substr($value, strrpos(substr($value, 0, -1), "/")+1, -1);
				if (!in_array($codi, $capitols)) { array_push($capitols, $codi); array_push($links, $value); }
			}
		}
	}

	
	//agafo la informació dels capítols:

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
	
	//miro si el podcast està actualitzat
	$titol = $dades->channel->item[$ultimcapitol]->title;
	if ($titol == $dadacapitol[0]) echo $titol." = ".$dadacapitol[0]."?<p><p>ja existeix!<p><p><p><p>";
	
	//si no ho està, actualitzo!
	else {
		echo $titol." = ".$dadacapitol[0]."?<p><p>";
		$capitol = $dades->channel->addChild('item');
		$capitol->addChild('title', $dadacapitol[0]);
		$capitol->addChild('link', 'http://www.ccma.cat'.$links[0]);
		$capitol->addChild('description', $dadacapitol[1]);
		$enclosure = $capitol->addChild('enclosure'); 
					   $enclosure->addAttribute('url', $dadacapitol[2]); 
					   $enclosure->addAttribute('length', mida($dadacapitol[2])); 
					   $enclosure->addAttribute('type', 'video/mp4');  
		$capitol->addChild('pubDate', gmdate('D, d M Y H:i:s \G\M\T', time()));
		
		$dades->asXML($xmlFile);
		echo "inserit<p>";	
		
		//fico maco el xml
		$dom = new DOMDocument('1.0');
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dl = @$dom->load($xmlFile); // remove error control operator (@) to print any error message generated while loading.
		if ( !$dl ) die('Error while parsing the document: ' . $xmlFile);
		$dom->save($xmlFile);  
	}
}

?>
