<?php
	
/*
	Swaps the primary user id value with a specified alternative ID field.  Makes multiple commits to user record in order to change the user ID fields. 
	
*/

<?php
	


/*
***************

	Gets the user from the Alma user GET API

***************
*/
	function getxml($url)
	{
		$curl = curl_init();
	        curl_setopt($curl,CURLOPT_URL, $url);
	        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
	        $result = curl_exec($curl);
	        curl_close($curl);
	        try
	        {
	        	// Check for limit error
				$xml = new SimpleXMLElement($result);
				if ($xml->errorsExist == "true" )
				{
					shell_exec('echo `date` ' . $xml->errorList->error->errorCode . " : " .  $xml->errorList->error->errorMessage .  ' >> mattype_errors.log');
					if($xml->errorList->error->errorCode == "DAILY_THRESHOLD" || $xml->errorList->error->errorCode == "PER_SECOND_THRESHOLD")
					{
						exit;
					}
				}
				else
				{	
					return $xml;
				}
	        }
	        catch(Exception $exception)
	        {
	        	shell_exec('echo `date`  ' . $url . ' >> swapids_errors.log');
	        	echo $exception;
	        	shell_exec('echo `date` ' . $exception . ' >> swapids_errors.log');

	        }
	}

/*
**************

	Call to the Alma PUT API to update the user ID
	
***************
*/
	function putxml($url,$body)
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/xml"));
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$response = curl_exec($curl);
		curl_close($curl);
		try 
		{
			$xml = new SimpleXMLElement($response);
			if ($xml->errorsExist == "true" )
			{
				shell_exec('echo `date` ' . $xml->errorList->error->errorCode . " : " .  $xml->errorList->error->errorMessage .  ' >> swapids_errors.log');
			}
			else
			{
				return $xml;
        	}			
		}
		catch(Exception $exception)
		{
			echo $exception;
			shell_exec('echo `date` ' . $exception . ' >> swapids_errors.log');
		}

	}
	
/*
***************
	
	Creates XML response from simplexml element
	
***************
*/	
	function makexml($xml)
	{
		$doc = new DOMDocument();
		$doc->formatOutput = TRUE;
		$doc->loadXML($xml->asXML());
		$return_xml = $doc->saveXML();
		return $return_xml;
	}	
	
	
/*
***************
	
	Adds new user identifier in the format
	<user_identifier segment_type="External">
		<id_type desc="Additional ID 2">OTHER_ID_2</id_type>
		<value>primary_id</value>
		<status>ACTIVE</status>
	</user_identifier>
	
***************	
*/	
	function addidentifier($xml,$primary_id,$new_id_type)
	{
		$new_identifier = $xml->user_identifiers->addChild('user_identifier');
		$new_identifier->addAttribute('segment_type', 'External');
		$id_type = $new_identifier->addChild('id_type', $new_id_type);
		$id_type->addAttribute('desc', 'Additional ID 3');
		$value = $new_identifier->addChild('value',$primary_id);
		$status = $new_identifier->addChild('status','ACTIVE');
		return $xml;
	}
	
/*
***************
	
	Main
	Read in campus API parameters
		
***************
*/		
	$swap_ini = $argv[3];
	$ini_array = parse_ini_file($swap_ini);

	$key= $ini_array['apikey'];
	$baseurl = $ini_array['baseurl'];
	$campuscode = $ini_array['campuscode'];
	$total_patrons = $ini_array['total_users'];
	$id_type_to_swap = $ini_array['id_type_to_swap'];
	$new_id_type = $ini_array['new_id_type'];
	
	// Setting the initial API parameters: start at 0, offset 0
	$limit = 100;
	$offset = $argv[1];
	$total_patrons = $argv[2]; // always 5000


	for($i=0; $i<=$total_patrons; $i+=$limit)
	{
		$url =  $baseurl . '/almaws/v1/users?apikey='.$key.'&limit='.$limit.'&offset='.$offset; 
		$xml = getxml($url);
		
		foreach($xml->user as $user)
		{
			$swap = false;
			/*
				Get primary IDs 
			*/
			$primary_id = $user->primary_id.'';
			echo $primary_id . PHP_EOL;
			if((strlen($primary_id) > 0)  && !(preg_match('/\s/',$primary_id) ) && !((preg_match('/;/',$primary_id))))
			{
				$userurl = $baseurl . '/almaws/v1/users/' . $primary_id . '?apikey='.$key;
				$patron_xml = getxml($userurl);
			
				$count = 0;

		
				if(isset($patron_xml->user_identifiers->user_identifier))
				{
					echo $primary_id;
					foreach($patron_xml->user_identifiers->user_identifier as $user_identifier)
					{
						if($user_identifier->id_type == $id_type_to_swap)
						{
							$count++;
						}
					}	
					echo "Count: " . $count . PHP_EOL;
					foreach($patron_xml->user_identifiers->user_identifier as $user_identifier)
					{
						if($count == 1)
						{
							if($user_identifier->id_type == $id_type_to_swap)
							{
								$new_primary = $user_identifier->value.'';
								if( preg_match("/[A-Za-z]/",$primary_id) && (strlen($new_primary) > 2) && !(preg_match('/[#$%@*() \/{}?]/',$new_primary)))
								{
									$dom = dom_import_simplexml($user_identifier);
									$dom->parentNode->removeChild($dom);
									$swap = true; 
								}
							}

						}
						else if($count < 1)
						{
							// Do something if there are no UNIV ID fields 
							// Shouldn't be the case?
							shell_exec('echo `date` No ' . $id_type_to_swap .' fields found for ' . $primary_id . ' >> swapids_errors.log'); 

						}
						else
						{
							// There are multiple UNIV ID fields. Have to ask what to do in this case.  				
							// This doesn't actually exist in practice.  
							shell_exec('echo `date` Multiple '.$id_type_to_swap.' fields found for ' . $primary_id . ' >> swapids_errors.log'); 
						}

					}				
					/*
						Swap ids
					
						Do this with multiple commits/PUT requests
						1st remove alt ID field 
						2nd: change the primary_id 
						3rd request: add user identifier OTHER_ID_1 as additional identifier
					*/
					
					// Check if there is an additional ID that should be swapped, and that our original ID is not currently already swapped
					if ($swap)
					{
									
						// First get/put 
						// Remove second identifier
						var_dump($patron_xml);						
					
				
						$return_xml =  makexml($patron_xml);
						$puturl = $baseurl . '/almaws/v1/users/' . $primary_id .'?user_id_type=all_unique&apikey='.$key;
						echo $puturl . PHP_EOL;
						$response = putxml($puturl,$return_xml);										
						shell_exec('echo `date` Removed old additional ID: ' .$new_primary . ', for : ' .$primary_id . ' >> swapids_errors.log');

					
						// Second get/put
						$updated_user_url = $baseurl . '/almaws/v1/users/' . $primary_id . '?apikey='.$key;
						$updated_user_xml = getxml($updated_user_url);

						$updated_user_xml->primary_id = $new_primary;
						$updated_return_xml = makexml($updated_user_xml);
						$second_response = putxml($puturl,$updated_return_xml);
					
					
						// Third get/put to user API, additional of final original ID to additional ID field 
						$new_user_url = $baseurl . '/almaws/v1/users/' . $new_primary . '?apikey='.$key;
						$second_xml = getxml($new_user_url);
						//var_dump($second_xml);
						$second_xml = addidentifier($second_xml,$primary_id,$new_id_type);
						$third_return_xml = makexml($second_xml);
						$final_response = putxml($new_user_url,$third_return_xml);

						shell_exec('echo `date` Successful swap for user old primary: ' .$primary_id . ', New primary: ' .$new_primary . ' >> swapids_errors.log');
					
					}	

				}
				else
				{
					shell_exec('echo `date` No additional IDs found for the user:'.$primary_id.'  >> swapids_errors.log');
				}
			
			}
			else
			{
				shell_exec('echo `date` No primary_id for user with name: ' .$user->last_name. ','. $user->first_name . ' >> swapids_errors.log');
			}	
		}
			
		shell_exec( 'echo `date` Current memory usage: ' . memory_get_usage() . ' >> swapids_errors.log');
	
		$offset += $limit;
	}	

?>



