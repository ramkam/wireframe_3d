wireframe_3d
============

Quick and dirty attempt to mod gcode to wireframe gcode, with the intent of getting an output similar to that (i guess it's not at all the same algo, i have no idea of what they have used): http://stefaniemueller.org/WirePrint/


Example of what it does : 
http://imgur.com/4ympXY5,g4Zyg7g

Algo:

. Load gcode, parse & store points corresponding to inner and outer walls, and calculate average E/mm

. downsample the number of points $n (1 point every n points), and $nl (1 layer every nl layers)

. Parse stored points layer by layer, and, for each point, draw a path to the nearest point on the layer

. generate wired mesh gcode, with some cleaning (if a move is over $maxDist then do a G0 instead of G1), recalculate E by using the average extrude rate

--

It's very buggy so far. just a proof of concept. 
I run it like this : 

php -f gcode_parser.php | grep ^G > test.gcode

enjoy ^^