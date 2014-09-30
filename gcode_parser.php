<?php
	// Tuned to work with cura output

	$file = "sample.gcode";


	// Lookup for M117
	// ;Layer count: 33		=> Store Nb of layers
	// ;LAYER:0  			=> Change current Layer number
	// G0 					=> Ignore - just a movement ?? maybe dont ignore, dunno
	// G1 					=> Use
	// ;TYPE:SKIRT 			=> Ignore 
	// ;TYPE:WALL-INNER		=> 
	// ;TYPE:WALL-OUTER		=>
	// ;TYPE:FILL 			=> Ignore 

	// Calculate E rate
	// 		Sample E rate from a bunch of moves (delta E / delta positions ) 

	// Add G0's where needed

	// Data model
	// Layers -> sequence of Positions & E

	// Config
	$waitBetweenMoves = 500; //ms
	$n = 15; // Take a node, each n nodes
	$nl = 5; // Take a layer, each nl layers


	// Load file
	$data = file( $file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);


	// Parse & store what's usefull
	$layers = Array();
	$currentLayer = -1;
	$useit = false;
	$currentZ = 0;
	$currentF = 3000;

	$distCovered = 0; // to calculate dE rate
	$posPrev = Array();
	$dErate = 0;
	$dEcumul = 0;


	foreach($data as $l) {
		#DEBUG# echo "$l \n";

		// Update Pointer status => Use it ? Layer no
		$keyword = ";LAYER";
		#DEBUG# echo substr($l, 0, strlen($keyword) ) . "=?=" . "#$keyword#\n";
		if ( substr($l, 0, strlen($keyword) ) == $keyword  ) {
			// Increment & init the structure
			$currentLayer++;
			$layers[$currentLayer] = Array();

			#DEBUG# echo "########################## $keyword ########################## \n";
			#DEBUG# echo "########################## $keyword : $currentLayer ########################## \n";

			// Allow for reading G0's:
			$useit = true;
		}
	
		$keyword = ";TYPE:WALL"; // Or WALL-OUTER ... depending on what ya wanna do
		if ( substr($l, 0, strlen($keyword) ) == $keyword  ) {
			$useit = true;
			#DEBUG# echo "########################## $keyword ########################## \n";
		}

		$keyword = ";TYPE:SKIRT";
		if ( substr($l, 0, strlen($keyword) ) == $keyword  ) {
			$useit = false;
			#DEBUG# echo "########################## $keyword ########################## \n";
		}

		$keyword = ";TYPE:FILL";
		if ( substr($l, 0, strlen($keyword) ) == $keyword  ) {
			$useit = false;
			#DEBUG# echo "########################## $keyword ########################## \n";
		}

		// Collect Gcode Data
		if ( $useit ) {
			$keyword = "G0";
			if ( substr($l, 0, strlen($keyword) ) == $keyword  ) {
				// Detect & Update Z (expecting all Z's to be on the G0's)
				$tmp = explode(" ", $l);

				foreach($tmp as $itm) {
					if ( substr($itm,0,1) == "Z" ) {
						$currentZ = substr($itm,1);
					}
					if ( substr($itm,0,1) == "F" ) {
						$currentF = substr($itm,1);
					}
				}
			}

			$keyword = "G1";
			if ( substr($l, 0, strlen($keyword) ) == $keyword  ) {
				// Store X Y E
				$pos = Array();
				$tmp = explode(" ", $l);

				foreach($tmp as $itm) {
					if ( substr($itm,0,1) == "X" ) {
						$pos["X"] = substr($itm,1);
					}
					if ( substr($itm,0,1) == "Y" ) {
						$pos["Y"] = substr($itm,1);
					}
					if ( substr($itm,0,1) == "Z" ) {
						$pos["Z"] = substr($itm,1);
					}
					if ( substr($itm,0,1) == "E" ) {
						$pos["E"] = substr($itm,1);
					}
					if ( substr($itm,0,1) == "F" ) {
						$pos["F"] = substr($itm,1);
					}
				}

				if ( ! isset($pos["Z"]) ) {
					$pos["Z"] = $currentZ;
				}
				if ( ! isset($pos["F"]) ) {
					$pos["F"] = $currentF;
				}

				$layers[$currentLayer][] = $pos;

				// Approximate dE
				if ( isset($posPrev['X'])  ) {
					$distCovered += distance($pos, $posPrev);
					$dEcumul += ($pos['E'] - $posPrev['E']) ;

					if ( $distCovered <> 0 ) {
						$dErate = $dEcumul / $distCovered;
					} 

					/*
					echo "##POS: ";
					print_r($pos);
					echo "##POSRPEV: ";
					print_r( $posPrev );
					echo "##DISTCOVERED: $distCovered\n";
					echo "##dEcumul $dEcumul \n";
					echo "##dErate $dErate \n";
					*/


				}


				$posPrev = $pos;


			} // G1
		} // Use it



	} // foreach $l

	// Done populating usefull data
	#DEBUG#  print_r($layers); die();

	#DEBUG# echo "##### dErate $dErate \n";

	// Postprocessing goes here

	// Downsample mesh
	//moved to top - $n = 15; // Take a node, each n nodes
	//$nl = 5; // Take a layer, each nl layers

	$layersSimple = Array();

	foreach( $layers as $k=>$nodes ) {
		if ( intval($k / $nl) == ($k/$nl) ) {
			$layersSimple[$k] = Array();

			foreach ($nodes as $i=>$node) {
				if ( intval($i/$n) == ($i/$n)) {
					$layersSimple[$k][] = $node;
				}
			}

		}
	}

	#DEBUG# 	print_r($layersSimple);
	/*
	foreach($layers as $k=>$nodes) {
		echo "Layer $k has " . count($nodes) . " nodes \n";
	}
	foreach($layersSimple as $k=>$nodes) {
		echo "LayerSimple $k has " . count($nodes) . " nodes \n";
	}
	*/



	// Alter movements

	// Draw layer k
	// then, 
	// for a given node at layer k
	// lookup for a nearby node on layer k+1, that is on the right, or on the tangeant but not the left ... (nozzle issues)
	// and trace a line to p at k+1 and go to p+1 at k

	$E = 0; // not exactly, but let's do it like that for now, FIX IT LATER... like reset E
	$prevPoint = Array();

	foreach( $layersSimple as $k=>$nodes ) {
		// Goto Node 0 without extruding
		echo "G0" . ' X' . $nodes[0]['X'] . ' Y' . $nodes[0]['Y'] . ' Z' . $nodes[0]['Z'] . "\n";


		foreach( $nodes as $node ) {

			// Draw the layer

			#DEBUG# echo "SAME LAYER $k : G1" . ' X' . $node['X'] . ' Y' . $node['Y'] . ' Z' . $node['Z'] . "\n";
			$E += ($dErate * distance($node, $prevPoint) );
			if ( distance($node, $prevPoint) > $maxDist ) {
				echo "G0" . ' X' . $node['X'] . ' Y' . $node['Y'] . ' Z' . $node['Z'] . "\n";
			} else {
				echo "G1" . ' X' . $node['X'] . ' Y' . $node['Y'] . ' Z' . $node['Z'] . ' E' . $E . "\n";
				echo "G4 P$waitBetweenMoves\n"; // wait for z ms
			}

			$prevPoint = $node;

			// find nearby (not too far, not too close) node at z+1
			$nodesUp = $layersSimple[$k+$nl];
			$bestDist = 999999;
			$bestkk = false;
			$bestNode = Array();
			$minDist = -1; // -1 = disabled
			$maxDist = 3*($nl/2); // 99999 = disabled

			// lookup
			foreach( $nodesUp as $kk=>$nodeUp ) {
				$dist = distance($node, $nodeUp);
				if ( $dist < $bestDist && $dist >= $minDist && $dist <= $maxDist ) {
					$bestDist = $dist;
					$bestkk = $kk;
					$bestNode = $nodeUp;
				}
			}

			// if there's a point on layer +1...
			if ( ! ($bestkk === false) ) {
				#DEBUG# echo "UPPER LAYER $k+$nl: G1 " . ' X' . $bestNode['X'] . ' Y' . $bestNode['Y'] . ' Z' . $bestNode['Z'] . "\n";			
				$E += ($dErate * distance($bestNode, $prevPoint) );
				echo "G1" . ' X' . $bestNode['X'] . ' Y' . $bestNode['Y'] . ' Z' . $bestNode['Z'] . ' E' . $E . "\n";
				echo "G4 P$waitBetweenMoves\n"; // wait for z ms

				$prevPoint = $node;
			}


		}
	}


	function distance($node1, $node2) {
		$dX = $node2['X'] - $node1['X'];
		$dY = $node2['Y'] - $node1['Y'];
		$dZ = $node2['Z'] - $node1['Z'];

		$dist = sqrt( $dX*$dX + $dY*$dY + $dZ*$dZ );

		return $dist;
	}

?>
