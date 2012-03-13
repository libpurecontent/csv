<?php

# Version 1.1.1

# Load required libraries
require_once ('application.php');

# Class for manipulation of CSV files
class csv
{
	# Function to write to a file
	function createNew ($filename, $headersArray, $delimiter = ',')
	{
		# Convert the headers array into a string
		$headers = implode ($delimiter, $headersArray);
		
		# Create the file and return the success status
		return application::createFileFromFullPath ($filename, $headers);
	}
	
	
	# Wrapper function to get CSV data
	#!# Consider further file error handling
	#!# Need to merge this with application::getCsvData
	function getData ($filename, $stripKey = true, $hasNoKeys = false)
	{
		# Get the headers
		#!# Not entirely efficient, but API becomes messy otherwise
		if (!$headers = csv::getHeaders ($filename)) {return false;}
		
		# Open the file
		if (!$fileHandle = fopen ($filename, 'rb')) {return false;}
		
		# Determine the longest line length
		$longestLineLength = 1000;
		$array = file ($filename);
		for ($i = 0; $i < count ($array); $i++) {
			if ($longestLineLength < strlen ($array[$i])) {
				$longestLineLength = strlen ($array[$i]);
			}
		}
		unset ($array);
		
		# Loop through each line of data
		$data = array ();
		$counter = 0;
		while ($csvData = fgetcsv ($fileHandle, $longestLineLength + 1)) {
			
			# Skip if in the first (header) row
			if (!$counter++) {continue;}
			
			# Check the first item exists and set it as the row key then unset it
			if ($rowKey = $csvData[0]) {
				if ($stripKey) {unset ($csvData[0]);}
				if ($hasNoKeys) {$rowKey = $counter - 1;}
				
				# Loop through each item of data
				#!# What should happen if a row has fewer columns than another? If there are fields missing, then it may be better to allow offsets to be generated as otherwise the data error may not be known. Filling in the remaining fields is probably wrong as we don't know which are missing.
				foreach ($csvData as $key => $value) {
					
					# Assign the entry into the table
					if (isSet ($headers[$key])) {$data[$rowKey][$headers[$key]] = $value;}
				}
			}
		}
		
		# Close the file
		fclose ($fileHandle);
		
		# Return the result
		return $data;
	}
	
	
	# Function to get the headers (i.e. the first line)
	function getHeaders ($filename)
	{
		# Open the file
		if (!is_readable ($filename)) {return false;}
		$fileHandle = fopen ($filename, 'rb');
		
		# Get the column names
		$headers = fgetcsv ($fileHandle, filesize ($filename) + 1);
		
		# Close the file
		fclose ($fileHandle);
		
		# If there are no headers, return false
		if (!$headers) {return false;}
		
		# Return the headers
		return $headers;
	}
	
	
	# Wrapper function to turn a (possibly multi-dimensional) associative array into a correctly-formatted CSV format (including escapes)
	function arrayToCsv ($array, $delimiter = ',', $nestParent = false)
	{
		# Start an array of headers and the data
		$headers = array ();
		$data = array ();
		
		# Loop through each key value pair
		foreach ($array as $key => $value) {
			
			# If the associative array is multi-dimensional, iterate and thence add the sub-headers and sub-values to the array
			if (is_array ($value)) {
				list ($subHeaders, $subData) = csv::arrayToCsv ($value, $delimiter, $key);
				
				# Merge the headers and subkeys
				$headers[] = $subHeaders;
				$data[] = $subData;
				
			# If the associative array is multi-dimensional, assign directly
			} else {
				
				# In nested mode, prepend the each key name with the parent name
				if ($nestParent) {$key = "$nestParent: $key";}
				
				# Add the key and value to arrays of the headers and data
				$headers[] = csv::safeDataCell ($key, $delimiter);
				$data[] = csv::safeDataCell ($value, $delimiter);
			}
		}
		
		# Compile the header and data lines, placing a delimeter between each item
		$headerLine = implode ($delimiter, $headers) . (!$nestParent ? "\n" : '');
		$dataLine = implode ($delimiter, $data) . (!$nestParent ? "\n" : '');
		
		# Return the result
		return array ($headerLine, $dataLine);
	}
	
	
	# Function to convert CSV string to an array - from www.php.net/manual/en/function.fgetcsv.php#50186
	function csvtoArray ($content, $delimiter = ',', $enclosure = '"', $optional = 1)
	{
		# Define a regexp
		$regexp = '/(('.$enclosure.')'.($optional?'?(?(2)':'(').'[^'.$enclosure.']*'.$enclosure.'|[^'.$delimiter.'\r\n]*))('.$delimiter.'|\r\n)/smi';
		
		# Perform the matches
		preg_match_all ($regexp, $content, $matches);
		
		# Assemble the data
		//application::dumpData ($matches);
		$data = array ();
		$linecount = 0;
		for ($i = 0; $i <= count ($matches[3]); $i++) {
			$data[$linecount][] = $matches[1][$i];
			if ($matches[3][$i] != $delimiter) {
				$linecount++;
			}
		}
		
		# Return the array
		return $data;
	}
	
	
	
	# Called function to make a data cell CSV-safe
	function safeDataCell ($data, $delimiter = ',')
	{
		#!# Consider a flag for HTML entity cleaning so that e.g. " rather than &#8220; appears in Excel
		
		# Double any quotes existing within the data
		$data = str_replace ('"', '""', $data);
		
		# Strip carriage returns to prevent textarea breaks appearing wrongly in a CSV file opened in Windows in e.g. Excel
		$data = str_replace ("\r", '', $data);
		#!# Try replacing the above line with the more correct
		# $data = str_replace ("\r\n", "\n", $data);
		
		# If an item contains the delimiter or line breaks, surround with quotes
		if ((strpos ($data, $delimiter) !== false) || (strpos ($data, "\n") !== false) || (strpos ($data, '"') !== false)) {$data = '"' . $data . '"';}
		
		# Return the cleaned data cell
		return $data;
	}
	
	
	# Function to convert a multi-dimensional keyed array to a CSV
	function dataToCsv ($data, $headers = '', $delimiter = ',')
	{
		# Convert the array into an array of data strings, one array item per row
		$csv = array ();
		#!# Hacky workaround if the data is empty
		if ($headers) {
			$headers = implode ($delimiter, $headers) . "\n";
		}
		foreach ($data as $key => $values) {
			list ($headers, $csv[]) = csv::arrayToCsv ($values);
		}
		
		# Add the headers
		array_unshift ($csv, $headers);
		
		# Compile the CSV lines (each of which will end with a newline already)
		$csvString = implode ('', $csv);
		
		# Return the CSV data
		return $csvString;
	}
	
	
	# Function to add (or amend) an item to a CSV file
	function addItem ($file, $newItem, $addStamp = '.old')
	{
		# Get the headers
		if (!$headers = csv::getHeaders ($file)) {return false;}
		
		# Get the current data
		$data = csv::getData ($file, $stripKey = false);
		
		# Order and validate (discard unwanted keys) the new data
		foreach ($newItem as $key => $attributes) {
			foreach ($headers as $header) {
				$data[$key][$header] = (isSet ($attributes[$header]) ? $attributes[$header] : '');
			}
		}
		
		# Convert the data back to a CSV formatted string
		$csvData = csv::dataToCsv ($data, $headers);
		
		# Rewrite the CSV
		if (!csv::rewrite ($file, $csvData, $addStamp)) {return false;}
		
		# Return true if everything worked
		return true;
	}
	
	
	# Function to delete item(s) from a CSV file
	function deleteData ($file, $keys, $addStamp = '.old')
	{
		# Get the headers
		$headers = csv::getHeaders ($file);
		
		# Get the current data
		$data = csv::getData ($file, $stripKey = false);
		
		# Convert the key(s) - representing the item(s) to delete - to an array if necessary
		$keys = application::ensureArray ($keys);
		
		# Delete each/the item
		foreach ($keys as $key) {
			unset ($data[$key]);
		}
		
		# Convert the data back to a CSV formatted string
		$csv = csv::dataToCsv ($data, $headers);
		
		# Rewrite the CSV
		if (!csv::rewrite ($file, $csv, $addStamp)) {return false;}
		
		# Return true if everything worked
		return true;
	}
	
	
	
	# Function to rewrite the CSV
	function rewrite ($file, $newData, $addStamp = '.old')
	{
		# Backup the previous file
		if ($addStamp) {
			$oldData = file_get_contents ($file);
			if (!application::createFileFromFullPath ($file, $oldData, $addStamp)) {return false;}
		}
		
		# Write the new file
		if (!application::createFileFromFullPath ($file, $newData, $addStamp = false)) {return false;}
		
		# Return true if everything worked
		return true;
	}
	
	
	# Function to import a set of CSV files into a set of database tables
	function filesToSql ($dataDirectory, $pattern /* e.g. ([a-z]{3})[0-9]{2}.csv - must have one capture */, $tableComment = '%s data', $prefix = '', $names = array (), &$errorsHtml = false, $highMemory = '500M')
	{
		# Enable high memory and prevent timeouts
		if ($highMemory) {
			set_time_limit (0);
			ini_set ('memory_limit', $highMemory);
		}
		
		# Load required libraries
		require_once ('directories.php');
		
		# Get the list of files to import
		if (!$csvFiles = directories::listFiles ($dataDirectory, array ('csv'), $directoryIsFromRoot = true)) {
			$errorsHtml = "No CSV files were found in {$dataLocation}";
			return false;
		}
		
		# Compile a set of metadata about each file
		$fileset = array ();
		foreach ($csvFiles as $file => $attributes) {
			$filename = $dataDirectory . $file;
			if (!preg_match ('/^' . $pattern . '$/', $file, $matches)) {
				$errorsHtml = "The CSV file {$file} didn't have the expected filename pattern.";
				return false;
			}
			$contents = file_get_contents ($filename);
			$fileset[$file] = array (
				'filename'				=> $filename,
				'grouping'				=> $matches[1],
				'headers'				=> self::getHeaders ($filename),
				'windowsLineEndings'	=> substr_count ($contents, "\r\n"),
			);
		}
		
		# Ensure the headers for each grouping match
		// $fileset['filename.csv']['headers'] = array ('foo');	// Add in deliberately wrong data to trigger a test failure
		$groupings = application::regroup ($fileset, 'grouping');
		foreach ($groupings as $grouping => $files) {
			if (!isSet ($names[$grouping])) {$names[$grouping] = $grouping;}	// Ensure the naming lookup exists if not supplied
			$headers = false;
			foreach ($files as $file => $attributes) {
				$headersThisFile = $attributes['headers'];
				if ($headers) {
					if ($headersThisFile !== $headers) {
						$errorsHtml = "The headers for CSV file {$file} didn't match another set of headers.";
						return false;
					}
				}
				$headers = $headersThisFile;
			}
		}
		
		# Initialise the structure for each grouping
		$databaseStructure = array ();
		foreach ($groupings as $grouping => $files) {
			foreach ($files as $file => $attributes) {
				$databaseStructure[$grouping] = array ();
				foreach ($attributes['headers'] as $field) {
					$databaseStructure[$grouping][$field] = array (
						'type' => 'int',
						'length' => 1,	// Avoids "VARCHAR " being created without a number
					);
				}
				continue 2;	// Skip the rest of the files in this grouping as we already know they have the same headers
			}
		}
		
		# Determine the optimal database fieldtypes for all the columns across all the spreadsheets; this will be slow
		foreach ($groupings as $grouping => $files) {
			foreach ($files as $file => $attributes) {
				$filename = $dataDirectory . $file;
				
			//	$data = self::getData ($filename, $stripKey = false, $hasNoKeys = true);
			//	foreach ($data as $index => $row) {
			//		foreach ($row as $key => $value) {
				
			/* This block extracted from getData() but without the data storage which quickly becomes inefficient on large datasets */
				$stripKey = false;
				$hasNoKeys = true;
				
				# Get the headers
				#!# Not entirely efficient, but API becomes messy otherwise
				if (!$headers = self::getHeaders ($filename)) {return false;}
				
				# Parse the CSV
				if (!$fileHandle = fopen ($filename, 'rb')) {return false;}
				
				# Determine the longest line length
				$longestLineLength = 1000;
				$array = file ($filename);
				for ($i = 0; $i < count ($array); $i++) {
					if ($longestLineLength < strlen ($array[$i])) {
						$longestLineLength = strlen ($array[$i]);
					}
				}
				unset ($array);
				
				# Loop through each line of data
				$data = array ();
				$counter = 0;
				while ($csvData = fgetcsv ($fileHandle, $longestLineLength + 1)) {
					
					# Skip if in the first (header) row
					if (!$counter++) {continue;}
					
					# Check the first item exists and set it as the row key then unset it
					if ($rowKey = $csvData[0]) {
						if ($stripKey) {unset ($csvData[0]);}
						if ($hasNoKeys) {$rowKey = $counter - 1;}
						
						# Loop through each item of data
						#!# What should happen if a row has fewer columns than another? If there are fields missing, then it may be better to allow offsets to be generated as otherwise the data error may not be known. Filling in the remaining fields is probably wrong as we don't know which are missing.
						foreach ($csvData as $key => $value) {
							$key = $headers[$key];
							
			/* End of extracted section */
							
							# For each column, find the longest value and switch the column to varchar if it is not numeric
							if (strlen ($value) > $databaseStructure[$grouping][$key]['length']) {
								$databaseStructure[$grouping][$key]['length'] = strlen ($value);
							}
							if ($databaseStructure[$grouping][$key]['type'] == 'int') {
								if (!ctype_digit ($value)) {
									$databaseStructure[$grouping][$key]['type'] = 'varchar';
								}
							}
						}
					}
				}
				// break;	// Debug testing to stop after a single file in each grouping
			}
		}
		
		// application::dumpData ($databaseStructure);
		// die;
		
		# Convert the database structure to SQL
		$sql = '';
		foreach ($databaseStructure as $table => $fields) {
			$fieldsSql = array ();
			$comment = sprintf ($tableComment, $names[$table]);
			$sql .= "\n\n" . "-- {$comment}";
			$sql .= "\n" . "DROP TABLE IF EXISTS `{$prefix}{$names[$table]}`;";
			$sql .= "\n" . "CREATE TABLE `{$prefix}{$names[$table]}` (";
			$fieldsSql[] = "id INT(11) NOT NULL AUTO_INCREMENT";
			foreach ($fields as $fieldname => $fieldAttributes) {
				$type = strtoupper ($fieldAttributes['type']);
				$length = $fieldAttributes['length'];
				if ($type == 'VARCHAR' && $length > 255) {
					$type = 'TEXT';
					$length = false;
				}
				$type = strtoupper ($fieldAttributes['type']);
				$collation = ($type == 'VARCHAR' ? ' COLLATE utf8_unicode_ci' : '');
				$fieldsSql[] = "`{$fieldname}` {$type}" . ($length ? "({$length})" : '') . $collation;
			}
			$fieldsSql[] = "PRIMARY KEY (id)";
			$sql .= "\n\t" . implode (",\n\t", $fieldsSql);
			$sql .= "\n" . ") ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci COMMENT='{$comment}';";
		}
		$sql .= "\n\n\n-- Data:\n\n";
		
		# Add statements for loading the data; see: http://dev.mysql.com/doc/refman/5.1/en/load-data.html
		foreach ($groupings as $grouping => $files) {
			$sql .= "\n\n" . "-- CSV {$names[$grouping]} data files:";
			foreach ($files as $file => $attributes) {
				$columns = implode (',', $attributes['headers']);
				$filename = $dataDirectory . $file;
				$sql .= "\n" . "
				-- Data in {$file}
				LOAD DATA LOCAL INFILE '{$filename}'
				INTO TABLE `{$prefix}{$names[$grouping]}`
				FIELDS TERMINATED BY ','
				ENCLOSED BY '\"'
				ESCAPED BY '\"'
				LINES TERMINATED BY '" . ($attributes['windowsLineEndings'] ? "\\r\\n" : "\\n") . "'
				IGNORE 1 LINES
				({$columns})
				;";
				//break 2;
			}
		}
		
		# Return the SQL
		return $sql;
	}
	
	
	# Function to convert Excel files in a directory to CSV files; see: http://unix.stackexchange.com/a/30245 and http://stackoverflow.com/questions/3874840/csv-to-excel-conversion
	public function xls2csv ($xlsDirectory, $csvDirectory, $pearPath = '/usr/local/lib/php/')
	{
		# Load the PEAR library; the function requires PHPExcel which must be in the path. Install using: /usr/local/bin/pear channel-discover pear.pearplex.net ; /usr/local/bin/pear install pearplex/PHPExcel
		
		if ($pearPath) {
			set_include_path (get_include_path () . PATH_SEPARATOR . $pearPath);
		}
		require_once ('PHPExcel/PHPExcel/IOFactory.php');
		
		# Ensure the input directory exists
		if (!is_dir ($xlsDirectory)) {
			return false;
		}
		
		# Ensure the output directory exists
		if (!is_dir ($csvDirectory)) {
			if (!mkdir ($csvDirectory)) {
				return false;
			}
		}
		
		# Get a list of all the files
		$files = directories::listFiles ($xlsDirectory, array ('xls'), $directoryIsFromRoot = true);
		
		# Do the file conversions
		$converted = 0;
		foreach ($files as $file => $attributes) {
			$xlsFile = $xlsDirectory . $file;
			$csvFile = $csvDirectory . preg_replace ('/.xls$/', '.csv', $file);
			$objReader = PHPExcel_IOFactory::createReader ('Excel5');
			$objPHPExcel = $objReader->load ($xlsFile);
			$objWriter = PHPExcel_IOFactory::createWriter ($objPHPExcel, 'CSV');
			$objWriter->enclosureIsOptional (true);				// Requires the patch at http://phpexcel.codeplex.com/workitem/17597
			$objWriter->save ($csvFile);
			$converted++;
		}
		
		# Return the number of converted files
		return $converted;
	}
}


#!# Consider an 'additionality' mode to the CSV driver for filewriting

?>