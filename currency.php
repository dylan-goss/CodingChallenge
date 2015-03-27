<?
	ini_set("log_errors", 1);
	ini_set("error_log", "currency_errors.log");

	$host = "localhost";
	$username = "username";
	$password = "password";
	$database = "database";
	$xml_url = "https://wikitech.wikimedia.org/wiki/Fundraising/tech/Currency_conversion_sample?ctype=text/xml&action=raw";

	$link = mysqli_connect($host, $username, $password, $database);

	//Check database connection
	if (mysqli_connect_errno()) 
	{
		error_log("Connect failed: ".mysqli_connect_error());
		exit();
	}

	//Convert $_GET to $argv - this allows the script to be usable from both command line and webserver
	if(count($_GET) > 0)
	{
		$argv = array();
		$argv[0] = "currency.php";

		foreach($_GET as $key => $value)
		{
			$i = count($argv);

			$argv[$i] = $key;

			$i = count($argv);

			$argv[$i] = $value;
		}
	}

	if(!isset($argv))
		$argv = null;

	if($argv == null || count($argv) == 1)
	{
		retrieve_rates($xml_url);
		exit();
	}
	else if(count($argv) == 3 && $argv[2] != '')
	{
		single_conversion($argv[1], $argv[2]);
		exit();
	}
	else if(count($argv) == 3)
	{
		array_conversion($argv[1]);
		exit();
	}
	else
	{
		print_r("Invalid number of arguments");
		exit();
	}

	function single_conversion ($code, $amount)
	{
		global $link;

		$code = mysqli_real_escape_string($link, $code);
		$amount = mysqli_real_escape_string($link, $amount);

		$stmt = mysqli_prepare($link, "SELECT rate FROM currency WHERE code = ?");
		mysqli_stmt_bind_param($stmt, "s", $code);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $rate);
		mysqli_stmt_fetch($stmt);

		$total = $rate * $amount;
		$total = number_format($total, 2, '.', '');
		printf("USD %s\n", $total);
	}

	function array_conversion ($array)
	{
		/* This requires a very specific formatting of the input array to be of use.
		   I believe it could be improved by simply extending the single conversion input to allow for multiples. */

		global $link;

		//Accounting for the fact that the space character can get converted to an underscore
		$array = str_replace("_", " ", $array);

		preg_match_all('/".*?"|\'.*?\'/', $array, $matches);
		
		if($matches === false)
		{
			error_log("Invalid array input");
			exit();
		}

		//Cleaning up after preg_match_all();
		$matches = $matches[0];

		$x = 0;

		$output_array = "array(";
		
		while(isset($matches[$x]))
		{
			$matches[$x] = trim($matches[$x], "'");

			$exp = explode(" ", $matches[$x]);

			//Accounting for the fact that the decimal character can get converted to a space
			if(count($exp) > 2)
			{
				$code = $exp[0];

				array_splice($exp, 0, 1);

				$amount = implode(".", $exp);
			}
			else
			{
				$code = $exp[0];
				$amount = $exp[1];
			}

			$code = mysqli_real_escape_string($link, $code);
			$amount = mysqli_real_escape_string($link, $amount);

			$stmt = mysqli_prepare($link, "SELECT rate FROM currency WHERE code = ?");
			mysqli_stmt_bind_param($stmt, "s", $code);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_bind_result($stmt, $rate);
			mysqli_stmt_fetch($stmt);

			$total = $rate * $amount;
			$total = number_format($total, 2, '.', '');
			
			$output_array .= "'USD ".$total."',";

			$x++;
		}

		$output_array = rtrim($output_array, ",");

		$output_array .= ")";

		print_r($output_array);
	}

	function retrieve_rates ($url)
	{
		global $link;

		//Verify the link provides a valid XML file
		if (($response_xml_data = file_get_contents($url))===false)
		{
			error_log("Error fetching XML");
			exit();
		} 
		else 
		{
			libxml_use_internal_errors(true);
			$data = simplexml_load_string($response_xml_data);

			if (!$data) 
			{
				foreach(libxml_get_errors() as $error) 
				{
					error_log($error->message);
				}
				exit();
			}
			else
			{
				//Ensure the XML structure is what we're expecting
				if(isset($data->conversion))
				{
					$x = 0;

					foreach($data->conversion as $conversion)
					{
						if(isset($conversion->currency))
						{
							if(isset($conversion->rate))
							{
								//No error at this stage - update the exchange rate if it exists, insert if it doesn't
								$code = mysqli_real_escape_string($link, $conversion->currency);
								$rate = mysqli_real_escape_string($link, $conversion->rate);

								//There's no such thing as a trusted source - parameterize everything
								$stmt = mysqli_prepare($link, "SELECT id FROM currency WHERE code = ?");
								mysqli_stmt_bind_param($stmt, "s", $code);
								mysqli_stmt_execute($stmt);
								mysqli_stmt_bind_result($stmt, $id);

								$i = 0;

								while(mysqli_stmt_fetch($stmt))
								{
									$i++;
								}

								mysqli_stmt_close($stmt);

								//Delete records if there are multiples of a single currency - something has gone wrong.
								if($i > 1)
								{
									$stmt = mysqli_prepare($link, "DELETE FROM currency WHERE code = ?");
									mysqli_stmt_bind_param($stmt, "s", $code);
									mysqli_stmt_execute($stmt);
									mysqli_stmt_bind_result($stmt, $id);
									mysqli_stmt_close($stmt);
									$i = 0;
								}

								if($i == 0)
								{
									$stmt = mysqli_prepare($link, "INSERT INTO currency (code, rate) VALUES (?, ?)");
									mysqli_stmt_bind_param($stmt, "sd", $code, $rate);
									mysqli_stmt_execute($stmt);
									mysqli_stmt_bind_result($stmt, $id);
									mysqli_stmt_close($stmt);
								}
								else
								{
									$stmt = mysqli_prepare($link, "UPDATE currency SET rate = ? WHERE code = ?)");
									mysqli_stmt_bind_param($stmt, "ds", $rate, $code);
									mysqli_stmt_execute($stmt);
									mysqli_stmt_bind_result($stmt, $id);
									mysqli_stmt_close($stmt);
								}
								
							}
							else
							{
								error_log("Rate element not found in XML at position ".$x);
							}
						}
						else
						{
							error_log("Currency element not found in XML at position ".$x);
							exit();
						}

						$x++;
					}
				}
				else
				{
					error_log("Conversion element not found in XML");
					exit();
				}
			}
		}
	}
?>