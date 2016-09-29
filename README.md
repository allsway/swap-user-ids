# swap-user-ids
Switches user primary ID with university ID

######swapids_xml.php
Takes as arguemnts:
   - offset value
   - total number of patrons that should be updated on that call (~16 records update per minute)
   - swapids.ini
   
######swapids.ini
```
;Campus API key
apikey = ""
baseurl = "https://api-na.hosted.exlibrisgroup.com"
;Campus Alma code
campuscode = ""
total_users = ""
;The code for the ID type that contains the employee ID number (OTHER_ID_1, OTHER_ID_2, UNIV_ID, BARCODE etc)
id_type_to_swap = ""
;The code for the new ID type that will contain the former primary_id value (options same as above)
new_id_type = ""
```

######spawn_swapids.sh
Takes as argument:
   - swapids.ini

Use spawn_swapids.sh to run over all users in your system. Defaults to running concurrent batches of 5000 users. 

Run as 
`sh spawn_swapids swapids.ini`

Single run as 
`nohup php ./swapids_xml.php {offset} {total number of patrons you want to update} swapids.ini > logfile.out &`

######swapids_errors.log
Logs all successful ID swaps, and failures due to validiation of the data

######logfile{num}.out
Logs any xml rendering errors, php fatal errors. 

