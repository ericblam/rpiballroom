<?php

// contains variables to log into the mysql database
require_once('includes/db_login.php');

//contains function definitions and header html code
include_once('header.php');

//contains functions for the authentication system
include_once('includes/login_functions.php');

//includes php DOM functions
include('simple_html_dom.php');

//check if session started
if (session_status() == PHP_SESSION_NONE) {
    sec_session_start();
}

?>

<div id="container">

<?php

//if user is logged in, execute the page, otherwise show the login screen
if(login_check($mysqli) == true) {

//define variables
$USER = "scraper";
$SHOW_BUDGET = false;


//no file is specifed, display a form for the user to specify one
if(!isset($_POST['upload'])){
?>
<head>
<title> Add </title>
</head>
<div style="width: 30%; margin: 0 auto; background: #f0e7d7;">
<form action="add_test.php" method="post" onsubmit="return confirm('Do you really want to upload a new budget? Doing so will overwrite the current budget, including all transactions entered. Ensure that it has been archived before proceeding!');" enctype="multipart/form-data">
<label for="file">Filename:</label>
<input type="file" name="file" id="file"><br>
<input type="hidden" name="upload" value="upload" />
<input type="submit" name="submit" value="Upload">
</form>
</div>

<?php 
}
//a file is specified, user has uploaded a file. so accept it
else{
	//allowed extentions
	$allowedExts = array("htm","html");
	
	//separate file extention
	$temp = explode(".", $_FILES["file"]["name"]);
	$extension = end($temp);
	
	//file path
	$upload_location = "/home4/brightk1/public_html/budget/";
	
	//max filesize
	$max_filesize = 200000;
	
	//if file meets requirements: 1) html file, 2)less than 20Mb, 3) correct extention
	if (($_FILES["file"]["type"] == "text/html") && ($_FILES["file"]["size"] < $max_filesize) && in_array($extension, $allowedExts)){
		if ($_FILES["file"]["error"] > 0){
			echo "Return Code: " . $_FILES["file"]["error"] . "<br>\n";
		}
		else{
			echo("Upload: " . $_FILES["file"]["name"] . "<br> \n");
			echo("Type: " . $_FILES["file"]["type"] . "<br> \n");
			echo("Size: " . ($_FILES["file"]["size"] / 1024) . " kB<br> \n");
			echo("Temp file: " . $_FILES["file"]["tmp_name"] . "<br> \n");

			if (file_exists($upload_location . $_FILES["file"]["name"])){
				echo($_FILES["file"]["name"] . " already exists.<br /> \n");
			}
			else{
				move_uploaded_file($_FILES["file"]["tmp_name"], $upload_location. $_FILES["file"]["name"]);
				echo("Stored in: " . $upload_location . $_FILES["file"]["name"] ."<br /> \n");
			}
		}
	}
	//something is wrong with the file
	else{
		echo "Invalid file <br />";
		if ($_FILES["file"]["type"] != "text/html"){
			echo("File is not of type 'text/html' <br /> \n");
		}
		elseif ($_FILES["file"]["size"] < $max_filesize){
			echo("File is greater than".num2str($max_filesize/1024)." kB  <br />\n");
		}
		elseif(in_array($extension, $allowedExts)){
			echo("File is not of type '.htm' <br /> \n");
		}
		else{
			echo("Something else went wrong");
		}
		die("No file to process");
	}



// Create DOM from URL or file
$html = file_get_html($_FILES["file"]["name"]);

$num_goals = 0;
$num_programs = 0;
$num_lines = 0;
$goals = array();


//find all tables in DOM
$tables = $html->find('table');

//table 6 contains the programs realization section which contains the line items
$realization_table = $tables[6];

//find all <tr> html tags (all rows) and iterate through them
foreach($realization_table->find('tr') as $row){
	
	// if that row contains the text 'Goal #: xxxxxx', it defines the start of a new goal
	// 		'Goal ([a-zA-Z]):(.*)?</' will return the goal letter in matches[1], and  the goal name in matches[2]
	if (preg_match('/Goal ([a-zA-Z]):(.*)?</',$row->outertext,$goal_matches)){
	
		//increment the goals counter
		$num_goals++;
		
		//reset the programs counter
		$num_programs=0;
		
		//create a new goal object initialized with goal letter and goal name and place it in array of goals
		//		also trim spaces from the beginning and end of the strings
		$goals[$num_goals] = GOAL::newgoal(trim($goal_matches[1]),($goal_matches[2]),NULL,NULL);
		
	}
	// if the row contains the text 'Program #: xxxxx', it defined the start of a new program
	// 		'/Program ([0-9]):(.*)?</' will return the goal number in matches[1], and the goal name in matches[2]
	elseif (preg_match('/Program ([0-9]):(.*)?</',$row->outertext,$prog_matches)){

		//increment the programs counter
		$num_programs++;
		
		// create a new program object within the goal object, initializing it with the program name
		//		also trim spaces from the beginning and end of the strings
		$goals[$num_goals]->add_program(PROGRAM::newprogram(trim($prog_matches[2]),trim($prog_matches[1]),NULL,NULL));
	}
	// if its not a goal or a program line, it likely contains a line item
	else{
		// budget line items are within a '<p class=red>' tag, if there are 5 of them in the row, it is a line item
		//		(the totals at the end of the section contain a different number of the tags, and aren't acted upon)
		if(count($row->find('p.red'))==5){
		
			// increment the number of lines counter
			$num_lines++;
			
			//initalize an array to hold the values we extract from the row
			$line_matches = array();
			
			//find all the '<p class=red>' tags in the row (should be 5) and loop through them
			foreach ($row->find('p.red') as $val){
			
				// look for a value within the tag, will be between the '>' and '<'
				//		'/>(.*)?</' will match anything between two angle brackets
				preg_match('/>(.*)?</',$val->outertext,$tmp_line_matches);
				
				//remove any '$' characters and strings at the beginning or ends of the string we found
				// 	save it to our storage array
				$line_matches[] = trim(str_replace ( '$' , '' , $tmp_line_matches[1]));
				
			}
			
			// create a new line_item object within the program object, initializing it with the program line_code, description, quantity and unit price
			$goals[$num_goals]->programs[$num_programs]->add_line(LINE_ITEM::newline($line_matches[0],$line_matches[1],$line_matches[2],$line_matches[3]));
			
		}
	}
}
//print_r($goals);

echo("<br /><br /><br />\n\n\n");
//initalize iterators
$goal_it = 1;
$program_it = 1;
$line_it = 1;

//loop through goals
foreach($goals as $goal){
	
	echo("Writing Goal ".$goal->letter."<br /> \n");
	
	//reset program counter
	$program_it = 1;
	
	//loop through each program in the goal
	foreach($goal->programs as $program){
	
		echo("Writing Program ".$program->number."<br /> \n");
		
		//loop through each line in the program
		foreach ($program->line_items as $line){
		
			//form sql string
			$line_sql = printf("INSERT IGNORE INTO %s_lines (USER,GOAL,PROGRAM,LINECODE,UNITPRICE,DESCRIPTION,QUANTITY) VALUES ('%s',%s,%s,'%s',%s,'%s',%s)",$BUDGET_YEAR,$USER,$goal->letter,$program->number,$line->line_code,$line->unit_price,$line->description,$line->quantity);
			
			
			echo($line_sql."<br />\n");
			
			//add to array to be executed
			$sql[] = $mysqli->real_escape_string($line_sql);
			
			//increment line counter
			$line_it++;
		}
		
		//increment program counter
		$program_it++;
	}
	
	//increment goal counter
	$goal_it++;
}

//Table structure:
//		$budget_year_lines contains line items from budget
//		$budget_year_trans contains transactions entered on this site
// 		$budget_year_info contains descriptions and other info from the budget
//
//		All tables will be cleared to add new budget

//check if _lines table already exists
$check_exists = "SHOW TABLES IN brightk1_budget LIKE '%_lines';";
if ($result = $mysqli->query($check_exists)) {
	if(!$result){
	  die('Could not get data: ' . $mysqli->error);
	}
	if($result->num_rows==1){
		$table_pre = current(split("_",end($result->fetch_array(MYSQLI_NUM))));
		$tables = array('_lines','_trans','_info');
		foreach($tables as $suffix){
			$drop_table = "DROP TABLE `".DATABASE."`.`".$table_pre .$suffix."`;";
			if (!$mysqli->query($drop_table)) {
				msg('error',"Errormessage: ".$mysqli->error." \n");
			}	
			else{
				msg('success',"Dropped table $table_pre $suffix <br /> \n");
			}
		}
	}
}



//mysql script to make table to store line items from budget
$make_table="CREATE TABLE ".$budget_year."_lines (
CHANGEDATE TIMESTAMP,
USER VARCHAR(255),
GOAL INT(12),
PROGRAM INT(12),
LINECODE VARCHAR(10),
UNITPRICE DECIMAL(8,2),
DESCRIPTION VARCHAR(255),
QUANTITY INT(12),
LINEID INT(11) NOT NULL auto_increment,
primary KEY (LINEID));";
//create the table
if (!$mysqli->query($make_table)) {
		echo("Errormessage: ".$mysqli->error." <br />\n");
}	
else{
	printf("Table ".$budget_year."_lines successfully created. <br />\n ");
}


//mysql script to make table to store info from budget
$make_table="CREATE TABLE ".$budget_year."_info (
CHANGEDATE TIMESTAMP,
USER VARCHAR(255),
GOAL INT(12),
PROGRAM INT(12),
DESCRIPTION VARCHAR(255),
INFOID INT(11) NOT NULL auto_increment,
primary KEY (INFOID));";
//create the table
if (!$mysqli->query($make_table)) {
		echo("Errormessage: ".$mysqli->error." <br />\n");
}	
else{
	printf("Table ".$budget_year."_info successfully created. <br />\n ");
}

//mysql script to make table to store transactions
$make_table="CREATE TABLE ".$budget_year."_trans (
CHANGEDATE TIMESTAMP,
USER VARCHAR(255),
GOAL INT(12),
PROGRAM INT(12),
LINECODE VARCHAR(10),
UNITPRICE DECIMAL(8,2),
DESCRIPTION VARCHAR(255),
QUANTITY INT(12),
LINEID INT(11),
TRANSID INT(11) NOT NULL auto_increment,
primary KEY (TRANSID));";
// LINEID contains the id of the line item in _lines table which this trans corresponds to

//create the table
if (!$mysqli->query($make_table)) {
		echo("Errormessage: ".$mysqli->error." <br />\n");
}	
else{
	printf("Table ".$budget_year."_trans successfully created. <br />\n ");
}

//display each of the queries for the user
foreach ($sql as $query){
	//echo($query."<br /> \n");
	if (!$mysqli->query($query)) {
		echo($query."<br /> \n");
		echo("Errormessage: ".$mysqli->error." <br />\n");
	}	
}
}
	
$mysqli->close();
?> 
<?php
} else { 
    msg('error','You are not authorized to access this page, please <a href=\"login.php?page='.urlencode($_SERVER['PHP_SELF']) .'\">login</a>.');
}

?>
	</div>
