<?php

// input via POST
// 3 parameters: branch, revision and the report file contents/name
//    -branch
//    -revision
//    -report_file
//
// all this script does is generate a directory for the branch+revision and store the (validated) report file in it
//
// the report file will either be for PHPT or PhpUnit reports generated by PFTT (see: git.php.net/pftt2.git )
//
// reports are stored as flat-files in reports/$branch/$revision (this is just a simple file upload script)
//
//
// INSTALL NOTE: web server user needs write access to `reports` directory
//
// the report file is pre-generated by PFTT and uploaded, instead of generating the report on upload
// or generating it when the user wants to view it. this is done because:
//   1. the result-packs (decompressed) are a lot of data
//   2. which is needed to get the output of failing tests to include in the report
//   3. simplifies the process of publishing reports as PFTT already generates reports comparing 2 builds
//   4. even just comparing the list of failing tests across multiple hosts and scenarios can be a lot of data
//      (even if it was uploaded separately from the compressed result-packs)
//   5. doing the comparison is more complex meaning more error prone and since access to qa.php.net is limited to git,
//      debugging, fixing and cleaning up errors in that process could be difficult

// TODO limit number of revisions
// TODO limit number of reports per revision


function exit_error($msg='') {
	header('HTTP/1.0 400 Bad Request');
	echo $msg;
	exit;
}

if (md5($_POST['token'])!="b1cab611a6a4ae40693c0f0f9df16692") {
	exit_error("Invalid Token");
}

$branch = strtoupper($_POST['branch']);
$revision = strtolower($_POST['revision']);

// do a bunch of validation on the input
switch($branch) {
case "PHP_5_3":
case "PHP_5_4":
case "PHP_5_5":
case "PHP_5_6":
case "PHP_5_7":
case "PHP_6_0":
case "PHP_MASTER":
case "MASTER":
case "STR_SIZE_AND_INT64":
	// valid
	break;
default:
	exit_error('Invalid branch');
}

// revisions must either have a . (fe 5.5.10) or start with 'r'
if ((strpos($revision, ".")===FALSE and substr($revision, 0, 1)!="r") or strlen($revision)>20) {
	exit_error('Invalid revision');
}

// validate report_file
if ($_FILES['report_file']['size'] > 100000 or $_FILES['report_file']['size'] < 100 ) {	
	exit_error('Invalid report file size');
} else if ($_FILES['report_file']['type']!="text/html") {
	exit_error('Invalid report type');
}

$file_contents = file_get_contents($_FILES['report_file']['tmp_name']);

// validate file contents
if (substr($file_contents, 0, 6)!="<html>" or strpos($file_contents, "PFTT")===FALSE) {
	exit_error('Invalid report file content');
}

// everything valid, now store it

// cleanup report filename
// will be named either:
//      PHPT_CMP_PHP_5_5-r31d67bd-NTS-X64-VC11_Local-FileSystem_CLI_v_PHP_5_5-rda84f3a-N.html
//      PhpUnit_CMP_PHP_5_5-r31d67bd-NTS-X64-VC11_Local-FileSystem_CLI_Symfony_v_PHP_5_5-rda84f3a-N.html
//
$report_name = trim($_FILES['report_file']['name']);
if (strlen($report_name) > 80) {
	$report_name = substr($report_name, 0, 80);
}
if (substr($report_name, -5) != ".html") {
	$report_name .= ".html";
}

// decide where to store it
$report_file = dirname($_SERVER['SCRIPT_FILENAME']) . "/reports/$branch/$revision/$report_name";
$report_dir = dirname($report_file);

// ensure dir exists
@mkdir($report_dir, 0644, TRUE);

// report_file is stored locally in a temporary file, move that file to the permanent location
move_uploaded_file($_FILES['report_file']['tmp_name'], $report_file);

// done, successfully
echo "Uploaded to $report_file";

?>