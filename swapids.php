<?php
	
	require 'vendor/autoload.php';
	use Guzzle\Http\Client;

	function getxml($url)
	{
		$curl = curl_init();
        curl_setopt($curl,CURLOPT_URL, $url);
        curl_setopt($curl,CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        curl_close($curl);
        if(isset($result))
        {
			$xml = new SimpleXMLElement($result);
			return $xml;
        }
        else
        {
            return -1;
        }

	}
	
	/*
	  
	
	*/
	function putxml($url,$body)
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/xml"));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $body);


		$response = curl_exec($curl);
		curl_close($curl);
		try 
		{
			$xml = new SimpleXMLElement($response);
			return $xml;
		}
		catch(Exception $exception)
		{
			echo $exception;
			shell_exec('echo `date` ' . $exception . ' >> swap_errors.log');
			exit;
		}

	}

	$ini_array = parse_ini_file("swapids.ini");

	$key= $ini_array['apikey'];
	$baseurl = $ini_array['baseurl'];
	$campuscode = $ini_array['campuscode'];
	
	$total_patrons =  10; //27447;
	$limit = 1;
	$offset = 0;
	$count = 0;

	//for($i=0; $i<=$total_patrons; $i+=$limit)
	//{
		$url =  $baseurl . '/almaws/v1/users?apikey='.$key.'&limit='.$limit.'&offset='.$offset; 
		$xml = getxml($url);
		
		foreach($xml->user as $user)
		{
			$swap = false;
			/*
				Get primary IDs 
			*/
			$primary_id= $user->primary_id;
			
		//	$userurl = $baseurl . '/almaws/v1/users/' . $primary_id . '?apikey='.$key;
			$userurl = $baseurl . '/almaws/v1/users/' . 'jonah' . '?apikey='.$key;
			$patron_xml = getxml($userurl);
			$id_type_to_swap = 'UNIV_ID';
			$match = 0;
			$old_primary = $patron_xml->primary_id;	
			$safe_value = $old_primary;

			foreach($patron_xml->user_identifiers->user_identifier as $user_identifier)
			{
				if($user_identifier->id_type == $id_type_to_swap)
				{
					$count++;
				}
				
			}			

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
				*/
				if ($swap)
				{
					$old_primary = $patron_xml->primary_id;	
					$patron_xml->user_identifiers->user_identifier[$match]->value = $old_primary;
					$patron_xml->primary_id = $new_primary;
					$patron_xml->user_identifiers->user_identifier[$match]->status = 'ACTIVE';
					$patron_xml->user_identifiers->user_identifier[$match]->id_type = 'OTHER_ID_1';
					var_dump($patron_xml);
					$puturl = $baseurl . '/almaws/v1/users/' . 'jonah' . '?user_id_type=all_unique&apikey='.$key;
					
					$doc = new DOMDocument();
					$doc->formatOutput = TRUE;
					$doc->loadXML($patron_xml->asXML());
					$return_xml = $doc->saveXML();
					$response = putxml($puturl,$return_xml);					

					var_dump($response);
				}	
		}
	//	$offset += 100;
	//}	

?>















