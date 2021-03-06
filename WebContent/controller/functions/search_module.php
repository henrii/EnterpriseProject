<?php
//search_module
//Provides functions for sending search requests and processing
//	responses from the Searching and Indexing modules

//Provides classes for storing response data
require('classes.php');

function Search_ExecuteQuery($identifier, $query_type, $num_results)
{		
	$xmlResponse = '';
	
	//Send service query
	if (!$xmlResponse = sendServiceRequest($identifier, $query_type, $num_results)) {
		Control_DisplayErrorMessage("sendServiceRequest failed.");
		return;
	}

	//Get results of query. Either list or info (courses and materials)
	if (!$result = parseServiceResponse($xmlResponse, $query_type))
	{
		Control_DisplayErrorMessage("parseServiceResponse failed");
		return;
	}
	
	//Display result of query		
	switch ($query_type)
	{
		case QueryType::CourseList:
			$items = $result;
			$user_interests = getUserInterests();
			include('../view/search_results.html');
			break;
		case QueryType::CourseInfo:
			$course = $result;
			$user_interests = getUserInterests();
			$user_completions = getUserCompletions();			
			include('../view/course_view.html');
			break;
		case QueryType::MaterialList:
			$items = $result;
			include('../view/search_results.html');
			break;
		case QueryType::MaterialInfo:
			$material = $result;			
			header('Location: '.$result->viewLink);
			break;			
	}
	 	
}

function Search_GetCourseName($course_id)
{
	$xmlResponse = '';	
	$identifier = $course_id;
	$query_type = QueryType::CourseInfo;
	
	
	if (!$xmlResponse = sendServiceRequest($identifier, $query_type, null)) {
		Control_DisplayErrorMessage("sendServiceRequest failed.");
		return false;
	}
	
	//Get results of query. Either list or info (courses and materials)
	if (!$result = parseServiceResponse($xmlResponse, $query_type))
	{
		Control_DisplayErrorMessage("parseServiceResponse failed");
		return false;
	}
	
	$course = $result;
	return $course->name;
	
}

//Creates/sends a HTTP request with the search string
//Returns the XML response
function sendServiceRequest($identifier, $query_type, $num_results)
{	
	$xmlResponse = "";
	$query_URL = "";
	$query_string = ""; 	
	
	//Get XML response from URL required
	switch ($query_type)
	{				
		
		
		case QueryType::CourseList:
			$query_URL = "http://attr192.srvr.cse.unsw.edu.au/COMP9323-MOOCIndexSearchServices/courses/gsearch?";
			$query_string = http_build_query(array('q' => $identifier, 'num' => $num_results));
			//remove when finished debugging
			$xmlResponse = file_get_contents("../model/course_list.xml");
			break;
		case QueryType::CourseInfo:
			$query_URL = "http://attr192.srvr.cse.unsw.edu.au/COMP9323-MOOCIndexSearchServices/courses/";			
			$query_string = $identifier;	
			//remove when finished debugging		
			$xmlResponse = file_get_contents("../model/course_info.xml");
			break;
		case QueryType::MaterialList:
			$query_URL = "http://attr192.srvr.cse.unsw.edu.au/COMP9323-MOOCIndexSearchServices/materials/search?";			
			$query_string = http_build_query(array('q' => $identifier, 'num' => $num_results));
			//remove when finished debugging
			$xmlResponse = file_get_contents("../model/material_list.xml");			
			break;
		case QueryType::MaterialInfo:
			$query_URL = "http://attr192.srvr.cse.unsw.edu.au/COMP9323-MOOCIndexSearchServices/materials/";
			$query_string = $identifier;		
			//remove when finished debugging
			$xmlResponse = file_get_contents("../model/material_info.xml");
			break;
	}
	
	
	$opts = array('http' => array('method'  => 'GET'));	
	$context  = stream_context_create($opts);
	$xmlResponse = file_get_contents($query_URL.$query_string, false, $context);
		
	
	if (!$xmlResponse)
	{
		$GLOBALS['exception'] = "Failed to receive courses from CDE module.";
		return false;
	}	
	
	return $xmlResponse;	
}

//Parse XML response from Lucene
//Returns an array of Course objects
function parseServiceResponse($xmlstring, $query_type) {
		
	//Parse the response	
	$xml_elements = new SimpleXMLElement($xmlstring);	
	$items = '';
	
	//Process XML here for courses	
	switch ($query_type)
	{
		case QueryType::CourseList:
			$items = getXML_Course_List($xml_elements);
			break;
		case QueryType::CourseInfo:
			$items = getXML_Course_Info($xml_elements);
			break;
		case QueryType::MaterialList:
			$items = getXML_Material_List($xml_elements);
			break;			
		case QueryType::MaterialInfo:
			$items = getXML_Material_Info($xml_elements);
			break;
	}	
	
	if (!$items)
	{
		$GLOBALS['exception'] = 'Unable to parse service response';
		return false;
	}
	
	return $items;	
}

function getXML_Course_List($course_elements)
{
	$course_list = array();
		
	foreach ($course_elements as $course)
	{
		$newCourse = new Course;
		$newCourse->ID = $course->ID;
		$newCourse->name = $course->name;
		$newCourse->provider = $course->provider;
		$newCourse->university = $course->university;
		
		$course_list[] = $newCourse;
	}		
	
	return $course_list;
}

function getXML_Course_Info($course_element)
{

	$newCourse = new Course;
	$newCourse->ID = $course_element->ID;
	$newCourse->name = $course_element->name;
	$newCourse->provider = $course_element->provider;
	$newCourse->university = $course_element->university;
	$newCourse->startdate = $course_element->startDate;
	$newCourse->description = $course_element->description;
	$newCourse->duration = $course_element->duration;
	$newCourse->website = $course_element->website;
	$newCourse->logoURI = $course_element->logoURI;
	$newCourse->videoURI = $course_element->videoURI;
	$newCourse->instructors = $course_element->instructors;

	return $newCourse;
}

function getXML_Material_List($material_elements)
{
	$material_list = array();
	
	foreach ($material_elements as $material)
	{
		$newMaterial = new Material;
		$newMaterial->courseID = $material->courseID;
		$newMaterial->courseName = urldecode($material->courseName);
		$newMaterial->ID = $material->ID;
		$newMaterial->title = urldecode($material->title);
		
		$material_list[] = $newMaterial;
	}	
	
	return $material_list;
}

function getXML_Material_Info($material_elements)
{
	$newMaterial = new Material;
	
	$newMaterial->ID = $material_elements->ID;
	$newMaterial->title = urldecode($material_elements->title);
	$newMaterial->courseID = $material_elements->courseID;
	$newMaterial->googleDriveID = $material_elements->googleDriveID;
	$newMaterial->viewLink = $material_elements->viewLink;	

	return $newMaterial;
}

function getUserInterests()
{
	$user_interests = array();
	if (Control_GetLoginStatus())
	{
		$user_interest_courses = DAO_GetUserInterestsByName(Control_GetUserName());
		foreach ($user_interest_courses as $course)
			$user_interests[] = $course->ID;
	}
	return $user_interests;
}

function getUserCompletions()
{
	$user_completions = array();
	if (Control_GetLoginStatus())
	{
		$user_completed_courses = DAO_GetUserCompletionsByName(Control_GetUserName());
		foreach ($user_completed_courses as $course)
			$user_completions[] = $course->ID;
	}
	return $user_completions;
}



/* Send POST requests
 $postdata = http_build_query(array('search_text' => $search_text));

$opts = array('http' =>
		array(
				'method'  => 'POST',
				'header'  => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
		)
);

$context  = stream_context_create($opts);

$result = @file_get_contents('http://localhost:81search', false, $context);
*/


?>