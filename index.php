nclude 'util.php';
	include 'users.php';

	ensure_login();

	$columnHeaders = array("Request ID/Model_type", "Product ID", "Pdf IDs","Book title/author", "E-mail", "Status", "Date/time enqueued", "Date/time compilation started", "Date/time completed/failed");
	$statusStyleClass = '';

	function makeTable($input) {
		global $columnHeaders;
		global $statusStyleClass;

		$tableStart = "<table cellpadding='5'><tr>";
		$tableEnd = "</tr></table>";

		echo $tableStart;
		foreach ($columnHeaders as $value) {
			echo "<td class=header>" . $value . "</td>";
		}
		echo "</tr>";

		foreach ($input as $row) {
			switch ($row['status']) {
			 	case '0':
			 		$status = 'Waiting';
			 		if (isArea9Email(getSessionParameter('email')) || isTopEmail(getSessionParameter('email'))) $status .= "</br></br><a href='compilation_info.php?canceltask=" . $row['requestId'] . "'> Cancel task</a>";
			 		$statusStyleClass = '';
			 		break;

			 	case '1':
			 		$status = 'Compiling';
			 		if (isArea9Email(getSessionParameter('email')) || isTopEmail(getSessionParameter('email'))) $status .= "</br></br><a href='compilation_info.php?killtask=" . $row['requestId'] . "'> Kill task</a>";
			 		$statusStyleClass = 'class=compiling';
			 		break;

		 		case '2':
			 		$status = 'Completed';
			 		$statusStyleClass = 'class=completed';
			 		break;
		 		
		 		case '3':
		 			$status = "<a href='compilation_info.php?showlog=" . $row['requestId'] . "&product=" . $row['productId'] . "'>" . "Failed</a>" .
		 			"</br></br><a href='compilation_info.php?restartjob=" . $row['requestId'] . "'> Restart task</a>";
			 		$statusStyleClass = 'class=failed';
			 		break;

			 	case '4':
			 		$status = 'Paused';
			 		$statusStyleClass = '';
			 		break;
			 	case '5':
			 		$status = "<a href='compilation_info.php?showlog=" . $row['requestId'] . "&product=" . $row['productId'] . "'>" . "Canceled</a>";
			 		$statusStyleClass = 'class=canceled';
			 		break;
			 	case '6':
			 		$status = "<a href='compilation_info.php?showlog=" . $row['requestId'] . "&product=" . $row['productId'] . "'>" . "Completed with warnings</a>" ;
		 			//"</br></br><a href='compilation_info.php?restartjob=" . $row['requestId'] . "'> Restart task</a>";
			 		$statusStyleClass = 'class=warning';
			 		break;
			}

			echo "<tr>";
			echo "<td>" . $row['requestId'] ."<br><font color='blue'>". $row['newModel'] . "</td>";
			echo "<td>" . $row['productId'] . "</td>";
			echo '<td style="max-width:150px;">' . $row['pdf_ids'] . "</td>";
			echo "<td>" . $row['title'] . "<br><font class='link'><a href=" . $row['composeLink'] . ">Compose</a>&nbsp&nbsp<a href=" . $row['annotateLink'] . ">Annotate</a></font></td>";
			echo "<td>" . $row['email'] . "</td>";
			echo "<td " . $statusStyleClass . ">" . $status . "</td>";
			echo "<td>" . $row['enqueuedDate'] . "</td>";
			echo "<td>" . $row['startDate'] . "</td>";
			echo "<td>" . $row['completedDate'] . "</td>";
			echo "</tr>";
		}
		echo $tableEnd;
	}

	function getAllTasks() {
		$link = connectToDb();
		mysql_select_db('einstein') or die('Could not select database: ' . mysql_error());

		$query = "SELECT * FROM `einstein`.`smartbook_compile_queue` WHERE DATE_ADD(`enqueued_time`, INTERVAL 7 DAY) >= NOW()";
		$resultQuery = mysql_query($query, $link) or die(mysql_error());
		$resultArray = array();
		while ($row = mysql_fetch_array($resultQuery)) {
			parse_str($row['request'], $a);

			$requestId = $row['request_id'];

			//$titleRequest = explode("\", \"", html_entity_decode(file_get_contents('http://annotate.area9.dk/Annotate/proxy.n?operation=flow_books_by_map&mapid=' . $a['mapid'] . '&key=a27bf822baa48e5241ba23310fe2d576')));
			//split("\", \"", html_entity_decode(file_get_contents('http://annotate.area9.dk/Annotate/proxy.n?operation=flow_books_by_map&mapid=' . $a['mapid'] . '&key=a27bf822baa48e5241ba23310fe2d576')), 5);
			//$title = (count($titleRequest) > 3) ? $titleRequest[1] . "<br>" . $titleRequest[2] : "";

			//$title_query = "SELECT cp.public_title, bu.name FROM `einstein`.`compose_products` cp, `einstein`.`business_unit` bu WHERE cp.mapid=" . quote_smart($a['mapid'], $link) . " AND cp.business_unit_id=bu.id";
			$title = "";
			$composeLink = "";
			$annotateLink = "";
			if (isset($a['mapid'])) {
				$title_query = "SELECT cp.public_title, cp.sub_title, cp.ISBN FROM `einstein`.`compose_products` cp WHERE cp.mapid=" . quote_smart($a['mapid'], $link);
				$titleRequest = mysql_query($title_query, $link) or die(mysql_error()); 
				if ($title_row = mysql_fetch_array($titleRequest)) {
					$title = $title_row[0] . "<br>" . $title_row[1];
					$composeLink = "http://production1.mhlearnsmart.com/einsteinmt/web/compose.php?expand_decks_for=" . $a['mapid'];
					$annotateLink = "http://annotate.area9.dk/Annotate/Annotate.html?isbn=" . $title_row[2];
				}
			}

			$completedDate = $row['active'] >= 2 ? date('d/m/Y H:i:s', strtotime($row['completed_time'])) : '';
			$startDate = $row['active'] >= 1 ? date('d/m/Y H:i:s', strtotime($row['start_time'])) : '';

			$resultArray[] = array(
				'requestId' => $requestId,
				'productId' => (isset($a['mapid'])) ? $a['mapid'] : "",
				'pdf_ids' => (isset($a['pdf_ids'])) ? str_replace (",", " " , $a['pdf_ids']) : "",
				'title' => $title,
				'email' => (isset($a['email'])) ? $a['email'] : "",
				'status' => $row['active'],
				'enqueuedDate' => date('d/m/Y H:i:s', strtotime($row['enqueued_time'])),
				'completedDate' => $completedDate,
				'startDate' => $startDate,
				'composeLink' => $composeLink,
				'annotateLink' => $annotateLink,
				'newModel' => (isset($a['newmodel'])) ? "NewModel" : "" 
			);
		}

		//unset($resultArray[0]);
		arsort($resultArray);

		if (is_resource($link)) mysql_close($link);
		makeTable($resultArray);
	}

	function printLog($request_id, $product) {
		$link = connectToDb();
		mysql_select_db('einstein') or die('Could not select database: ' . mysql_error());

		$query = "SELECT * FROM `einstein`.`smartbook_compile_queue` WHERE request_id = " . quote_smart($request_id, $link);
		$resultQuery = mysql_query($query, $link) or die(mysql_error());
		$errors = ($row = mysql_fetch_array($resultQuery)) ? $row['errors'] : "";

		$query = "SELECT cp.public_title, bu.name FROM `einstein`.`compose_products` cp, `einstein`.`business_unit` bu WHERE cp.mapid=" . quote_smart($product, $link) . " AND cp.business_unit_id=bu.id";
		$resultQuery = mysql_query($query, $link) or die(mysql_error());
		$product_name = ($row = mysql_fetch_array($resultQuery)) ? $row[0] . " " . $row[1] : "";

		if (is_resource($link)) mysql_close($link);
		echo "<h1>Compilation log for product " . $product_name . "</h1>";
		echo $errors . "</br>";
		if (stripos($errors, "Compilation of the chapterData warnings:") !== false || stripos($errors, "Cannot find pdf or htmls") !== false) echo "<a href='http://html1.mhlearnsmart.com/smartbook2/flow/www/flowswf.html?name=editor&product=" . $product . "' target='_blank'><h4>Click here to edit chapterStructure mismatches</h4></a>";
		if (stripos($errors, "has mismatches") !== false) echo "<a href='http://html1.mhlearnsmart.com/smartbook2/highmark/?product=" . $product . "' target='_blank'><h4>Click here to edit product in highlight editor</h4></a>";
		echo "<a href='compilation_info.php?restartjob=" . $request_id . "'> <h4>Click here to restart this task</h4></a>";
		echo "<a href='compilation_info.php'>Go back to Compile queue page!</a>";
	}

	function restartTask($request_id) {
		$link = connectToDb();
		mysql_select_db('einstein') or die('Could not select database: ' . mysql_error());

		$query = "SELECT * FROM `einstein`.`smartbook_compile_queue` WHERE request_id = " . quote_smart($request_id, $link);
		$resultQuery = mysql_query($query, $link) or die(mysql_error());
		$task = ($row = mysql_fetch_array($resultQuery)) ? $row['request'] : "";

		$result = ""; 

		if ($task != "") {
			$query = "INSERT INTO `einstein`.`smartbook_compile_queue` (`request`, `active`, `enqueued_time`) " .
				"VALUES (" . quote_smart($task, $link) . ", 0, NOW())";
			if ( !mysql_query($query) ) {
				$result = "Insert einstein.smartbook_compile_queue failed: " . mysql_error() . "\n";
			} else {
				$result = "Task added";
			}
		} else {
			$result = "Can not add this task. Request is empty.";
		}
		if (is_resource($link)) mysql_close($link);
		echo "</br><h3>" . $result . "</h3>" . "<br/><a href='compilation_info.php'>Go back to Compile queue page!</a>";
	}

	function killTask($request_id) {
		$link = connectToDb();
		mysql_select_db('einstein') or die('Could not select database: ' . mysql_error());
		$email = getSessionParameter('email');
		$err = "This task was killed by " . $email;
		$query = "UPDATE `einstein`.`smartbook_compile_queue` SET active=3, completed_time=NOW(), errors=" . quote_smart($err, $link);
		$query = $query . " WHERE request_id = " . quote_smart($request_id, $link);
		$result = mysql_query($query, $link) or die(mysql_error());
		if (is_resource($link)) mysql_close($link);
		echo "<h3>Task was killed</h3>" . "<br/><a href='compilation_info.php'>Go back to Compile queue page!</a>";
	}

	function cancelTask($request_id) {
		$link = connectToDb();
		mysql_select_db('einstein') or die('Could not select database: ' . mysql_error());
		$email = getSessionParameter('email');
		$err = "This task was canceled by " . $email;
		$query = "UPDATE `einstein`.`smartbook_compile_queue` SET active=5, completed_time=NOW(), errors=" . quote_smart($err, $link);
		$query = $query . " WHERE request_id = " . quote_smart($request_id, $link);
		$result = mysql_query($query, $link) or die(mysql_error());
		if (is_resource($link)) mysql_close($link);
		echo "<h3>Task was canceled</h3>" . "<br/><a href='compilation_info.php'>Go back to Compile queue page!</a>";
	}

?>

<html>
	<head>
		<title>Compilation queue status page</title>
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
		<style type="text/css">		
			body {
				background-color: white;
				color: black;
				font-family: Arial;
				font-size: small;
				text-align: center;
			}
			font.link {
				font-size: smaller;
			}
			table {  		
				border-collapse: collapse;	
				font-size: small;	
				margin: auto;
			}
			td, th { 
				border-width: 1;
				border-left-style:solid;
				border-right-style:solid;
				text-align: center;
				border-bottom-style:solid;
			}		
			
			td.failed {
				background-color: red;
			}
			td.completed {
				background-color: green;
			}
			td.compiling {
				background-color: #d8eaf9;
			}
			td.canceled {
				background-color: orange;
			}
			td.warning {
				background-color: yellow;	
			}
			td.header {
				border-left-style:solid;
				border-top-style:solid;
				border-bottom-style:solid;
				font-weight:bold;
				background-color: #f0f0f0;
			}
			td.bold_text {
				font-weight:bold;
			}
		</style>
	</head>
	<body>
		<?php 
			$showlog = getParameter('showlog');
			$product = getParameter('product');
			$restartjob = getParameter('restartjob');
			$killtask = getParameter('killtask');
			$canceltask = getParameter('canceltask');
			if ($showlog != "") {
				printLog($showlog, $product);
			} else if ($restartjob != "") {
				restartTask($restartjob);
			} else if ($killtask != "") {
				killTask($killtask);
			} else if ($canceltask != ""){
				cancelTask($canceltask);
