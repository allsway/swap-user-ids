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
					if($xml->errorsExist->errorList->error->errorCode == "DAILY_THRESHOLD" || $xml->errorsExist->errorList->error->errorCode == "PER_SECOND_THRESHOLD")
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
	        	echo $url . PHP_EOL;
	        	shell_exec('echo `date`  ' . $url . ' >> swapids_error.log');
	        	echo $exception;
	        	shell_exec('echo `date` ' . $exception . ' >> swapids_error.log');

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
		var_dump($response);
		curl_close($curl);
		try 
		{
			$xml = new SimpleXMLElement($response);
			if ($xml->errorsExist == "true" )
			{
				shell_exec('echo `date` ' . $xml->errorList->error->errorCode . " : " .  $xml->errorList->error->errorMessage .  ' >> swapid_error.log');
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
	
	Main
	Read in campus API parameters
		
***************
*/		
	$ini_array = parse_ini_file("swapids.ini");

	$key= $ini_array['apikey'];
	$baseurl = $ini_array['baseurl'];
	$campuscode = $ini_array['campuscode'];
	$total_patrons = $ini_array['total_users'];
	
	
	// Setting the initial API parameters: start at 0, offset 0
	$limit = 1;
	$offset = 100;
	$count = 0;

//	for($i=0; $i<=$total_patrons; $i+=$limit)
//	{
		$url =  $baseurl . '/almaws/v1/users?apikey='.$key.'&limit='.$limit.'&offset='.$offset; 
		echo $url;
		$xml = getxml($url);
		
		foreach($xml->user as $user)
		{
			$swap = false;
			/*
				Get primary IDs 
			*/
			$primary_id = $user->primary_id;
			
			$userurl = $baseurl . '/almaws/v1/users/' . $primary_id . '?apikey='.$key;
			$patron_xml = getxml($userurl);
			
			$id_type_to_swap = 'OTHER_ID_1';
			$match = 0;
			$safe_value = $primary_id.'';

			foreach($patron_xml->user_identifiers->user_identifier as $user_identifier)
			{
				if($user_identifier->id_type == $id_type_to_swap)
				{
					$count++;
				}
				
			}			
			if(isset($patron_xml->user_identifiers->user_identifier))
			{
				foreach($patron_xml->user_identifiers->user_identifier as $user_identifier)
				{
					$j = 0;
					if($count == 1)
					{
						if($user_identifier->id_type == $id_type_to_swap)
						{
							$new_primary = $user_identifier->value.'';
							$swap = true; 
							$j++;
							$match = $j;
						}

					}
					else if($count < 1)
					{
						// Do something if there are no UNIV ID fields 
						// Shouldn't be the case?
						shell_exec('echo `date` No UNIV ID fields found for ' . $primary_id . ' >> swap_errors.log'); 

					}
					else
					{
						// There are multiple UNIV ID fields. Have to ask what to do in this case.  				
						// This doesn't actually exist in practice.  
						shell_exec('echo `date` Multiple UNIV ID fields found for ' . $primary_id . ' >> swap_errors.log'); 
					}
				}				
				/*
					Swap ids
					
					Do this with *2* PUT requests
					1st remove alt ID field 
					2nd: change the primary_id 
					3rd request: add user identifier OTHER_ID_1 as additional identifier
				*/
				if ($swap)
				{
				
					echo PHP_EOL . $primary_id . PHP_EOL;
					
					// First get/put 
					//Remove second identifier
					echo $match . PHP_EOL;
					var_dump($patron_xml->user_identifiers);
					$dom = dom_import_simplexml($patron_xml->user_identifiers->user_identifier[$match]);
       					$dom->parentNode->removeChild($dom);

					$return_xml =  makexml($patron_xml);
					var_dump($return_xml);
					$puturl = $baseurl . '/almaws/v1/users/' . $primary_id .'?user_id_type=all_unique&apikey='.$key;

					
					// Make PUT change request
					$response = putxml($puturl,$return_xml);										

					// Second get/put
					$updated_user_url = $baseurl . '/almaws/v1/users/' . $primary_id . '?apikey='.$key;
			     		$updated_user_xml = getxml($updated_user_url);

					$updated_user_xml->primary_id = $new_primary;
					$updated_return_xml = makexml($updated_user_xml);
					$second_response = putxml($puturl,$updated_return_xml);

					
					// Third call to user API 
					$new_user_url = $baseurl . '/almaws/v1/users/' . $new_primary . '?apikey='.$key;
			     		$second_xml = getxml($new_user_url);

					$new_identifier = $second_xml->user_identifiers->addChild('user_identifier');
					$new_identifier->addAttribute('segment_type', 'External');
					$id_type = $new_identifier->addChild('id_type', 'OTHER_ID_1');
					$id_type->addAttribute('desc', 'Additional ID 1');
					$value = $new_identifier->addChild('value',$primary_id);
					$status = $new_identifier->addChild('status','ACTIVE');
					
					$third_return_xml = makexml($second_xml);
					var_dump($third_return_xml);
					$final_response = putxml($new_user_url,$third_return_xml);

				}	
			}
		}
	//	$offset += 100;
	//}	

?>





