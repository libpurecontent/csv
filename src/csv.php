<?php

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
	function getData ($filename, $stripKey = true)
	{
		# Get the headers
		#!# Not entirely efficient, but API becomes messy otherwise
		if (!$headers = csv::getHeaders ($filename)) {return false;}
		
		# Open the file
		if (!$fileHandle = fopen ($filename, 'rb')) {return false;}
		
		# Loop through each line of data
		$data = array ();
		$counter = 0;
		while ($csvData = fgetcsv ($fileHandle, filesize ($filename))) {
			
			# Skip if in the first (header) row
			if (!$counter++) {continue;}
			
			# Check the first item exists and set it as the row key then unset it
			if ($rowKey = $csvData[0]) {
				if ($stripKey) {unset ($csvData[0]);}
				
				# Loop through each item of data
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
		if (!$fileHandle = fopen ($filename, 'rb')) {return false;}
		
		# Get the column names
		$headers = fgetcsv ($fileHandle, filesize ($filename));
		
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
	
	
	# Function to convert a multi-dimensional keyed array back to a CSV
	function dataToCsv ($data, $headers, $delimiter = ',')
	{
		# Convert the array into an array of data strings, one array item per row
		$csv = array ();
		#!# Hacky workaround if the data is empty
		$headers = implode ($delimiter, $headers) . "\n";
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
				$data[$key][$header] = $attributes[$header];
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
}


#!# Consider an 'additionality' mode to the CSV driver for filewriting
