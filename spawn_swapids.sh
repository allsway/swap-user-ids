#!/bin/bash

ini_file="$1"
patronchunk=5000 #batches of 5000 patrons at a time
totalpatrons=26740
#don't want to call the API > 25 times concurrently, and leaving some room 
if  (($(($totalpatrons/$patronchunk)) < 21)); then
	for (( i=0; i <= $totalpatrons; i+=patronchunk )) 
	do 
		echo $i
		# Spawn php script for each 
		nohup php ./swapids_xml.php $i $patronchunk $ini_file > logfile${i}.out &
	done
else
	echo "Exceeding number of allowed API calls, adjust chunking"
fi
